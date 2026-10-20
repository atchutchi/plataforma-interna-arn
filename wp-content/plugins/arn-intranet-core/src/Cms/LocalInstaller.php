<?php
/** Explicit preparation of closed fictional CMS accounts and settings.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSchema, AuthorizationStorageService, ChangeContext, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository};
use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\LocalNewsInstaller;
use RuntimeException;
use WP_User;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
/** No HTTP registration, existing-user adoption or silent repair of partial setup. */
final class LocalInstaller {
	private const ERROR = 'Instalação fictícia de parâmetros recusada.';
	public function __construct( private readonly ?string $exclusive_prefix = null, private readonly ?string $private_root = null ) {}

	public static function logins( string $prefix ): array {
		$lead = 1 === preg_match( '/\Aarn_cms_test_[a-f0-9]{12}_\z/D', $prefix ) ? $prefix : '';
		return array_map( static fn( string $login ): string => $lead . $login, CurrentActor::LOGINS );
	}

	public static function passwordFile( string $login ): string {
		return str_replace( '.', '-', $login ) . '.password';
	}

	public function install(): array {
		if ( ! Configuration::current() || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || ( null !== $this->exclusive_prefix && 1 !== preg_match( '/\Aarn_cms_test_[a-f0-9]{12}_\z/D', $this->exclusive_prefix ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		global $wpdb;
		$prefix = $this->exclusive_prefix ?? $wpdb->prefix;
		$root   = $this->private_root ?? ( defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ? ARN_LOCAL_PRIVATE_FILES_DIR : '' );
		if ( ! is_string( $root ) || ! is_writable( $root ) ) {
			throw new RuntimeException( self::ERROR );
		}
		// Reuse canonical-path and ancestor-link verification without importing bytes.
		new PrivateBlobStore( $root, array( ABSPATH, dirname( __DIR__, 5 ), WP_CONTENT_DIR ), array( 'application/pdf' ), UploadPolicy::MAX_BYTES );
		$root  = realpath( $root );
		$users = array();
		foreach ( self::logins( $prefix ) as $login ) {
			$user = get_user_by( 'login', $login );
			$path = $root . DIRECTORY_SEPARATOR . self::passwordFile( $login );
			// phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Compare the server-derived address with the native record.
			if ( is_link( $path ) || ( $user instanceof WP_User && ( $login . '@example.invalid' !== $user->user_email || array( 'subscriber' ) !== array_values( $user->roles ) || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || $prefix !== get_user_meta( $user->ID, '_arn_cms_fixture', true ) || ! is_file( $path ) || 48 !== strlen( (string) file_get_contents( $path ) ) || ! wp_check_password( (string) file_get_contents( $path ), $user->user_pass, $user->ID ) ) ) || ( ! $user instanceof WP_User && ( file_exists( $path ) || false !== get_user_by( 'email', $login . '@example.invalid' ) ) ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$users[] = $user;
		}
		$all = $users[0] instanceof WP_User && $users[1] instanceof WP_User;
		if ( ! $all && ( $users[0] instanceof WP_User || $users[1] instanceof WP_User ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $prefix );
		$db->suppress_errors( true );
		try {
			$schema        = new SettingsSchema( $db );
			$auth_schema   = new AuthorizationSchema( $db );
			$auth          = new WpdbAuthorizationRepository( $db );
			$module_tables = array_map( $schema->table( ... ), array( 'state', 'audit', 'schema' ) );
			$actual        = $db->get_col( $db->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) );
			if ( ! is_array( $actual ) || '' !== $db->last_error || ( ! $all && ( null !== $this->exclusive_prefix ? array() !== $actual : array() !== array_intersect( $module_tables, $actual ) ) ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$repository = new SettingsRepository( $db, new CurrentActor( $db, time( ... ) ), time( ... ) );
			if ( $all ) {
				$auth_schema->validate();
				$schema->assertReady();
				foreach ( $users as $index => $user ) {
					$snapshot = $auth->find( $user->ID );
					if ( null === $snapshot || AccountState::Active !== $snapshot->state || ( 0 === $index ? null === LocalNewsInstaller::existingGrantId( $snapshot, Role::CmsAdministrator, Scope::institutional() ) : array() !== $snapshot->stored_grants ) ) {
						throw new RuntimeException( self::ERROR );
					}
				}
				// A complete existing installation must have a valid state, never seed it again.
				$db->query( 'START TRANSACTION' );
				( new SettingsReader( $db ) )->locked();
				$db->query( 'ROLLBACK' );
				return array(
					'created'  => false,
					'user_ids' => array_map( static fn( WP_User $user ): int => $user->ID, $users ),
				);
			}
			if ( null !== $this->exclusive_prefix ) {
				$auth_schema->install();
			} else {
				$auth_schema->validate();
			}
			$schema->install();
			$repository->initialize();
			$service = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
			$context = new ChangeContext( get_current_user_id(), 'Preparação fictícia dos parâmetros do CMS', 'LAB-CMS', time() );
			$ids     = array();
			foreach ( self::logins( $prefix ) as $index => $login ) {
				$password = bin2hex( random_bytes( 24 ) );
				$handle   = @fopen( $root . DIRECTORY_SEPARATOR . self::passwordFile( $login ), 'xb' );
				if ( false === $handle ) {
					throw new RuntimeException( self::ERROR );
				}
				try {
					if ( 48 !== fwrite( $handle, $password ) || ! fflush( $handle ) ) {
						throw new RuntimeException( self::ERROR );
					}
				} finally {
					fclose( $handle );
				}
				$id = wp_insert_user(
					array(
						'user_login'   => $login,
						'user_email'   => $login . '@example.invalid',
						'user_pass'    => $password,
						'display_name' => 0 === $index ? 'Gestor CMS Teste' : 'Conta sem competência CMS Teste',
						'role'         => 'subscriber',
						'locale'       => 'pt_PT',
						'meta_input'   => array(
							LocalFixtureUser::META_KEY => '1',
							'_arn_cms_fixture'         => $prefix,
						),
					)
				);
				if ( ! is_int( $id ) || $id < 1 ) {
					throw new RuntimeException( self::ERROR );
				}
				$service->createAccount( $id, $context );
				$service->changeState( $id, AccountState::Active, 1, $context );
				if ( 0 === $index ) {
					$service->createGrant( new Grant( $id, Role::CmsAdministrator, Scope::institutional(), time(), null ), 2, $context );
				}
				$ids[] = $id;
			}
			return array(
				'created'  => true,
				'user_ids' => $ids,
			);
		} finally {
			$db->query( 'ROLLBACK' );
			$db->close();
		}
	}
}
