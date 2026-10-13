<?php
/**
 * Regra local aplicada apenas depois da validação OIDC completa.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

/**
 * Resolve uma conta autorizada. Não valida JWT nem estabelece sessão.
 */
final class GoogleAccountPolicy {

	/**
	 * O futuro cliente mantido tem de validar assinatura, emissor, audiência,
	 * expiração, state e nonce antes desta chamada. Claims brutas não são prova
	 * de identidade. Nenhum hook ou endpoint chama esta classe nesta entrega.
	 *
	 * @param array<string, mixed> $validated_claims Claims já validadas pelo cliente.
	 * @param string               $workspace_domain Domínio aprovado na configuração.
	 * @param LocalIdentityLink[]  $links Vínculos explícitos candidatos, nunca pesquisa por email.
	 */
	public static function localUserId( array $validated_claims, string $workspace_domain, array $links ): ?int {
		$issuer  = $validated_claims['iss'] ?? null;
		$subject = $validated_claims['sub'] ?? null;
		if (
			! is_string( $issuer ) || ! is_string( $subject ) ||
			! OidcConfiguration::validDomain( $workspace_domain ) ||
			! LocalIdentityLink::validSubject( $subject ) ||
			( $validated_claims['hd'] ?? null ) !== $workspace_domain ||
			true !== ( $validated_claims['email_verified'] ?? null )
		) {
			return null;
		}

		$canonical = LocalIdentityLink::canonicalIssuer( $issuer );
		if ( null === $canonical ) {
			return null;
		}

		$matches = array();
		foreach ( $links as $link ) {
			if ( ! $link instanceof LocalIdentityLink ) {
				return null;
			}
			if ( $link->issuer === $canonical && $link->subject === $subject ) {
				$matches[] = $link;
			}
		}

		// Recusar ambiguidade mesmo se o segundo vínculo estiver desactivado.
		if ( 1 !== count( $matches ) || ! $matches[0]->authorized || ! $matches[0]->active ) {
			return null;
		}

		return $matches[0]->user_id;
	}
}
