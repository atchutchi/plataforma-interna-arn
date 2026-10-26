<?php
/** Append-only local diagnostic receipts, independent from business media.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Closure;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class HealthReports {
	private readonly string $root;
	public function __construct( string $root, private readonly array $excluded, private readonly Closure $authority, private readonly Closure $clock ) {
		new PrivateBlobStore( $root, $excluded, array( 'application/pdf' ), 1 );
		$this->root = str_replace( '\\', '/', realpath( $root ) );
	}
	private function authorize( int $actor ): void {
		if ( $actor < 1 || true !== ( $this->authority )( $actor ) ) {
			throw new RuntimeException( 'Diagnóstico indisponível.' ); }
		new PrivateBlobStore( $this->root, $this->excluded, array( 'application/pdf' ), 1 );
	}
	public static function validate( array $result ): void {
		if ( array_keys( $result ) !== array( 'scanner', 'image', 'pdf', 'storage' ) || ! in_array( $result['scanner'], array( 'available', 'unavailable' ), true ) || ! in_array( $result['image'], array( 'available', 'unavailable' ), true ) || ! in_array( $result['pdf'], array( 'available', 'unavailable', 'disabled' ), true ) || ! is_array( $result['storage'] ) || array_keys( $result['storage'] ) !== array( 'queue', 'quarantine', 'delivery' ) ) {
			throw new RuntimeException( 'Resultado inválido.' ); }
		foreach ( $result['storage'] as $row ) {
			if ( null === $row ) {
				continue; }
			if ( ! is_array( $row ) || array_keys( $row ) !== array( 'files', 'bytes', 'directories', 'batches', 'entries', 'free' ) ) {
				throw new RuntimeException( 'Inventário inválido.' ); }
			foreach ( $row as $value ) {
				if ( ! is_int( $value ) || $value < 0 ) {
					throw new RuntimeException( 'Métrica inválida.' ); }
			}
		}
	}
	private function bytes( string $path, int $limit ): string {
		clearstatcache( true, $path );
		$before = @lstat( $path );
		if ( false === $before || 0100000 !== ( $before['mode'] & 0170000 ) || 1 !== $before['nlink'] || $before['size'] < 1 || $before['size'] > $limit || is_link( $path ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new RuntimeException( 'Recibo incompleto.' ); }
		try {
			$s = fstat( $handle );
			if ( false === $s || 0100000 !== ( $s['mode'] & 0170000 ) || 1 !== $s['nlink'] || $s['size'] < 1 || $s['size'] > $limit || $s['ino'] !== $before['ino'] || $s['dev'] !== $before['dev'] || $s['size'] !== $before['size'] ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
			$bytes = stream_get_contents( $handle, $limit + 1 );
			if ( ! is_string( $bytes ) || strlen( $bytes ) !== $s['size'] ) {
				throw new RuntimeException( 'Recibo incompleto.' ); }
			return $bytes;
		} finally {
			fclose( $handle ); }
	}
	private function write( string $path, string $bytes ): void {
		$h = @fopen( $path, 'xb' );
		if ( false === $h ) {
			throw new RuntimeException( 'Recibo não confirmado.' ); }
		try {
			if ( fwrite( $h, $bytes ) !== strlen( $bytes ) || ! fflush( $h ) || ! fsync( $h ) ) {
				throw new RuntimeException( 'Recibo não confirmado.' ); }
		} finally {
			fclose( $h ); }
	}
	private function receipt( string $job, string $name, array $data ): void {
		$bytes = json_encode( $data, JSON_THROW_ON_ERROR );
		$this->write( $job . '/' . $name . '.sha256', hash( 'sha256', $bytes ) );
		$this->write( $job . '/' . $name . '.json', $bytes );
	}
	private function read( string $job, string $name ): array {
		$bytes = $this->bytes( $job . '/' . $name . '.json', 4096 );
		if ( ! hash_equals( hash( 'sha256', $bytes ), $this->bytes( $job . '/' . $name . '.sha256', 64 ) ) ) {
			throw new RuntimeException( 'Recibo divergente.' ); }
		$data = json_decode( $bytes, true, 8, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		return $data;
	}
	public function listing( int $actor ): array {
		$this->authorize( $actor );
		$entries = scandir( $this->root );
		if ( false === $entries || count( $entries ) > 53 ) {
			throw new RuntimeException( 'Capacidade de diagnósticos excedida.' ); }
		$rows = array();
		foreach ( array_diff( $entries, array( '.', '..', 'run.lock' ) ) as $id ) {
			if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
				throw new RuntimeException( 'Arquivo de diagnósticos inválido.' ); }
			try {
				$job = $this->root . '/' . $id;
				new PrivateBlobStore( $job, $this->excluded, array( 'application/pdf' ), 1 );
				$start = $this->read( $job, 'start' );
				if ( array_keys( $start ) !== array( 'id', 'actor', 'at' ) || $id !== $start['id'] || ! is_int( $start['actor'] ) || $start['actor'] < 1 || ! is_int( $start['at'] ) || $start['at'] < 1 ) {
					throw new RuntimeException( 'Início inválido.' ); }
				$row = array(
					'status' => 'incomplete',
					'start'  => $start,
				);
				if ( file_exists( $job . '/result.json' ) || is_link( $job . '/result.json' ) || file_exists( $job . '/result.sha256' ) || is_link( $job . '/result.sha256' ) ) {
					$end = $this->read( $job, 'result' );
					if ( array_keys( $end ) !== array( 'start_hash', 'at', 'result' ) || hash( 'sha256', json_encode( $start, JSON_THROW_ON_ERROR ) ) !== $end['start_hash'] || ! is_int( $end['at'] ) || $end['at'] < $start['at'] || ! is_array( $end['result'] ) ) {
						throw new RuntimeException( 'Conclusão inválida.' ); }
					self::validate( $end['result'] );
					$row = array(
						'status' => 'complete',
						'start'  => $start,
						'end'    => $end,
					);
				}
				$rows[ $id ] = $row;
			} catch ( \Throwable ) {
				$rows[ $id ] = array( 'status' => 'unavailable' ); }
		}
		$this->authorize( $actor );
		return $rows;
	}
	public function run( int $actor, string $id, Closure $probe ): array {
		$this->authorize( $actor );
		clearstatcache( true, $this->root . '/run.lock' );
		$before = @lstat( $this->root . '/run.lock' );
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) || is_link( $this->root . '/run.lock' ) || ( false !== $before && ( 0100000 !== ( $before['mode'] & 0170000 ) || 1 !== $before['nlink'] ) ) ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		$lock = @fopen( $this->root . '/run.lock', 'c+b' );
		$stat = is_resource( $lock ) ? fstat( $lock ) : false;
		if ( false === $stat || 0100000 !== ( $stat['mode'] & 0170000 ) || 1 !== $stat['nlink'] || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( is_resource( $lock ) ) {
				fclose( $lock ); }
			throw new RuntimeException( 'Diagnóstico ocupado.' ); }
		try {
			$rows = $this->listing( $actor );
			if ( isset( $rows[ $id ] ) && 'complete' === $rows[ $id ]['status'] && $rows[ $id ]['start']['actor'] === $actor ) {
				return $rows[ $id ]; }
			if ( isset( $rows[ $id ] ) || count( $rows ) >= 50 || in_array( 'unavailable', array_column( $rows, 'status' ), true ) ) {
				throw new RuntimeException( 'Recibos incompletos ou capacidade indisponível.' ); }
			$job   = $this->root . '/' . $id;
			$space = disk_free_space( $this->root );
			$at    = ( $this->clock )();
			if ( false === $space || $space < 67108864 || ! is_int( $at ) || $at < 1 ) {
				throw new RuntimeException( 'Capacidade ou relógio indisponível.' ); }
			if ( ! mkdir( $job, 0700 ) ) {
				throw new RuntimeException( 'Diagnóstico indisponível.' ); }
			$start = array(
				'id'    => $id,
				'actor' => $actor,
				'at'    => $at,
			);
			$this->receipt( $job, 'start', $start );
			$result = $probe( $job );
			self::validate( $result );
			$this->authorize( $actor );
			$at = ( $this->clock )();
			if ( ! is_int( $at ) || $at < $start['at'] ) {
				throw new RuntimeException( 'Relógio inválido.' ); }
			$this->receipt(
				$job,
				'result',
				array(
					'start_hash' => hash( 'sha256', json_encode( $start, JSON_THROW_ON_ERROR ) ),
					'at'         => $at,
					'result'     => $result,
				)
			);
			return $this->listing( $actor )[ $id ];
		} finally {
			fclose( $lock ); }
	}
}
