<?php
/**
 * Ensaio de sessões federadas com WordPress e tabelas de laboratório.
 *
 * @package ArnIntranetCore
 */

use Arn\Intranet\Identity\IdentityLinkService;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Identity\WpdbIdentityLinkRepository;
use Arn\Intranet\Identity\WordPressIdentityLinkAuthority;
use Arn\Intranet\Identity\WordPressFederatedAccess;
use Arn\Intranet\Identity\WordPressFederatedSession;
use Arn\Intranet\Identity\GoogleLoginController;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio permitido apenas em laboratório.' );
}
$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$prefix   = 'arn_fs_' . bin2hex( random_bytes( 6 ) ) . '_';
$database->set_prefix( $prefix );
$service  = new IdentityLinkService( new WpdbIdentityLinkRepository( $database ), new WordPressIdentityLinkAuthority() );
$actor_id = get_current_user_id();
$user_id  = 0;
$checks   = 0;
$assert   = static function ( bool $condition ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Ensaio federado falhou.' );
	}
	++$checks;
};
try {
	$controller = new GoogleLoginController();
	$controller->registerHooks();
	$assert( false !== has_action( 'login_form_arn_google' ) );
	$assert( false !== has_action( 'login_form_arn_google_callback' ) );
	$assert( false !== has_action( 'admin_post_arn_google_link' ) );
	$service->installSchema( $actor_id );
	$user_id = wp_insert_user(
		array(
			'user_login' => $prefix . 'user',
			'user_email' => $prefix . 'user@example.test',
			'user_pass'  => wp_generate_password( 32 ),
			'role'       => 'subscriber',
		)
	);
	$assert( is_int( $user_id ) );
	$service->create( $actor_id, $user_id, 'https://accounts.google.com', 'fixture-session-sub' );
	$service->activate( $actor_id, $user_id );
	$assert( true === ( new WpdbIdentityLinkRepository( $database ) )->hasSchema() );
	$original_prefix = $GLOBALS['wpdb']->prefix;
	try {
		$GLOBALS['wpdb']->prefix = $prefix;
		$assert( GoogleLoginController::linkedUser( $user_id )->user_id === $user_id );
		$assert( apply_filters( 'authenticate', get_userdata( $user_id ), '', '' ) instanceof WP_Error );
		$existing_key_errors = new WP_Error();
		do_action( 'validate_password_reset', $existing_key_errors, get_userdata( $user_id ) );
		$assert( $existing_key_errors->has_errors() );
	} finally {
		$GLOBALS['wpdb']->prefix = $original_prefix;
	}
	$lookup       = static fn( int $id ) => $service->findForUser( $id );
	$guard        = new WordPressFederatedAccess( $lookup, static fn(): bool => true );
	$reset_errors = new WP_Error();
	$guard->validatePasswordReset( $reset_errors, get_userdata( $user_id ) );
	$assert( $reset_errors->has_errors() );
	$assert( $guard->filterAuthentication( get_userdata( $user_id ) ) instanceof WP_Error );
	$assert( false === $guard->filterPasswordReset( true, $user_id ) );
	$assert( false === $guard->filterApplicationPasswords( true, get_userdata( $user_id ) ) );
	$assert( $actor_id === $guard->filterCurrentUser( $actor_id ) );
	$issuer   = new WordPressFederatedSession();
	$token    = $issuer->issue( $service->findForUser( $user_id ) );
	$manager  = WP_Session_Tokens::get_instance( $user_id );
	$metadata = $manager->get( $token );
	$assert( true === $metadata['arn_oidc'] );
	$assert( $metadata['expiration'] <= time() + 3600 );
	$cookie                      = wp_generate_auth_cookie( $user_id, $metadata['expiration'], 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	$assert( $user_id === $guard->filterCurrentUser( $user_id ) );
	$other_token                    = $issuer->issue( $service->findForUser( $user_id ) );
	$metadata['arn_oidc_issued_at'] = time() - 1900;
	$metadata['arn_oidc_last_seen'] = time() - 1800;
	$manager->update( $token, $metadata );
	$assert( false === $guard->filterCurrentUser( $user_id ) );
	$assert( false === $manager->verify( $token ) );
	$assert( true === $manager->verify( $other_token ) );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, $manager->get( $other_token )['expiration'], 'logged_in', $other_token );
	$assert( $user_id === $guard->filterCurrentUser( $user_id ) );
	$token                       = $issuer->issue( $service->findForUser( $user_id ) );
	$cookie                      = wp_generate_auth_cookie( $user_id, $manager->get( $token )['expiration'], 'logged_in', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	$service->revoke( $actor_id, $user_id );
	$assert( false === $guard->filterCurrentUser( $user_id ) );
	$assert( false === $manager->verify( $token ) );
	$assert( false === $manager->verify( $other_token ) );
	$assert( $guard->filterAuthentication( get_userdata( $user_id ) ) instanceof WP_Error );
	WP_CLI::success( 'Sessão federada real: ' . $checks . ' asserções.' );
} finally {
	unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	$database->query( $database->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_identity_audit' ) );
	$database->query( $database->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_identity_links' ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	if ( is_int( $user_id ) && $user_id > 0 ) {
		wp_delete_user( $user_id );
	}
}
