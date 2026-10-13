<?php
/**
 * Competência local provisória para operações explícitas de identidade.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

/**
 * Consultar manage_options não atribui esta competência a ninguém.
 * A matriz institucional e a operação administrativa futura continuam pendentes.
 */
final class WordPressIdentityLinkAuthority implements IdentityLinkAuthority {

	/**
	 * Exige actor actual, conta local e competência no site corrente.
	 *
	 * @param int $actor_id Actor local da decisão.
	 */
	public function canManage( int $actor_id ): bool {
		return $actor_id > 0 && get_current_user_id() === $actor_id &&
			$this->accountExists( $actor_id ) && user_can( $actor_id, 'manage_options' );
	}

	/**
	 * Confirma uma conta prévia, incluindo pertença ao site em multisite.
	 *
	 * @param int $user_id Conta alvo.
	 */
	public function accountExists( int $user_id ): bool {
		return $user_id > 0 && false !== get_userdata( $user_id ) &&
			( ! is_multisite() || is_user_member_of_blog( $user_id ) );
	}
}
