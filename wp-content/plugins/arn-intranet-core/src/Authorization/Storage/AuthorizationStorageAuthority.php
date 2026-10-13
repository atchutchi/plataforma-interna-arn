<?php
/**
 * Autoridade explícita para o laboratório de persistência.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

/** Nenhuma competência é inferida por email ou papel funcional. */
interface AuthorizationStorageAuthority {

	/**
	 * Confirma actor administrativo autorizado.
	 *
	 * @param int $actor_id Actor.
	 * @return bool
	 */
	public function canManage( int $actor_id ): bool;

	/**
	 * Confirma conta fictícia previamente existente e permitida.
	 *
	 * @param int $user_id Conta alvo.
	 * @return bool
	 */
	public function canUseAccount( int $user_id ): bool;
}
