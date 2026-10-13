<?php
/**
 * Fronteiras WordPress isoladas para a autoridade de gestão de identidade.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use Arn\Intranet\Tests\IdentityAuthorityState;

/**
 * Actor do pedido, nunca o ID fornecido pelo chamador.
 */
function get_current_user_id(): int {
	return IdentityAuthorityState::$actor;
}

/**
 * Conta já existente.
 *
 * @param int $user_id Conta consultada.
 * @return object|false
 */
function get_userdata( int $user_id ): object|false {
	return in_array( $user_id, IdentityAuthorityState::$users, true ) ? (object) array( 'ID' => $user_id ) : false;
}

/**
 * Competência efectiva, sem atribuição.
 *
 * @param int    $user_id Actor consultado.
 * @param string $capability Competência solicitada.
 */
function user_can( int $user_id, string $capability ): bool {
	return 7 === $user_id && 'manage_options' === $capability && IdentityAuthorityState::$allowed;
}

/**
 * Ambiente do site.
 */
function is_multisite(): bool {
	return IdentityAuthorityState::$multisite;
}

/**
 * Pertença explícita da conta ao site.
 *
 * @param int $user_id Conta consultada.
 */
function is_user_member_of_blog( int $user_id ): bool {
	return in_array( $user_id, IdentityAuthorityState::$users, true ) && IdentityAuthorityState::$member;
}
