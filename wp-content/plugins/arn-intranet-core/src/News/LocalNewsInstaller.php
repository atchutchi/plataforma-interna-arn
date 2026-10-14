<?php
/** Explicit CLI-only installer for two fictional newsroom accounts.
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
final class LocalNewsInstaller {
	private const ERROR    = 'Instalação fictícia de notícias recusada.';
	private const ACCOUNTS = array(
		array( 'autor.noticias.teste', 'autor.noticias.teste@example.test', 'autor-noticias-local.password' ),
		array( 'editor.noticias.teste', 'editor.noticias.teste@example.test', 'editor-noticias-local.password' ),
	);

	public function __construct( private readonly ?string $prefix = null, private readonly ?string $private_root = null ) {}

	public static function eligible( string $environment, mixed $newsroom, mixed $files, mixed $fictional, mixed $storage, bool $cli, bool $admin ): bool {
		return NewsConfiguration::enabled( $environment, $newsroom, $files, $fictional, $storage ) && $cli && $admin;
	}

	/** Installs schema and exact grants. No password is returned or printed. */
	public function install(): array {
		if ( ! self::eligible(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_NEWSROOM' ) ? ARN_ALLOW_LOCAL_NEWSROOM : null,
			defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) ? ARN_ALLOW_LOCAL_PRIVATE_FILES : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
			defined( 'WP_CLI' ) && true === WP_CLI,
			current_user_can( 'manage_options' )
		) ) {
			throw new RuntimeException( self::ERROR );
		}
		$root = null !== $this->private_root ? realpath( $this->private_root ) : ( defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ? realpath( ARN_LOCAL_PRIVATE_FILES_DIR ) : false );
		if ( false === $root ) {
			throw new RuntimeException( self::ERROR );
		}
		try {
			LocalPrivateFiles::store( $root );
		} catch ( Throwable ) {
			throw new RuntimeException( self::ERROR );
		}
		$actor_id = get_current_user_id();
		global $wpdb;
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $this->prefix ?? $wpdb->prefix );
		$db->suppress_errors( true );
		$repository = new WpdbAuthorizationRepository( $db );
		$service    = new AuthorizationStorageService( $repository, new LocalAuthorizationStorageAuthority() );
		$context    = static fn( string $reason ): ChangeContext => new ChangeContext( $actor_id, $reason, 'LAB-NEWS', time() );
		$service->installSchema( $context( 'Instalação fictícia de notícias' ) );
		( new FileSchema( $db ) )->install();
		( new NewsSchema( $db ) )->install();
		$results = array();
		foreach ( self::ACCOUNTS as $index => [$login, $email, $filename] ) {
			$role    = 0 === $index ? Role::Employee : Role::Editorial;
			$scope   = 0 === $index ? Scope::own() : Scope::queues( array( 'editorial-ficticia' ) );
			$user    = get_user_by( 'login', $login );
			$created = false;
			if ( false === $user ) {
				$file = $root . DIRECTORY_SEPARATOR . $filename;
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
				$id = wp_insert_user(
					array(
						'user_login' => $login,
						'user_pass'  => $password,
						'user_email' => $email,
						'role'       => 'subscriber',
					)
				);
				if ( is_wp_error( $id ) || ! is_int( $id ) || $id < 1 ) {
					@unlink( $file );
					throw new RuntimeException( self::ERROR );
				}
				update_user_meta( $id, LocalFixtureUser::META_KEY, '1' );
				$user    = get_userdata( $id );
				$created = true;
			}
			if ( ! $user instanceof WP_User || $email !== $user->user_email || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || array( 'subscriber' ) !== array_values( $user->roles ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$snapshot = $repository->find( $user->ID );
			if ( null === $snapshot ) {
				$service->createAccount( $user->ID, $context( 'Criação de conta fictícia de notícias' ) );
				$snapshot = $repository->find( $user->ID );
			}
			if ( AccountState::Pending === $snapshot->state ) {
				$service->changeState( $user->ID, AccountState::Active, $snapshot->revision, $context( 'Activação de conta fictícia de notícias' ) );
				$snapshot = $repository->find( $user->ID );
			}
			if ( AccountState::Active !== $snapshot->state ) {
				throw new RuntimeException( self::ERROR );
			}
			$grant_id = self::existingGrantId( $snapshot, $role, $scope );
			if ( null === $grant_id ) {
				$grant_id = $service->createGrant( new Grant( $user->ID, $role, $scope, time(), null ), $snapshot->revision, $context( 'Concessão fictícia de notícias' ) );
			}
			$results[] = array(
				'user_id'  => $user->ID,
				'created'  => $created,
				'grant_id' => $grant_id,
			);
		}
		return $results;
	}

	/** Any extra, expired, duplicate or revoked grant blocks setup. */
	public static function existingGrantId( AuthorizationSnapshot $snapshot, Role $role, Scope $scope ): ?int {
		$id = null;
		foreach ( $snapshot->stored_grants as $stored ) {
			$grant = $stored->grant;
			if ( $grant->role !== $role || $grant->scope->type !== $scope->type || $grant->scope->targets !== $scope->targets || $grant->revoked || null !== $grant->expires_at || $grant->starts_at > time() || null !== $id ) {
				throw new RuntimeException( self::ERROR );
			}
			$id = $stored->id;
		}
		return $id;
	}
}
