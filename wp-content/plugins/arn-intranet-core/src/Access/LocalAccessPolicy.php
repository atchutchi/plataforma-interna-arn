<?php
/**
 * Regras de acesso da fundação local.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Access;

/**
 * Impede acesso fictício fora do computador local e privilégios concedidos só pelo email.
 */
final class LocalAccessPolicy {

	public const ENVIRONMENT_LOCAL = 'local';

	/**
	 * Contas da secção 5.4. A presença nesta lista não atribui funções.
	 *
	 * @var string[]
	 */
	public const EMAILS_REQUIRING_EXPLICIT_ASSIGNMENT = array(
		'ferreira.atchutchi@arn.gw',
		'clayton.correia@arn.gw',
		'admin@arn.gw',
	);

	/**
	 * O modo fictício só existe com autorização explícita e ambiente local.
	 *
	 * @param string $environment_type Valor de wp_get_environment_type().
	 * @param bool   $explicit_flag    Constante ARN_ALLOW_FICTIONAL_LOCAL_ACCESS.
	 */
	public static function fictionalAccessAllowed( string $environment_type, bool $explicit_flag ): bool {
		return $explicit_flag && self::ENVIRONMENT_LOCAL === $environment_type;
	}

	/**
	 * Nenhum email, domínio ou conta designada concede administração por si só.
	 *
	 * @param string $email Endereço indicado, sem validar se a conta existe.
	 */
	public static function emailGrantsAdministration( string $email ): bool {
		$normalized = strtolower( trim( $email ) );

		foreach ( self::EMAILS_REQUIRING_EXPLICIT_ASSIGNMENT as $designated ) {
			if ( strtolower( $designated ) === $normalized ) {
				return false;
			}
		}

		return false;
	}
}
