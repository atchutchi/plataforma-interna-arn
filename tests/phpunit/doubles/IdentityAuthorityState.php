<?php
/**
 * Fronteiras WordPress isoladas para a autoridade de gestão de identidade.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

/**
 * Estado fictício que não utiliza emails ou atribuição de funções.
 */
final class IdentityAuthorityState {
	/**
	 * Actor do pedido actual.
	 *
	 * @var int
	 */
	public static int $actor = 7;
	/**
	 * Competência local já atribuída.
	 *
	 * @var bool
	 */
	public static bool $allowed = true;
	/**
	 * Modo multisite.
	 *
	 * @var bool
	 */
	public static bool $multisite = false;
	/**
	 * Pertença ao site actual.
	 *
	 * @var bool
	 */
	public static bool $member = true;
	/**
	 * Contas previamente criadas.
	 *
	 * @var int[]
	 */
	public static array $users = array( 7, 12 );
}
