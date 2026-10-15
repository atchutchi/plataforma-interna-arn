<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Exclusive private files use atomic xb creation and verified cleanup.
/** CLI-only installer for three fictional People profiles. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\LocalNewsInstaller;
use Arn\Intranet\Organization\Storage\LocalUnitContext;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use RuntimeException;
use Throwable;
use WP_User;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.NoSilencedErrors
final class LocalPeopleInstaller {
	private const ERROR = 'Instalação People fictícia recusada.';
	public function __construct( private readonly ?string $exclusive_prefix = null, private readonly ?string $private_root = null ) {}

	public static function validPrefix( string $prefix ): bool {
		return LocalPeopleRoster::validPrefix( $prefix );
	}

	public static function eligible( string $environment, mixed $people, mixed $fictional, mixed $authorization, mixed $organization, bool $cli, bool $admin ): bool {
		return PeopleConfiguration::enabled( $environment, $people, $fictional, $authorization, $organization ) && $cli && $admin;
	}

	/** Creates or verifies exact resources without repairing partial states. */
	public function install(): array {
		if ( ( null !== $this->exclusive_prefix && ! self::validPrefix( $this->exclusive_prefix ) )
			|| ! PeopleConfiguration::current()
			|| ! defined( 'WP_CLI' ) || true !== WP_CLI
			|| ! current_user_can( 'manage_options' ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$requested_root = $this->private_root ?? ( defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ? ARN_LOCAL_PRIVATE_FILES_DIR : '' );
		$root           = realpath( $requested_root );
		$project        = realpath( dirname( __DIR__, 5 ) );
		if ( false === $root || false === $project || is_link( $requested_root ) || ! LocalNewsInstaller::allowedLocation( $root, $project ) ) {
			throw new RuntimeException( self::ERROR );
		}
		try {
			LocalPrivateFiles::store( $root );
		} catch ( Throwable ) {
			throw new RuntimeException( self::ERROR );
		}
		$accounts = $this->accounts();
		$present  = array();
		foreach ( $accounts as $label => [$login, $email, $file] ) {
			$user              = get_user_by( 'login', $login );
			$present[ $label ] = $user instanceof WP_User;
			$path              = $root . DIRECTORY_SEPARATOR . $file;
			if ( is_link( $path ) || ( $user instanceof WP_User && ( ! is_file( $path ) || 48 !== strlen( (string) file_get_contents( $path ) ) ) ) || ( ! $user instanceof WP_User && ( file_exists( $path ) || false !== get_user_by( 'email', $email ) ) ) ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$all  = ! in_array( false, $present, true );
		$none = ! in_array( true, $present, true );
		if ( ! $all && ! $none ) {
			throw new RuntimeException( self::ERROR );
		}
		global $wpdb;
		$prefix = $this->exclusive_prefix ?? $wpdb->prefix;
		$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $prefix );
		$db->suppress_errors( true );
		$people_schema   = new PeopleSchema( $db );
		$auth_schema     = new AuthorizationSchema( $db );
		$auth_repository = new WpdbAuthorizationRepository( $db );
		$units           = new WpdbLocalUnitRepository( $db );
		$actor_id        = get_current_user_id();
		if ( null === $this->exclusive_prefix ) {
			$auth_schema->validate();
			$unit_snapshot   = $units->snapshot();
			$people_existing = $this->tableCount( $db, $people_schema );
			if ( $all ) {
				$people_schema->validate();
			} elseif ( 0 !== $people_existing ) {
				throw new RuntimeException( self::ERROR );
			}
		} else {
			$exclusive_tables = $this->exclusiveTables( $db, $prefix );
			if ( ( $none && array() !== $exclusive_tables ) || ( $all && $exclusive_tables !== $this->expectedExclusiveTables( $prefix ) ) ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( $all ) {
				$auth_schema->validate();
				$people_schema->validate();
				$unit_snapshot = $units->snapshot();
			} else {
				$auth_schema->install();
				$units->installSchema();
				$unit_snapshot = $units->snapshot();
				if ( 0 === $unit_snapshot->revision ) {
					$units->apply( 'initialize', 0, new LocalUnitContext( $actor_id, 'Preparação fictícia exclusiva People', time() ) );
					$unit_snapshot = $units->snapshot();
				}
			}
		}
		$this->assertUnits( $unit_snapshot );
		if ( $all ) {
			return $this->verifyExisting( $accounts, $auth_repository, new WpdbPeopleRepository( $db, new WpdbPeopleAuthority( $db ) ) );
		}
		$people_schema->install();
		$service = new AuthorizationStorageService( $auth_repository, new LocalAuthorizationStorageAuthority() );
		$context = static fn( string $reason ): ChangeContext => new ChangeContext( $actor_id, $reason, 'LAB-PEOPLE', time() );
		$people  = new WpdbPeopleRepository( $db, new WpdbPeopleAuthority( $db ) );
		$result  = array(
			'user_ids' => array(),
			'created'  => array(),
		);
		foreach ( $accounts as $label => [$login, $email, $file] ) {
			$user = $this->createUser( $login, $email, $root . DIRECTORY_SEPARATOR . $file );
			$service->createAccount( $user->ID, $context( 'Criação fictícia People' ) );
			$snapshot = $auth_repository->find( $user->ID );
			$service->changeState( $user->ID, AccountState::Active, $snapshot->revision, $context( 'Activação fictícia People' ) );
			foreach ( $this->scopes( $label ) as [$role, $scope] ) {
				$snapshot = $auth_repository->find( $user->ID );
				$service->createGrant( new Grant( $user->ID, $role, $scope, time(), null ), $snapshot->revision, $context( 'Concessão fictícia People' ) );
			}
			$base         = LocalPeopleRoster::details( $label );
			$professional = new ProfessionalDetails( $base->code, $base->name, $email, $base->job, $base->unit_id );
			$now          = time();
			$people->seed( new Profile( $user->ID, $professional, new SelfService( '', '', '' ), 1, $now ), $actor_id, $now );
			$result['user_ids'][ $label ] = $user->ID;
			$result['created'][ $label ]  = true;
		}
		return $result;
	}

	private function accounts(): array {
		$accounts = array();
		foreach ( LocalPeopleRoster::logins( $this->exclusive_prefix ) as $label => $login ) {
			$accounts[ $label ] = array( $login, $login . '@example.invalid', str_replace( '.', '-', $login ) . '.password' );
		}
		return $accounts;
	}

	private function scopes( string $label ): array {
		$scopes = array( array( Role::Employee, Scope::own() ) );
		if ( 'manager' === $label ) {
			$scopes[] = array( Role::UnitManager, Scope::units( array( 'LAB-A' ) ) );
		}
		return $scopes;
	}

	private function assertUnits( \Arn\Intranet\Organization\Storage\LocalUnitSnapshot $snapshot ): void {
		$active = array();
		foreach ( $snapshot->units as $unit ) {
			if ( $unit->active ) {
				$active[] = $unit->id;
			}
		}
		if ( ! in_array( 'LAB-A', $active, true ) || ! in_array( 'LAB-B', $active, true ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function verifyExisting( array $accounts, WpdbAuthorizationRepository $repository, WpdbPeopleRepository $people ): array {
		$result = array(
			'user_ids' => array(),
			'created'  => array(),
		);
		foreach ( $accounts as $label => [$login, $email] ) {
			$user = get_user_by( 'login', $login );
			if ( ! $user instanceof WP_User || $user->user_email !== $email || $user->user_login !== $login || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || array( 'subscriber' ) !== array_values( $user->roles ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$snapshot = $repository->find( $user->ID );
			$profile  = $people->find( $user->ID );
			$base     = LocalPeopleRoster::details( $label );
			$expected = new ProfessionalDetails( $base->code, $base->name, $email, $base->job, $base->unit_id );
			if ( null === $snapshot || AccountState::Active !== $snapshot->state || null === $profile || get_object_vars( $profile->professional ) !== get_object_vars( $expected ) || ! $this->exactGrants( $snapshot, $this->scopes( $label ) ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$result['user_ids'][ $label ] = $user->ID;
			$result['created'][ $label ]  = false;
		}
		return $result;
	}

	private function exactGrants( AuthorizationSnapshot $snapshot, array $expected ): bool {
		if ( count( $snapshot->stored_grants ) !== count( $expected ) ) {
			return false;
		}
		$matched = array();
		foreach ( $snapshot->stored_grants as $stored ) {
			$grant = $stored->grant;
			if ( $grant->revoked || null !== $grant->expires_at || $grant->starts_at > time() ) {
				return false;
			}
			foreach ( $expected as $index => [$role, $scope] ) {
				if ( ! isset( $matched[ $index ] ) && $grant->role === $role && $grant->scope->type === $scope->type && $grant->scope->targets === $scope->targets ) {
					$matched[ $index ] = true;
					continue 2;
				}
			}
			return false;
		}
		return count( $matched ) === count( $expected );
	}

	private function tableCount( IdentityWpdb $db, PeopleSchema $schema ): int {
		$names = array_map( $schema->table( ... ), array( 'people', 'audit', 'schema' ) );
		$count = 0;
		foreach ( $names as $name ) {
			$count += (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $name ) );
		}
		return $count;
	}

	private function exclusiveTables( IdentityWpdb $db, string $prefix ): array {
		$names = $db->get_col( $db->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s ORDER BY TABLE_NAME', $db->esc_like( $prefix ) . '%' ) );
		if ( ! is_array( $names ) || '' !== $db->last_error ) {
			throw new RuntimeException( self::ERROR );
		}
		return $names;
	}

	private function expectedExclusiveTables( string $prefix ): array {
		$names = array_map( static fn( string $suffix ): string => $prefix . $suffix, array( 'arn_authz_accounts', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_schema', 'arn_org_audit', 'arn_org_header', 'arn_org_units', 'arn_people', 'arn_people_audit', 'arn_people_schema' ) );
		sort( $names );
		return $names;
	}

	private function createUser( string $login, string $email, string $file ): WP_User {
		if ( file_exists( $file ) || is_link( $file ) || false !== get_user_by( 'login', $login ) || false !== get_user_by( 'email', $email ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$password = wp_generate_password( 48, true, true );
		$handle   = @fopen( $file, 'xb' );
		if ( false === $handle ) {
			throw new RuntimeException( self::ERROR );
		}
		try {
			if ( strlen( $password ) !== fwrite( $handle, $password ) || ! fflush( $handle ) ) {
				throw new RuntimeException( self::ERROR );
			}
		} catch ( Throwable ) {
			fclose( $handle );
			@unlink( $file );
			throw new RuntimeException( self::ERROR );
		} finally {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}
		}
		$id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => $password,
				'user_email' => $email,
				'role'       => 'subscriber',
			)
		);
		if ( is_wp_error( $id ) || ! is_int( $id ) || $id <= 0 ) {
			@unlink( $file );
			throw new RuntimeException( self::ERROR );
		}
		update_user_meta( $id, LocalFixtureUser::META_KEY, '1' );
		$user = get_userdata( $id );
		if ( ! $user instanceof WP_User ) {
			throw new RuntimeException( self::ERROR );
		}
		return $user;
	}
}
