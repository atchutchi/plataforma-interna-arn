<?php
/**
 * Fronteira de autorização local para a gestão de identidades.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

/**
 * Uma identidade externa nunca concede esta competência.
 */
interface IdentityLinkAuthority {

	/**
	 * Confirma que o actor local actual pode tomar esta decisão.
	 *
	 * @param int $actor_id Actor local positivo.
	 */
	public function canManage( int $actor_id ): bool;

	/**
	 * Confirma a existência prévia da conta no site actual.
	 *
	 * @param int $user_id Conta local positiva.
	 */
	public function accountExists( int $user_id ): bool;
}
