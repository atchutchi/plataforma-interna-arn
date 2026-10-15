<?php
/** Explicit CLI installer for the five fictional editorial accounts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;
use WP_User;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors

/** Never registered on activation or a web request. */
final class LocalEditorialInstaller {
	private const ERROR  = 'Instalação editorial fictícia recusada.';
	private const LABELS = array( 'author', 'employee_a', 'employee_b', 'alternate', 'editor' );

	public function __construct( private readonly ?string $prefix = null, private readonly ?string $private_root = null ) {}

	public static function validPrefix( string $prefix ): bool {
		return 1 === preg_match( '/\Aarn_ed_[a-f0-9]{10}_\z/D', $prefix );
	}

	public static function eligible( string $environment, mixed $newsroom, mixed $files, mixed $fictional, mixed $storage, mixed $editorial, bool $cli, bool $admin ): bool {
		return NewsConfiguration::enabled( $environment, $newsroom, $files, $fictional, $storage, $editorial ) && $cli && $admin;
	}

	/** Migrate schema 1 explicitly, then install or verify the exact five-person roster. */
	public function install(): array {
		if ( ! self::eligible(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_NEWSROOM' ) ? ARN_ALLOW_LOCAL_NEWSROOM : null,
			defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) ? ARN_ALLOW_LOCAL_PRIVATE_FILES : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
			defined( 'ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW' ) ? ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW : null,
			defined( 'WP_CLI' ) && true === WP_CLI,
			current_user_can( 'manage_options' )
		) || ( null !== $this->prefix && ! self::validPrefix( $this->prefix ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$root    = null !== $this->private_root ? realpath( $this->private_root ) : ( defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ? realpath( ARN_LOCAL_PRIVATE_FILES_DIR ) : false );
		$project = realpath( dirname( __DIR__, 5 ) );
		if ( false === $root || false === $project || ! LocalNewsInstaller::allowedLocation( $root, $project ) ) {
			throw new RuntimeException( self::ERROR );
		}
		try {
			LocalPrivateFiles::store( $root );
		} catch ( Throwable ) {
			throw new RuntimeException( self::ERROR );
		}
		global $wpdb;
		$prefix   = $this->prefix ?? $wpdb->prefix;
		$accounts = $this->accounts();
		$present  = array();
		foreach ( $accounts as $label => $account ) {
			$present[ $label ] = false !== get_user_by( 'login', $account[0] );
		}
		$expected_presence = null === $this->prefix ? array(
			'author'     => true,
			'employee_a' => false,
			'employee_b' => false,
			'alternate'  => false,
			'editor'     => true,
		) : array_fill_keys( self::LABELS, false );
		if ( $present !== $expected_presence && ! array_reduce( $present, static fn( bool $all, bool $exists ): bool => $all && $exists, true ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$actor_id = get_current_user_id();
		$db       = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $prefix );
		$db->suppress_errors( true );
		$repository = new WpdbAuthorizationRepository( $db );
		$service    = new AuthorizationStorageService( $repository, new LocalAuthorizationStorageAuthority() );
		$context    = static fn( string $reason ): ChangeContext => new ChangeContext( $actor_id, $reason, 'LAB-NEWS', time() );
		$service->installSchema( $context( 'Instalação fictícia editorial' ) );
		if ( null === $this->prefix && $present === $expected_presence ) {
			foreach ( array( 'author', 'editor' ) as $label ) {
				$user     = get_user_by( 'login', $accounts[ $label ][0] );
				$snapshot = $user instanceof WP_User ? $repository->find( $user->ID ) : null;
				$role     = 'author' === $label ? Role::Employee : Role::Editorial;
				$scope    = 'author' === $label ? Scope::own() : Scope::queues( array( 'editorial-ficticia' ) );
				if ( ! $user instanceof WP_User || $user->user_email !== $accounts[ $label ][1] || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || array( 'subscriber' ) !== array_values( $user->roles ) || null === $snapshot || AccountState::Active !== $snapshot->state || null === LocalNewsInstaller::existingGrantId( $snapshot, $role, $scope ) ) {
					throw new RuntimeException( self::ERROR );
				}
			}
		}
		( new FileSchema( $db ) )->install();
		$schema = new NewsSchema( $db );
		try {
			$schema->assertReady();
		} catch ( Throwable ) {
			$exists = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $schema->table( 'schema' ) ) );
			if ( 0 === $exists ) {
				$schema->install();
			} else {
				$schema->upgrade( $actor_id, time() );
			}
		}
		$schema->assertReady();
		$results = array();
		foreach ( $accounts as $label => $account ) {
			[$login, $email, $filename] = $account;
			$user                       = get_user_by( 'login', $login );
			$created                    = false;
			if ( false === $user ) {
				if ( null === $this->prefix && in_array( $label, array( 'author', 'editor' ), true ) ) {
					throw new RuntimeException( self::ERROR );
				}
				$user    = $this->createUser( $login, $email, $root . DIRECTORY_SEPARATOR . $filename );
				$created = true;
			}
			if ( ! $user instanceof WP_User || $email !== $user->user_email || $login !== $user->user_login || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || array( 'subscriber' ) !== array_values( $user->roles ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$snapshot = $repository->find( $user->ID );
			if ( $created ) {
				if ( null !== $snapshot ) {
					throw new RuntimeException( self::ERROR );
				}
				$service->createAccount( $user->ID, $context( 'Criação de conta fictícia editorial' ) );
				$snapshot = $repository->find( $user->ID );
				$service->changeState( $user->ID, AccountState::Active, $snapshot->revision, $context( 'Activação de conta fictícia editorial' ) );
				$snapshot = $repository->find( $user->ID );
			}
			if ( null === $snapshot || AccountState::Active !== $snapshot->state ) {
				throw new RuntimeException( self::ERROR );
			}
			$scopes = $this->scopes( $label, $accounts );
			$ids    = $this->exactGrants( $snapshot, $scopes, 'editor' === $label );
			if ( ! $created && array() === $ids ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( $created ) {
				foreach ( $scopes as [$role, $scope] ) {
					$id       = $service->createGrant( new Grant( $user->ID, $role, $scope, time(), null ), $snapshot->revision, $context( 'Concessão fictícia editorial' ) );
					$ids[]    = $id;
					$snapshot = $repository->find( $user->ID );
				}
			} elseif ( 'editor' === $label && 1 === count( $ids ) ) {
				$ids[] = $service->createGrant( new Grant( $user->ID, Role::Editorial, $scopes[1][1], time(), null ), $snapshot->revision, $context( 'Ampliação fictícia auditada da audiência editorial' ) );
			}
			$results[] = array(
				'user_id'   => $user->ID,
				'created'   => $created,
				'grant_ids' => $ids,
			);
		}
		return $results;
	}

	/** Only explicit prefixes derive names. Normal names remain closed. */
	private function accounts(): array {
		if ( null === $this->prefix ) {
			$logins = array(
				'author'     => 'autor.noticias.teste',
				'employee_a' => 'colaborador.noticias.a.teste',
				'employee_b' => 'colaborador.noticias.b.teste',
				'alternate'  => 'editor.suplente.teste',
				'editor'     => 'editor.noticias.teste',
			);
		} else {
			$logins = array_combine( self::LABELS, array_map( fn( string $label ): string => $this->prefix . $label, self::LABELS ) );
		}
		$result = array();
		foreach ( $logins as $label => $login ) {
			$result[ $label ] = array( $login, $login . '@example.test', str_replace( '.', '-', $login ) . '.password' );
		}
		return $result;
	}

	/** Employee Own and two separate editorial grants with full explicit coverage. */
	private function scopes( string $label, array $accounts ): array {
		if ( in_array( $label, array( 'author', 'employee_a', 'employee_b' ), true ) ) {
			return array( array( Role::Employee, Scope::own() ) );
		}
		$people = array();
		foreach ( array( 'author', 'employee_a', 'employee_b' ) as $employee ) {
			$user = get_user_by( 'login', $accounts[ $employee ][0] );
			if ( ! $user instanceof WP_User ) {
				throw new RuntimeException( self::ERROR );
			}
			$people[] = 'news_person_' . $user->ID;
		}
		$audiences = array_merge( array( 'news_all', 'news_unit_LAB-A', 'news_unit_LAB-B' ), $people );
		sort( $audiences, SORT_STRING );
		return array(
			array( Role::Editorial, Scope::queues( array( 'editorial-ficticia' ) ) ),
			array( Role::Editorial, Scope::audiences( $audiences ) ),
		);
	}

	/** Missing initial grants are allowed only for an account created in this call. */
	private function exactGrants( AuthorizationSnapshot $snapshot, array $scopes, bool $allow_editor_upgrade ): array {
		$ids = array();
		foreach ( $snapshot->stored_grants as $stored ) {
			$matched = false;
			foreach ( $scopes as $index => [$role, $scope] ) {
				$grant = $stored->grant;
				if ( $grant->role === $role && $grant->scope->type === $scope->type && $grant->scope->targets === $scope->targets && ! $grant->revoked && null === $grant->expires_at && $grant->starts_at <= time() && ! isset( $ids[ $index ] ) ) {
					$ids[ $index ] = $stored->id;
					$matched       = true;
					break;
				}
			}
			if ( ! $matched ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		if ( array() === $ids && array() === $snapshot->stored_grants ) {
			return array();
		}
		if ( $allow_editor_upgrade && 1 === count( $ids ) && isset( $ids[0] ) ) {
			return array( $ids[0] );
		}
		if ( count( $ids ) !== count( $scopes ) ) {
			throw new RuntimeException( self::ERROR );
		}
		ksort( $ids, SORT_NUMERIC );
		return array_values( $ids );
	}

	private function createUser( string $login, string $email, string $file ): WP_User {
		if ( file_exists( $file ) || false !== get_user_by( 'email', $email ) ) {
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
		try {
			$id = wp_insert_user(
				array(
					'user_login' => $login,
					'user_pass'  => $password,
					'user_email' => $email,
					'role'       => 'subscriber',
				)
			);
		} catch ( Throwable ) {
			@unlink( $file );
			throw new RuntimeException( self::ERROR );
		}
		if ( is_wp_error( $id ) || ! is_int( $id ) || $id < 1 ) {
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
