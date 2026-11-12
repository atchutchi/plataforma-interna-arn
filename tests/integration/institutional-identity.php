<?php
/** Ensaio institucional descartável, sem identidade externa ou dados reais.
 *
 * @package ArnIntranet
 */

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Apenas contador de ensaio CLI.
use Arn\Intranet\Identity\{IdentityWpdb, LocalIdentityLink, WpdbIdentityLinkRepository, AuthorizedIdentityClaim, WordPressFederatedAccess};
use Arn\Intranet\Identity\Institutional\{PrincipalSource, SessionIssuer, ModuleAccess};
use Arn\Intranet\Authorization\{AccountState, Action, Request, Scope, Role, Grant};
use Arn\Intranet\Authorization\Storage\{WpdbAuthorizationRepository, ChangeContext};
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Apenas laboratório.' ); }
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$prefix = 'arn_ii_' . bin2hex( random_bytes( 6 ) ) . '_';
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$links      = new WpdbIdentityLinkRepository( $db );
$auth       = new WpdbAuthorizationRepository( $db );
$actor      = get_current_user_id();
$checks     = 0;
$assert     = static function ( bool $condition ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Assercao institucional ' . ( $checks + 1 ) );
	} ++$checks;
};
$refused    = static function ( callable $call ) use ( $assert ): void {
	try {
		$call();
	} catch ( Throwable ) {
		$assert( true );
		return;
	} $assert( false );
};
$user       = 0;
$filter     = null;
$old_https  = $_SERVER['HTTPS'] ?? null;
$old_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
try {
	$links->installSchema();
	$links->installSchema();
	$assert( '0' === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_identity_links" ) );
	$links->create( new LocalIdentityLink( 987654321, 'accounts.google.com', 'synthetic-legacy', false, false ), $actor );
	$links->changeState( 987654321, true, true, $actor );
	$history_before = $db->get_results( "SELECT user_id, actor_id, action, occurred_at FROM {$prefix}arn_identity_audit ORDER BY id", ARRAY_A );
	// Reproduz v1 e uma interrupção após a primeira coluna do upgrade.
	$db->query( "ALTER TABLE {$prefix}arn_identity_links DROP COLUMN generation" );
	$db->query( "ALTER TABLE {$prefix}arn_identity_audit DROP COLUMN previous_generation, DROP COLUMN new_generation" );
	$refused( static fn() => $links->findForUser( 1 ) );
	$filter = static fn( string $sql ): string => str_contains( $sql, 'ADD `previous_generation`' ) ? 'INVALID MIGRATION FIXTURE' : $sql;
	add_filter( 'query', $filter );
	$refused( static fn() => $links->migrateGenerations() );
	remove_filter( 'query', $filter );
	$filter = null;
	$refused( static fn() => $links->findForUser( 1 ) );
	$links->migrateGenerations();
	$links->migrateGenerations();
	$assert( null === $links->findForUser( 1 ) );
	$legacy = $links->findForUser( 987654321 );
	$assert( null !== $legacy && $legacy->active && $legacy->authorized && 'synthetic-legacy' === $legacy->subject && 1 === $legacy->generation );
	$assert( $db->get_results( "SELECT user_id, actor_id, action, occurred_at FROM {$prefix}arn_identity_audit ORDER BY id", ARRAY_A ) === $history_before );
	$assert( '0' === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_identity_audit WHERE new_generation IS NOT NULL" ) );
	$db->query( "ALTER TABLE {$prefix}arn_identity_links ALTER COLUMN generation SET DEFAULT 7" );
	$claim = $links->claimActive( static fn() => new AuthorizedIdentityClaim( new LocalIdentityLink( 987654322, 'accounts.google.com', 'synthetic-claim', true, true ), $actor ) );
	$assert( 1 === $claim->generation && 1 === $links->findForUser( 987654322 )->generation );
	$claim_history = $db->get_results( "SELECT previous_generation, new_generation FROM {$prefix}arn_identity_audit WHERE user_id=987654322 ORDER BY id", ARRAY_A );
	$assert(
		array(
			array(
				'previous_generation' => null,
				'new_generation'      => '1',
			),
			array(
				'previous_generation' => '1',
				'new_generation'      => '1',
			),
		) === $claim_history
	);
	$auth->installSchema();
	$user = wp_insert_user(
		array(
			'user_login' => $prefix . 'fictional',
			'user_email' => $prefix . '@example.test',
			'user_pass'  => wp_generate_password( 40 ),
			'role'       => 'administrator',
		)
	);
	$assert( is_int( $user ) );
	$context = new ChangeContext( $actor, 'Ensaio ficticio', 'fixture/institutional', time() );
	$auth->createAccount( $user, $context );
	$auth->changeState( $user, AccountState::Active, 1, $context );
	$links->create( new LocalIdentityLink( $user, 'accounts.google.com', 'fictional-sub', false, false ), $actor );
	$assert( 1 === $links->findForUser( $user )->generation );
	$links->changeState( $user, true, true, $actor );
	$assert( 2 === $links->findForUser( $user )->generation );
	$links->changeState( $user, true, true, $actor );
	$assert( 2 === $links->findForUser( $user )->generation );
	$source           = new PrincipalSource( $links, $auth, static fn(): bool => true, static fn(): int => time() );
	$issuer           = new SessionIssuer( $source );
	$_SERVER['HTTPS'] = 'on';
	$token            = $issuer->issue( $links->findForUser( $user ) );
	$assert( null !== $source->read( $user, $token ) );
	wp_set_current_user( $user );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $token );
	$native                      = new WordPressFederatedAccess( static fn( int $id ) => $links->findForUser( $id ), static fn(): bool => false, $source );
	$assert( $native->filterCurrentUser( $user ) === $user );
	$module  = new ModuleAccess( $source, static fn(): int => time() );
	$request = new Request( Action::ReadContent, Scope::institutional(), 0, 0, true, true, true, true );
	$assert( ! $module->allows( $request ) ); // Administrador técnico sem concessão.
	$auth->createGrant( new Grant( $user, Role::Employee, Scope::institutional(), time() - 1, null ), 2, $context );
	$assert( null === $source->read( $user, $token ) );
	$token                       = $issuer->issue( $links->findForUser( $user ) );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $token );
	$assert( $module->allows( $request ) );
	$extra                       = $issuer->issue( $links->findForUser( $user ) );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $extra );
	$uncomposed                  = new WordPressFederatedAccess( static fn( int $id ) => $links->findForUser( $id ), static fn(): bool => true );
	$assert( false === $uncomposed->filterCurrentUser( $user ) );
	$assert( null === $source->read( $user, $extra ) );
	$assert( null !== $source->read( $user, $token ) );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user, time() + 3600, 'logged_in', $token );
	$links->changeState( $user, false, false, $actor );
	$assert( ! $module->allows( $request ) );
	$links->changeState( $user, true, true, $actor );
	$assert( 4 === $links->findForUser( $user )->generation );
	$assert( ! $module->allows( $request ) ); // Reactivação não restaura o token antigo.
	$fresh = $issuer->issue( $links->findForUser( $user ) );
	$assert( null !== $source->read( $user, $fresh ) );
	$racing = new PrincipalSource(
		$links,
		$auth,
		static fn(): bool => true,
		static fn(): int => time(),
		static function ( int $id, string $value ) use ( $links, $actor ): ?array {
			$metadata = WP_Session_Tokens::get_instance( $id )->get( $value );
			$links->changeState( $id, false, false, $actor );
			return $metadata;
		}
	);
	$assert( null === $racing->read( $user, $fresh ) );
	$links->changeState( $user, true, true, $actor );
	$before = count( WP_Session_Tokens::get_instance( $user )->get_all() );
	$refused( static fn() => ( new SessionIssuer( $racing ) )->issue( $links->findForUser( $user ) ) );
	$assert( count( WP_Session_Tokens::get_instance( $user )->get_all() ) === $before );
	$links->changeState( $user, true, true, $actor );
	$latest = $issuer->issue( $links->findForUser( $user ) );
	$auth->changeState( $user, AccountState::Suspended, $auth->find( $user )->revision, $context );
	$assert( null === $source->read( $user, $latest ) );
	$auth->changeState( $user, AccountState::Active, $auth->find( $user )->revision, $context );
	$assert( null === $source->read( $user, $latest ) );
	$generation = $links->findForUser( $user )->generation;
	$filter     = static fn( string $sql ): string => str_starts_with( $sql, "INSERT INTO `{$prefix}arn_identity_audit`" ) ? 'INVALID AUDIT FIXTURE' : $sql;
	add_filter( 'query', $filter );
	$refused( static fn() => $links->changeState( $user, false, false, $actor ) );
	remove_filter( 'query', $filter );
	$filter = null;
	$assert( $generation === $links->findForUser( $user )->generation && $links->findForUser( $user )->active );
	$assert( (string) $generation === (string) $db->get_var( "SELECT new_generation FROM {$prefix}arn_identity_audit ORDER BY id DESC LIMIT 1" ) );
	// Remover uma linha à mão não permite repor a sua geração enquanto existir histórico.
	$db->query( $db->prepare( "DELETE FROM {$prefix}arn_identity_links WHERE user_id=%d", $user ) );
	$refused( static fn() => $links->create( new LocalIdentityLink( $user, 'accounts.google.com', 'fictional-sub', false, false ), $actor ) );
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter ); }
	wp_set_current_user( $actor );
	if ( null === $old_https ) {
		unset( $_SERVER['HTTPS'] );
	} else {
		$_SERVER['HTTPS'] = $old_https; }
	if ( null === $old_cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $old_cookie; }
	if ( is_int( $user ) && $user > 0 ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user ); }
	foreach ( array( 'arn_identity_links', 'arn_identity_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_audit', 'arn_authz_schema' ) as $table ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $table ) ); }
	$db->close();
}
WP_CLI::success( 'Identidade institucional: ' . $checks . ' verificacoes. Dados ficticios removidos.' );
