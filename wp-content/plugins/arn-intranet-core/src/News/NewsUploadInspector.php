<?php
/** Helpful field feedback for bytes in an accepted PHP upload.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment -- Small field validator with a single public entry point.
/** The private blob store still validates the copied bytes authoritatively. */
final class NewsUploadInspector {
	/** Inspect server-side bytes after is_uploaded_file, never browser name or MIME. */
	public static function error( string $source, string $kind ): ?string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.PHP.NoSilencedErrors.Discouraged -- Bounded read turns an unavailable upload into a field error.
		$bytes = @file_get_contents( $source, false, null, 0, 1048577 );
		if ( false === $bytes || '' === $bytes ) {
			return 'Selecciona novamente um ficheiro válido.';
		}
		if ( strlen( $bytes ) > 1048576 ) {
			return 'Cada ficheiro pode ter no máximo 1 MiB.';
		}
		$head      = substr( $bytes, 0, 4096 );
		$tail      = substr( $bytes, -1024 );
		$mime      = ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $head );
		$signature = match ( $mime ) {
			'application/pdf' => str_starts_with( $head, '%PDF-' ) && str_contains( $tail, '%%EOF' ),
			'image/png' => str_starts_with( $head, "\x89PNG\r\n\x1a\n" ) && str_ends_with( $tail, "IEND\xaeB`\x82" ),
			'image/jpeg' => str_starts_with( $head, "\xff\xd8\xff" ) && str_ends_with( $tail, "\xff\xd9" ),
			default => false,
		};
		if ( ! $signature || ( 'attachment' !== $kind && ! in_array( $mime, array( 'image/png', 'image/jpeg' ), true ) ) ) {
			return 'attachment' === $kind ? 'Selecciona um PDF, PNG ou JPEG válido.' : 'Selecciona uma imagem PNG ou JPEG válida.';
		}
		if ( 'attachment' !== $kind ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid image bytes must become a field error, not an HTTP warning.
			$dimensions = @getimagesizefromstring( $bytes );
			if ( false === $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > 4096 || $dimensions[1] > 4096 || $dimensions[0] * $dimensions[1] > 16000000 ) {
				return 'A imagem deve ter até 4096 píxeis por lado e 16 milhões de píxeis no total.';
			}
		}
		return null;
	}
}
