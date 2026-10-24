<?php
/** Bounded local child processes without a shell.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, WordPress.PHP.DiscouragedPHPFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Output stays in the private job directory, never in a public temporary folder. */
final class LocalProcess {

	public static function run( array $command, string $directory, int $seconds = 60 ): array {
		if ( $seconds < 1 || $seconds > 90 || ! is_file( $command[0] ?? '' ) || ! is_dir( $directory ) ) {
			throw new RuntimeException( 'Processamento privado indisponível.' );
		}
		$handles = array();
		foreach ( array( 'out', 'err' ) as $key ) {
			$handles[ $key ] = @fopen( $directory . '/' . bin2hex( random_bytes( 16 ) ) . '.' . $key, 'x+b' );
			if ( false === $handles[ $key ] ) {
				foreach ( $handles as $handle ) {
					if ( is_resource( $handle ) ) {
						fclose( $handle );
					}
				}
				throw new RuntimeException( 'Processamento privado indisponível.' );
			}
		}
		$process = false;
		try {
			$process = @proc_open(
				$command,
				array(
					0 => array( 'pipe', 'r' ),
					1 => $handles['out'],
					2 => $handles['err'],
				),
				$pipes,
				$directory,
				null,
				array(
					'bypass_shell'       => true,
					'create_new_console' => false,
					'suppress_errors'    => true,
				)
			);
			if ( ! is_resource( $process ) ) {
				throw new RuntimeException( 'Processamento privado indisponível.' );
			}
			fclose( $pipes[0] );
			$deadline = microtime( true ) + $seconds;
			do {
				$status = proc_get_status( $process );
				if ( microtime( true ) > $deadline || fstat( $handles['out'] )['size'] > 65536 || fstat( $handles['err'] )['size'] > 65536 ) {
					throw new RuntimeException( 'Processamento privado inconclusivo.' );
				}
				if ( $status['running'] ) {
					usleep( 20000 );
				}
			} while ( $status['running'] );
			$result = array( 'code' => $status['exitcode'] );
			foreach ( $handles as $key => $handle ) {
				rewind( $handle );
				$result[ $key ] = stream_get_contents( $handle, 65537 );
			}
			return $result;
		} finally {
			if ( is_resource( $process ) ) {
				if ( proc_get_status( $process )['running'] ) {
					proc_terminate( $process );
					$stop = microtime( true ) + 0.5;
					while ( proc_get_status( $process )['running'] && microtime( true ) < $stop ) {
						usleep( 10000 );
					}
					if ( proc_get_status( $process )['running'] ) {
						proc_terminate( $process, 9 );
					}
				}
				proc_close( $process );
			}
			foreach ( $handles as $handle ) {
				fclose( $handle );
			}
		}
	}
}
