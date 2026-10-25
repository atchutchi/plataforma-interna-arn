<?php
/** Durable private queue. No delivery, publication or deletion authority.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, PrivateBlobStore};
use Closure;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class MediaQueue {
	private readonly string $root;
	public function __construct( string $root, private readonly array $excluded, private readonly Closure $authority, private readonly Closure $clock, private readonly bool $owner_only = false ) {
		new PrivateBlobStore( $root, $excluded, array( 'application/pdf' ), 1048576 );
		$this->root = str_replace( '\\', '/', realpath( $root ) );
	}

	private function authorize( int $actor ): void {
		if ( $actor < 1 || true !== ( $this->authority )( $actor ) ) {
			throw new RuntimeException( 'Fila indisponível para esta conta.' ); }
		new PrivateBlobStore( $this->root, $this->excluded, array( 'application/pdf' ), 1048576 );
	}

	private function directory( string $id ): string {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
			throw new RuntimeException( 'Lote inválido.' ); }
		$path = $this->root . '/' . $id;
		new PrivateBlobStore( $path, $this->excluded, array( 'application/pdf' ), 1048576 );
		return $path;
	}

	private function lock( string $path ) {
		if ( is_link( $path ) ) {
			throw new RuntimeException( 'Fila ocupada ou indisponível.' ); }
		$handle = @fopen( $path, 'c+b' );
		$stat   = is_resource( $handle ) ? fstat( $handle ) : false;
		if ( false === $stat || 0100000 !== ( $stat['mode'] & 0170000 ) || 1 !== $stat['nlink'] || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			if ( is_resource( $handle ) ) {
				fclose( $handle ); }
			throw new RuntimeException( 'Fila ocupada ou indisponível.' );
		}
		return $handle;
	}

	public function enqueue( array $sources, int $actor, ?string $request_id = null, ?array $context = null ): string {
		$this->authorize( $actor );
		if ( null !== $context ) {
			ConsumerIntent::validate( $context, count( $sources ) ); }
		if ( ! array_is_list( $sources ) || count( $sources ) < 1 || count( $sources ) > 8 ) {
			throw new RuntimeException( 'Escolhe entre um e oito ficheiros.' ); }
		$lock = $this->lock( $this->root . '/capacity.lock' );
		try {
			$entries = scandir( $this->root );
			if ( false === $entries || count( array_filter( $entries, static fn( string $entry ): bool => 1 === preg_match( '/^[a-f0-9]{32}$/D', $entry ) ) ) >= 50 || disk_free_space( $this->root ) < 67108864 ) {
				throw new RuntimeException( 'Capacidade da fila indisponível.' ); }
			$id = $request_id ?? bin2hex( random_bytes( 16 ) );
			if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) || file_exists( $this->root . '/' . $id ) || is_link( $this->root . '/' . $id ) ) {
				throw new RuntimeException( 'Pedido repetido ou inválido. Consulta os lotes existentes.' ); }
			if ( ! mkdir( $this->root . '/' . $id, 0700 ) ) {
				throw new RuntimeException( 'Fila indisponível.' ); }
			$path  = $this->directory( $id );
			$store = new PrivateBlobStore( $path, $this->excluded, array( 'application/pdf', 'image/png', 'image/jpeg' ), 1048576 );
			$items = array();
			foreach ( $sources as $source ) {
				if ( ! is_string( $source ) ) {
					throw new RuntimeException( 'Original inválido.' ); }
				$blob   = $store->importFile( $source );
				$handle = fopen( $path . '/' . $blob->id, 'r+b' );
				try {
					if ( ! fsync( $handle ) ) {
						throw new RuntimeException( 'Cópia incompleta.' ); }
				} finally {
					fclose( $handle );
				}
				$items[] = array(
					'id'     => $blob->id,
					'sha256' => $blob->sha256,
					'size'   => $blob->size,
					'mime'   => $blob->mime,
				);
			}
			$this->append(
				$id,
				0,
				$this->event(
					null === $context ? 'queued' : 'submitted',
					$actor,
					-1,
					'',
					null === $context ? $items : array(
						'items'   => $items,
						'context' => $context,
					)
				)
			);
			return $id;
		} finally {
			fclose( $lock );
		}
	}

	private function event( string $type, int $actor, int $item = -1, string $reason = '', array $data = array() ): array {
		return array(
			'type'   => $type,
			'actor'  => $actor,
			'at'     => ( $this->clock )(),
			'item'   => $item,
			'reason' => $reason,
			'data'   => $data,
		);
	}

	/** Caller holds the batch lock. Partial records fail closed, never silently skipped. */
	private function readJournal( string $path ): array {
		$state    = null;
		$previous = str_repeat( '0', 64 );
		$heads    = glob( dirname( $path ) . '/head-*' );
		if ( false === $heads || count( $heads ) > 200 ) {
			throw new RuntimeException( 'Marcos de auditoria inválidos.' ); }
		if ( ! file_exists( $path ) ) {
			if ( array() !== $heads ) {
				throw new RuntimeException( 'Diário incompleto.' ); }
			return array( $state, $previous ); }
		if ( is_link( $path ) || ! is_file( $path ) || filesize( $path ) > 1048576 ) {
			throw new RuntimeException( 'Diário indisponível.' ); }
		$handle = fopen( $path, 'rb' );
		try {
			$stat = fstat( $handle );
			if ( 1 !== $stat['nlink'] || 0100000 !== ( $stat['mode'] & 0170000 ) ) {
				throw new RuntimeException( 'Diário inválido.' ); }
			while ( ! feof( $handle ) ) {
				$line = fgets( $handle, 262145 );
				if ( false === $line && feof( $handle ) ) {
					break; }
				if ( ! is_string( $line ) || ! str_ends_with( $line, "\n" ) ) {
					throw new RuntimeException( 'Diário incompleto.' ); }
				$row = json_decode( $line, true, 32, JSON_THROW_ON_ERROR );
				if ( ! is_array( $row ) || array_keys( $row ) !== array( 'sequence', 'previous', 'event', 'hash' ) || ( $state['revision'] ?? 0 ) + 1 !== $row['sequence'] || $previous !== $row['previous'] || ! is_array( $row['event'] ) || ! is_string( $row['hash'] ) || ! hash_equals( hash( 'sha256', json_encode( array( $row['sequence'], $previous, $row['event'] ), JSON_THROW_ON_ERROR ) ), $row['hash'] ) ) {
					throw new RuntimeException( 'Diário inválido.' ); }
				$state     = QueueState::apply( $state, $row['event'] );
				$head      = dirname( $path ) . '/head-' . sprintf( '%06d', $row['sequence'] );
				$head_stat = @lstat( $head );
				if ( false === $head_stat || is_link( $head ) || 0100000 !== ( $head_stat['mode'] & 0170000 ) || 1 !== $head_stat['nlink'] || 64 !== $head_stat['size'] || file_get_contents( $head ) !== $row['hash'] ) {
					throw new RuntimeException( 'Marco de auditoria inválido.' ); }
				$previous = $row['hash'];
			}
		} finally {
			fclose( $handle );
		}
		if ( count( $heads ) !== ( $state['revision'] ?? 0 ) ) {
			throw new RuntimeException( 'Diário truncado ou resultado indeterminado.' ); }
		return array( $state, $previous );
	}

	private function append( string $id, int $revision, array $event ): array {
		$this->authorize( $event['actor'] );
		$path = $this->directory( $id );
		$lock = $this->lock( $path . '/journal.lock' );
		try {
			[$state, $previous] = $this->readJournal( $path . '/journal.jsonl' );
			if ( null !== $state && $this->owner_only && $state['owner'] !== $event['actor'] ) {
				throw new RuntimeException( 'Envio indisponível.' ); }
			if ( ( $state['revision'] ?? 0 ) !== $revision ) {
				throw new RuntimeException( 'O lote foi alterado. Actualiza a página.' ); }
			$next = QueueState::apply( $state, $event );
			$row  = array(
				'sequence' => $next['revision'],
				'previous' => $previous,
				'event'    => $event,
				'hash'     => hash( 'sha256', json_encode( array( $next['revision'], $previous, $event ), JSON_THROW_ON_ERROR ) ),
			);
			$line = json_encode( $row, JSON_THROW_ON_ERROR ) . "\n";
			$this->authorize( $event['actor'] );
			// Intent marker precedes the append. A crash in either write fails closed.
			$marker = fopen( $path . '/head-' . sprintf( '%06d', $next['revision'] ), 'xb' );
			if ( false === $marker ) {
				throw new RuntimeException( 'Marco de auditoria indisponível.' ); }
			try {
				if ( 64 !== fwrite( $marker, $row['hash'] ) || ! fflush( $marker ) || ! fsync( $marker ) ) {
					throw new RuntimeException( 'Marco de auditoria incompleto.' ); }
			} finally {
				fclose( $marker );
			}
			$handle = fopen( $path . '/journal.jsonl', 'ab' );
			try {
				if ( strlen( $line ) !== fwrite( $handle, $line ) || ! fflush( $handle ) || ! fsync( $handle ) ) {
					throw new RuntimeException( 'Resultado indeterminado. Preserva a quarentena.' ); }
			} finally {
				fclose( $handle );
			}
			return $next;
		} finally {
			fclose( $lock );
		}
	}

	public function read( string $id, int $actor ): array {
		$this->authorize( $actor );
		$path = $this->directory( $id );
		$lock = $this->lock( $path . '/journal.lock' );
		try {
			[$state] = $this->readJournal( $path . '/journal.jsonl' );
			if ( null === $state ) {
				throw new RuntimeException( 'Lote incompleto. Preserva as cópias.' ); }
			$this->authorize( $actor );
			if ( $this->owner_only && $state['owner'] !== $actor ) {
				throw new RuntimeException( 'Envio indisponível.' ); }
			return $state;
		} finally {
			fclose( $lock );
		}
	}

	public function listing( int $actor ): array {
		$this->authorize( $actor );
		$entries = scandir( $this->root );
		if ( false === $entries || count( $entries ) > 54 ) {
			throw new RuntimeException( 'Capacidade da fila excedida.' ); }
		$rows = array();
		foreach ( $entries as $id ) {
			if ( 1 === preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
				try {
					$rows[ $id ] = $this->read( $id, $actor );
				} catch ( Throwable ) {
					if ( ! $this->owner_only ) {
						$rows[ $id ] = array( 'unavailable' => true ); }
				}
			}
		}
		$this->authorize( $actor );
		return $rows;
	}

	public function manage( string $id, int $revision, int $item, string $action, string $reason, int $actor ): array {
		$this->authorize( $actor );
		if ( ! in_array( $action, array( 'retry', 'hold', 'cancel', 'recover', 'archive' ), true ) ) {
			throw new RuntimeException( 'Operação inválida.' ); }
		// Recovery cannot steal a live worker. The same lock also serializes manual decisions.
		$lock = $this->lock( $this->root . '/worker.lock' );
		try {
			return $this->append( $id, $revision, $this->event( $action, $actor, $item, $reason ) );
		} finally {
			fclose( $lock );
		}
	}


	/** The durable intent precedes business writes. Unknown outcomes are never replayed. */
	public function complete( string $id, int $revision, int $actor, Closure $preflight, Closure $apply ): int {
		$this->authorize( $actor );
		$lock = $this->lock( $this->root . '/worker.lock' );
		try {
			$state = $this->read( $id, $actor );
			if ( $state['owner'] !== $actor || null === $state['context'] ) {
				throw new RuntimeException( 'Envio indisponível.' ); }
			$preflight( $state );
			if ( 'applied' === $state['consumption'] ) {
				return $state['receipt']; }
			$state   = $this->append( $id, $revision, $this->event( 'applying', $actor ) );
			$receipt = $apply( $state, $this->directory( $id ) );
			$this->append( $id, $state['revision'], $this->event( 'applied', $actor, -1, '', array( 'receipt' => $receipt ) ) );
			return $receipt;
		} finally {
			fclose( $lock ); }
	}

	public function work( int $actor, int $limit, Closure $pipeline ): int {
		$this->authorize( $actor );
		if ( $limit < 1 || $limit > 3 ) {
			throw new RuntimeException( 'O worker aceita entre uma e três entradas.' ); }
		$lock      = $this->lock( $this->root . '/worker.lock' );
		$processed = 0;
		try {
			$rows = $this->listing( $actor );
			uasort( $rows, static fn( array $a, array $b ): int => ( $a['created_at'] ?? PHP_INT_MAX ) <=> ( $b['created_at'] ?? PHP_INT_MAX ) );
			foreach ( $rows as $id => $state ) {
				if ( isset( $state['unavailable'] ) || $state['archived'] ) {
					continue; }
				foreach ( $state['items'] as $i => $item ) {
					if ( 'queued' !== $item['status'] ) {
						continue; }
					$state  = $this->append( $id, $state['revision'], $this->event( 'claim', $actor, $i ) );
					$handle = null;
					try {
						$store  = new PrivateBlobStore( $this->directory( $id ), $this->excluded, array( 'application/pdf', 'image/png', 'image/jpeg' ), 1048576 );
						$handle = $store->open( new BlobDescriptor( ...$item['blob'] ) );
						$result = ( $pipeline )( $actor )->prepare( $handle );
						$data   = array(
							'attempt' => basename( str_replace( '\\', '/', $result['job'] ) ),
							'hashes'  => $result['hashes'],
						);
						$event  = $this->event( 'processed', $actor, $i, '', $data );
					} catch ( Throwable ) {
						$event = $this->event( 'failed', $actor, $i );
					} finally {
						if ( is_resource( $handle ) ) {
							fclose( $handle ); }
					}
					// No catch around commit: an indeterminate append must never be overwritten.
					$state = $this->append( $id, $state['revision'], $event );
					if ( ++$processed >= $limit ) {
						return $processed; }
				}
			}
			return $processed;
		} finally {
			fclose( $lock );
		}
	}
}
