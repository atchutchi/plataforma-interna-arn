<?php
/** Explicit private laboratory composition.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** A disabled opt-in never makes an antimalware claim. A broken enabled opt-in fails closed. */
final class LocalMediaProcessing {
	public static function pipeline( int $actor ): ?MediaPipeline {
		if ( ! defined( 'ARN_ALLOW_LOCAL_MEDIA_PROCESSING' ) || false === ARN_ALLOW_LOCAL_MEDIA_PROCESSING ) {
			return null;
		}
		if ( true !== ARN_ALLOW_LOCAL_MEDIA_PROCESSING || 'local' !== wp_get_environment_type() ) {
			throw new RuntimeException( 'Processamento privado indisponível.' );
		}
		foreach ( array( 'ARN_MEDIA_QUARANTINE_DIR', 'ARN_MEDIA_CLAMSCAN', 'ARN_MEDIA_SIGNATURES_DIR', 'ARN_MEDIA_GD_EXTENSION_DIR', 'ARN_MEDIA_PHP_CLI', 'ARN_LOCAL_PRIVATE_FILES_DIR' ) as $key ) {
			if ( ! defined( $key ) || ! is_string( constant( $key ) ) || '' === constant( $key ) ) {
				throw new RuntimeException( 'Processamento privado indisponível.' );
			}
		}
		$extensions = realpath( ARN_MEDIA_GD_EXTENSION_DIR );
		if ( false === $extensions || ! is_dir( $extensions ) || ! function_exists( 'set_time_limit' ) || ! set_time_limit( 150 ) ) {
			throw new RuntimeException( 'Runtime de processamento indisponível.' );
		}
		$scanner = new ClamAvScanner( ARN_MEDIA_CLAMSCAN, ARN_MEDIA_SIGNATURES_DIR );
		return new MediaPipeline(
			ARN_MEDIA_QUARANTINE_DIR,
			array_merge( \Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles::publicRoots(), array( ARN_LOCAL_PRIVATE_FILES_DIR ) ),
			$scanner->scan( ... ),
			static function ( string $source, string $job, float $deadline ) use ( $extensions ): void {
				$seconds = min( 5, (int) floor( $deadline - microtime( true ) ) );
				$result  = LocalProcess::run( array( ARN_MEDIA_PHP_CLI, '-n', '-d', 'extension_dir=' . $extensions, '-d', 'extension=gd', '-d', 'memory_limit=192M', '-d', 'display_errors=0', __DIR__ . '/ImageWorker.php', $source, $job ), $job, $seconds );
				if ( 0 !== $result['code'] || 'processed' !== $result['out'] || '' !== trim( $result['err'] ) ) {
					throw new RuntimeException( 'Processamento de imagem indisponível.' );
				}
			},
			$actor,
			PdfRenderer::configured()
		);
	}
}
