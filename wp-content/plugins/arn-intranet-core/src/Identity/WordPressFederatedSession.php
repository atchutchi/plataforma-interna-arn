<?php
/**
 * Emissão de sessões WordPress após autenticação federada.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use RuntimeException;
use WP_Session_Tokens;

/** Emite exclusivamente a sessão local correspondente ao vínculo autorizado. */
final class WordPressFederatedSession {

	/**
	 * Cria a sessão autorizada e guarda os limites federados.
	 *
	 * @param LocalIdentityLink $link Vínculo resolvido após OIDC e autorização local.
	 * @return string Token apenas para consumo interno, nunca HTML ou logs.
	 * @throws RuntimeException Quando a conta ou a sessão não está autorizada.
	 */
	public function issue( LocalIdentityLink $link ): string {
		if ( ! $link->active || ! $link->authorized || false === get_userdata( $link->user_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Sessão institucional não autorizada.' );
		}
		$now                            = time();
		$manager                        = WP_Session_Tokens::get_instance( $link->user_id );
		$token                          = $manager->create( $now + FederatedSessionPolicy::LIFETIME );
		$metadata                       = $manager->get( $token );
		$metadata['arn_oidc']           = true;
		$metadata['arn_oidc_issued_at'] = $now;
		$metadata['arn_oidc_last_seen'] = $now;
		$manager->update( $token, $metadata );
		if ( true !== ( $manager->get( $token )['arn_oidc'] ?? null ) ) {
			$manager->destroy( $token );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Não foi possível emitir a sessão institucional.' );
		}
		$expiration = static fn(): int => FederatedSessionPolicy::LIFETIME;
		add_filter( 'auth_cookie_expiration', $expiration );
		try {
			wp_set_auth_cookie( $link->user_id, false, is_ssl(), $token );
		} finally {
			remove_filter( 'auth_cookie_expiration', $expiration );
		}
		do_action( 'wp_login', get_userdata( $link->user_id )->user_login, get_userdata( $link->user_id ) );
		return $token;
	}
}
