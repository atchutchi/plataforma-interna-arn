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

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$task_context = json_decode( (string) getenv( 'ARN_EDITORIAL_CONTENTION' ), true );
if ( ! is_array( $task_context ) || array_keys( $task_context ) !== array( 'prefix', 'editor', 'article', 'participants', 'ready', 'go', 'entered' ) || ! is_string( $task_context['prefix'] ) || 1 !== preg_match( '/^arn_ed_[a-f0-9]{10}_$/D', $task_context['prefix'] ) || ! is_int( $task_context['editor'] ) || $task_context['editor'] < 1 || ! is_int( $task_context['article'] ) || $task_context['article'] < 1 || ! is_array( $task_context['participants'] ) ) {
	throw new RuntimeException( 'Contexto exclusivo inválido.' );
}
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $task_context['prefix'] );
$db->suppress_errors( true );
$snapshot = ( new WpdbAuthorizationRepository( $db ) )->find( $task_context['editor'] );
if ( ! $snapshot instanceof AuthorizationSnapshot ) {
	throw new RuntimeException( 'Snapshot exclusivo indisponível.' );
}
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
	static fn(): array => array( $task_context['editor'] )
);
$real      = new WpdbNewsAuthority( $db, $current, null, static fn(): array => $task_context['participants'] );
$authority = new class( $real, $task_context['entered'], $db ) implements NewsAuthority, EditorialAuthority {
	public function __construct( private readonly WpdbNewsAuthority $real, private readonly string $entered, private readonly IdentityWpdb $database ) {}
	public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array {
		if ( false === file_put_contents( $this->entered, (string) $this->database->get_var( 'SELECT CONNECTION_ID()' ), LOCK_EX ) ) {
			throw new RuntimeException( 'Marcador exclusivo indisponível.' );
		}
		return $this->real->lockAndAssert( $author_id, $review_scope, $operation );
	}
	public function authorizeDecision( Article $article, int $version, string $operation, array $locked ): array {
		return $this->real->authorizeDecision( $article, $version, $operation, $locked );
	}
	public function authorizeNotice( Article $article, object $notice, array $locked ): bool {
		return $this->real->authorizeNotice( $article, $notice, $locked );
	}
};
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
try {
	( new WpdbNewsRepository( $db, $authority ) )->decide( $task_context['article'], 'approve', 1, 2, 'Decisão concorrente' );
	echo "EDITORIAL_CONFIRMED\n";
} catch ( RuntimeException ) {
	echo "EDITORIAL_REFUSED\n";
}
