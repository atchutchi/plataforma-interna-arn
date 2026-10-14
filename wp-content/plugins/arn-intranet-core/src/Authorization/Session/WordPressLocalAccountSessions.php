<?php
/** Liga o estado persistente às sessões fictícias WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Access\LocalFixtureAccess;
use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;
use WP_Error;
use WP_Session_Tokens;
use WP_User;

/** Consulta sem autoridade administrativa nem cache da decisão. */
final class WordPressLocalAccountSessions {
	/** Consulta interna do snapshot.
	 *
	 * @var Closure
	 */
	private readonly Closure $lookup;
	/** Gate efectivo, sem depender do utilizador corrente.
	 *
	 * @var Closure
	 */
	private readonly Closure $enabled;
	/**
	 * Adia a ligação SQL até identificar uma fixture autorizada.
	 *
	 * @param callable|null $lookup Consulta interna por ID.
	 * @param callable|null $enabled Gate interno para composição e testes.
	 */
	public function __construct( ?callable $lookup = null, ?callable $enabled = null ) {
		global $wpdb;
		$prefix        = $wpdb->prefix ?? '';
		$this->lookup  = null !== $lookup ? Closure::fromCallable( $lookup ) : static function ( int $user_id ) use ( $prefix ): ?AuthorizationSnapshot {
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			try {
				$database->set_prefix( $prefix );
				return ( new WpdbAuthorizationRepository( $database ) )->find( $user_id );
			} finally {
				$database->close();
			}
		};
		$this->enabled = null !== $enabled ? Closure::fromCallable( $enabled ) : static fn(): bool => function_exists( 'wp_get_environment_type' )
			&& 'local' === wp_get_environment_type()
			&& defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) && true === ARN_ALLOW_FICTIONAL_LOCAL_ACCESS
			&& defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) && true === ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE;
	}
	/** Regista depois das guardas existentes e dos validadores nativos. */
	public function register(): void {
		add_filter( 'authenticate', array( $this, 'filterAuthentication' ), PHP_INT_MAX );
		add_filter( 'determine_current_user', array( $this, 'filterCurrentUser' ), PHP_INT_MAX );
		add_filter( 'attach_session_information', array( $this, 'attachSessionInformation' ), PHP_INT_MAX, 2 );
		add_filter( 'send_auth_cookies', array( $this, 'filterSendAuthCookies' ), PHP_INT_MAX, 6 );
		add_filter( 'allow_password_reset', array( $this, 'filterPasswordReset' ), PHP_INT_MAX, 2 );
		add_action( 'validate_password_reset', array( $this, 'validatePasswordReset' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'filterApplicationPasswords' ), PHP_INT_MAX, 2 );
	}
	/**
	 * Mantém recusas anteriores e só admite fixtures activas.
	 *
	 * @param WP_User|WP_Error|null $user Resultado anterior.
	 * @return WP_User|WP_Error|null
	 */
	public function filterAuthentication( $user ) {
		if ( ! $user instanceof WP_User || ! $this->identified( $user->ID, $this->currentSession( $user->ID ) ) ) {
			return $user;
		}
		return LocalAccountSessionPolicy::active( $this->snapshot( $user->ID ), $user->ID ) ? $user : $this->error();
	}
	/**
	 * Revalida a geração guardada em cada pedido.
	 *
	 * @param int|false $user_id Identidade nativa.
	 * @return int|false
	 */
	public function filterCurrentUser( $user_id ) {
		if ( ! $user_id ) {
			return $user_id;
		}
		$token = wp_get_session_token();
		if ( $this->acceptSession( (int) $user_id, $token ) ) {
			return $user_id;
		}
		wp_clear_auth_cookie();
		return false;
	}
	/**
	 * Marca mesmo uma emissão recusada para não perder a identificação.
	 *
	 * @param array $session Informação nativa.
	 * @param int   $user_id Conta do token.
	 */
	public function attachSessionInformation( array $session, int $user_id ): array {
		if ( ! $this->identified( $user_id, $session ) ) {
			return $session;
		}
		$snapshot                      = $this->snapshot( $user_id );
		$session['arn_authz_local']    = true;
		$session['arn_authz_user_id']  = $user_id;
		$session['arn_authz_revision'] = LocalAccountSessionPolicy::active( $snapshot, $user_id ) ? $snapshot->revision : 0;
		return $session;
	}
	/**
	 * Revalida depois de criar o token, antes de enviar cookies.
	 *
	 * @param bool   $send Decisão anterior.
	 * @param int    $expire Expiração do cookie.
	 * @param int    $expiration Expiração da sessão.
	 * @param int    $user_id Conta nativa, zero durante limpeza.
	 * @param string $scheme Esquema nativo.
	 * @param string $token Token criado no servidor.
	 */
	public function filterSendAuthCookies( bool $send, int $expire, int $expiration, int $user_id, string $scheme, string $token ): bool {
		if ( 0 === $user_id ) {
			return $send;
		}
		return $this->acceptSession( $user_id, $token ) && $send;
	}
	/**
	 * Aplica o estado também à emissão de chaves de recuperação.
	 *
	 * @param bool|WP_Error $allow Decisão anterior.
	 * @param int           $user_id Conta.
	 * @return bool|WP_Error
	 */
	public function filterPasswordReset( $allow, int $user_id ) {
		if ( ! $allow || $allow instanceof WP_Error ) {
			return $allow;
		}
		return ! $this->identified( $user_id, $this->currentSession( $user_id ) ) || LocalAccountSessionPolicy::active( $this->snapshot( $user_id ), $user_id );
	}
	/**
	 * Recusa chaves já emitidas quando o estado deixou de permitir acesso.
	 *
	 * @param WP_Error      $errors Erros acumulados.
	 * @param WP_User|false $user Conta resolvida pela chave.
	 */
	public function validatePasswordReset( WP_Error $errors, $user ): void {
		if ( $user instanceof WP_User && true !== $this->filterPasswordReset( true, $user->ID ) ) {
			$errors->add( 'arn_local_account_unavailable', __( 'A conta fictícia local não está disponível.', 'arn-intranet-core' ) );
		}
	}
	/**
	 * Credenciais sem geração de sessão não podem autenticar fixtures.
	 *
	 * @param bool    $available Decisão anterior.
	 * @param WP_User $user Conta.
	 */
	public function filterApplicationPasswords( bool $available, WP_User $user ): bool {
		return $available && ! $this->identified( $user->ID, $this->currentSession( $user->ID ) );
	}
	/**
	 * Mantém contas legítimas fora do DAO e revoga conforme a causa.
	 *
	 * @param int    $user_id Conta.
	 * @param string $token Token a revalidar.
	 */
	private function acceptSession( int $user_id, string $token ): bool {
		$manager = WP_Session_Tokens::get_instance( $user_id );
		$session = '' === $token ? array() : ( $manager->get( $token ) ?? array() );
		if ( ! $this->identified( $user_id, $session ) ) {
			return true;
		}
		$snapshot = $this->snapshot( $user_id );
		if ( LocalAccountSessionPolicy::valid( $snapshot, $user_id, $session, true ) ) {
			return true;
		}
		if ( LocalAccountSessionPolicy::active( $snapshot, $user_id ) ) {
			$manager->destroy( $token );
		} else {
			$manager->destroy_all();
		}
		return false;
	}
	/**
	 * Identificação é distinta da autorização e inclui sessões marcadas.
	 *
	 * @param int   $user_id Conta.
	 * @param array $session Metadados guardados.
	 */
	private function identified( int $user_id, array $session ): bool {
		$user = get_userdata( $user_id );
		if ( $user instanceof WP_User && 'lab_admin' === strtolower( $user->user_login ) ) {
			return false;
		}
		return true === ( $session['arn_authz_local'] ?? null ) || ( $user instanceof WP_User && LocalFixtureAccess::isFixture( $user ) );
	}
	/**
	 * Exige marca persistente, existência e flags antes da leitura dedicada.
	 *
	 * @param int $user_id Conta.
	 */
	private function snapshot( int $user_id ): ?AuthorizationSnapshot {
		try {
			if ( ! get_userdata( $user_id ) instanceof WP_User || '1' !== get_user_meta( $user_id, LocalFixtureUser::META_KEY, true ) || true !== ( $this->enabled )() ) {
				return null;
			}
			$snapshot = ( $this->lookup )( $user_id );
			return $snapshot instanceof AuthorizationSnapshot && $snapshot->user_id === $user_id ? $snapshot : null;
		} catch ( Throwable ) {
			return null;
		}
	}
	/**
	 * Obtém apenas os metadados do token corrente para a conta indicada.
	 *
	 * @param int $user_id Conta.
	 */
	private function currentSession( int $user_id ): array {
		$token = wp_get_session_token();
		return '' === $token ? array() : ( WP_Session_Tokens::get_instance( $user_id )->get( $token ) ?? array() );
	}
	/** Mensagem pública estática. */
	private function error(): WP_Error {
		return new WP_Error( 'arn_local_account_unavailable', __( 'A conta fictícia local não está disponível.', 'arn-intranet-core' ) );
	}
}
