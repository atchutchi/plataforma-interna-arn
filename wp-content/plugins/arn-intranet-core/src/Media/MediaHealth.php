<?php
/** Synthetic operational probes. Never scans a business object or changes its state.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class MediaHealth {
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC';
	public static function pdf(): string {
		$stream  = "0.1 0.4 0.3 rg 20 20 160 160 re f\n";
		$objects = array( '<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Kids [3 0 R] /Count 1 >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Resources << >> /Contents 4 0 R >>', '<< /Length ' . strlen( $stream ) . ">>\nstream\n" . $stream . 'endstream' );
		$pdf     = "%PDF-1.4\n";
		$offsets = array( 0 );
		foreach ( $objects as $i => $object ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $i + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= "xref\n0 5\n0000000000 65535 f \n";
		foreach ( array_slice( $offsets, 1 ) as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset ); }
		return $pdf . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
	}
	private static function fixture( string $job, string $name, string $bytes ): string {
		$dir = $job . '/' . $name;
		if ( ! mkdir( $dir, 0700 ) ) {
			throw new RuntimeException( 'Ensaio indisponível.' ); }
		$h = fopen( $dir . '/source', 'xb' );
		if ( false === $h ) {
			throw new RuntimeException( 'Ensaio indisponível.' ); }
		try {
			if ( strlen( $bytes ) !== fwrite( $h, $bytes ) || ! fflush( $h ) || ! fsync( $h ) ) {
				throw new RuntimeException( 'Ensaio incompleto.' ); }
		} finally {
			fclose( $h ); }
		return $dir;
	}
	public static function probe( string $job ): array {
		$result = array(
			'scanner' => 'unavailable',
			'image'   => 'unavailable',
			'pdf'     => 'unavailable',
			'storage' => array(),
		);
		try {
			$dir               = self::fixture( $job, 'scanner', "ARN laboratory benign scanner diagnostic.\n" );
			$scanner           = new ClamAvScanner( ARN_MEDIA_CLAMSCAN, ARN_MEDIA_SIGNATURES_DIR );
			$result['scanner'] = 'clean' === $scanner->scan( $dir . '/source', $dir ) ? 'available' : 'unavailable';
		} catch ( Throwable ) {
			$result['scanner'] = 'unavailable'; }
		try {
			$dir = self::fixture( $job, 'image', base64_decode( self::PNG, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fixed benign one-pixel PNG.
			$r   = LocalProcess::run( array( ARN_MEDIA_PHP_CLI, '-n', '-d', 'extension_dir=' . ARN_MEDIA_GD_EXTENSION_DIR, '-d', 'extension=gd', '-d', 'memory_limit=192M', '-d', 'display_errors=0', __DIR__ . '/ImageWorker.php', $dir . '/source', $dir ), $dir, 5 );
			if ( 0 === $r['code'] && 'processed' === $r['out'] && '' === trim( $r['err'] ) ) {
				foreach ( array( 'preview', 'thumbnail' ) as $variant ) {
					$size = getimagesize( $dir . '/' . $variant . '.png' );
					if ( false === $size || IMAGETYPE_PNG !== $size[2] || 1 !== $size[0] || 1 !== $size[1] ) {
						throw new RuntimeException( 'Imagem inválida.' ); }
				}
				$result['image'] = 'available';
			}
		} catch ( Throwable ) {
			$result['image'] = 'unavailable'; }
		try {
			$render = PdfRenderer::configured();
			if ( null === $render ) {
				$result['pdf'] = 'disabled'; } else {
				$dir = self::fixture( $job, 'pdf', self::pdf() );
				$render( $dir . '/source', $dir, microtime( true ) + 16 );
				$result['pdf'] = 'available';
				}
		} catch ( Throwable ) {
			$result['pdf'] = 'unavailable'; }
		foreach ( array(
			'queue'      => 'ARN_MEDIA_QUEUE_DIR',
			'quarantine' => 'ARN_MEDIA_QUARANTINE_DIR',
			'delivery'   => 'ARN_LOCAL_PRIVATE_FILES_DIR',
		) as $key => $constant ) {
			try {
				$result['storage'][ $key ] = InventorySnapshot::read( constant( $constant ), LocalPrivateFiles::publicRoots() ); } catch ( Throwable ) {
				$result['storage'][ $key ] = null; }
		}
		HealthReports::validate( $result );
		return $result;
	}
}
