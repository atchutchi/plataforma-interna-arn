<?php
/** Closed task fixtures, separate from Workspace and existing module accounts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\{Role, Scope};
use RuntimeException;
use WP_User;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Closed fictional composition, constant failures.
/** Definitions do not create users or grant permissions. */
final class LocalRoster {

	public static function validPrefix( string $prefix ): bool {
		return 1 === preg_match( '/\Aarn_tsk_[a-f0-9]{10}_\z/D', $prefix );
	}

	public static function definitions( ?string $prefix = null ): array {
		if ( null !== $prefix && ! self::validPrefix( $prefix ) ) {
			throw new RuntimeException( 'Prefixo de tarefas inválido.' );
		}
		$rows = array();
		foreach ( array(
			'a'       => 'f113.alfa.teste',
			'b'       => 'f113.beta.teste',
			'manager' => 'f113.responsavel.a.teste',
		) as $label => $login ) {
			$rows[ $label ] = array(
				'login' => null === $prefix ? $login : $prefix . $label,
				'units' => array( 'b' === $label ? 'LAB-B' : 'LAB-A' ),
			);
		}
		return $rows;
	}

	public static function grants( string $label ): array {
		if ( ! isset( self::definitions()[ $label ] ) ) {
			throw new RuntimeException( 'Fixture de tarefas inválida.' );
		}
		$grants = array( array( Role::Employee, Scope::own() ) );
		if ( 'manager' === $label ) {
			$grants[] = array( Role::UnitManager, Scope::units( array( 'LAB-A' ) ) );
		}
		return $grants;
	}

	/** Detach the map and require the complete exact login/membership pairs. */
	public static function normalize( array $roster, string $database_prefix ): array {
		if ( 3 !== count( $roster ) ) {
			throw new RuntimeException( 'Elenco de tarefas incompleto.' );
		}
		$definitions = array();
		foreach ( self::definitions( self::validPrefix( $database_prefix ) ? $database_prefix : null ) as $person ) {
			$definitions[ $person['login'] ] = $person['units'];
		}
		$seen = array();
		$copy = array();
		foreach ( $roster as $id => $person ) {
			if ( ! is_int( $id ) || $id < 1 || ! is_array( $person ) || 2 !== count( $person ) || array_diff( array_keys( $person ), array( 'login', 'units' ) ) || ! is_string( $person['login'] ) || ! isset( $definitions[ $person['login'] ] ) || isset( $seen[ $person['login'] ] ) || ! is_array( $person['units'] ) || $person['units'] !== $definitions[ $person['login'] ] ) {
				throw new RuntimeException( 'Elenco de tarefas inválido.' );
			}
			$seen[ $person['login'] ] = true;
			$copy[ $id ]              = array(
				'login' => $person['login'],
				'units' => array_values( $definitions[ $person['login'] ] ),
			);
		}
		ksort( $copy, SORT_NUMERIC );
		return $copy;
	}

	/** Resolve only previously installed exact fictional identities. */
	public static function resolve( ?string $prefix = null ): array {
		if ( ! Configuration::current() ) {
			throw new RuntimeException( 'Elenco de tarefas desactivado.' );
		}
		global $wpdb;
		$expected_prefix = $prefix ?? $wpdb->prefix;
		$rows            = array();
		foreach ( self::definitions( $prefix ) as $person ) {
			$user = get_user_by( 'login', $person['login'] );
			if ( ! $user instanceof WP_User || $user->user_email !== $person['login'] . '@example.invalid' || array( 'subscriber' ) !== array_values( $user->roles ) || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || get_user_meta( $user->ID, '_arn_tasks_fixture', true ) !== $expected_prefix ) {
				throw new RuntimeException( 'Elenco de tarefas indisponível.' );
			}
			$rows[ $user->ID ] = $person;
		}
		return self::normalize( $rows, $expected_prefix );
	}
}
