<?php
/**
 * Política pura de duração e inactividade para o ensaio local OIDC.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

/**
 * Não emite sessões, não consulta WordPress e não atribui funções.
 */
final class FederatedSessionPolicy {

	/**
	 * Duração máxima escolhida para o ensaio local, em segundos.
	 */
	public const LIFETIME = 3600;

	/**
	 * Inactividade máxima escolhida para o ensaio local, em segundos.
	 */
	public const IDLE = 1800;

	/**
	 * Avalia vínculo corrente e metadados de WP_Session_Tokens.
	 * Estes limites não constituem aprovação da política institucional.
	 *
	 * @param LocalIdentityLink|null $link    Vínculo corrente da conta local.
	 * @param array<string, mixed>   $session Metadados da sessão WordPress.
	 * @param int                    $now     Instante actual em segundos.
	 * @param bool                   $enabled Activação explícita do ensaio.
	 */
	public static function valid( ?LocalIdentityLink $link, array $session, int $now, bool $enabled ): bool {
		if ( true !== $enabled || null === $link || ! $link->authorized || ! $link->active || true !== ( $session['arn_oidc'] ?? null ) ) {
			return false;
		}
		$issued     = $session['arn_oidc_issued_at'] ?? null;
		$last_seen  = $session['arn_oidc_last_seen'] ?? null;
		$expiration = $session['expiration'] ?? null;
		if ( ! is_int( $issued ) || ! is_int( $last_seen ) || ! is_int( $expiration ) ) {
			return false;
		}
		return $issued >= 0 && $issued <= $last_seen && $last_seen <= $now &&
			$now - $issued < self::LIFETIME && $now - $last_seen < self::IDLE && $expiration > $now;
	}
}
