<?php
/** Bounded CLI supervision with durable private audit and cooperative stop.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Closure;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class QueueSupervisor {
	private readonly string $root;
	public function __construct( string $root, private readonly array $excluded, private readonly Closure $authority, private readonly Closure $clock, private readonly Closure $sleep ) {
		new PrivateBlobStore( $root, $excluded, array( 'application/pdf' ), 1 );
		$this->root = str_replace( '\\', '/', realpath( $root ) );
	}
	private function authorize( int $actor ): void {
		if ( $actor < 1 || true !== ( $this->authority )( $actor ) ) {
			throw new RuntimeException( 'Supervisão indisponível.' ); }
		new PrivateBlobStore( $this->root, $this->excluded, array( 'application/pdf' ), 1 );
	}
	private function lock( string $name ) {
		$path = $this->root . '/' . $name . '.lock';
		if ( is_link( $path ) ) {
			throw new RuntimeException( 'Bloqueio inválido.' ); }
		$handle = @fopen( $path, 'c+b' );
		$stat   = is_resource( $handle ) ? fstat( $handle ) : false;
		if ( false === $stat || 0100000 !== ( $stat['mode'] & 0170000 ) || 1 !== $stat['nlink'] ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle ); }
			throw new RuntimeException( 'Supervisor ocupado ou indisponível.' );
		}
		$deadline = hrtime( true ) + ( 'journal' === $name ? 5000000000 : 0 );
		while ( ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			if ( hrtime( true ) >= $deadline ) {
				fclose( $handle );
				throw new RuntimeException( 'Supervisor ocupado ou indisponível.' );
			}
			usleep( 20000 );
		}
		return $handle;
	}
	private function bytes( string $path, int $max ): string {
		if ( is_link( $path ) ) {
			throw new RuntimeException( 'Auditoria inválida.' ); }
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new RuntimeException( 'Auditoria incompleta.' ); }
		try {
			$stat = fstat( $handle );
			if ( false === $stat || 0100000 !== ( $stat['mode'] & 0170000 ) || 1 !== $stat['nlink'] || $stat['size'] < 1 || $stat['size'] > $max ) {
				throw new RuntimeException( 'Auditoria inválida.' ); }
			$value = stream_get_contents( $handle, $max + 1 );
			if ( ! is_string( $value ) || strlen( $value ) !== $stat['size'] ) {
				throw new RuntimeException( 'Auditoria incompleta.' ); }
			return $value;
		} finally {
			fclose( $handle );
		}
	}
	/** Caller holds journal lock. A marker without its event blocks all further work. */
	private function read(): array {
		$files = scandir( $this->root );
		if ( false === $files || count( $files ) > 2004 ) {
			throw new RuntimeException( 'Capacidade de auditoria excedida.' ); }
		$heads  = array_values( array_filter( $files, static fn( string $f ): bool => str_starts_with( $f, 'head-' ) ) );
		$events = array_values( array_filter( $files, static fn( string $f ): bool => str_starts_with( $f, 'event-' ) ) );
		if ( count( $heads ) !== count( $events ) || count( $heads ) > 1000 || array_diff( $files, array_merge( array( '.', '..', 'run.lock', 'journal.lock' ), $heads, $events ) ) ) {
			throw new RuntimeException( 'Auditoria incompleta ou desconhecida.' ); }
		$state = null;
		$hash  = str_repeat( '0', 64 );
		foreach ( $events as $i => $file ) {
			$number = sprintf( '%06d', $i + 1 );
			if ( 'event-' . $number !== $file || 'head-' . $number !== $heads[ $i ] ) {
				throw new RuntimeException( 'Sequência de auditoria inválida.' ); }
			$bytes = $this->bytes( $this->root . '/' . $file, 8192 );
			$row   = json_decode( $bytes, true, 16, JSON_THROW_ON_ERROR );
			if ( ! is_array( $row ) || array_keys( $row ) !== array( 'sequence', 'previous', 'event' ) || $row['sequence'] !== $i + 1 || $row['previous'] !== $hash || ! is_array( $row['event'] ) || ! hash_equals( hash( 'sha256', $bytes ), $this->bytes( $this->root . '/head-' . $number, 64 ) ) ) {
				throw new RuntimeException( 'Cadeia de auditoria inválida.' ); }
			$state = SupervisorState::apply( $state, $row['event'] );
			$hash  = hash( 'sha256', $bytes );
		}
		return array( $state, $hash );
	}
	private function write( string $path, string $bytes ): void {
		$handle = @fopen( $path, 'xb' );
		if ( false === $handle ) {
			throw new RuntimeException( 'Auditoria não confirmada.' ); }
		try {
			if ( strlen( $bytes ) !== fwrite( $handle, $bytes ) || ! fflush( $handle ) || ! fsync( $handle ) ) {
				throw new RuntimeException( 'Auditoria não confirmada.' ); }
		} finally {
			fclose( $handle );
		}
	}
	private function append( int $actor, string $type, array $data = array(), ?int $revision = null ): array {
		$this->authorize( $actor );
		$lock = $this->lock( 'journal' );
		try {
			[$state, $hash] = $this->read();
			if ( 'advance' === $type ) {
				if ( null === $state || 'waiting' !== $state['status'] ) {
					throw new RuntimeException( 'Estado do supervisor inválido.' ); }
				if ( $state['stop'] || $state['cycle'] === $state['cycles'] ) {
					$type = 'finish';
					$data = array( 'outcome' => $state['stop'] ? 'stopped' : 'finished' );
				} elseif ( ( $this->clock )() < $state['next_at'] ) {
					$this->authorize( $actor );
					return $state;
				} else {
					$type = 'begin';
				}
			}
			$current = $state['revision'] ?? 0;
			if ( $current >= 1000 || ( null !== $revision && $current !== $revision ) || ( 'start' === $type && $current > 974 ) ) {
				throw new RuntimeException( 'Revisão ou capacidade de auditoria indisponível.' ); }
			$event = array(
				'type'  => $type,
				'actor' => $actor,
				'at'    => ( $this->clock )(),
				'data'  => $data,
			);
			$next  = SupervisorState::apply( $state, $event );
			$bytes = json_encode(
				array(
					'sequence' => $current + 1,
					'previous' => $hash,
					'event'    => $event,
				),
				JSON_THROW_ON_ERROR
			);
			$this->authorize( $actor );
			$number = sprintf( '%06d', $current + 1 );
			$this->write( $this->root . '/head-' . $number, hash( 'sha256', $bytes ) );
			$this->write( $this->root . '/event-' . $number, $bytes );
			return $next;
		} finally {
			fclose( $lock );
		}
	}
	public function status( int $actor ): ?array {
		$this->authorize( $actor );
		$lock = $this->lock( 'journal' );
		try {
			[$state] = $this->read();
			$this->authorize( $actor );
			return $state;
		} finally {
			fclose( $lock );
		}
	}
	public function stop( int $actor, int $revision, string $reason ): array {
		return $this->append( $actor, 'stop', array( 'reason' => $reason ), $revision );
	}
	/** Acknowledgement never retries, recovers or deletes any queue entry. */
	public function acknowledge( int $actor, int $revision, string $reason ): array {
		$this->authorize( $actor );
		$lock = $this->lock( 'run' );
		try {
			$state = $this->status( $actor );
			if ( null === $state || $state['revision'] !== $revision ) {
				throw new RuntimeException( 'Execução alterada.' ); }
			return $this->append( $actor, 'ack', array( 'reason' => $reason ), $revision );
		} finally {
			fclose( $lock );
		}
	}
	public function run( int $actor, int $cycles, int $interval, int $limit, Closure $work ): array {
		$this->authorize( $actor );
		$lock = $this->lock( 'run' );
		try {
			$state  = $this->append(
				$actor,
				'start',
				array(
					'run'      => bin2hex( random_bytes( 16 ) ),
					'cycles'   => $cycles,
					'interval' => $interval,
					'limit'    => $limit,
				)
			);
			$waited = 0;
			try {
				while ( true ) {
					$state = $this->append( $actor, 'advance' );
					if ( in_array( $state['status'], array( 'finished', 'stopped' ), true ) ) {
						return $state; }
					if ( 'waiting' === $state['status'] ) {
						if ( ++$waited > $interval ) {
							throw new RuntimeException( 'Relógio indisponível.' ); }
						( $this->sleep )( 1 );
						continue;
					}
					$count = $work( $actor, $limit );
					$this->append( $actor, 'complete', array( 'count' => $count ) );
					$waited = 0;
				}
			} catch ( Throwable $error ) {
				// If authority or audit is lost this append fails too; leave the execution unresolved.
				$this->append( $actor, 'finish', array( 'outcome' => 'failed' ) );
				throw $error;
			}
		} finally {
			fclose( $lock );
		}
	}
}
