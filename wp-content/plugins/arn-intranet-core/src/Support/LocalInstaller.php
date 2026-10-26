<?php
/** Explicit fictional installation. No activation, GET or account repair path.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\{AccountState, Grant};
use Arn\Intranet\Authorization\Storage\{AuthorizationSchema, AuthorizationSnapshot, AuthorizationStorageService, ChangeContext, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\LocalNewsInstaller;
use Arn\Intranet\Organization\Storage\{LocalUnitContext, WpdbLocalUnitRepository};
use RuntimeException;
use WP_User;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors -- Explicit CLI installer, closed identifiers and private password files.
final class LocalInstaller {
	private const ERROR = 'Instalação fictícia de tickets recusada.';
	public function __construct( private readonly ?string $exclusive_prefix = null, private readonly ?string $private_root = null ) {}

	public static function eligible( string $environment, mixed $tasks, mixed $fictional, mixed $authorization, mixed $organization, bool $cli, bool $admin ): bool {
		return Configuration::enabled( $environment, $tasks, $fictional, $authorization, $organization ) && $cli && $admin;
	}

	public static function passwordFile( string $login ): string {
		return str_replace( '.', '-', $login ) . '.password';
	}

	public function install(): array {
		if ( ! Configuration::current() || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || ( null !== $this->exclusive_prefix && ! LocalRoster::validPrefix( $this->exclusive_prefix ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$requested = $this->private_root ?? ( defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ? ARN_LOCAL_PRIVATE_FILES_DIR : '' );
		$root      = is_string( $requested ) ? realpath( $requested ) : false;
		$project   = realpath( dirname( __DIR__, 5 ) );
		if ( false === $root || false === $project || is_link( $requested ) || ! is_dir( $root ) || ! is_writable( $root ) || ! LocalNewsInstaller::allowedLocation( $root, $project ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$definitions = LocalRoster::definitions( $this->exclusive_prefix );
		$present     = array();
		foreach ( $definitions as $label => $person ) {
			$user              = get_user_by( 'login', $person['login'] );
			$path              = $root . DIRECTORY_SEPARATOR . self::passwordFile( $person['login'] );
			$present[ $label ] = $user instanceof WP_User;
			if ( is_link( $path ) || ( $user instanceof WP_User && ( ! is_file( $path ) || 48 !== strlen( (string) file_get_contents( $path ) ) || ! wp_check_password( (string) file_get_contents( $path ), $user->user_pass, $user->ID ) ) ) || ( ! $user instanceof WP_User && ( file_exists( $path ) || false !== get_user_by( 'email', $person['login'] . '@example.invalid' ) ) ) ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$all  = ! in_array( false, $present, true );
		$none = ! in_array( true, $present, true );
		if ( ! $all && ! $none ) {
			throw new RuntimeException( self::ERROR );
		}
		$pages          = new Pages( $this->exclusive_prefix );
		$existing_pages = $pages->preflight( $all );
		global $wpdb;
		$prefix = $this->exclusive_prefix ?? $wpdb->prefix;
		if ( ( $all && ! InstallationState::ready( $prefix ) ) || ( $none && InstallationState::exists( $prefix ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $prefix );
		$db->suppress_errors( true );
		try {
			$schema        = new Schema( $db );
			$module_tables = array_map( $schema->table( ... ), array( 'tickets', 'audit', 'notifications', 'schema' ) );
			$actual        = $db->get_col( $db->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_NAME', $db->esc_like( $prefix ) . '%' ) );
			if ( ! is_array( $actual ) || '' !== $db->last_error ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( null !== $this->exclusive_prefix ) {
				$expected = array_merge( $module_tables, array_map( static fn( string $suffix ): string => $prefix . $suffix, array( 'arn_authz_accounts', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_schema', 'arn_org_audit', 'arn_org_header', 'arn_org_units' ) ) );
				sort( $expected );
				if ( ( $none && array() !== $actual ) || ( $all && $actual !== $expected ) ) {
					throw new RuntimeException( self::ERROR );
				}
			} elseif ( $none && array_intersect( $module_tables, $actual ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$auth_schema = new AuthorizationSchema( $db );
			$auth        = new WpdbAuthorizationRepository( $db );
			$units       = new WpdbLocalUnitRepository( $db );
			// Every page, credential, identity and table preflight precedes writes.
			if ( null !== $this->exclusive_prefix && $none ) {
				$auth_schema->install();
				$units->installSchema();
				$units->apply( 'initialize', 0, new LocalUnitContext( get_current_user_id(), 'Preparação exclusiva fictícia de tickets', time() ) );
			} else {
				$auth_schema->validate();
			}
			$snapshot = $units->snapshot();
			$active   = array();
			foreach ( $snapshot->units as $unit ) {
				if ( $unit->active ) {
					$active[] = $unit->id;
				}
			}
			if ( $snapshot->revision < 1 || 3 !== count( $snapshot->units ) || ! in_array( 'LAB-A', $active, true ) || ! in_array( 'LAB-B', $active, true ) ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( $all ) {
				$schema->assertReady();
				$roster = LocalRoster::resolve( $this->exclusive_prefix );
				$result = array(
					'user_ids' => array(),
					'created'  => array(),
					'pages'    => $existing_pages,
				);
				foreach ( $definitions as $label => $person ) {
					$user    = get_user_by( 'login', $person['login'] );
					$account = $auth->find( $user->ID );
					if ( ! isset( $roster[ $user->ID ] ) || null === $account || AccountState::Active !== $account->state || ! self::exactGrants( $account, LocalRoster::grants( $label ), time() ) ) {
						throw new RuntimeException( self::ERROR );
					}
					$result['user_ids'][ $label ] = $user->ID;
					$result['created'][ $label ]  = false;
				}
				return $result;
			}
			$schema->install();
			$service = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
			$context = static fn( string $reason ): ChangeContext => new ChangeContext( get_current_user_id(), $reason, 'LAB-TICKETS', time() );
			$result  = array(
				'user_ids' => array(),
				'created'  => array(),
			);
			foreach ( $definitions as $label => $person ) {
				$password = bin2hex( random_bytes( 24 ) );
				$handle   = @fopen( $root . DIRECTORY_SEPARATOR . self::passwordFile( $person['login'] ), 'xb' );
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
						'user_login'   => $person['login'],
						'user_email'   => $person['login'] . '@example.invalid',
						'user_pass'    => $password,
						'display_name' => match ( $label ) {
												'a' => 'Alfa Teste', 'b' => 'Beta Teste', 'manager' => 'Técnico Suporte Teste', 'deputy' => 'Técnico Unidade A Teste' },
						'role'         => 'subscriber',
						'locale'       => 'pt_PT',
						'meta_input'   => array(
							LocalFixtureUser::META_KEY => '1',
							'_arn_tickets_fixture'     => $prefix,
						),
					)
				);
				if ( ! is_int( $id ) || $id < 1 ) {
					throw new RuntimeException( self::ERROR );
				}
				$service->createAccount( $id, $context( 'Criação fictícia de tickets' ) );
				$service->changeState( $id, AccountState::Active, $auth->find( $id )->revision, $context( 'Activação fictícia de tickets' ) );
				foreach ( LocalRoster::grants( $label ) as [$role, $scope] ) {
					$service->createGrant( new Grant( $id, $role, $scope, time(), null ), $auth->find( $id )->revision, $context( 'Concessão fictícia de tickets' ) );
				}
				$result['user_ids'][ $label ] = $id;
				$result['created'][ $label ]  = true;
			}
			$result['pages'] = $pages->install();
			InstallationState::write( $prefix, $result['user_ids'], $result['pages'] );
			return $result;
		} finally {
			$db->close();
		}
	}

	private static function exactGrants( AuthorizationSnapshot $snapshot, array $expected, int $now ): bool {
		if ( count( $snapshot->stored_grants ) !== count( $expected ) ) {
			return false;
		}
		$matched = array();
		foreach ( $snapshot->stored_grants as $stored ) {
			$grant = $stored->grant;
			if ( $grant->revoked || null !== $grant->expires_at || $grant->starts_at > $now ) {
				return false;
			}
			foreach ( $expected as $index => [$role, $scope] ) {
				if ( ! isset( $matched[ $index ] ) && $role === $grant->role && $scope->type === $grant->scope->type && $scope->targets === $grant->scope->targets ) {
					$matched[ $index ] = true;
					continue 2;
				}
			}
			return false;
		}
		return count( $matched ) === count( $expected );
	}
}
