<?php
/** Bounded, closed-format trace for the exclusive editorial subprocess.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests\Fixtures;

use InvalidArgumentException;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions

/** Only phases, monotonic time and numeric/boolean facts may reach test output. */
final class EditorialContentionTrace {

	private const PHASES = array( 'boot', 'connected', 'snapshot-ready', 'ready', 'released', 'decision-start', 'authority-enter', 'authority-locked', 'authority-refused', 'decision-authorized', 'decision-refused', 'before-rollback', 'confirmed', 'refused', 'failed', 'revocation-start', 'revocation-hook', 'revocation-wait-confirmed', 'revocation-probe', 'revocation-hook-failed', 'revocation-refused', 'revocation-confirmed' );

	public function __construct( private readonly string $path ) {
		if ( realpath( dirname( $path ) ) !== realpath( sys_get_temp_dir() ) || 1 !== preg_match( '/\Aarn_ed_[a-f0-9]{10}_[a-f0-9]{16}\.trace\z/D', basename( $path ) ) || is_link( $path ) ) {
			throw new InvalidArgumentException( 'Caminho de observação exclusivo inválido.' );
		}
	}

	public function record( string $phase, array $facts = array() ): void {
		$record = array(
			'phase' => $phase,
			'at_ns' => hrtime( true ),
			'facts' => $facts,
		);
		if ( ! self::valid( $record ) ) {
			throw new InvalidArgumentException( 'Observação fora do formato fechado.' );
		}
		if ( is_link( $this->path ) ) {
			throw new RuntimeException( 'Ficheiro de observação inválido.' );
		}
		$handle = fopen( $this->path, 'ab' );
		if ( false === $handle ) {
			throw new RuntimeException( 'Observação indisponível.' );
		}
		try {
			if ( ! flock( $handle, LOCK_EX ) ) {
				throw new RuntimeException( 'Observação sem lock.' );
			}
			$line = json_encode( $record, JSON_THROW_ON_ERROR ) . "\n";
			$stat = fstat( $handle );
			if ( false === $stat || $stat['size'] + strlen( $line ) > 65536 || fwrite( $handle, $line ) !== strlen( $line ) ) {
				throw new RuntimeException( 'Observação incompleta ou demasiado extensa.' );
			}
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}

	public function snapshot(): array {
		$result = array(
			'available' => false,
			'complete'  => false,
			'records'   => array(),
		);
		if ( ! is_file( $this->path ) || is_link( $this->path ) ) {
			return $result;
		}
		$handle = fopen( $this->path, 'rb' );
		if ( false === $handle ) {
			return $result;
		}
		try {
			if ( ! flock( $handle, LOCK_SH ) ) {
				return $result;
			}
			$bytes = stream_get_contents( $handle, 65537 );
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
		if ( ! is_string( $bytes ) || '' === $bytes || strlen( $bytes ) > 65536 ) {
			return $result;
		}
		$result['available'] = true;
		$result['complete']  = str_ends_with( $bytes, "\n" );
		foreach ( explode( "\n", rtrim( $bytes, "\n" ) ) as $line ) {
			$record = json_decode( $line, true, 8 );
			if ( ! is_array( $record ) || ! self::valid( $record ) ) {
				$result['complete'] = false;
				continue;
			}
			$result['records'][] = $record;
		}
		return $result;
	}

	private static function valid( array $record ): bool {
		if ( array_keys( $record ) !== array( 'phase', 'at_ns', 'facts' ) || ! in_array( $record['phase'], self::PHASES, true ) || ! is_int( $record['at_ns'] ) || $record['at_ns'] < 0 || ! is_array( $record['facts'] ) ) {
			return false;
		}
		foreach ( $record['facts'] as $name => $value ) {
			$valid = match ( $name ) {
				'errno' => is_int( $value ) && $value >= 0 && $value <= 65535,
				'connection' => is_int( $value ) && $value > 0,
				'sql_error' => is_bool( $value ),
				default => false,
			};
			if ( ! $valid ) {
				return false;
			}
		}
		return true;
	}
}
