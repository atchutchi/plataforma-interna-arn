<?php
/** Closed server roster for the fictional editorial laboratory.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Access\LocalFixtureUser;
use Closure;
use Throwable;
use WP_User;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment

/** Every normal participant must exist and carry the exact fixture marker. */
final class LocalNewsRoster {
	private const UNITS = array(
		'autor.noticias.teste'         => array( 'LAB-A' ),
		'editor.noticias.teste'        => array(),
		'editor.suplente.teste'        => array(),
		'colaborador.noticias.a.teste' => array( 'LAB-A' ),
		'colaborador.noticias.b.teste' => array( 'LAB-B' ),
	);
	private readonly Closure $lookup;

	public function __construct( ?Closure $lookup = null ) {
		$this->lookup = $lookup ?? static function ( string $login ): ?array {
			$user = get_user_by( 'login', $login );
			return $user instanceof WP_User ? array(
				'id'     => $user->ID,
				'login'  => $user->user_login,
				'marked' => '1' === get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) && array( 'subscriber' ) === array_values( $user->roles ),
			) : null;
		};
	}

	/** Server-only ID to exact unit membership, empty if any expected account fails. */
	public function participantUnits(): array {
		try {
			$people = array();
			foreach ( self::UNITS as $login => $units ) {
				$row = ( $this->lookup )( $login );
				if ( ! is_array( $row ) || ! is_int( $row['id'] ?? null ) || $row['id'] < 1 || ( $row['login'] ?? null ) !== $login || true !== ( $row['marked'] ?? null ) || isset( $people[ $row['id'] ] ) ) {
					return array();
				}
				$people[ $row['id'] ] = $units;
			}
			ksort( $people, SORT_NUMERIC );
			return $people;
		} catch ( Throwable ) {
			return array();
		}
	}

	public function unitsFor( int $user_id ): ?array {
		$people = $this->participantUnits();
		return $people[ $user_id ] ?? null;
	}

	public function reviewerIds(): array {
		$people = $this->participantUnits();
		if ( 5 !== count( $people ) ) {
			return array();
		}
		$ids = array();
		foreach ( array( 'editor.noticias.teste', 'editor.suplente.teste' ) as $login ) {
			$row = ( $this->lookup )( $login );
			if ( ! is_array( $row ) || ! isset( $people[ $row['id'] ?? 0 ] ) ) {
				return array();
			}
			$ids[] = $row['id'];
		}
		return $ids;
	}

	/** Resolve the one named person preset from the complete marked server roster. */
	public function personAId(): ?int {
		$people = $this->participantUnits();
		if ( 5 !== count( $people ) ) {
			return null;
		}
		try {
			$row = ( $this->lookup )( 'colaborador.noticias.a.teste' );
			$id  = $row['id'] ?? null;
			return is_int( $id ) && ( $row['login'] ?? null ) === 'colaborador.noticias.a.teste' && ( $people[ $id ] ?? null ) === array( 'LAB-A' ) ? $id : null;
		} catch ( Throwable ) {
			return null;
		}
	}
}
