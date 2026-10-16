<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Exclusive private files use atomic xb creation and verified cleanup.
/** Exclusive People installer and negative development gate. */
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\LocalUnitContext;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Arn\Intranet\People\LocalPeopleInstaller;
use Arn\Intranet\People\LocalPeopleRoster;
use Arn\Intranet\People\PeopleSchema;
use Arn\Intranet\People\PeoplePages;
use Arn\Intranet\People\SelfService;
use Arn\Intranet\People\WpdbPeopleAuthority;
use Arn\Intranet\People\WpdbPeopleRepository;

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Ensaio People indisponível.' );
}
$prefix = 'arn_peo_' . bin2hex( random_bytes( 5 ) ) . '_';
$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_people_private_' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Raiz privada indisponível.' );
}
$logins       = LocalPeopleRoster::logins( $prefix );
$owned        = array();
$owned_pages  = array();
$capture_page = static function ( int $id ) use ( &$owned_pages, $prefix ): void {
	$post = get_post( $id );
	if ( $post instanceof WP_Post && 'page' === $post->post_type && str_starts_with( $post->post_name, rtrim( $prefix, '_' ) . '-' ) ) {
		$owned_pages[ $id ] = $id;
	}
};
add_action( 'save_post_page', $capture_page, PHP_INT_MIN, 1 );
$capture = static function ( int $id ) use ( &$owned, $logins ): void {
	$user = get_userdata( $id );
	if ( $user instanceof WP_User && in_array( $user->user_login, $logins, true ) && $user->user_email === $user->user_login . '@example.invalid' ) {
		$owned[] = $id;
	}
};
add_action( 'user_register', $capture, PHP_INT_MIN, 1 );
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$initial_post_count = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts );
$count_tables       = static fn(): int => (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) );
$checks             = 0;
$assert             = static function ( bool $value, string $name ) use ( &$checks ): void {
	if ( ! $value ) {
		throw new RuntimeException( 'Falhou People installer: ' . esc_html( $name ) );
	}
	++$checks;
};
$deny               = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, 'recusa' );
		return;
	}
	$assert( false, 'operação devia recusar' );
};
try {
	$installer = new LocalPeopleInstaller( $prefix, $root );
	if ( 'development' === wp_get_environment_type() ) {
		$before = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), $count_tables(), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) );
		$deny( static fn() => $installer->install() );
		$after = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), $count_tables(), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) );
		$assert( $before === $after && array() === $owned && array() === $owned_pages, 'development sem efeitos' );
		echo esc_html( 'People installer: development negativo, ' . $checks . ' verificações.' . PHP_EOL );
		return;
	}
	if ( ! defined( 'ARN_ALLOW_LOCAL_PEOPLE' ) ) {
		$normal_before = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $GLOBALS['wpdb']->esc_like( $GLOBALS['wpdb']->prefix . 'arn_people' ) . '%' ) ) );
		$sql_calls     = 0;
		$count_query   = static function ( string $sql ) use ( &$sql_calls ): string {
			++$sql_calls;
			return $sql;
		};
		add_filter( 'query', $count_query );
		try {
			$deny( static fn() => ( new LocalPeopleInstaller( null, $root ) )->install() );
		} finally {
			remove_filter( 'query', $count_query );
		}
		$normal_after = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $GLOBALS['wpdb']->esc_like( $GLOBALS['wpdb']->prefix . 'arn_people' ) . '%' ) ) );
		$assert( 0 === $sql_calls && $normal_before === $normal_after, 'flag normal ausente recusa antes de SQL e contas' );
	}
	foreach ( array( 'ARN_ALLOW_LOCAL_PEOPLE', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		}
		$assert( true === constant( $flag ), 'flag local activa' );
	}
	$deny( static fn() => ( new LocalPeopleInstaller( 'arn_peo_bad_', $root ) )->install() );
	$assert( 0 === $count_tables() && array() === $owned, 'prefixo inválido sem efeito' );
	$deny( static fn() => ( new LocalPeopleInstaller( $prefix, ABSPATH ) )->install() );
	$assert( 0 === $count_tables() && array() === $owned, 'raiz pública sem efeito' );
	$collision_page = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Colisão exclusiva',
			'post_name'    => ( new PeoplePages( $prefix ) )->slug( 'team' ),
			'post_content' => 'Outro conteúdo',
		),
		true
	);
	$assert( is_int( $collision_page ) && $collision_page > 0, 'colisão de página preparada' );
	$before_collision = array( $count_tables(), count( $owned ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) );
	$deny( static fn() => $installer->install() );
	$assert( array( $count_tables(), count( $owned ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) ) === $before_collision, 'colisão de página recusa antes de efeitos' );
	wp_delete_post( $collision_page, true );
	$collision_file = $root . DIRECTORY_SEPARATOR . str_replace( '.', '-', $logins['alfa'] ) . '.password';
	file_put_contents( $collision_file, 'colisao' );
	$deny( static fn() => $installer->install() );
	$assert( 0 === $count_tables() && array() === $owned, 'colisão de ficheiro sem adopção' );
	unlink( $collision_file );
	( new AuthorizationSchema( $db ) )->install();
	$preexisting_units = new WpdbLocalUnitRepository( $db );
	$preexisting_units->installSchema();
	$dependency_state = static function () use ( $db, $prefix, $root, $count_tables ): array {
		$rows = array();
		foreach ( array( 'arn_authz_accounts', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_schema', 'arn_org_audit', 'arn_org_header', 'arn_org_units' ) as $suffix ) {
			$rows[ $suffix ] = $db->get_results( $db->prepare( 'SELECT * FROM %i', $prefix . $suffix ), ARRAY_A );
		}
		return array( $count_tables(), $rows, (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), scandir( $root ) );
	};
	foreach ( array( 0, 1 ) as $revision ) {
		if ( 1 === $revision ) {
			$preexisting_units->apply( 'initialize', 0, new LocalUnitContext( get_current_user_id(), 'Pré-requisito fictício pré-existente', time() ) );
		}
		$state = $dependency_state();
		$deny( static fn() => $installer->install() );
		$assert( $state === $dependency_state() && array() === $owned && $revision === $preexisting_units->snapshot()->revision, 'dependências pré-existentes recusadas sem efeitos' );
	}
	foreach ( array( 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_audit', 'arn_org_units', 'arn_org_header' ) as $suffix ) {
		$db->query( $db->prepare( 'DROP TABLE %i', $prefix . $suffix ) );
	}
	$assert( 0 === $count_tables(), 'pré-requisitos exclusivos removidos pelo ensaio' );
	$result = $installer->install();
	foreach ( $result['pages'] as $page_id ) {
		$owned_pages[ $page_id ] = $page_id; }
	$installer_admin = get_current_user_id();
	try {
		wp_set_current_user( $result['user_ids']['alfa'] );
		foreach ( array( 'directory', 'profile', 'team' ) as $view ) {
			$people_page = get_post( $result['pages'][ $view ] );
			$assert( $people_page instanceof WP_Post && PeoplePages::content( $view ) === $people_page->post_content, 'página exclusiva de conteúdo fixo' );
			$rest     = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/pages/' . $result['pages'][ $view ] ) );
			$data     = $rest->get_data();
			$rendered = is_array( $data ) ? ( $data['content']['rendered'] ?? '' ) : '';
			$assert( 200 === $rest->get_status() && is_string( $rendered ) && ! str_contains( $rendered, 'Alfa Teste' ) && ! str_contains( $rendered, 'Guardar contactos' ) && ! str_contains( $rendered, 'name="phone"' ), 'REST da fixture não expõe People' );
		}
	} finally {
		wp_set_current_user( $installer_admin );
	}
	$assert(
		array( 'user_ids', 'created', 'pages' ) === array_keys( $result ) && 3 === count( $result['user_ids'] ) && 3 === count( $result['pages'] ) && array(
			'alfa'    => true,
			'beta'    => true,
			'manager' => true,
		) === $result['created'],
		'três contas exclusivas criadas'
	);
	$assert( 3 === count( $owned ) && 10 === $count_tables(), 'tabelas People, autorização e unidades exclusivas' );
	$alfa          = $result['user_ids']['alfa'];
	$alfa_snapshot = ( new WpdbAuthorizationRepository( $db ) )->find( $alfa );
	$alfa_session  = array(
		'arn_authz_local'    => true,
		'arn_authz_user_id'  => $alfa,
		'arn_authz_revision' => $alfa_snapshot->revision,
	);
	$editor        = new WpdbPeopleRepository( $db, new WpdbPeopleAuthority( $db, static fn(): array => array( $alfa, $alfa_session ) ) );
	$edited        = $editor->updateOwn( $alfa, new SelfService( '+245 111', 'Sala de ensaio', '' ), 1 );
	$assert( 2 === $edited->revision, 'contacto próprio editado antes da repetição' );
	$pages_before   = array_map( static fn( int $id ): array => get_object_vars( get_post( $id ) ), $result['pages'] );
	$options_before = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options );
	$again          = $installer->install();
	$assert(
		$again['user_ids'] === $result['user_ids'] && $again['pages'] === $result['pages'] && array(
			'alfa'    => false,
			'beta'    => false,
			'manager' => false,
		) === $again['created'],
		'repetição exacta'
	);
	$assert( 2 === $editor->find( $alfa )->revision && '+245 111' === $editor->find( $alfa )->self_service->phone, 'repetição conserva alteração legítima' );
	$assert( array_map( static fn( int $id ): array => get_object_vars( get_post( $id ) ), $result['pages'] ) === $pages_before && (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) === $options_before, 'repetição não altera páginas nem opções' );
	wp_update_post(
		array(
			'ID'           => $result['pages']['team'],
			'post_content' => 'Conteúdo incompatível',
		)
	);
	$before_bad_page = array( get_object_vars( get_post( $result['pages']['team'] ) ), $editor->find( $alfa )->revision, count( $owned ), $count_tables(), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) );
	$deny( static fn() => $installer->install() );
	$assert( array( get_object_vars( get_post( $result['pages']['team'] ) ), $editor->find( $alfa )->revision, count( $owned ), $count_tables(), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) ) === $before_bad_page, 'repetição recusa página incompatível sem reparar ou alterar dados' );
	wp_update_post(
		array(
			'ID'           => $result['pages']['team'],
			'post_content' => PeoplePages::content( 'team' ),
		)
	);
	delete_user_meta( $alfa, \Arn\Intranet\Access\LocalFixtureUser::META_KEY );
	$deny( static fn() => $installer->install() );
	update_user_meta( $alfa, \Arn\Intranet\Access\LocalFixtureUser::META_KEY, '1' );
	$assert( 2 === $editor->find( $alfa )->revision, 'marca errada recusada sem adopção' );
	$repository = new WpdbAuthorizationRepository( $db );
	$service    = new AuthorizationStorageService( $repository, new LocalAuthorizationStorageAuthority() );
	$beta       = $result['user_ids']['beta'];
	$snapshot   = $repository->find( $beta );
	$service->changeState( $beta, AccountState::Suspended, $snapshot->revision, new ChangeContext( get_current_user_id(), 'Suspensão fictícia para ensaio', 'LAB-PEOPLE', time() ) );
	$deny( static fn() => $installer->install() );
	$assert( AccountState::Suspended === $repository->find( $beta )->state, 'repetição não repara suspensão' );
	$snapshot = $repository->find( $beta );
	$service->changeState( $beta, AccountState::Active, $snapshot->revision, new ChangeContext( get_current_user_id(), 'Reactivação fictícia para ensaio', 'LAB-PEOPLE', time() ) );
	$snapshot = $repository->find( $beta );
	$service->revokeGrant( $beta, $snapshot->stored_grants[0]->id, $snapshot->revision, new ChangeContext( get_current_user_id(), 'Revogação fictícia para ensaio', 'LAB-PEOPLE', time() ) );
	$deny( static fn() => $installer->install() );
	$assert( $repository->find( $beta )->stored_grants[0]->grant->revoked, 'repetição não repara revogação' );
	$people = new WpdbPeopleRepository( $db, new WpdbPeopleAuthority( $db ) );
	$assert( 3 === count( $people->all() ), 'perfis preservados após recusa' );
	echo esc_html( 'People installer: local exclusivo, ' . $checks . ' verificações.' . PHP_EOL );
} finally {
	remove_action( 'save_post_page', $capture_page, PHP_INT_MIN );
	foreach ( $owned_pages as $page_id ) {
		$owned_post = get_post( $page_id );
		if ( $owned_post instanceof WP_Post && 'page' === $owned_post->post_type && str_starts_with( $owned_post->post_name, rtrim( $prefix, '_' ) . '-' ) ) {
			wp_delete_post( $page_id, true );
		}
	}
	foreach ( array( 'directory', 'profile', 'team' ) as $view ) {
		if ( get_page_by_path( ( new PeoplePages( $prefix ) )->slug( $view ), OBJECT, 'page' ) instanceof WP_Post ) {
			throw new RuntimeException( 'Limpeza People de páginas incompleta.' );
		}
	}
	remove_action( 'user_register', $capture, PHP_INT_MIN );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $owned as $fixture_user_id ) {
		$user = get_userdata( $fixture_user_id );
		if ( $user instanceof WP_User && in_array( $user->user_login, $logins, true ) ) {
			wp_delete_user( $fixture_user_id );
		}
	}
	foreach ( array( 'arn_people_audit', 'arn_people', 'arn_people_schema', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_audit', 'arn_org_units', 'arn_org_header' ) as $suffix ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) );
	}
	$files = glob( $root . DIRECTORY_SEPARATOR . '*.password' );
	foreach ( false === $files ? array() : $files as $file ) {
		if ( dirname( $file ) === $root && ! is_link( $file ) ) {
			unlink( $file );
		}
	}
	if ( is_dir( $root ) ) {
		rmdir( $root );
	}
	if ( file_exists( $root ) ) {
		throw new RuntimeException( 'Limpeza People de ficheiros incompleta.' );
	}
	if ( 0 !== $count_tables() ) {
		throw new RuntimeException( 'Limpeza People de tabelas incompleta.' );
	}
	foreach ( $logins as $login ) {
		if ( false !== get_user_by( 'login', $login ) ) {
			throw new RuntimeException( 'Limpeza People de contas incompleta.' );
		}
	}
	if ( $initial_post_count !== (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts ) ) {
		throw new RuntimeException( 'Limpeza People de posts incompleta.' );
	}
	$db->close();
}
