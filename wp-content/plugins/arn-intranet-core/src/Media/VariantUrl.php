<?php
/** Pure URL projection for a server-generated original route.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

/** Preserves the original route and adds only a closed presentation variant. */
final class VariantUrl {
	/**
	 * Build an image URL without depending on the WordPress rendering bootstrap.
	 *
	 * @param string $original Server-generated private original URL.
	 * @param string $variant Closed derivative name.
	 */
	public static function image( string $original, string $variant ): string {
		if ( ! in_array( $variant, array( 'thumbnail', 'preview' ), true ) ) {
			return $original;
		}
		$parts = explode( '#', $original, 2 );
		return $parts[0] . ( str_contains( $parts[0], '?' ) ? '&' : '?' ) . 'arn_media_variant=' . $variant . ( isset( $parts[1] ) ? '#' . $parts[1] : '' );
	}
}
