<?php
/**
 * Controlos WordPress para contas federadas.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use Closure;
use Throwable;
use WP_Error;
use WP_Session_Tokens;
use WP_User;

/** Revalida o vínculo e impede password, reset ou API da conta federada. */
final class WordPressFederatedAccess {

	/**
	 * Consulta actual do vínculo local.
	 *
	 * @var Closure
	 */
	private readonly Closure $lookup;

	/**
	 * Estado efectivo do cliente OIDC.
	 *
	 * @var Closure
	 */
	private readonly Closure $enabled;

	/**
	 * Recebe as consultas do vínculo e da configuração activa.
	 *
	 * @param callable $lookup  Consulta actual por conta local.
	 * @param callable $enabled Estado efectivo do cliente nesta instalação.
	 */
	public function __construct( callable $lookup, callable $enabled ) {
		$this->lookup  = Closure::fromCallable( $lookup );
		$this->enabled = Closure::fromCallable( $enabled );
	}

	/** Regista após os mecanismos nativos, sem conceder competências. */
	public function register(): void {
		add_filter( 'authenticate', array( $this, 'filterAuthentication' ), PHP_INT_MAX );
		add_filter( 'determine_current_user', array( $this, 'filterCurrentUser' ), PHP_INT_MAX );
		add_filter( 'allow_password_reset', array( $this, 'filterPasswordReset' ), PHP_INT_MAX, 2 );
		add_action( 'validate_password_reset', array( $this, 'validatePasswordReset' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'filterApplicationPasswords' ), PHP_INT_MAX, 2 );
	}

	/**
	 * Recusa autenticação nativa para contas com vínculo federado.
	 *
	 * @param WP_User|WP_Error|null $user Identidade local antes da recusa.
	 * @return WP_User|WP_Error|null Resultado fechado para contas vinculadas.
	 */
	public function filterAuthentication( $user ) {
		if ( ! $user instanceof WP_User ) {
			return $user;
		}
		try {
			if ( null === ( $this->lookup )( $user->ID ) ) {
				return $user;
			}
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A falha segue para a recusa abaixo, mantendo o acesso fechado.
			// A falha de persistência não permite contornar a federação.
		}
		return new WP_Error( 'arn_google_authentication_required', __( 'Esta conta exige autenticação Google autorizada.', 'arn-intranet-core' ) );
	}

	/**
	 * Revalida o vínculo e os limites da sessão em cada pedido.
	 *
	 * @param int|false $user_id Resultado de cookies ou API nativos.
	 * @return int|false Identidade revalidada.
	 */
	public function filterCurrentUser( $user_id ) {
		if ( ! $user_id ) {
			return $user_id;
		}
		$manager    = WP_Session_Tokens::get_instance( (int) $user_id );
		$token      = wp_get_session_token();
		$session    = '' === $token ? array() : $manager->get( $token );
		$revoke_all = true;
		try {
			$link = ( $this->lookup )( (int) $user_id );
			if ( null === $link && true !== ( $session['arn_oidc'] ?? null ) ) {
				return $user_id;
			}
			$enabled    = ( $this->enabled )();
			$revoke_all = null === $link || ! $link->authorized || ! $link->active || true !== $enabled;
			if ( FederatedSessionPolicy::valid( $link, $session, time(), $enabled ) ) {
				$session['arn_oidc_last_seen'] = time();
				$manager->update( $token, $session );
				return $user_id;
			}
		} catch ( Throwable ) {
			// Qualquer falha ou configuração removida recusa a sessão.
			$revoke_all = true;
		}
		if ( $revoke_all ) {
			$manager->destroy_all();
		} else {
			$manager->destroy( $token );
		}
		wp_clear_auth_cookie();
		return false;
	}

	/**
	 * Impede a recuperação local de contas federadas.
	 *
	 * @param bool|WP_Error $allow   Decisão anterior.
	 * @param int           $user_id Conta que pede recuperação.
	 * @return bool|WP_Error Decisão final.
	 */
	public function filterPasswordReset( $allow, int $user_id ) {
		if ( ! $allow || $allow instanceof WP_Error ) {
			return $allow;
		}
		try {
			return null === ( $this->lookup )( $user_id );
		} catch ( Throwable ) {
			return false;
		}
	}

	/**
	 * Bloqueia também chaves de recuperação emitidas antes do vínculo.
	 *
	 * @param WP_Error      $errors Erros acumulados na recuperação.
	 * @param WP_User|false $user   Conta identificada pela chave nativa.
	 */
	public function validatePasswordReset( WP_Error $errors, $user ): void {
		if ( $user instanceof WP_User && true !== $this->filterPasswordReset( true, $user->ID ) ) {
			$errors->add( 'arn_google_authentication_required', __( 'Esta conta exige autenticação Google autorizada.', 'arn-intranet-core' ) );
		}
	}

	/**
	 * Recusa palavras-passe de aplicação para contas federadas.
	 *
	 * @param bool    $available Decisão anterior.
	 * @param WP_User $user      Conta para autenticação API.
	 */
	public function filterApplicationPasswords( bool $available, WP_User $user ): bool {
		return $available && true === $this->filterPasswordReset( true, $user->ID );
	}
}
