<?php
/** Bounded metadata-only inventory. No content reads or deletion.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Closure;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class InventorySnapshot {

	public static function read( string $root, array $excluded, ?Closure $free = null ): array {
		new PrivateBlobStore( $root, $excluded, array( 'application/pdf' ), 1 );
		$root     = str_replace( '\\', '/', realpath( $root ) );
		$result   = array(
			'files'       => 0,
			'bytes'       => 0,
			'directories' => 0,
			'batches'     => 0,
			'entries'     => 0,
			'free'        => 0,
		);
		$pending  = array( array( $root, 0 ) );
		$visited  = 0;
		$deadline = hrtime( true ) + 2000000000;
		while ( $pending ) {
			[$directory, $depth] = array_pop( $pending );
			new PrivateBlobStore( $directory, $excluded, array( 'application/pdf' ), 1 );
			foreach ( new \FilesystemIterator( $directory, \FilesystemIterator::SKIP_DOTS ) as $entry ) {
				if ( 0 === $depth ) {
					++$result['entries']; }
				if ( ++$visited > 5000 || hrtime( true ) > $deadline ) {
					throw new RuntimeException( 'Inventário excede os limites locais.' ); }
				$path = $entry->getPathname();
				$stat = @lstat( $path );
				if ( false === $stat || is_link( $path ) ) {
					throw new RuntimeException( 'Inventário incompleto.' ); }
				$type = $stat['mode'] & 0170000;
				if ( 0040000 === $type ) {
					if ( $depth >= 4 ) {
						throw new RuntimeException( 'Inventário excede a profundidade local.' ); }
					++$result['directories'];
					if ( 0 === $depth && 1 === preg_match( '/^[a-f0-9]{32}$/D', $entry->getFilename() ) ) {
						++$result['batches']; }
					$pending[] = array( $path, $depth + 1 );
				} elseif ( 0100000 === $type && 1 === $stat['nlink'] && $stat['size'] >= 0 ) {
					++$result['files'];
					$result['bytes'] += $stat['size'];
				} else {
					throw new RuntimeException( 'Entrada de inventário inválida.' ); }
			}
		}
		$space = null === $free ? @disk_free_space( $root ) : $free( $root );
		if ( ( ! is_int( $space ) && ! is_float( $space ) ) || ! is_finite( (float) $space ) || $space < 0 || $space >= PHP_INT_MAX ) {
			throw new RuntimeException( 'Espaço livre indisponível.' ); }
		$result['free'] = (int) $space;
		return $result;
	}
}
