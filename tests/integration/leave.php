<?php
/** Explicit leave installation on new random resources, with global row preservation.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\{AuthorizationStorageService, ChangeContext, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Requests\{Configuration, CurrentActor, InstallationState, LocalInstaller, LocalRoster, Pages, Repository, Schema};

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals -- Disposable synthetic resources and hash-only preservation, never normal accounts.
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_REQUESTS_SQL_HARNESS' ) || true !== ARN_REQUESTS_SQL_HARNESS || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio de instalação de pedidos indisponível.' );
}
if ( 'development' === wp_get_environment_type() ) {
	if ( Configuration::current() ) {
		throw new RuntimeException( 'Gate abriu em development.' );
	}
	WP_CLI::success( 'Leave integration: development recusado antes dos recursos.' );
	return;
}
if ( 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Ambiente local obrigatório.' );
}
foreach ( array( 'ARN_ALLOW_LOCAL_REQUESTS', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
	if ( ! defined( $flag ) ) {
		define( $flag, true );
	}
	if ( true !== constant( $flag ) ) {
		throw new RuntimeException( 'Opt-in de ensaio recusado.' );
	}
}
global $wpdb;
$admin      = get_current_user_id();
$old_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$prefix     = 'arn_req_' . bin2hex( random_bytes( 5 ) ) . '_';
$root       = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . 'installer';
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Raiz privada exclusiva indisponível.' );
}
$checks        = 0;
$assert        = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou Leave integration: ' . $label );
	}
	++$checks;
};
$deny          = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( RuntimeException ) {
		$assert( true, 'recusa sem reparação' );
		return;
	}
	$assert( false, 'operação indevida aceite' );
};
$snapshot      = static function ( array $tables ) use ( $wpdb ): array {
	$result = array();
	foreach ( $tables as $table ) {
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $wpdb->last_error ) {
			throw new RuntimeException( 'Snapshot de preservação indisponível.' );
		}
		$hashes = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
		sort( $hashes, SORT_STRING );
		$result[ $table ] = $hashes;
	}
	return $result;
};
$normal_tables = $wpdb->get_col( 'SHOW TABLES' );
if ( ! is_array( $normal_tables ) || '' !== $wpdb->last_error ) {
	throw new RuntimeException( 'Inventário de preservação indisponível.' );
}
$baseline = $snapshot( $normal_tables );
$db       = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$schema = new Schema( $db );
$tables = array_map( $schema->table( ... ), array( 'requests', 'types', 'audit', 'notifications', 'schema' ) );
foreach ( array( 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_audit', 'arn_org_units', 'arn_org_header' ) as $part ) {
	$tables[] = $prefix . $part;
}
$task_pages     = new Pages( $prefix );
$owned_pages    = array();
$preserve_fresh = static fn( mixed $proposed, mixed $previous ): mixed => $previous; // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Native option filter.
add_filter( 'pre_update_option_fresh_site', $preserve_fresh, PHP_INT_MAX, 2 );
try {
	foreach ( $tables as $table ) {
		$assert( ! in_array( $table, $normal_tables, true ), 'prefixo exclusivo livre' );
	}
	$installer = new LocalInstaller( $prefix, $root );
	$collision = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => 'Colisão fictícia',
			'post_name'   => $task_pages->slug( 'own' ),
		),
		true
	);
	$assert( is_int( $collision ) && $collision > 0, 'colisão própria criada' );
	$owned_pages[] = $collision;
	$before        = $snapshot( $normal_tables );
	$deny( $installer->install( ... ) );
	$assert( $before === $snapshot( $normal_tables ) && array() === glob( $root . DIRECTORY_SEPARATOR . '*' ), 'colisão não cria identidades ou ficheiros' );
	$assert( false !== wp_delete_post( $collision, true ), 'colisão própria removida' );
	$owned_pages = array();
	$result      = $installer->install();
	$owned_pages = array_values( $result['pages'] );
	$assert( 4 === count( $result['user_ids'] ) && array( true, true, true, true ) === array_values( $result['created'] ) && 2 === count( $owned_pages ), 'instalação explícita completa' );
	$assert( InstallationState::ready( $prefix ), 'marcador coincide com recursos reais' );
	$schema->assertReady();
	$before = $snapshot( array_merge( $normal_tables, $tables ) );
	$repeat = $installer->install();
	$assert( $result['user_ids'] === $repeat['user_ids'] && array( false, false, false, false ) === array_values( $repeat['created'] ) && $before === $snapshot( array_merge( $normal_tables, $tables ) ), 'repetição integral não altera linhas' );
	if ( ! defined( 'ARN_ALLOW_LOCAL_LEAVE' ) ) {
		define( 'ARN_ALLOW_LOCAL_LEAVE', true );
	}
	$leave_schema = new \Arn\Intranet\Leave\Schema( $db );
	foreach ( array( 'requests', 'calendars', 'audit', 'notifications', 'schema' )as$part ) {
		$tables[] = $leave_schema->table( $part );
	}
	$leave_schema->install();
	$roster  = LocalRoster::resolve( $prefix );
	$auth    = new WpdbAuthorizationRepository( $db );
	$task_id = $result['user_ids']['manager'];
	wp_set_current_user( $task_id );
	$expiry                        = time() + 3600;
	$manager                       = WP_Session_Tokens::get_instance( $task_id );
	$token                         = $manager->create( $expiry );
	$session                       = $manager->get( $token );
	$session['arn_authz_local']    = true;
	$session['arn_authz_user_id']  = $task_id;
	$session['arn_authz_revision'] = $auth->find( $task_id )->revision;
	$manager->update( $token, $session );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $task_id, $expiry, 'logged_in', $token );
	$test_now                    = time();
	$clock                       = static fn(): int => $test_now;
	$repo                        = new \Arn\Intranet\Leave\Repository( $db, new CurrentActor( $db, $roster, $clock ), $clock );
	require __DIR__ . '/leave-cases.php';
} finally {
	$db->query( 'ROLLBACK' );
	wp_set_current_user( $admin );
	if ( null === $old_cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $old_cookie;
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( LocalRoster::definitions( $prefix ) as $person ) {
		$user = get_user_by( 'login', $person['login'] );
		if ( $user instanceof WP_User ) {
			if ( $user->user_email !== $person['login'] . '@example.invalid' || get_user_meta( $user->ID, '_arn_requests_fixture', true ) !== $prefix || ! wp_delete_user( $user->ID ) ) {
				throw new RuntimeException( 'Limpeza de identidade fictícia recusada.' );
			}
		}
		$task_path = $root . DIRECTORY_SEPARATOR . LocalInstaller::passwordFile( $person['login'] );
		if ( is_file( $task_path ) && ! is_link( $task_path ) ) {
			unlink( $task_path );
		}
	}
	foreach ( array( 'own', 'team' ) as $view ) {
		$task_post = get_page_by_path( $task_pages->slug( $view ), OBJECT, 'page' );
		if ( $task_post instanceof WP_Post && ! in_array( (int) $task_post->ID, $owned_pages, true ) && Pages::content( $view ) === $task_post->post_content ) {
			$owned_pages[] = (int) $task_post->ID;
		}
	}
	foreach ( $owned_pages as $task_page ) {
		$task_post = get_post( $task_page );
		if ( $task_post instanceof WP_Post ) {
			if ( ! str_starts_with( $task_post->post_name, rtrim( $prefix, '_' ) . '-' ) || false === wp_delete_post( $task_page, true ) ) {
				throw new RuntimeException( 'Limpeza de página exclusiva recusada.' );
			}
		}
	}
	delete_option( InstallationState::optionName( $prefix ) );
	foreach ( $tables as $table ) {
		if ( ! str_starts_with( $table, $prefix ) || false === $db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $table ) ) ) {
			throw new RuntimeException( 'Limpeza de tabela exclusiva recusada.' );
		}
	}
	$db->close();
	remove_filter( 'pre_update_option_fresh_site', $preserve_fresh, PHP_INT_MAX );
	if ( ! rmdir( $root ) ) {
		throw new RuntimeException( 'Raiz exclusiva contém recursos inesperados.' );
	}
}
$after          = $snapshot( $normal_tables );
$changed_tables = array_keys( array_filter( $after, static fn( array $rows, string $table ): bool => $rows !== $baseline[ $table ], ARRAY_FILTER_USE_BOTH ) );
$assert( array() === $changed_tables, 'todas as linhas das tabelas anteriores preservadas: ' . implode( ', ', $changed_tables ) );
WP_CLI::success( 'Leave integration: ' . $checks . ' verificações, ' . count( $normal_tables ) . ' tabelas anteriores preservadas e recursos exclusivos removidos.' );
