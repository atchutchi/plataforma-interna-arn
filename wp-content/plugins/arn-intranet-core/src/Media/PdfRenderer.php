<?php
/** Opt-in static first-page rendering in a separate bounded process.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Closure;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** No decoder runs in the WordPress process. Missing enabled dependencies fail closed. */
final class PdfRenderer {
	public static function configured(): ?Closure {
		if ( ! defined( 'ARN_ALLOW_LOCAL_PDF_PREVIEWS' ) || false === ARN_ALLOW_LOCAL_PDF_PREVIEWS ) {
			return null;
		}
		if ( true !== ARN_ALLOW_LOCAL_PDF_PREVIEWS || 'local' !== wp_get_environment_type() || ! defined( 'ARN_MEDIA_PDF_PYTHON' ) || ! is_string( ARN_MEDIA_PDF_PYTHON ) ) {
			throw new RuntimeException( 'Pré-visualização PDF indisponível.' );
		}
		$python = ARN_MEDIA_PDF_PYTHON;
		return static function ( string $source, string $job, float $deadline ) use ( $python ): void {
			if ( ! is_file( $python ) || is_link( $python ) || false === realpath( $python ) || str_replace( '\\', '/', realpath( $python ) ) !== str_replace( '\\', '/', $python ) ) {
				throw new RuntimeException( 'Runtime PDF indisponível.' );
			}
			$result = LocalProcess::run( array( $python, '-I', '-B', __DIR__ . '/PdfWorker.py', $source, $job ), $job, min( 15, (int) floor( $deadline - microtime( true ) ) ) );
			if ( 0 !== $result['code'] || 'processed' !== $result['out'] || '' !== trim( $result['err'] ) ) {
				throw new RuntimeException( 'Pré-visualização PDF indisponível.' );
			}
			foreach ( array(
				'thumbnail' => 320,
				'preview'   => 1280,
			) as $variant => $limit ) {
				$path = $job . '/' . $variant . '.png';
				$size = is_file( $path ) && ! is_link( $path ) ? getimagesize( $path ) : false;
				if ( false === $size || IMAGETYPE_PNG !== $size[2] || min( $size[0], $size[1] ) < 1 || max( $size[0], $size[1] ) > $limit ) {
					throw new RuntimeException( 'Pré-visualização PDF inválida.' );
				}
			}
		};
	}
}
