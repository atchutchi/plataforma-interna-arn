<?php
/**
 * Failed page creation must leave every runtime entry closed.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exclusive fixture resources, removed in finally.
use Arn\Intranet\F112\{Configuration, InstallationState, LocalInstaller, LocalRoster, Pages, Portal};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Communications\NoticeSchema;
use Arn\Intranet\Calendar\EventSchema;

require_once __DIR__ . '/f112-fixture.php';
function arn_f112_readiness_test(): void {
	if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_F112_SQL_HARNESS' ) || true !== ARN_F112_SQL_HARNESS || ! current_user_can( 'manage_options' ) ) {
		throw new RuntimeException( 'Ensaio de conclusão recusado.' );
	}
	if ( 'development' === wp_get_environment_type() ) {
		if ( Configuration::current( 'communications' ) || Configuration::current( 'calendar' ) ) {
			throw new RuntimeException( 'Gate development abriu.' );
		}
		WP_CLI::success( 'F112 conclusão: development negativo, sem instalação.' );
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
	$checks = 0;
	$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
		if ( ! $ok ) {
			throw new RuntimeException( 'Falhou conclusão F112: ' . $label );
		}
		++$checks;
	};
	foreach ( array( 'communications', 'calendar' ) as $failed_module ) {
		$prefix = 'arn_f112_' . bin2hex( random_bytes( 5 ) ) . '_';
		$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $prefix . 'private';
		$assert( mkdir( $root, 0700 ), 'raiz privada exclusiva' );
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $prefix );
		$db->suppress_errors( true );
		$before     = arn_f112_normal_snapshot();
		$pages      = new Pages( $prefix );
		$fail_page  = static fn( bool $is_empty, array $data ): bool => ( $data['post_name'] ?? '' ) === $pages->slug( $failed_module ) || $is_empty;
		$old_filter = has_filter( 'pre_update_option_fresh_site' );
		try {
			add_filter( 'wp_insert_post_empty_content', $fail_page, PHP_INT_MAX, 2 );
			$failed = false;
			try {
				( new LocalInstaller( $prefix, $root ) )->install();
			} catch ( RuntimeException ) {
				$failed = true;
			} finally {
				remove_filter( 'wp_insert_post_empty_content', $fail_page, PHP_INT_MAX );
			}
			$assert( $failed, 'falha injectada na página ' . $failed_module );
			$assert( has_filter( 'pre_update_option_fresh_site' ) === $old_filter, 'filtro temporário removido mesmo quando a página falha' );
			( new NoticeSchema( $db ) )->assertReady();
			( new EventSchema( $db ) )->assertReady();
			$assert( 6 === count( LocalRoster::resolve( $prefix ) ), 'todos os pré-requisitos que antes abriam gestão já existem' );
			$assert( ! InstallationState::exists( $prefix ), 'nenhum marcador de sucesso parcial' );
			$assert( ! Portal::available( 'communications', $prefix ) && ! Portal::available( 'calendar', $prefix ), 'composição de CMS e portal fechada antes da fonte' );
			$partial       = arn_f112_normal_snapshot();
			$repeat_failed = false;
			try {
				( new LocalInstaller( $prefix, $root ) )->install();
			} catch ( RuntimeException ) {
				$repeat_failed = true;
			}
			$assert( $repeat_failed && arn_f112_normal_snapshot() === $partial, 'repetição recusa sem completar páginas ou repor acessos' );
		} finally {
			remove_filter( 'wp_insert_post_empty_content', $fail_page, PHP_INT_MAX );
			delete_option( InstallationState::optionName( $prefix ) );
			foreach ( array( 'communications', 'calendar' ) as $module ) {
				$post = get_page_by_path( $pages->slug( $module ), OBJECT, 'page' );
				if ( $post instanceof WP_Post && Pages::content( $module ) === $post->post_content ) {
					wp_delete_post( $post->ID, true );
				}
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
			$assert( arn_f112_normal_snapshot() === $before, 'todas as linhas normais preservadas' );
		}
	}
	WP_CLI::success( 'F112 conclusão: ' . $checks . ' verificações e limpeza própria.' );
}
arn_f112_readiness_test();
