<?php
/** Recibos imutáveis, privados e sequenciais, conservados após interrupção.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class MigrationReceipts {
	public function __construct( private readonly MaintenanceGate $gate, private readonly string $binding ) {
		if ( 1 !== preg_match( '/^[a-f0-9]{64}$/D', $binding ) ) {
			throw new RuntimeException( 'Destino inválido.' ); }
	}
	public function all(): array {
		$this->gate->assertExclusive();
		$files = glob( $this->gate->directory . '/receipt-*.json' );
		if ( false === $files ) {
			throw new RuntimeException( 'Recibos indisponíveis.' ); }
		sort( $files, SORT_STRING );
		$records = array();
		foreach ( $files as $file ) {
			$sequence = count( $records ) + 1;
			if ( basename( $file ) !== sprintf( 'receipt-%08d.json', $sequence ) || is_link( $file ) || filesize( $file ) > 4096 ) {
				throw new RuntimeException( 'Sequência de recibos inválida.' ); }
			$record = json_decode( file_get_contents( $file ), true, 16, JSON_THROW_ON_ERROR );
			if ( ! is_array( $record ) || 7 !== count( $record ) || ( $record['binding'] ?? null ) !== $this->binding || ( $record['sequence'] ?? null ) !== $sequence || ( $record['version'] ?? null ) !== 1 || ! is_int( $record['actor'] ?? null ) || $record['actor'] < 1 || ! is_string( $record['backup_ref'] ?? null ) || 1 !== preg_match( '/^[A-Za-z0-9._-]{1,120}$/D', $record['backup_ref'] ) || ! is_string( $record['time'] ?? null ) || 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}\+00:00$/D', $record['time'] ) || ! in_array( $record['phase'] ?? null, array( 'started', 'identity', 'authorization', 'complete', 'released' ), true ) ) {
				throw new RuntimeException( 'Recibo incompatível.' ); }
			$records[] = $record;
		}
		return $records;
	}
	public function append( string $phase, int $actor, string $backup_ref ): void {
		$records = $this->all();
		if ( $actor < 1 || ! in_array( $phase, array( 'started', 'identity', 'authorization', 'complete', 'released' ), true ) || 1 !== preg_match( '/^[A-Za-z0-9._-]{1,120}$/D', $backup_ref ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		$sequence = count( $records ) + 1;
		$json     = json_encode(
			array(
				'version'    => 1,
				'sequence'   => $sequence,
				'binding'    => $this->binding,
				'phase'      => $phase,
				'actor'      => $actor,
				'backup_ref' => $backup_ref,
				'time'       => gmdate( 'c' ),
			),
			JSON_THROW_ON_ERROR
		);
		$temp     = $this->gate->directory . '/pending-' . bin2hex( random_bytes( 12 ) );
		$stream   = fopen( $temp, 'x+b' );
		if ( false === $stream ) {
			throw new RuntimeException( 'Recibo não persistido.' ); }
		try {
			if ( fwrite( $stream, $json ) !== strlen( $json ) || ! fflush( $stream ) || ! fsync( $stream ) ) {
				throw new RuntimeException( 'Recibo não persistido.' ); }
		} finally {
			fclose( $stream ); }
		$target = $this->gate->directory . '/' . sprintf( 'receipt-%08d.json', $sequence );
		if ( file_exists( $target ) || ! rename( $temp, $target ) ) {
			throw new RuntimeException( 'Recibo não confirmado.' ); }
	}
}
