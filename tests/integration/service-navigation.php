<?php
/**
 * Services use the real WordPress navigation authorities on an empty local lab.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\EditorialPreview\Portal;
use Arn\Intranet\EditorialPreview\ServiceDirectory;
use Arn\Intranet\EditorialPreview\NavigationPageList;
use Arn\Intranet\EditorialPreview\ModuleNavigation;
use Arn\Intranet\F112\Navigation;
use Arn\Intranet\F112\LocalInstaller;
use Arn\Intranet\F112\LocalRoster;
use Arn\Intranet\F112\Pages;
use Arn\Intranet\F112\InstallationState;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\People\LocalPeopleInstaller;
use Arn\Intranet\People\LocalPeopleRoster;
use Arn\Intranet\People\PeopleNavigation;
use Arn\Intranet\People\PeoplePages;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Arn\Intranet\Organization\Storage\LocalUnitContext;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- CLI fixture proof, no production entry point.
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio de serviços apenas em laboratório local.' );
}
if ( 'development' === wp_get_environment_type() ) {
	if ( array() !== PeopleNavigation::destinations() || array() !== Navigation::destinations() || array() !== Portal::model( 'services', array() )['services'] ) {
		throw new RuntimeException( 'Destinos indevidos fora do ambiente local.' );
	}
	echo esc_html( 'Services WordPress: development negativo, sem criar recursos.' . PHP_EOL );
	return;
}
global $wpdb;
$suffixes = array( 'arn_people_audit', 'arn_people', 'arn_people_schema', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_audit', 'arn_org_units', 'arn_org_header' );
foreach ( array( 'arn_comm_', 'arn_calendar_' ) as $module_prefix ) {
	foreach ( array( 'objects', 'versions', 'audit', 'notifications', 'schema' ) as $part ) {
		$suffixes[] = $module_prefix . $part;
	}
}
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $wpdb->prefix );
// Refuse before creating anything if an existing business module could be affected.
foreach ( $suffixes as $suffix ) {
	if ( 0 !== (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->prefix . $suffix ) ) ) {
		throw new RuntimeException( 'O ensaio exige tabelas de negócio ausentes.' );
	}
}
$logins = LocalPeopleRoster::logins();
foreach ( LocalRoster::definitions() as $label => $person ) {
	$logins[ 'f112_' . $label ] = $person['login'];
}
foreach ( $logins as $login ) {
	if ( false !== get_user_by( 'login', $login ) || false !== get_user_by( 'email', $login . '@example.invalid' ) ) {
		throw new RuntimeException( 'O ensaio exige contas fictícias ausentes.' );
	}
}
$service_pages = new PeoplePages();
$service_pages->preflight( false );
( new Pages() )->preflight( false );
if ( InstallationState::exists( $wpdb->prefix ) ) {
	throw new RuntimeException( 'O ensaio exige marcador F112 ausente.' );
}
$admin  = get_current_user_id();
$cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_services_' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Raiz privada indisponível.' );
}
$owned_pages             = array();
$owned_users             = array();
$owned_navigation        = array();
$owned_anchors           = array();
$navigation_fixture_slug = 'arn-services-fixture-' . bin2hex( random_bytes( 5 ) );
$capture_page            = static function ( int $id ) use ( &$owned_pages, $navigation_fixture_slug ): void {
	$post = get_post( $id );
	if ( $post instanceof WP_Post && ( $navigation_fixture_slug === $post->post_name || in_array( $post->post_name, array( 'funcionarios', 'meu-perfil', 'minha-equipa', 'comunicados', 'calendario' ), true ) ) ) {
		$owned_pages[ $id ] = $id;
	}
};
$capture_user            = static function ( int $id ) use ( &$owned_users, $logins ): void {
	$user = get_userdata( $id );
	if ( $user instanceof WP_User && in_array( $user->user_login, $logins, true ) && $user->user_email === $user->user_login . '@example.invalid' ) {
		$owned_users[ $id ] = $id;
	}
};
add_action( 'save_post_page', $capture_page, PHP_INT_MIN );
add_action( 'user_register', $capture_user, PHP_INT_MIN );
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou Services: ' . esc_html( $label ) );
	}
	++$checks;
};
try {
	$assert( array() === PeopleNavigation::destinations() && array() === Navigation::destinations(), 'flags desligadas' );
	$assert( array() === Portal::model( 'home', array() )['services'], 'início vazio com flags desligadas' );
	foreach ( array( 'ARN_ALLOW_LOCAL_PEOPLE', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		}
		$assert( true === constant( $flag ), 'opt-in explícito' );
	}
	( new AuthorizationSchema( $db ) )->install();
	$units = new WpdbLocalUnitRepository( $db );
	$units->installSchema();
	$units->apply( 'initialize', 0, new LocalUnitContext( $admin, 'Preparação exclusiva do ensaio de serviços', time() ) );
	$installed      = ( new LocalPeopleInstaller( null, $root ) )->install();
	$generic_parent = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => $navigation_fixture_slug,
			'post_title'  => 'Página de ensaio',
		),
		true
	);
	$assert( is_int( $generic_parent ) && $generic_parent > 0, 'página genérica de ensaio' );
	$generic_child = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_name'   => 'calendario',
			'post_title'  => 'Calendário de ensaio',
			'post_parent' => $generic_parent,
		),
		true
	);
	$assert( is_int( $generic_child ) && $generic_child > 0, 'página filha homónima de ensaio' );
	$ids   = $installed['user_ids'];
	$auth  = new WpdbAuthorizationRepository( $db );
	$login = static function ( int $id, bool $valid = true ) use ( $auth ): void {
		wp_set_current_user( $id );
		$manager                    = WP_Session_Tokens::get_instance( $id );
		$expiry                     = time() + 600;
		$token                      = $manager->create( $expiry );
		$data                       = $manager->get( $token );
		$data['arn_authz_local']    = true;
		$data['arn_authz_user_id']  = $id;
		$data['arn_authz_revision'] = $valid ? $auth->find( $id )->revision : 0;
		$manager->update( $token, $data );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $id, $expiry, 'logged_in', $token );
	};
	$assert( array() === PeopleNavigation::destinations(), 'administrador nativo sem autoridade People' );
	$assert( array() === Portal::model( 'home', array() )['services'], 'início não concede acessos ao administrador' );
	$login( $ids['alfa'] );
	$assert( array( 'directory', 'profile' ) === array_keys( PeopleNavigation::destinations() ), 'colaborador sem equipa' );
	$saved_menu = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE post_type=%s ORDER BY ID', $wpdb->posts, 'wp_navigation' ), ARRAY_A );
	$expected   = ( new ServiceDirectory() )->items();
	$assert( array( 'directory', 'profile' ) === array_column( $expected, 'id' ) && Portal::model( 'services', array( 'q' => 'private' ) )['services'] === $expected, 'modelo real e fontes reais coincidem' );
	$home = Portal::model( 'home', array( 'q' => array( 'ignorado' ) ) );
	$assert( ( $home['services'] ?? null ) === $expected, 'início usa os mesmos destinos autorizados sem filtros do URL' );
	$home_html   = static function () use ( $generic_parent ): string {
		$previous        = $GLOBALS['post'] ?? null;
		$GLOBALS['post'] = get_post( $generic_parent ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Contexto temporário da vista real, restaurado em finally.
		try {
			return Portal::render( array( 'view' => 'home' ) );
		} finally {
			$GLOBALS['post'] = $previous; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restauro do contexto anterior do teste.
		}
	};
	$home_table  = static function ( string $suffix ) use ( $db, $wpdb ): array {
		$rows   = $db->get_results( $db->prepare( 'SELECT * FROM %i', $wpdb->prefix . $suffix ), ARRAY_A );
		$hashes = array_map( static fn( array $row ): string => hash( 'sha256', wp_json_encode( $row ) ), $rows );
		sort( $hashes, SORT_STRING );
		return $hashes;
	};
	$home_before = array();
	foreach ( $suffixes as $suffix ) {
		if ( in_array( $suffix, array( 'arn_people_audit', 'arn_people', 'arn_people_schema', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_audit', 'arn_org_units', 'arn_org_header' ), true ) ) {
			$home_before[ $suffix ] = $home_table( $suffix );
		}
	}
	$home_output = $home_html();
	$assert( 1 === substr_count( $home_output, '<h1 ' ) && str_contains( $home_output, 'Os teus acessos' ) && str_contains( $home_output, 'href="' . esc_url( get_permalink( $installed['pages']['profile'] ) ) . '"' ), 'início real com um título e acesso próprio' );
	$assert( ! str_contains( $home_output, 'href="' . esc_url( get_permalink( $installed['pages']['team'] ) ) . '"' ) && ! str_contains( $home_output, 'Em preparação' ), 'início sem equipa recusada ou operações futuras' );
	foreach ( $home_before as $suffix => $rows ) {
		$assert( $rows === $home_table( $suffix ), 'leitura do início preserva dados e auditoria ' . $suffix );
	}
	$blocks = new WP_Block_List( array(), array() );
	$blocks = PeopleNavigation::links( $blocks );
	$blocks = PeopleNavigation::links( $blocks );
	$actual = array();
	foreach ( $blocks as $block ) {
		$actual[] = $block->attributes['url'];
	}
	$assert( 2 === count( $blocks ) && array_column( $expected, 'url' ) === $actual, 'menu coincide e não duplica' );
	$automatic_menu = do_blocks( '<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:page-list /--><!-- /wp:navigation -->' );
	$assert( 1 === substr_count( $automatic_menu, 'href="' . esc_url( get_permalink( $installed['pages']['profile'] ) ) . '"' ) && 1 === substr_count( $automatic_menu, 'href="' . esc_url( get_permalink( $installed['pages']['directory'] ) ) . '"' ), 'fallback automático sem duplicação' );
	$assert( ! str_contains( $automatic_menu, 'href="' . esc_url( get_permalink( $installed['pages']['team'] ) ) . '"' ), 'fallback automático sem equipa recusada' );
	$assert( str_contains( $automatic_menu, 'href="' . esc_url( get_permalink( $generic_child ) ) . '"' ), 'página filha com slug homónimo preservada' );
	$manual_link    = static fn( string $label, string $url ): string => '<!-- wp:navigation-link ' . wp_json_encode(
		array(
			'label' => $label,
			'url'   => $url,
			'kind'  => 'custom',
		)
	) . ' /-->';
	$manual_content = '<!-- wp:navigation-submenu {"label":"Acessos de ensaio"} -->'
		. $manual_link( 'Equipa reservada', get_permalink( $installed['pages']['team'] ) )
		. $manual_link( 'Equipa por caminho equivalente', home_url( '/./minha-equipa/' ) )
		. $manual_link( 'Os meus dados', get_permalink( $installed['pages']['profile'] ) )
		. '<!-- wp:navigation-submenu {"label":"Mais acessos"} -->'
		. $manual_link( 'Perfil repetido', get_permalink( $installed['pages']['profile'] ) . '#dados' )
		. $manual_link( 'Página comum', get_permalink( $generic_child ) )
		. $manual_link( 'Ligação externa', 'https://other.example.invalid/minha-equipa/' )
		. '<!-- /wp:navigation-submenu --><!-- /wp:navigation-submenu -->'
		. '<!-- wp:navigation-submenu ' . wp_json_encode(
			array(
				'label'          => 'Grupo misto',
				'url'            => get_permalink( $installed['pages']['team'] ),
				'kind'           => 'custom',
				'isTopLevelLink' => true,
			)
		) . ' -->'
		. $manual_link( 'Página preservada', get_permalink( $generic_parent ) )
		. '<!-- /wp:navigation-submenu -->'
		. '<!-- wp:navigation-submenu {"label":"Sem destinos"} -->'
		. $manual_link( 'Equipa indisponível', get_permalink( $installed['pages']['team'] ) )
		. '<!-- /wp:navigation-submenu -->';
	$manual_id      = wp_insert_post(
		array(
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_title'   => 'Menu fictício de ensaio',
			'post_content' => wp_slash( $manual_content ),
			'post_author'  => $admin,
		),
		true
	);
	$assert( is_int( $manual_id ) && $manual_id > 0, 'menu fictício guardado' );
	$owned_navigation[] = $manual_id;
	$manual_before      = get_post( $manual_id )->post_content;
	$original_blocks    = new WP_Block_List( parse_blocks( $manual_content ) );
	$original_parsed    = array_map( static fn( $block ): array => $block->parsed_block, iterator_to_array( $original_blocks ) );
	ModuleNavigation::links( $original_blocks );
	$assert( array_map( static fn( $block ): array => $block->parsed_block, iterator_to_array( $original_blocks ) ) === $original_parsed, 'objectos de blocos originais preservados' );
	$manual_html = do_blocks(
		'<!-- wp:navigation ' . wp_json_encode(
			array(
				'ref'         => $manual_id,
				'overlayMenu' => 'never',
			)
		) . ' /-->'
	);
	$assert( ! str_contains( $manual_html, 'href="' . esc_url( get_permalink( $installed['pages']['team'] ) ) . '"' ), 'menu guardado sem equipa recusada' );
	$assert( ! str_contains( $manual_html, 'Equipa por caminho equivalente' ), 'caminho normalizado não contorna filtro do menu' );
	$assert( 1 === substr_count( $manual_html, 'href="' . esc_url( get_permalink( $installed['pages']['profile'] ) ) . '"' ) && str_contains( $manual_html, 'Os meus dados' ), 'menu guardado conserva primeiro rótulo sem duplicação aninhada' );
	$assert( str_contains( $manual_html, 'href="' . esc_url( get_permalink( $generic_child ) ) . '"' ) && get_post( $manual_id )->post_content === $manual_before, 'menu guardado e página comum preservados' );
	$assert( str_contains( $manual_html, 'Grupo misto' ) && str_contains( $manual_html, 'Página preservada' ) && ! str_contains( $manual_html, 'Sem destinos' ), 'submenu recusado conserva filhos permitidos sem botão vazio' );
	$assert( str_contains( $manual_html, 'https://other.example.invalid/minha-equipa/' ) && ! str_contains( $manual_html, 'Perfil repetido' ), 'ligação externa preservada e fragmento local duplicado excluído' );
	$login( $ids['manager'] );
	$manual_manager = do_blocks(
		'<!-- wp:navigation ' . wp_json_encode(
			array(
				'ref'         => $manual_id,
				'overlayMenu' => 'never',
			)
		) . ' /-->'
	);
	$assert( 1 === substr_count( $manual_manager, 'href="' . esc_url( get_permalink( $installed['pages']['team'] ) ) . '"' ), 'menu guardado mostra equipa uma vez ao gestor' );
	$login( $ids['alfa'], false );
	$manual_invalid = do_blocks(
		'<!-- wp:navigation ' . wp_json_encode(
			array(
				'ref'         => $manual_id,
				'overlayMenu' => 'never',
			)
		) . ' /-->'
	);
	$assert( ! str_contains( $manual_invalid, 'href="' . esc_url( get_permalink( $installed['pages']['profile'] ) ) . '"' ) && str_contains( $manual_invalid, 'Página comum' ), 'sessão inválida retira módulos sem ocultar página comum' );
	$login( $ids['alfa'] );
	foreach ( array( 'draft', 'empty-label' ) as $hidden_case ) {
		wp_update_post(
			array(
				'ID'          => $generic_parent,
				'post_status' => 'draft' === $hidden_case ? 'draft' : 'publish',
			)
		);
		$hidden_parent  = array(
			'label' => 'empty-label' === $hidden_case ? '' : 'Grupo oculto',
			'kind'  => 'post-type',
			'type'  => 'page',
			'id'    => $generic_parent,
			'url'   => get_permalink( $generic_parent ),
		);
		$hidden_content = '<!-- wp:navigation-submenu ' . wp_json_encode( $hidden_parent ) . ' -->' . $manual_link( 'Perfil sob pai oculto', get_permalink( $installed['pages']['profile'] ) ) . '<!-- /wp:navigation-submenu -->';
		wp_update_post(
			array(
				'ID'           => $manual_id,
				'post_content' => wp_slash( $hidden_content ),
			)
		);
		$hidden_html = do_blocks(
			'<!-- wp:navigation ' . wp_json_encode(
				array(
					'ref'         => $manual_id,
					'overlayMenu' => 'never',
				)
			) . ' /-->'
		);
		$assert( 1 === substr_count( $hidden_html, 'href="' . esc_url( get_permalink( $installed['pages']['profile'] ) ) . '"' ) && ! str_contains( $hidden_html, 'Perfil sob pai oculto' ), 'pai não renderizado não consome destino autorizado' );
	}
	wp_update_post(
		array(
			'ID'          => $generic_parent,
			'post_status' => 'publish',
		)
	);
	wp_delete_post( $manual_id, true );
	$assert( $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE post_type=%s ORDER BY ID', $wpdb->posts, 'wp_navigation' ), ARRAY_A ) === $saved_menu, 'projecção e links não alteram navegação guardada' );
	$login( $ids['manager'] );
	$assert( array( 'directory', 'profile', 'team' ) === array_keys( PeopleNavigation::destinations() ), 'gestor com equipa' );
	$assert( array( 'directory', 'profile', 'team' ) === array_column( Portal::model( 'home', array() )['services'], 'id' ), 'gestor tem os três atalhos no início' );
	$profile_id = $installed['pages']['profile'];
	wp_update_post(
		array(
			'ID'           => $profile_id,
			'post_content' => '<!-- wp:paragraph --><p>Alterado</p><!-- /wp:paragraph -->',
		)
	);
	$assert( ! isset( PeopleNavigation::destinations()['profile'] ), 'página adulterada excluída' );
	$assert( ! in_array( 'profile', array_column( Portal::model( 'home', array() )['services'], 'id' ), true ), 'início exclui perfil adulterado' );
	wp_update_post(
		array(
			'ID'           => $profile_id,
			'post_content' => PeoplePages::content( 'profile' ),
			'post_status'  => 'draft',
		)
	);
	$assert( ! isset( PeopleNavigation::destinations()['profile'] ), 'página não publicada excluída' );
	$login( $ids['alfa'], false );
	$assert( array() === ( new ServiceDirectory() )->items(), 'geração de sessão inválida' );
	$assert( array() === Portal::model( 'home', array() )['services'] && str_contains( $home_html(), 'Ainda não há acessos disponíveis para a tua conta' ), 'sessão inválida tem início vazio' );
	wp_set_current_user( 0 );
	unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	$assert( array() === ( new ServiceDirectory() )->items(), 'sem actor' );
	$assert( array() === Portal::model( 'home', array() )['services'], 'início sem actor não tem atalhos' );
	wp_set_current_user( $admin );
	foreach ( array( 'ARN_ALLOW_LOCAL_COMMUNICATIONS', 'ARN_ALLOW_LOCAL_CALENDAR' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		}
		$assert( true === constant( $flag ), 'opt-in F112 explícito' );
	}
	$unit_snapshot = $units->snapshot();
	foreach ( $unit_snapshot->units as $unit ) {
		if ( 'LAB-B' === $unit->id && ! $unit->active ) {
			$units->apply( 'reactivate-b', $unit_snapshot->revision, new LocalUnitContext( $admin, 'Preparação fictícia de B para serviços F112', time() ) );
		}
	}
	$f112 = ( new LocalInstaller( null, $root ) )->install();
	$login( $f112['user_ids']['a'] );
	$expected_f112 = ( new ServiceDirectory() )->items();
	$assert( array( 'communications', 'calendar' ) === array_column( $expected_f112, 'id' ) && Portal::model( 'services', array() )['services'] === $expected_f112, 'modelo F112 positivo com autoridade real' );
	$assert( Portal::model( 'home', array() )['services'] === $expected_f112, 'início F112 coincide com destinos autorizados' );
	$assert( str_contains( $home_html(), 'href="' . esc_url( get_permalink( $f112['pages']['calendar'] ) ) . '"' ), 'início renderiza acesso F112 real' );
	require_once __DIR__ . '/home-dashboard-fixture.php';
	$dashboard_cleanup = arn_home_dashboard_fixture( $db, $f112['user_ids'], $login, $assert, $home_table, $owned_anchors );
	$dashboard_cleanup();
	$f112_blocks = Navigation::links( new WP_Block_List( array(), array() ) );
	$f112_blocks = Navigation::links( $f112_blocks );
	$f112_urls   = array();
	foreach ( $f112_blocks as $block ) {
		$f112_urls[] = $block->attributes['url'];
	}
	$assert( 2 === count( $f112_blocks ) && array_column( $expected_f112, 'url' ) === $f112_urls, 'menu F112 coincide e não duplica' );
	$f112_menu = do_blocks( '<!-- wp:navigation {"overlayMenu":"never"} --><!-- wp:page-list /--><!-- /wp:navigation -->' );
	$assert( 1 === substr_count( $f112_menu, 'href="' . esc_url( get_permalink( $f112['pages']['communications'] ) ) . '"' ) && 1 === substr_count( $f112_menu, 'href="' . esc_url( get_permalink( $f112['pages']['calendar'] ) ) . '"' ), 'fallback F112 sem duplicação' );
	$assert( ! str_contains( $f112_menu, 'href="' . esc_url( get_permalink( $installed['pages']['directory'] ) ) . '"' ), 'fallback F112 sem destino People recusado' );
	$manual_f112_content = '<!-- wp:navigation-submenu {"label":"Comunicação de ensaio"} -->'
		. $manual_link( 'Avisos para mim', get_permalink( $f112['pages']['communications'] ) )
		. '<!-- wp:navigation-submenu {"label":"Agenda de ensaio"} -->'
		. $manual_link( 'Eventos para mim', get_permalink( $f112['pages']['calendar'] ) )
		. $manual_link( 'Avisos repetidos', get_permalink( $f112['pages']['communications'] ) . '#avisos' )
		. $manual_link( 'Perfil People recusado', get_permalink( $installed['pages']['profile'] ) )
		. '<!-- /wp:navigation-submenu --><!-- /wp:navigation-submenu -->';
	$manual_f112_id      = wp_insert_post(
		array(
			'post_type'    => 'wp_navigation',
			'post_status'  => 'publish',
			'post_title'   => 'Menu F112 fictício',
			'post_content' => wp_slash( $manual_f112_content ),
			'post_author'  => $admin,
		),
		true
	);
	$assert( is_int( $manual_f112_id ) && $manual_f112_id > 0, 'menu F112 fictício guardado' );
	$owned_navigation[] = $manual_f112_id;
	$manual_f112_before = get_post( $manual_f112_id )->post_content;
	$manual_f112_html   = do_blocks(
		'<!-- wp:navigation ' . wp_json_encode(
			array(
				'ref'         => $manual_f112_id,
				'overlayMenu' => 'never',
			)
		) . ' /-->'
	);
	$assert( 1 === substr_count( $manual_f112_html, 'href="' . esc_url( get_permalink( $f112['pages']['communications'] ) ) . '"' ) && 1 === substr_count( $manual_f112_html, 'href="' . esc_url( get_permalink( $f112['pages']['calendar'] ) ) . '"' ), 'menu F112 guardado sem duplicação em submenus' );
	$assert( str_contains( $manual_f112_html, 'Avisos para mim' ) && ! str_contains( $manual_f112_html, 'Perfil People recusado' ) && get_post( $manual_f112_id )->post_content === $manual_f112_before, 'menu F112 conserva rótulo e conteúdo sem acesso People' );
	wp_delete_post( $manual_f112_id, true );
	wp_update_post(
		array(
			'ID'           => $f112['pages']['communications'],
			'post_content' => '<!-- wp:paragraph --><p>Alterado</p><!-- /wp:paragraph -->',
		)
	);
	$assert( array( 'calendar' ) === array_column( ( new ServiceDirectory() )->items(), 'id' ), 'F112 adulterado excluído sem ocultar calendário' );
	$assert( array( 'calendar' ) === array_column( Portal::model( 'home', array() )['services'], 'id' ), 'início conserva calendário quando comunicado indisponível' );
	$partial_dashboard = Portal::model( 'home', array() )['dashboard'];
	$assert( ! $partial_dashboard['communications']['available'] && $partial_dashboard['calendar']['available'], 'painel isola página de comunicados indisponível' );
	$login( $f112['user_ids']['a'], false );
	$assert( array() === ( new ServiceDirectory() )->items(), 'geração F112 inválida' );
	$login( $ids['manager'] );
	$standalone_list = do_blocks( '<!-- wp:page-list /-->' );
	$assert( str_contains( $standalone_list, 'href="' . esc_url( get_permalink( $installed['pages']['team'] ) ) . '"' ), 'lista fora da navegação mantém comportamento nativo' );
	$pages_before_error = array_column( get_pages(), 'ID' );
	$wrapped            = NavigationPageList::arguments(
		array(
			'render_callback' => static function (): never {
				throw new RuntimeException( 'Falha de renderização fictícia' );
			},
		),
		'core/page-list'
	);
	$thrown             = false;
	try {
		$wrapped['render_callback'](
			array(),
			'',
			new WP_Block(
				array(
					'blockName' => 'core/page-list',
					'attrs'     => array(),
				),
				array( 'showSubmenuIcon' => true )
			)
		);
	} catch ( RuntimeException ) {
		$thrown = true;
	}
	$assert( $thrown && array_column( get_pages(), 'ID' ) === $pages_before_error, 'filtro temporário restaurado após excepção' );
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	$GLOBALS['current_screen'] = WP_Screen::get( 'dashboard' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Contexto administrativo apenas neste processo CLI de teste.
	$assert( array() === PeopleNavigation::destinations() && array() === Navigation::destinations(), 'admin' );
	$assert( array() === Portal::model( 'home', array() )['services'], 'início sem atalhos no contexto admin' );
	$editor_blocks = new WP_Block_List( parse_blocks( $manual_content ) );
	$assert( ModuleNavigation::links( $editor_blocks ) === $editor_blocks, 'edição administrativa não filtrada' );
	unset( $GLOBALS['current_screen'] );
	define( 'REST_REQUEST', true );
	$assert( array() === PeopleNavigation::destinations() && array() === Navigation::destinations(), 'REST' );
	$assert( array() === Portal::model( 'home', array() )['services'], 'início sem atalhos no REST' );
	$assert( ModuleNavigation::links( $editor_blocks ) === $editor_blocks, 'REST conserva blocos para as guardas nativas' );
	echo esc_html( 'Services WordPress: ' . $checks . ' verificações.' . PHP_EOL );
} finally {
	wp_set_current_user( $admin );
	unset( $GLOBALS['current_screen'] );
	if ( null === $cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	}
	remove_action( 'save_post_page', $capture_page, PHP_INT_MIN );
	remove_action( 'user_register', $capture_user, PHP_INT_MIN );
	foreach ( $owned_anchors as $anchor_id ) {
		wp_delete_post( $anchor_id, true );
		if ( null !== get_post( $anchor_id ) ) {
			throw new RuntimeException( 'Limpeza de âncora de ensaio falhou.' );
		}
	}
	foreach ( $owned_navigation as $navigation_id ) {
		wp_delete_post( $navigation_id, true );
	}
	delete_option( InstallationState::optionName( $wpdb->prefix ) );
	foreach ( $owned_pages as $service_page_id ) {
		wp_delete_post( $service_page_id, true );
		if ( null !== get_post( $service_page_id ) ) {
			throw new RuntimeException( 'Limpeza de página de ensaio falhou.' );
		}
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $owned_users as $service_user_id ) {
		wp_delete_user( $service_user_id );
		if ( false !== get_userdata( $service_user_id ) ) {
			throw new RuntimeException( 'Limpeza de conta de ensaio falhou.' );
		}
	}
	foreach ( $suffixes as $suffix ) {
		if ( false === $db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $suffix ) ) ) {
			throw new RuntimeException( 'Limpeza de tabela de ensaio falhou.' );
		}
	}
	$password_files = glob( $root . DIRECTORY_SEPARATOR . '*.password' );
	foreach ( false === $password_files ? array() : $password_files as $file ) {
		if ( ! is_link( $file ) && dirname( $file ) === $root ) {
			unlink( $file );
		}
	}
	rmdir( $root );
	$db->close();
}
