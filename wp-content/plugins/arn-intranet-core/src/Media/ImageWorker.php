<?php
/** Isolated CLI image decoding. No WordPress bootstrap or HTTP entry point.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, WordPress.Security.EscapeOutput.OutputNotEscaped
if ( 'cli' !== PHP_SAPI || 3 !== count( $argv ?? array() ) || ! extension_loaded( 'gd' ) ) {
	exit( 2 );
}
try {
	$source = $argv[1];
	$job    = realpath( $argv[2] );
	if ( false === $job || dirname( ( false === realpath( $source ) ? '' : realpath( $source ) ) ) !== $job || is_link( $source ) || filesize( $source ) > 1048576 ) {
		exit( 2 );
	}
	$bytes = file_get_contents( $source );
	$size  = @getimagesizefromstring( $bytes );
	if ( false === $size || ! in_array( $size[2], array( IMAGETYPE_PNG, IMAGETYPE_JPEG ), true ) || $size[0] < 1 || $size[1] < 1 || max( $size[0], $size[1] ) > 4096 || $size[0] * $size[1] > 16000000 ) {
		exit( 2 );
	}
	$source_image = @imagecreatefromstring( $bytes );
	if ( false === $source_image ) {
		exit( 2 );
	}
	foreach ( array(
		'thumbnail' => 320,
		'preview'   => 1280,
	) as $variant => $limit ) {
		$ratio  = min( 1, $limit / max( $size[0], $size[1] ) );
		$width  = max( 1, (int) floor( $size[0] * $ratio ) );
		$height = max( 1, (int) floor( $size[1] * $ratio ) );
		$image  = imagecreatetruecolor( $width, $height );
		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		if ( ! imagecopyresampled( $image, $source_image, 0, 0, 0, 0, $width, $height, $size[0], $size[1] ) ) {
			exit( 2 );
		}
		$handle = @fopen( $job . '/' . $variant . '.png', 'xb' );
		if ( false === $handle || ! imagepng( $image, $handle, 6 ) || ! fflush( $handle ) ) {
			exit( 2 );
		}
		fclose( $handle );
	}
	echo 'processed';
} catch ( Throwable ) {
	exit( 2 );
}
