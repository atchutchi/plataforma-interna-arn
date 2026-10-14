<?php
/** Política pura da geração de sessões fictícias.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** A revisão persistente invalida todas as gerações anteriores. */
final class LocalAccountSessionPolicy {
	/**
	 * Exige um snapshot activo da mesma conta.
	 *
	 * @param AuthorizationSnapshot|null $snapshot Leitura actual.
	 * @param int                        $user_id Conta autenticada.
	 */
	public static function active( ?AuthorizationSnapshot $snapshot, int $user_id ): bool {
		return null !== $snapshot && $user_id > 0 && $snapshot->user_id === $user_id && AccountState::Active === $snapshot->state;
	}
	/**
	 * Não normaliza os campos internos do token.
	 *
	 * @param AuthorizationSnapshot|null $snapshot Leitura actual.
	 * @param int                        $user_id Conta autenticada.
	 * @param array                      $session Metadados guardados no servidor.
	 * @param bool                       $enabled Gate efectivo.
	 */
	public static function valid( ?AuthorizationSnapshot $snapshot, int $user_id, array $session, bool $enabled ): bool {
		return $enabled && self::active( $snapshot, $user_id )
			&& true === ( $session['arn_authz_local'] ?? null )
			&& ( $session['arn_authz_user_id'] ?? null ) === $user_id
			&& ( $session['arn_authz_revision'] ?? null ) === $snapshot->revision;
	}
}
