<?php
/**
 * Exclusive two-writer People integration worker.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Bounded test worker, never loaded by the plugin.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Private integration barrier files are removed by the parent.
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\People\LocalPeopleRoster;
use Arn\Intranet\People\SelfService;
use Arn\Intranet\People\WpdbPeopleAuthority;
use Arn\Intranet\People\WpdbPeopleRepository;

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Worker People indisponível.' );
}
$prefix   = getenv( 'ARN_PEOPLE_TEST_PREFIX' );
$root     = getenv( 'ARN_PEOPLE_TEST_ROOT' );
$label    = getenv( 'ARN_PEOPLE_TEST_LABEL' );
$actor_id = filter_var( getenv( 'ARN_PEOPLE_TEST_USER_ID' ), FILTER_VALIDATE_INT );
$revision = filter_var( getenv( 'ARN_PEOPLE_TEST_AUTH_REVISION' ), FILTER_VALIDATE_INT );
$base     = realpath( dirname( __DIR__, 2 ) );
$root     = is_string( $root ) ? realpath( $root ) : false;
if ( ! is_string( $prefix ) || ! LocalPeopleRoster::validPrefix( $prefix ) || ! is_string( $label ) || ! in_array( $label, array( 'a', 'b' ), true ) || ! is_int( $actor_id ) || $actor_id <= 0 || ! is_int( $revision ) || $revision < 1 || false === $base || false === $root || str_starts_with( strtolower( $root ), strtolower( $base ) . DIRECTORY_SEPARATOR ) || str_starts_with( strtolower( $root ), strtolower( ABSPATH ) ) ) {
	throw new RuntimeException( 'Worker People inválido.' );
}
if ( ! defined( 'ARN_ALLOW_LOCAL_PEOPLE' ) ) {
	define( 'ARN_ALLOW_LOCAL_PEOPLE', true );
}
$ready  = $root . DIRECTORY_SEPARATOR . '.people-worker-' . $label . '.ready';
$result = $root . DIRECTORY_SEPARATOR . '.people-worker-' . $label . '.result';
if ( file_exists( $ready ) || file_exists( $result ) || is_link( $ready ) || is_link( $result ) ) {
	throw new RuntimeException( 'Barreira People inválida.' );
}
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
if ( ! in_array( $actor_id, LocalPeopleRoster::ids( $prefix ), true ) ) {
	throw new RuntimeException( 'Actor People inválido.' );
}
$session       = array(
	'arn_authz_local'    => true,
	'arn_authz_user_id'  => $actor_id,
	'arn_authz_revision' => $revision,
);
$signal        = static function ( string $sql ) use ( $prefix, $ready ): string {
	if ( str_contains( $sql, $prefix . 'arn_authz_accounts' ) && str_contains( $sql, 'FOR UPDATE' ) ) {
		if ( false === file_put_contents( $ready, 'ready', LOCK_EX ) ) {
			throw new RuntimeException( 'Barreira People indisponível.' );
		}
	}
	return $sql;
};
$worker_status = 'rejected';
add_filter( 'query', $signal );
try {
	$authority = new WpdbPeopleAuthority( $db, static fn(): array => array( $actor_id, $session ) );
	$people    = new WpdbPeopleRepository( $db, $authority );
	$people->updateOwn( $actor_id, new SelfService( 'a' === $label ? '+245 222' : '+245 444', 'Sala Á', '' ), 2 );
	$worker_status = 'committed';
} catch ( RuntimeException $error ) {
	if ( 'Alteração People recusada. Recarrega a página.' !== $error->getMessage() ) {
		throw $error;
	}
	$worker_status = 'rejected';
} finally {
	remove_filter( 'query', $signal );
	$db->close();
}
if ( false === file_put_contents( $result, $worker_status, LOCK_EX ) ) {
	throw new RuntimeException( 'Resultado People indisponível.' );
}
