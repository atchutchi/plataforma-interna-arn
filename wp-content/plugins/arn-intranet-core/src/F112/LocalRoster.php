<?php
/**
 * F1-12 local fictional composition.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\{Role, Scope};
use RuntimeException;
use WP_User;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Closed fictional roster.
final class LocalRoster {
	public static function validPrefix( string $prefix ): bool {
		return 1 === preg_match( '/\Aarn_f112_[a-f0-9]{10}_\z/D', $prefix );
	}

	public static function definitions( ?string $prefix = null ): array {
		if ( null !== $prefix && ! self::validPrefix( $prefix ) ) {
			throw new RuntimeException( 'Prefixo F112 inválido.' );
		}
		$logins = array(
			'a'        => 'f112.colaborador.a',
			'b'        => 'f112.colaborador.b',
			'manager'  => 'f112.responsavel.a',
			'author'   => 'f112.autor.institucional',
			'reviewer' => 'f112.revisor.institucional',
			'events'   => 'f112.gestor.eventos',
		);
		$rows   = array();
		foreach ( $logins as $label => $login ) {
			$rows[ $label ] = array(
				'login' => null === $prefix ? $login : $prefix . $label,
				'units' => match ( $label ) {
									'a', 'manager' => array( 'LAB-A' ), 'b' => array( 'LAB-B' ), default => array() },
			);
		}
		return $rows;
	}

	public static function grants( string $label ): array {
		if ( ! isset( self::definitions()[ $label ] ) ) {
			throw new RuntimeException( 'Fixture F112 inválida.' );
		}
		$grants = array( array( Role::Employee, Scope::own() ) );
		if ( 'manager' === $label ) {
			$grants[] = array( Role::UnitManager, Scope::units( array( 'LAB-A' ) ) );
			$grants[] = array( Role::UnitEventManager, Scope::units( array( 'LAB-A' ) ) );
		}
		if ( in_array( $label, array( 'author', 'reviewer' ), true ) ) {
			$role     = 'author' === $label ? Role::NoticeAuthor : Role::NoticeReviewer;
			$grants[] = array( $role, Scope::queues( array( 'communications-ficticia' ) ) );
			$grants[] = array( $role, Scope::audiences( array( 'f112_all', 'f112_unit_LAB-A', 'f112_unit_LAB-B' ) ) );
		}
		if ( 'events' === $label ) {
			$grants[] = array( Role::InstitutionalEventManager, Scope::institutional() );
		}
		return $grants;
	}

	public static function resolve( ?string $prefix = null ): array {
		$rows = array();
		global $wpdb;
		foreach ( self::definitions( $prefix ) as $person ) {
			$user = get_user_by( 'login', $person['login'] );
			if ( ! $user instanceof WP_User || $user->user_email !== $person['login'] . '@example.invalid' || array( 'subscriber' ) !== array_values( $user->roles ) || '1' !== get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) || ( $prefix ?? $wpdb->prefix ) !== get_user_meta( $user->ID, '_arn_f112_fixture', true ) ) {
				throw new RuntimeException( 'Elenco F112 indisponível.' );
			}
			$rows[ $user->ID ] = $person;
		}
		ksort( $rows, SORT_NUMERIC );
		return $rows;
	}
}
