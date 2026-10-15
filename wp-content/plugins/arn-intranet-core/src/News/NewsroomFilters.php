<?php
/** Strict CMS filters applied to authorised projections.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Filter only rows projected after authorization. */
final class NewsroomFilters {
	/** Reject malformed or structured GET values. */
	public static function parse( array $get ): ?array {
		$keys    = array( 'arn_author', 'arn_from', 'arn_to', 'arn_state', 'arn_paged' );
		$filters = array();
		foreach ( $keys as $key ) {
			$value = $get[ $key ] ?? '';
			if ( ! is_string( $value ) ) {
				return null;
			}
			$filters[ $key ] = $value;
		}
		if ( '' !== $filters['arn_author'] && 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $filters['arn_author'] ) ) {
			return null;
		}
		if ( '' !== $filters['arn_state'] && ! in_array( $filters['arn_state'], array( 'draft', 'pending', 'rejected', 'published', 'archived' ), true ) ) {
			return null;
		}
		if ( '' !== $filters['arn_paged'] && 1 !== preg_match( '/^[1-9][0-9]{0,5}$/D', $filters['arn_paged'] ) ) {
			return null;
		}
		foreach ( array( 'arn_from', 'arn_to' ) as $key ) {
			if ( '' !== $filters[ $key ] && ! self::date( $filters[ $key ] ) ) {
				return null;
			}
		}
		if ( '' !== $filters['arn_from'] && '' !== $filters['arn_to'] && $filters['arn_from'] > $filters['arn_to'] ) {
			return null;
		}
		return $filters;
	}

	/** Require an exact UTC calendar date. */
	private static function date( string $value ): bool {
		if ( 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value ) ) {
			return false;
		}
		$day = \DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new \DateTimeZone( 'UTC' ) );
		return false !== $day && $day->format( 'Y-m-d' ) === $value;
	}

	/** Select rows before counting or pagination. */
	public static function apply( array $rows, array $filters ): array {
		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $filters ): bool {
					$date = gmdate( 'Y-m-d', (int) $row['at'] );
					return ( '' === $filters['arn_author'] || (int) $filters['arn_author'] === $row['author_id'] )
					&& ( '' === $filters['arn_state'] || $filters['arn_state'] === $row['state'] )
					&& ( '' === $filters['arn_from'] || $date >= $filters['arn_from'] )
					&& ( '' === $filters['arn_to'] || $date <= $filters['arn_to'] );
				}
			)
		);
	}
}
