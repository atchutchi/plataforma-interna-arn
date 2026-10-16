<?php
/**
 * F1-12 local fictional composition.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exclusive synthetic installer test with guarded cleanup.
use Arn\Intranet\F112\{Configuration, LocalInstaller, LocalRoster, Pages};
use Arn\Intranet\Authorization\{AccountState, Role};
use Arn\Intranet\Authorization\Storage\{AuthorizationStorageService, ChangeContext, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Communications\{NoticeSchema, NoticeRepository, NoticeContent};
use Arn\Intranet\F112\{CurrentActor, Audience};

require_once __DIR__ . '/f112-fixture.php';
function arn_f112_installer_test(): void {
	if ( ! defined( 'ARN_F112_SQL_HARNESS' ) || true !== ARN_F112_SQL_HARNESS || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
		throw new RuntimeException( 'Ensaio exclusivo indisponível.' );
	}
	if ( 'development' === wp_get_environment_type() ) {
		if ( Configuration::current( 'communications' ) || Configuration::current( 'calendar' ) ) {
			throw new RuntimeException( 'Gate development abriu.' );
		}
		WP_CLI::success( 'F112 instalador: development negativo sem efeitos.' );
		return;
	}
	if ( 'local' !== wp_get_environment_type() ) {
		throw new RuntimeException( 'Ambiente local obrigatório.' );
	}
	foreach ( array( 'ARN_ALLOW_LOCAL_COMMUNICATIONS', 'ARN_ALLOW_LOCAL_CALENDAR', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		}
	}
	$prefix = 'arn_f112_' . bin2hex( random_bytes( 5 ) ) . '_';
	$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . 'private';
	if ( ! mkdir( $root, 0700 ) ) {
		throw new RuntimeException( 'Raiz exclusiva indisponível.' );
	}
	$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$db->set_prefix( $prefix );
	$db->suppress_errors( true );
	$checks    = 0;
	$assert    = static function ( bool $ok, string $label ) use ( &$checks ): void {
		if ( ! $ok ) {
			throw new RuntimeException( 'Falhou instalador F112: ' . $label );
		}
		++$checks;
	};
	$deny      = static function ( callable $operation ) use ( $assert ): void {
		try {
			$operation();
		} catch ( RuntimeException ) {
			$assert( true, 'recusa fechada' );
			return;
		}
		$assert( false, 'operação devia recusar' );
	};
	$snapshot  = static function () use ( $db, $prefix, $root ): array {
		$tables = $db->get_col( $db->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_NAME', $db->esc_like( $prefix ) . '%' ) );
		$result = array(
			'normal'    => arn_f112_normal_snapshot(),
			'exclusive' => array(),
			'files'     => array(),
		);
		foreach ( $tables as $table ) {
			$rows   = $db->get_results( $db->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
			$hashes = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
			sort( $hashes );
			$result['exclusive'][ $table ] = $hashes;
		}
		foreach ( glob( $root . DIRECTORY_SEPARATOR . '*' ) as $path ) {
			$result['files'][ basename( $path ) ] = hash_file( 'sha256', $path );
		}
		return $result;
	};
	$before    = arn_f112_normal_snapshot();
	$collision = null;
	try {
		foreach ( array( 'arn_comunicado', 'arn_evento' ) as $type ) {
			register_post_type( $type, \Arn\Intranet\F112\PostTypes::arguments( $type ) );
			$assert( array() === get_all_post_type_supports( $type ), 'âncora sem editor ou revisões' );
		}
		$installer = new LocalInstaller( $prefix, $root );
		$pages     = new Pages( $prefix );
		foreach ( array( 'communications', 'calendar' ) as $module ) {
			$collision = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'draft',
					'post_name'   => $pages->slug( $module ),
					'post_title'  => 'Colisão fictícia',
				)
			);
			$initial   = $snapshot();
			$deny( static fn() => $installer->install() );
			$assert( $initial === $snapshot(), 'colisão não criou recursos' );
			wp_delete_post( $collision, true );
			$collision = null;
		}
		$first = LocalRoster::definitions( $prefix )['a']['login'];
		$path  = $root . DIRECTORY_SEPARATOR . LocalInstaller::passwordFile( $first );
		file_put_contents( $path, 'ocupado' );
		$initial = $snapshot();
		$deny( static fn() => $installer->install() );
		$assert( $initial === $snapshot(), 'ficheiro ocupado sem efeitos' );
		unlink( $path );
		// Even one module table must not be adopted as a fresh installation.
		$schema = new NoticeSchema( $db );
		$table  = $schema->table( 'schema' );
		$db->query( $db->prepare( 'CREATE TABLE %i (id INT PRIMARY KEY) ENGINE=InnoDB', $table ) );
		$initial = $snapshot();
		$deny( static fn() => $installer->install() );
		$assert( $initial === $snapshot(), 'schema parcial não reparado' );
		$db->query( $db->prepare( 'DROP TABLE %i', $table ) );
		$fresh_before = get_option( 'fresh_site' );
		WP_CLI::log( 'Cenário de site novo: ' . ( '1' === $fresh_before ? 'sim' : 'não' ) );
		// Core hashes trim boundary whitespace, while wp_check_password does not.
		// Force the old random_password route to expose that mismatch deterministically.
		$boundary_password = static fn( string $password, int $length ): string => 48 === $length ? ' ' . str_repeat( 'x', 46 ) . ' ' : $password;
		add_filter( 'random_password', $boundary_password, PHP_INT_MAX, 2 );
		try {
			$result = $installer->install();
		} finally {
			remove_filter( 'random_password', $boundary_password, PHP_INT_MAX );
		}
		foreach ( LocalRoster::definitions( $prefix ) as $person ) {
			$secret = file_get_contents( $root . DIRECTORY_SEPARATOR . LocalInstaller::passwordFile( $person['login'] ) );
			$user   = get_user_by( 'login', $person['login'] );
			$assert( 48 === strlen( $secret ) && trim( $secret ) === $secret && wp_check_password( $secret, $user->user_pass, $user->ID ), 'segredo privado canónico com roundtrip nativo exacto' );
		}
		$assert( get_option( 'fresh_site' ) === $fresh_before, 'páginas marcadoras não alteram o estado de site novo' );
		$assert( 6 === count( $result['user_ids'] ) && 2 === count( $result['pages'] ) && ! in_array( false, $result['created'], true ), 'seis contas e duas páginas' );
		$assert( 6 === count( LocalRoster::resolve( $prefix ) ), 'marcas e papéis exactos' );
		$assert( \Arn\Intranet\F112\InstallationState::ready( 'communications', $prefix ) && \Arn\Intranet\F112\InstallationState::ready( 'calendar', $prefix ), 'conclusão explícita dos dois módulos' );
		$marker_name = \Arn\Intranet\F112\InstallationState::optionName( $prefix );
		$marker      = get_option( $marker_name );
		foreach ( array( null, '{"version":1}' ) as $invalid_marker ) {
			if ( null === $invalid_marker ) {
				delete_option( $marker_name );
			} else {
				add_option( $marker_name, $invalid_marker, '', false );
			}
			$partial = $snapshot();
			$assert( ! \Arn\Intranet\F112\Portal::available( 'communications', $prefix ) && ! \Arn\Intranet\F112\Portal::available( 'calendar', $prefix ), 'marcador ausente ou inválido fecha ambos os módulos' );
			$deny( static fn() => $installer->install() );
			$assert( $partial === $snapshot(), 'marcador ausente ou inválido não é reparado' );
			delete_option( $marker_name );
		}
		add_option( $marker_name, $marker, '', false );
		global $wpdb;
		foreach ( array( 'communications', 'calendar' ) as $module ) {
			$page_id = $result['pages'][ $module ];
			$wpdb->update( $wpdb->posts, array( 'post_content' => '' ), array( 'ID' => $page_id ) );
			clean_post_cache( $page_id );
			try {
				$other = 'communications' === $module ? 'calendar' : 'communications';
				$assert( ! \Arn\Intranet\F112\Portal::available( $module, $prefix ) && \Arn\Intranet\F112\Portal::available( $other, $prefix ), 'página incompatível fecha apenas o próprio módulo após conclusão' );
			} finally {
				$wpdb->update( $wpdb->posts, array( 'post_content' => Pages::content( $module ) ), array( 'ID' => $page_id ) );
				clean_post_cache( $page_id );
			}
		}
		$initial = $snapshot();
		$repeat  = $installer->install();
		$assert( $result['user_ids'] === $repeat['user_ids'] && ! in_array( true, $repeat['created'], true ) && $initial === $snapshot(), 'repetição integralmente idempotente' );
		foreach ( $result['pages'] as $module => $id ) {
			$assert( $module === $pages->viewFor( get_post( $id ) ), 'página canónica reconhecida' );
		}
		// Exercise an actual audited creation and edit before repeat installation.
		$auth       = new WpdbAuthorizationRepository( $db );
		$manager    = $result['user_ids']['manager'];
		$clock      = static fn(): int => time();
		$proof      = array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $manager,
			'arn_authz_revision' => $auth->find( $manager )->revision,
		);
		$actors     = new CurrentActor(
			$db,
			'communications',
			LocalRoster::resolve( $prefix ),
			$clock,
			static fn(): array => array(
				$manager,
				$proof,
			)
		);
		$repository = new NoticeRepository( $db, $actors, $clock, str_repeat( 'f', 64 ) );
		$notice     = $repository->create( new NoticeContent( 'Comunicado fictício', 'Corpo fictício', Audience::unit( 'LAB-A' ), 'team-direct', null ), 'Criação sintética instalador' );
		$repository->revise( $notice->id, new NoticeContent( 'Comunicado editado', 'Corpo editado', Audience::unit( 'LAB-A' ), 'team-direct', null ), $notice->revision, 'Edição sintética instalador' );
		$initial = $snapshot();
		$installer->install();
		$assert( $initial === $snapshot(), 'conteúdo existente não reinicializado' );
		$id = $result['user_ids']['a'];
		delete_user_meta( $id, '_arn_f112_fixture' );
		$initial = $snapshot();
		$deny( static fn() => $installer->install() );
		$assert( $initial === $snapshot(), 'conta sem marca não adoptada' );
		update_user_meta( $id, '_arn_f112_fixture', $prefix );
		$auth    = new WpdbAuthorizationRepository( $db );
		$service = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
		$service->changeState( $id, AccountState::Suspended, $auth->find( $id )->revision, new ChangeContext( get_current_user_id(), 'Suspensão sintética instalador', 'LAB-F112', time() ) );
		$initial = $snapshot();
		$deny( static fn() => $installer->install() );
		$assert( $initial === $snapshot(), 'suspensão não reparada' );
		$service->changeState( $id, AccountState::Active, $auth->find( $id )->revision, new ChangeContext( get_current_user_id(), 'Reactivação sintética instalador', 'LAB-F112', time() ) );
		$account = $auth->find( $id );
		$service->revokeGrant( $id, $account->stored_grants[0]->id, $account->revision, new ChangeContext( get_current_user_id(), 'Revogação sintética instalador', 'LAB-F112', time() ) );
		$initial = $snapshot();
		$deny( static fn() => $installer->install() );
		$assert( $initial === $snapshot(), 'revogação não reparada' );
	} finally {
		delete_option( \Arn\Intranet\F112\InstallationState::optionName( $prefix ) );
		if ( is_int( $collision ) && $collision > 0 && str_starts_with( (string) get_post_field( 'post_name', $collision ), rtrim( $prefix, '_' ) ) ) {
			wp_delete_post( $collision, true );
		}
		foreach ( array( 'communications', 'calendar' ) as $module ) {
			$post = get_page_by_path( ( new Pages( $prefix ) )->slug( $module ), OBJECT, 'page' );
			if ( $post instanceof WP_Post && Pages::content( $module ) === $post->post_content ) {
				wp_delete_post( $post->ID, true );
			}
		}
		global $wpdb;
		$anchor_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT ID FROM %i WHERE post_type=%s AND post_excerpt=%s', $wpdb->posts, 'arn_comunicado', 'arn-f112:' . $prefix . ':communications' ) );
		foreach ( $anchor_ids as $anchor_id ) {
			$wpdb->delete( $wpdb->posts, array( 'ID' => $anchor_id ) );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( LocalRoster::definitions( $prefix ) as $person ) {
			$user = get_user_by( 'login', $person['login'] );
			if ( $user instanceof WP_User && $user->user_email === $person['login'] . '@example.invalid' && get_user_meta( $user->ID, '_arn_f112_fixture', true ) === $prefix ) {
				wp_delete_user( $user->ID );
			}
			$file = $root . DIRECTORY_SEPARATOR . LocalInstaller::passwordFile( $person['login'] );
			if ( is_file( $file ) && ! is_link( $file ) ) {
				unlink( $file );
			}
		}
		foreach ( array( 'arn_authz_grants', 'arn_authz_audit', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_audit', 'arn_org_units', 'arn_org_header', 'arn_comm_objects', 'arn_comm_versions', 'arn_comm_audit', 'arn_comm_notifications', 'arn_comm_schema', 'arn_calendar_objects', 'arn_calendar_versions', 'arn_calendar_audit', 'arn_calendar_notifications', 'arn_calendar_schema' ) as $suffix ) {
			$assert( false !== $db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) ), 'limpeza de tabela própria' );
		}
		$assert( rmdir( $root ), 'limpeza de ficheiros próprios' );
		$assert( 0 === (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) ), 'nenhuma tabela residual própria' );
		$after = arn_f112_normal_snapshot();
		if ( $after !== $before ) {
			foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $table ) {
				if ( ( $before[ $table ] ?? null ) !== ( $after[ $table ] ?? null ) ) {
					WP_CLI::log( 'Tabela normal divergente, sem valores: ' . $table );
				}
			}
			foreach ( array_unique( array_merge( array_keys( $before['__options_by_name'] ?? array() ), array_keys( $after['__options_by_name'] ?? array() ) ) ) as $option ) {
				if ( ( $before['__options_by_name'][ $option ] ?? null ) !== ( $after['__options_by_name'][ $option ] ?? null ) ) {
					WP_CLI::log( 'Opção divergente, sem valor: ' . $option );
				}
			}
		}
		$assert( $after === $before, 'todas as linhas normais preservadas' );
	}
	WP_CLI::success( 'F112 instalador: ' . $checks . ' verificações e limpeza própria.' );
}
arn_f112_installer_test();
