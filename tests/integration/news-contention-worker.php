<?php
/** Child process for the exclusive account-before-article lock test.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\CurrentNewsActor;
use Arn\Intranet\News\NewsAuthority;
use Arn\Intranet\News\Version;
use Arn\Intranet\News\WpdbNewsAuthority;
use Arn\Intranet\News\WpdbNewsRepository;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
// phpcs:disable WordPress.WP.AlternativeFunctions -- Exclusive child process signals with temporary files.

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$context = json_decode( (string) getenv( 'ARN_NEWS_CONTENTION' ), true );
if ( ! is_array( $context ) || array_keys( $context ) !== array( 'prefix', 'author_id', 'article_id', 'ready', 'go', 'entered' )
	|| ! is_string( $context['prefix'] ) || 1 !== preg_match( '/^arn_ns_[a-f0-9]{10}_$/D', $context['prefix'] )
	|| ! is_int( $context['author_id'] ) || $context['author_id'] < 1 || ! is_int( $context['article_id'] ) || $context['article_id'] < 1 ) {
	throw new RuntimeException( 'Contexto de ensaio inválido.' );
}
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $context['prefix'] );
$db->suppress_errors( true );
$reader_db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$reader_db->set_prefix( $context['prefix'] );
$snapshot = ( new WpdbAuthorizationRepository( $reader_db ) )->find( $context['author_id'] );
if ( ! $snapshot instanceof AuthorizationSnapshot ) {
	throw new RuntimeException( 'Snapshot de ensaio indisponível.' );
}
$initial    = new class( $snapshot ) {
	public function __construct( private readonly AuthorizationSnapshot $snapshot ) {}
	public function find( int $user_id ): ?AuthorizationSnapshot {
		return $user_id === $this->snapshot->user_id ? $this->snapshot : null;
	}
};
$current    = new CurrentNewsActor(
	static fn() => $initial,
	static fn(): int => $context['author_id'],
	static fn( int $user_id ): array => array(
		'arn_authz_local'    => true,
		'arn_authz_user_id'  => $user_id,
		'arn_authz_revision' => $snapshot->revision,
	),
	static fn(): array => array(),
	static fn(): array => array( $context['author_id'] => array( 'LAB-A' ) )
);
$real       = new WpdbNewsAuthority( $db, $current );
$authority  = new class( $real, $context['entered'], $db ) implements NewsAuthority {
	public function __construct( private readonly NewsAuthority $real, private readonly string $entered, private readonly IdentityWpdb $database ) {}
	public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array {
		if ( false === file_put_contents( $this->entered, (string) $this->database->get_var( 'SELECT CONNECTION_ID()' ), LOCK_EX ) ) {
			throw new RuntimeException( 'Sinal de ensaio indisponível.' );
		}
		return $this->real->lockAndAssert( $author_id, $review_scope, $operation );
	}
};
$repository = new WpdbNewsRepository( $db, $authority );
if ( false === file_put_contents( $context['ready'], 'ready', LOCK_EX ) ) {
	throw new RuntimeException( 'Sinal de ensaio indisponível.' );
}
$deadline = microtime( true ) + 15;
while ( ! is_file( $context['go'] ) && microtime( true ) < $deadline ) {
	usleep( 50000 );
}
if ( ! is_file( $context['go'] ) ) {
	throw new RuntimeException( 'Sincronização de ensaio falhou.' );
}
$next = new Version( 2, new Content( 'Revisão concorrente', 'Texto', 'nota-interna', null, array(), array() ), $context['author_id'], time() );
$repository->revise( $context['article_id'], $next, 1, 'Concorrência real' );
echo "NEWS_WORKER_OK\n";
