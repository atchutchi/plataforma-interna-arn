<?php
/** In-memory selection for the closed local roster.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

use InvalidArgumentException;

/** Filter eligible profiles before counting or paginating. */
final class PeopleSelection {
	/** Return internal Profile objects, never a public projection.
	 *
	 * @param array<Profile> $profiles Closed roster profiles.
	 * @param PeopleQuery    $query Validated filters.
	 * @param callable       $eligible Target eligibility predicate.
	 * @throws InvalidArgumentException If the roster is malformed.
	 */
	public static function select( array $profiles, PeopleQuery $query, callable $eligible ): array {
		$matching = array();
		foreach ( $profiles as $profile ) {
			if ( ! $profile instanceof Profile ) {
				throw new InvalidArgumentException( 'Lista de perfis inválida.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure domain exception.
			}
			if ( ! $eligible( $profile ) ) {
				continue;
			}
			if ( '' !== $query->unit && $profile->professional->unit_id !== $query->unit ) {
				continue;
			}
			if ( '' !== $query->job && $profile->professional->job !== $query->job ) {
				continue;
			}
			if ( '' !== $query->q && 1 !== preg_match( '/' . preg_quote( $query->q, '/' ) . '/iu', $profile->professional->name ) ) {
				continue;
			}
			$matching[] = $profile;
		}
		usort(
			$matching,
			static function ( Profile $a, Profile $b ): int {
				$name_order = strcmp( $a->professional->name, $b->professional->name );
				return 0 !== $name_order ? $name_order : $a->user_id <=> $b->user_id;
			}
		);
		$total = count( $matching );
		$pages = (int) ceil( $total / 10 );
		return array(
			'items' => $query->page > $pages ? array() : array_slice( $matching, ( $query->page - 1 ) * 10, 10 ),
			'total' => $total,
			'page'  => $query->page,
			'pages' => $pages,
		);
	}
}
