<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Three strictly fictional local people. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use RuntimeException;

final class LocalPeopleRoster {
	public static function validPrefix( string $prefix ): bool {
		return 1 === preg_match( '/\Aarn_peo_[a-f0-9]{10}_\z/D', $prefix );
	}

	public static function logins( ?string $exclusive_prefix = null ): array {
		if ( null === $exclusive_prefix ) {
			return array(
				'alfa'    => 'f107.alfa.teste',
				'beta'    => 'f107.beta.teste',
				'manager' => 'f107.responsavel.a.teste',
			);
		}
		if ( ! self::validPrefix( $exclusive_prefix ) ) {
			throw new RuntimeException( 'Prefixo People inválido.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant exception.
		}
		return array(
			'alfa'    => $exclusive_prefix . 'alfa',
			'beta'    => $exclusive_prefix . 'beta',
			'manager' => $exclusive_prefix . 'manager',
		);
	}

	public static function details( string $label ): ProfessionalDetails {
		return match ( $label ) {
			'alfa' => new ProfessionalDetails( 'LAB-PEO-A', 'Alfa Teste', 'f107.alfa.teste@example.invalid', 'Técnico de teste', 'LAB-A' ),
			'beta' => new ProfessionalDetails( 'LAB-PEO-B', 'Beta Teste', 'f107.beta.teste@example.invalid', 'Técnico de teste', 'LAB-B' ),
			'manager' => new ProfessionalDetails( 'LAB-PEO-M', 'Responsável A Teste', 'f107.responsavel.a.teste@example.invalid', 'Responsável de teste', 'LAB-A' ),
			default => throw new RuntimeException( 'Pessoa local inválida.' ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant exception.
		};
	}

	public static function ids( ?string $prefix = null ): array {
		$ids = array();
		foreach ( self::logins( $prefix ) as $label => $login ) {
			$user = get_user_by( 'login', $login );
			if ( ! $user instanceof \WP_User || $user->user_email !== $login . '@example.invalid' || '1' !== get_user_meta( $user->ID, \Arn\Intranet\Access\LocalFixtureUser::META_KEY, true ) || array( 'subscriber' ) !== array_values( $user->roles ) ) {
				throw new RuntimeException( 'Elenco People indisponível.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant exception.
			}
			$ids[ $label ] = $user->ID;
		}
		return $ids;
	}

	/** Used only while the explicit installer is creating the closed roster. */
	public static function contains( int $user_id, ?string $prefix = null ): bool {
		$user = get_userdata( $user_id );
		return $user instanceof \WP_User && in_array( $user->user_login, self::logins( $prefix ), true ) && $user->user_email === $user->user_login . '@example.invalid'
			&& '1' === get_user_meta( $user_id, \Arn\Intranet\Access\LocalFixtureUser::META_KEY, true )
			&& array( 'subscriber' ) === array_values( $user->roles );
	}
}
