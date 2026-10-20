<?php
/** Native WordPress users/sessions with exclusive CMS and authorization tables.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Cms\{Configuration, CurrentActor, LocalInstaller, SettingsInput, SettingsPage, SettingsRepository, SettingsSchema, SettingsView, UploadPolicy};
use Arn\Intranet\Authorization\{AccountState};
use Arn\Intranet\Authorization\Storage\{AuthorizationStorageService, ChangeContext, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;

// phpcs:disable Squiz.Commenting, WordPress -- Explicit isolated CLI harness, hash-only preservation.
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_CMS_SQL_HARNESS' ) || true !== ARN_CMS_SQL_HARNESS || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio CMS indisponível.' );
}
if ( 'development' === wp_get_environment_type() ) {
	if ( Configuration::current() ) {
		throw new RuntimeException( 'CMS abriu em development.' );
	}
	WP_CLI::success( 'CMS installer: development recusado antes dos recursos.' );
	return;
}
if ( 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Ambiente local obrigatório.' );
}
foreach ( array( 'ARN_ALLOW_LOCAL_CMS_SETTINGS', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) as $flag ) {
	if ( ! defined( $flag ) ) {
		define( $flag, true ); }
	if ( true !== constant( $flag ) ) {
		throw new RuntimeException( 'Opt-in recusado.' ); }
}
global $wpdb;
$admin      = get_current_user_id();
$old_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$prefix     = 'arn_cms_test_' . bin2hex( random_bytes( 6 ) ) . '_';
$root       = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . 'installer';
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Pasta exclusiva indisponível.' ); }
$checks   = 0;
$assert   = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou CMS installer: ' . $label ); }
	++$checks;
};
$deny     = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, 'recusado' );
		return; }
	$assert( false, 'operação indevida aceite' );
};
$snapshot = static function ( array $tables ) use ( $wpdb ): array {
	$out = array();
	foreach ( $tables as $table ) {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			throw new RuntimeException( 'Snapshot indisponível.' ); }
		$hashes = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
		sort( $hashes, SORT_STRING );
		$out[ $table ] = $hashes;
	}
	return $out;
};
$normal   = $wpdb->get_col( 'SHOW TABLES' );
$baseline = $snapshot( $normal );
$db       = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$schema = new SettingsSchema( $db );
$tables = array_map( $schema->table( ... ), array( 'audit', 'state', 'schema' ) );
foreach ( array( 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema' ) as $suffix ) {
	$tables[] = $prefix . $suffix; }
$logins    = LocalInstaller::logins( $prefix );
$installer = new LocalInstaller( $prefix, $root );
$auth      = new WpdbAuthorizationRepository( $db );
$repo      = new SettingsRepository( $db, new CurrentActor( $db, time( ... ) ), time( ... ) );
$login     = static function ( int $id ) use ( $auth ): string {
	wp_set_current_user( $id );
	$expiry                        = time() + 3600;
	$tokens                        = WP_Session_Tokens::get_instance( $id );
	$token                         = $tokens->create( $expiry );
	$session                       = $tokens->get( $token );
	$session['arn_authz_local']    = true;
	$session['arn_authz_user_id']  = $id;
	$session['arn_authz_revision'] = $auth->find( $id )->revision;
	$tokens->update( $token, $session );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $id, $expiry, 'logged_in', $token );
	return $token;
};
try {
	$assert( array() === array_intersect( $tables, $normal ), 'prefixo livre' );
	$collision = $root . DIRECTORY_SEPARATOR . LocalInstaller::passwordFile( $logins[0] );
	file_put_contents( $collision, 'collision' );
	$deny( $installer->install( ... ) );
	$assert( $baseline === $snapshot( $normal ), 'colisão de credencial conserva tabelas' );
	unlink( $collision );
	$result = $installer->install();
	$assert( true === $result['created'] && 2 === count( $result['user_ids'] ), 'duas contas fictícias criadas' );
	$manager = $result['user_ids'][0];
	$denied  = $result['user_ids'][1];
	$before  = $snapshot( array_merge( $normal, $tables ) );
	$repeat  = $installer->install();
	$assert( false === $repeat['created'] && $repeat['user_ids'] === $result['user_ids'] && $before === $snapshot( array_merge( $normal, $tables ) ), 'idempotência sem alterações' );
	$deny( $repo->read( ... ) );
	$login( $denied );
	$deny( $repo->read( ... ) );
	$user = get_userdata( $denied );
	$user->set_role( 'administrator' );
	$deny( $repo->read( ... ) );
	$user->set_role( 'subscriber' );
	$token = $login( $manager );
	$assert( 1 === $repo->read()->revision, 'sessão nativa do gestor lê parâmetros' );
	$before = $snapshot( array_merge( $normal, $tables ) );
	$model  = SettingsPage::decide( 'GET', array(), array(), $repo, $manager, static fn(): bool => false );
	$assert( null !== $model && $before === $snapshot( array_merge( $normal, $tables ) ), 'GET do transporte é só leitura' );
	$post                   = SettingsInput::fields( $repo->read() ) + array( 'revision' => '1', 'nonce' => wp_create_nonce( SettingsInput::nonce( $manager ) ) );
	$post['document_bytes'] = '2048';
	$post['reason']         = 'Alteração fictícia com sessão nativa';
	$verify                 = static fn( string $nonce ): bool => false !== wp_verify_nonce( $nonce, SettingsInput::nonce( get_current_user_id() ) );
	$assert( array( 'saved' => true ) === SettingsPage::decide( 'POST', array(), $post, $repo, $manager, $verify ), 'POST com nonce nativo altera' );
	$assert( 2 === $repo->read()->revision && 1 === count( $repo->history() ), 'revisão e auditoria confirmadas' );
	$model = SettingsPage::decide( 'POST', array(), $post, $repo, $manager, $verify );
	$assert( null !== $model && '' !== $model['error'] && '2048' === $model['fields']['document_bytes'] && 2 === $model['state']->revision, 'conflito conserva campos' );
	$render_before                         = $snapshot( array_merge( $normal, $tables ) );
	$view                                  = $model;
	$payload                               = '<script>window.cmsUnsafe=true</script>';
	$view['fields']['reason']              = $payload;
	$view['history'][0]['after']['reason'] = $payload;
	$view['fields']['document_bytes']      = '123';
	ob_start();
	SettingsView::render( $view );
	$html = ob_get_clean();
	$assert( ! str_contains( $html, $payload ) && 2 === substr_count( $html, esc_html( $payload ) ), 'motivos escapados no formulário e histórico' );
	$assert( str_contains( $html, 'value="123"' ) && str_contains( $html, 'Em vigor: 2 048 bytes' ), 'vista distingue campos recuperados e política actual' );
	$assert( str_contains( $html, 'role="alert" tabindex="-1" autofocus' ) && str_contains( $html, 'aria-describedby="document-help"' ), 'erro focável e ajuda associada' );
	$assert( $render_before === $snapshot( array_merge( $normal, $tables ) ), 'renderização nativa não escreve' );
	$before           = $snapshot( array_merge( $normal, $tables ) );
	$post['revision'] = '2';
	SettingsPage::decide( 'POST', array(), $post, $repo, $manager, $verify );
	$assert( $before === $snapshot( array_merge( $normal, $tables ) ), 'no-op não escreve' );
	$post['nonce'] = 'invalid';
	$assert( null === SettingsPage::decide( 'POST', array(), $post, $repo, $manager, $verify ), 'nonce inválido recusado' );
	$tokens                = WP_Session_Tokens::get_instance( $manager );
	$session               = $tokens->get( $token );
	$expired               = $session;
	$expired['expiration'] = time() - 1;
	$tokens->update( $token, $expired );
	$deny( $repo->read( ... ) );
	$token = $login( $manager );
	update_user_meta( $manager, '_arn_cms_fixture', 'foreign' );
	$deny( $repo->read( ... ) );
	update_user_meta( $manager, '_arn_cms_fixture', $prefix );
	$assert( 2 === $repo->read()->revision, 'marca reposta só na fixture própria' );
	wp_set_current_user( $admin );
	$before = $snapshot( array_merge( $normal, $tables ) );
	$installer->install();
	$assert( $before === $snapshot( array_merge( $normal, $tables ) ), 'repetição conserva política alterada' );
	$service = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
	$service->changeState( $manager, AccountState::Suspended, $auth->find( $manager )->revision, new ChangeContext( $admin, 'Suspensão exclusiva do ensaio', 'LAB-CMS', time() ) );
	$before = $snapshot( array_merge( $normal, $tables ) );
	$deny( $installer->install( ... ) );
	$assert( $before === $snapshot( array_merge( $normal, $tables ) ), 'instalador não reactiva conta' );
	wp_set_current_user( $manager );
	$deny( $repo->read( ... ) );
	$assert( null === SettingsPage::decide( 'GET', array(), array(), $repo, $manager, $verify ), 'conta suspensa não recebe formulário' );
	wp_set_current_user( $admin );
	$assert( false !== $db->query( $db->prepare( 'DROP TABLE %i', $schema->table( 'audit' ) ) ), 'schema parcial exclusivo' );
	$deny( $installer->install( ... ) );
	$assert( '0' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $schema->table( 'audit' ) ) ), 'schema parcial não reparado' );
} finally {
	$db->query( 'ROLLBACK' );
	wp_set_current_user( $admin );
	if ( null === $old_cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $old_cookie; }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $logins as $name ) {
		$user = get_user_by( 'login', $name );
		if ( $user instanceof WP_User ) {
			if ( $name . '@example.invalid' !== $user->user_email || $prefix !== get_user_meta( $user->ID, '_arn_cms_fixture', true ) || ! wp_delete_user( $user->ID ) ) {
				throw new RuntimeException( 'Limpeza de fixture recusada.' ); }
		}
		$path = $root . DIRECTORY_SEPARATOR . LocalInstaller::passwordFile( $name );
		if ( is_file( $path ) && ! is_link( $path ) ) {
			unlink( $path ); }
	}
	foreach ( $tables as $table ) {
		if ( ! str_starts_with( $table, $prefix ) || false === $db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $table ) ) ) {
			throw new RuntimeException( 'Limpeza exclusiva recusada.' ); }
	}
	$db->close();
	if ( ! rmdir( $root ) ) {
		throw new RuntimeException( 'Recursos privados inesperados.' ); }
}
$assert( $baseline === $snapshot( $normal ), 'todas as linhas anteriores preservadas' );
WP_CLI::success( 'CMS installer: ' . $checks . ' verificações e ' . count( $normal ) . ' tabelas anteriores preservadas.' );
