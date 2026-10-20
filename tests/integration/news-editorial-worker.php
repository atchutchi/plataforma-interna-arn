<?php
/** Real subprocess for exclusive editorial contention checks.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\CurrentNewsActor;
use Arn\Intranet\News\EditorialAuthority;
use Arn\Intranet\News\NewsAuthority;
use Arn\Intranet\News\WpdbNewsAuthority;
use Arn\Intranet\News\WpdbNewsRepository;
use Arn\Intranet\Tests\Fixtures\EditorialContentionTrace;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$task_context = json_decode( (string) getenv( 'ARN_EDITORIAL_CONTENTION' ), true );
if ( ! is_array( $task_context ) || array_keys( $task_context ) !== array( 'prefix', 'editor', 'article', 'participants', 'ready', 'go', 'entered', 'trace' ) || ! is_string( $task_context['prefix'] ) || 1 !== preg_match( '/^arn_ed_[a-f0-9]{10}_$/D', $task_context['prefix'] ) || ! is_int( $task_context['editor'] ) || $task_context['editor'] < 1 || ! is_int( $task_context['article'] ) || $task_context['article'] < 1 || ! is_array( $task_context['participants'] ) || ! is_string( $task_context['trace'] ) ) {
	throw new RuntimeException( 'Contexto exclusivo inválido.' );
}
require_once __DIR__ . '/fixtures/EditorialContentionTrace.php';
$trace = new EditorialContentionTrace( $task_context['trace'] );
$trace->record( 'boot' );
$db    = null;
$facts = static function () use ( &$db ): array {
	if ( ! is_object( $db ) ) {
		return array();
	}
	$result = array( 'sql_error' => '' !== $db->last_error );
	try {
		if ( $db->dbh instanceof mysqli ) {
			$result['errno'] = $db->dbh->errno;
		}
	} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- O errno desconhecido é omitido, sem expor a causa.
		// A falta de errno fica desconhecida, sem publicar a excepção.
	}
	return $result;
};
try {
	$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$db->set_prefix( $task_context['prefix'] );
	$db->suppress_errors( true );
	$trace->record( 'connected', array( 'connection' => (int) $db->get_var( 'SELECT CONNECTION_ID()' ) ) );
	$snapshot = ( new WpdbAuthorizationRepository( $db ) )->find( $task_context['editor'] );
	if ( ! $snapshot instanceof AuthorizationSnapshot ) {
		throw new RuntimeException( 'Snapshot exclusivo indisponível.' );
	}
	$trace->record( 'snapshot-ready' );
	$initial   = new class( $snapshot ) {
		public function __construct( private readonly AuthorizationSnapshot $snapshot ) {}
		public function find( int $user_id ): ?AuthorizationSnapshot {
			return $user_id === $this->snapshot->user_id ? $this->snapshot : null;
		}
	};
	$current   = new CurrentNewsActor(
		static fn() => $initial,
		static fn(): int => $task_context['editor'],
		static fn( int $user_id ): array => array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $user_id,
			'arn_authz_revision' => $snapshot->revision,
		),
		static fn(): array => array( $task_context['editor'] ),
		static fn(): array => $task_context['participants']
	);
	$real      = new WpdbNewsAuthority( $db, $current, null, static fn(): array => $task_context['participants'] );
	$authority = new class( $real, $task_context['entered'], $db, $trace, $facts ) implements NewsAuthority, EditorialAuthority {
		public function __construct( private readonly WpdbNewsAuthority $real, private readonly string $entered, private readonly IdentityWpdb $database, private readonly EditorialContentionTrace $trace, private readonly Closure $facts ) {}
		public function assertUploadCurrent(): void {
			$this->real->assertUploadCurrent(); }
		public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array {
			if ( false === file_put_contents( $this->entered, (string) $this->database->get_var( 'SELECT CONNECTION_ID()' ), LOCK_EX ) ) {
				throw new RuntimeException( 'Marcador exclusivo indisponível.' );
			}
			$this->trace->record( 'authority-enter' );
			try {
				$result = $this->real->lockAndAssert( $author_id, $review_scope, $operation );
			} catch ( Throwable $error ) {
				$this->trace->record( 'authority-refused', ( $this->facts )() );
				throw $error;
			}
			$this->trace->record( 'authority-locked' );
			return $result;
		}
		public function authorizeDecision( Article $article, int $version, string $operation, array $locked ): array {
			try {
				$result = $this->real->authorizeDecision( $article, $version, $operation, $locked );
			} catch ( Throwable $error ) {
				$this->trace->record( 'decision-refused', ( $this->facts )() );
				throw $error;
			}
			$this->trace->record( 'decision-authorized' );
			return $result;
		}
		public function authorizeNotice( Article $article, object $notice, array $locked ): bool {
			return $this->real->authorizeNotice( $article, $notice, $locked );
		}
	};
	$trace->record( 'ready' );
	if ( false === file_put_contents( $task_context['ready'], 'ready', LOCK_EX ) ) {
		throw new RuntimeException( 'Marcador exclusivo indisponível.' );
	}
	$deadline = microtime( true ) + 30;
	while ( ! is_file( $task_context['go'] ) && microtime( true ) < $deadline ) {
		usleep( 50000 );
	}
	if ( ! is_file( $task_context['go'] ) ) {
		throw new RuntimeException( 'Sincronização exclusiva falhou.' );
	}
	$trace->record( 'released' );
	$rollback_observer = static function ( string $sql ) use ( $trace, $facts ): string {
		if ( 1 === preg_match( '/\AROLLBACK(?:\s|;|$)/i', trim( $sql ) ) ) {
			$trace->record( 'before-rollback', $facts() );
		}
		return $sql;
	};
	add_filter( 'query', $rollback_observer );
	try {
		$trace->record( 'decision-start' );
		if ( 'early-worker' === getenv( 'ARN_EDITORIAL_DIAGNOSTIC_FAULT' ) ) {
			throw new RuntimeException( 'Recusa precoce injectada no ensaio.' );
		}
		( new WpdbNewsRepository( $db, $authority ) )->decide( $task_context['article'], 'approve', 1, 2, 'Decisão concorrente' );
		$trace->record( 'confirmed' );
		echo "EDITORIAL_CONFIRMED\n";
	} catch ( RuntimeException ) {
		$trace->record( 'refused', $facts() );
		echo "EDITORIAL_REFUSED\n";
	} finally {
		remove_filter( 'query', $rollback_observer );
	}
} catch ( Throwable ) {
	try {
		$trace->record( 'failed', $facts() );
	} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A prova incompleta é comunicada pelo processo pai.
		// O processo pai assinalará a prova ausente ou incompleta.
	}
	throw new RuntimeException( 'Processo editorial de ensaio indisponível.' );
}
