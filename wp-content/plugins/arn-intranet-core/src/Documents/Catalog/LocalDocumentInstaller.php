<?php
/** Explicit local installer for the fictional HR documentary account.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use WP_User;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

/** Never invoked by activation, GET or the normal fixture installer. */
final class LocalDocumentInstaller {
	private const ERROR = 'Instalação documental fictícia recusada.';
	private const LOGIN = 'rh.documentos.teste';
	private const EMAIL = 'rh.documentos.teste@example.test';
	private const UNIT  = 'unidade-ficticia-a';

	public function __construct( private readonly ?string $prefix = null, private readonly ?string $password_file = null ) {}

	/** Exact local, flag, CLI and administrative gate. */
	public static function eligible( string $environment, mixed $catalog, mixed $files, mixed $fictional, mixed $storage, bool $cli, bool $admin ): bool {
		return 'local' === $environment && true === $catalog && true === $files && true === $fictional && true === $storage && $cli && $admin;
	}

	/** Install schemas and only the fictional HR account with audited state/grant. */
	public function install(): array {
		if ( ! self::eligible(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_DOCUMENT_CATALOG' ) ? ARN_ALLOW_LOCAL_DOCUMENT_CATALOG : null,
			defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) ? ARN_ALLOW_LOCAL_PRIVATE_FILES : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
			defined( 'WP_CLI' ) && true === WP_CLI,
			current_user_can( 'manage_options' )
		) ) {
			throw new RuntimeException( self::ERROR );
		}
		$actor_id = get_current_user_id();
		if ( $actor_id < 1 ) {
			throw new RuntimeException( self::ERROR );
		}
		global $wpdb;
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $this->prefix ?? $wpdb->prefix );
		$db->suppress_errors( true );
		$repository = new WpdbAuthorizationRepository( $db );
		$service    = new AuthorizationStorageService( $repository, new LocalAuthorizationStorageAuthority() );
		$context    = static fn( string $reason ): ChangeContext => new ChangeContext( $actor_id, $reason, 'LAB-DOCUMENT-CATALOG', time() );
		$service->installSchema( $context( 'Instalação documental fictícia' ) );
		( new FileSchema( $db ) )->install();
		( new CatalogSchema( $db ) )->install();
		$user    = get_user_by( 'login', self::LOGIN );
		$created = false;
		if ( false === $user ) {
			$file = $this->password_file ?? ( defined( 'ARN_LOCAL_DOCUMENT_PASSWORD_FILE' ) ? ARN_LOCAL_DOCUMENT_PASSWORD_FILE : '' );
			$this->assertPrivatePasswordFile( $file );
			$password = wp_generate_password( 48, true, true );
			$handle   = @fopen( $file, 'xb' );
			if ( false === $handle ) {
				throw new RuntimeException( self::ERROR );
			}
			try {
				if ( strlen( $password ) !== fwrite( $handle, $password ) || ! fflush( $handle ) ) {
					throw new RuntimeException( self::ERROR );
				}
			} catch ( \Throwable ) {
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
					'user_login'   => self::LOGIN,
					'user_pass'    => $password,
					'user_email'   => self::EMAIL,
					'display_name' => 'RH Documentos Teste',
					'role'         => 'subscriber',
				)
			);
			if ( is_wp_error( $id ) || ! is_int( $id ) || $id < 1 ) {
				@unlink( $file );
				throw new RuntimeException( self::ERROR );
			}
			$user = get_userdata( $id );
			if ( ! $user instanceof WP_User ) {
				throw new RuntimeException( self::ERROR );
			}
			update_user_meta( $id, LocalFixtureUser::META_KEY, '1' );
			$created = true;
		}
		if ( ! $user instanceof WP_User || self::EMAIL !== $user->user_email || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || array( 'subscriber' ) !== array_values( $user->roles ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$snapshot = $repository->find( $user->ID );
		if ( null === $snapshot ) {
			$service->createAccount( $user->ID, $context( 'Criação da conta fictícia documental' ) );
			$snapshot = $repository->find( $user->ID );
		}
		if ( AccountState::Pending === $snapshot->state ) {
			$service->changeState( $user->ID, AccountState::Active, $snapshot->revision, $context( 'Activação da conta fictícia documental' ) );
			$snapshot = $repository->find( $user->ID );
		}
		if ( AccountState::Active !== $snapshot->state ) {
			throw new RuntimeException( self::ERROR );
		}
		$grant_id = self::existingGrantId( $snapshot );
		if ( null === $grant_id ) {
			$grant_id = $service->createGrant( new Grant( $user->ID, Role::HumanResources, Scope::units( array( self::UNIT ) ), time(), null ), $snapshot->revision, $context( 'Concessão RH fictícia documental' ) );
		}
		return array(
			'user_id'  => $user->ID,
			'created'  => $created,
			'grant_id' => $grant_id,
		);
	}

	/** A revoked grant is a recorded decision and cannot be restored by setup. */
	public static function existingGrantId( AuthorizationSnapshot $snapshot ): ?int {
		$grant_id = null;
		foreach ( $snapshot->stored_grants as $stored ) {
			$grant = $stored->grant;
			if ( Role::HumanResources === $grant->role && \Arn\Intranet\Authorization\ScopeType::Units === $grant->scope->type && array( self::UNIT ) === $grant->scope->targets && ! $grant->revoked && null === $grant->expires_at && $grant->starts_at <= time() ) {
				if ( null !== $grant_id ) {
					throw new RuntimeException( self::ERROR );
				}
				$grant_id = $stored->id;
			} else {
				throw new RuntimeException( self::ERROR );
			}
		}
		return $grant_id;
	}

	/** Password file must be inside the configured private root, never public. */
	private function assertPrivatePasswordFile( string $file ): void {
		$root           = defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ? realpath( ARN_LOCAL_PRIVATE_FILES_DIR ) : false;
		$parent         = realpath( dirname( $file ) );
		$project        = realpath( dirname( __DIR__, 6 ) );
		$inside_project = false !== $project && str_starts_with( strtolower( false !== $root ? $root : '' ), strtolower( $project . DIRECTORY_SEPARATOR ) );
		$ignored_local  = false !== $project && str_starts_with( strtolower( false !== $root ? $root : '' ), strtolower( $project . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR ) );
		if ( false === $root || false === $parent || false === $project || $root !== $parent || 'rh-documentos-local.password' !== basename( $file ) || file_exists( $file ) || ( $inside_project && ! $ignored_local ) ) {
			throw new RuntimeException( self::ERROR );
		}
		try {
			LocalPrivateFiles::store( $root );
		} catch ( \Throwable ) {
			throw new RuntimeException( self::ERROR );
		}
	}
}
