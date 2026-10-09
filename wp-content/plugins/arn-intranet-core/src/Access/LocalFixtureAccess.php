<?php
/**
 * Restringe a utilização de contas fictícias já guardadas no WordPress.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Access;

use WP_Error;
use WP_Session_Tokens;
use WP_User;

/**
 * Aplica a política ao login e a sessões emitidas antes de mudar de ambiente.
 */
final class LocalFixtureAccess {

	/**
	 * Autorização calculada com os dois requisitos do ambiente.
	 *
	 * @var bool
	 */
	private bool $access_allowed;

	/**
	 * Prepara a política efetiva deste pedido.
	 *
	 * @param string $environment_type Ambiente WordPress.
	 * @param bool   $explicit_flag    Autorização local explícita.
	 */
	public function __construct( string $environment_type, bool $explicit_flag ) {
		$this->access_allowed = LocalAccessPolicy::fictionalAccessAllowed( $environment_type, $explicit_flag );
	}

	/**
	 * Valida depois dos mecanismos nativos de credenciais e cookies.
	 */
	public function register(): void {
		add_filter( 'authenticate', array( $this, 'filterAuthentication' ), PHP_INT_MAX );
		add_filter( 'determine_current_user', array( $this, 'filterCurrentUser' ), PHP_INT_MAX );
		add_filter( 'allow_password_reset', array( $this, 'filterPasswordReset' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'filterApplicationPasswords' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Mantém a identificação das fixtures criadas antes de existir o metadado.
	 * Uma coincidência apenas no login ou no email não identifica uma fixture.
	 *
	 * @param WP_User $user Conta já identificada pelo WordPress.
	 */
	public static function isFixture( WP_User $user ): bool {
		if ( '1' === get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) ) {
			return true;
		}

		return LocalFixtureUser::LOGIN === strtolower( $user->user_login )
			&& LocalFixtureUser::EMAIL === strtolower( $user->user_email );
	}

	/**
	 * Recusa novas autenticações, incluindo login por email e XML-RPC.
	 *
	 * @param WP_User|WP_Error|null $user Resultado da autenticação anterior.
	 * @return WP_User|WP_Error|null
	 */
	public function filterAuthentication( $user ) {
		if ( ! $user instanceof WP_User || ! $this->isBlocked( $user ) ) {
			return $user;
		}

		return new WP_Error(
			'arn_fictional_access_disabled',
			__( 'A conta de teste só está disponível no ambiente local autorizado.', 'arn-intranet-core' )
		);
	}

	/**
	 * Rejeita cookies e identidades de API já validados e revoga as sessões.
	 * A conta e a autoria dos conteúdos ficam preservadas.
	 *
	 * @param int|false $user_id Identificador obtido pelos mecanismos anteriores.
	 * @return int|false
	 */
	public function filterCurrentUser( $user_id ) {
		if ( ! $user_id || $this->access_allowed ) {
			return $user_id;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! $this->isBlocked( $user ) ) {
			return $user_id;
		}

		WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
		wp_clear_auth_cookie();

		return false;
	}

	/**
	 * Não emite recuperação local para uma fixture desativada.
	 *
	 * @param bool|WP_Error $allow   Decisão anterior.
	 * @param int           $user_id Conta que pede recuperação.
	 * @return bool|WP_Error
	 */
	public function filterPasswordReset( $allow, int $user_id ) {
		if ( ! $allow || $allow instanceof WP_Error ) {
			return $allow;
		}

		$user = get_userdata( $user_id );
		return ! ( $user instanceof WP_User && $this->isBlocked( $user ) );
	}

	/**
	 * Impede que application passwords contornem a política da fixture.
	 *
	 * @param bool    $available Decisão anterior.
	 * @param WP_User $user      Conta a autenticar.
	 */
	public function filterApplicationPasswords( bool $available, WP_User $user ): bool {
		return $available && ! $this->isBlocked( $user );
	}

	/**
	 * Restringe apenas as contas fictícias identificadas.
	 *
	 * @param WP_User $user Conta WordPress.
	 */
	private function isBlocked( WP_User $user ): bool {
		return ! $this->access_allowed && self::isFixture( $user );
	}
}
