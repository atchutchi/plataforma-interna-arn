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
use Arn\Intranet\Identity\PreRegistration\{CurrentPreRegistration, PreRegistrationState};

/** Emite exclusivamente a sessão local correspondente ao vínculo autorizado. */
final class WordPressFederatedSession {

	/**
	 * Cria a sessão autorizada e guarda os limites federados.
	 *
	 * @param LocalIdentityLink $link Vínculo resolvido após OIDC e autorização local.
	 * @param int|null          $pre_registration_revision Revisão corrente ou conta anterior sem pré-cadastro.
	 * @return string Token apenas para consumo interno, nunca HTML ou logs.
	 * @throws RuntimeException Quando a conta ou a sessão não está autorizada.
	 */
	public function issue( LocalIdentityLink $link, ?int $pre_registration_revision = null ): string {
		if ( ! $link->active || ! $link->authorized || false === get_userdata( $link->user_id ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Sessão institucional não autorizada.' );
		}
		$registration = CurrentPreRegistration::lookup( $link->user_id );
		if ( ( null === $registration && null !== $pre_registration_revision ) || ( null !== $registration && ( null === $pre_registration_revision || $registration->revision !== $pre_registration_revision || PreRegistrationState::Linked !== $registration->state || ! CurrentPreRegistration::enabled() ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Erro constante.
			throw new RuntimeException( 'Sessão institucional não autorizada.' );
		}
		$now                            = time();
		$manager                        = WP_Session_Tokens::get_instance( $link->user_id );
		$token                          = $manager->create( $now + FederatedSessionPolicy::LIFETIME );
		$metadata                       = $manager->get( $token );
		$metadata['arn_oidc']           = true;
		$metadata['arn_oidc_issued_at'] = $now;
		$metadata['arn_oidc_last_seen'] = $now;
		if ( null !== $pre_registration_revision ) {
			$metadata['arn_workspace_revision'] = $pre_registration_revision;
		}
		$manager->update( $token, $metadata );
		if ( true !== ( $manager->get( $token )['arn_oidc'] ?? null ) ) {
			$manager->destroy( $token );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Não foi possível emitir a sessão institucional.' );
		}
		if ( null !== $pre_registration_revision ) {
			try {
				$current = CurrentPreRegistration::lookup( $link->user_id );
				if ( null === $current || PreRegistrationState::Linked !== $current->state || $current->revision !== $pre_registration_revision || ! CurrentPreRegistration::enabled() || ( $manager->get( $token )['arn_workspace_revision'] ?? null ) !== $pre_registration_revision ) {
					throw new RuntimeException();
				}
			} catch ( \Throwable ) {
				$manager->destroy( $token );
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Erro constante.
				throw new RuntimeException( 'Sessão institucional não autorizada.' );
			}
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
