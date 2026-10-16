<?php
/**
 * Elegibilidade inicial de uma identidade já validada por OIDC.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\LocalIdentityLink;
use Arn\Intranet\Identity\OidcConfiguration;
/**
 * Não valida JWT, não cria vínculos e não confere permissões de negócio.
 */
final class FirstGoogleClaimPolicy {
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param array  $validated_claims Saída do cliente OIDC mantido.
	 * @param string $primary_email Email primário seleccionado.
	 * @param string $domain Domínio configurado.
	 * @param int    $expires_at Limite exclusivo.
	 * @param int    $now Relógio UTC.
	 * @return bool Elegibilidade sem efeitos.
	 */
	public static function allows( array $validated_claims, string $primary_email, string $domain, int $expires_at, int $now ): bool {
		return $now > 0 && $now < $expires_at && OidcConfiguration::validDomain( $domain ) &&
			str_ends_with( $primary_email, '@' . $domain ) && strtolower( $primary_email ) === $primary_email &&
			false !== filter_var( $primary_email, FILTER_VALIDATE_EMAIL ) &&
			true === ( $validated_claims['email_verified'] ?? null ) &&
			( $validated_claims['hd'] ?? null ) === $domain && ( $validated_claims['email'] ?? null ) === $primary_email &&
			is_string( $validated_claims['iss'] ?? null ) && null !== LocalIdentityLink::canonicalIssuer( $validated_claims['iss'] ) &&
			is_string( $validated_claims['sub'] ?? null ) && LocalIdentityLink::validSubject( $validated_claims['sub'] );
	}
}
