<?php
/** Private quarantine and processing before consumer transactions.
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
/** Quarantine is not a delivery store. Nothing here grants access or deletes a source. */
final class MediaPipeline {
	private int $count = 0;
	private int $bytes = 0;
	private readonly string $root;
	private readonly float $deadline;

	public function __construct( string $root, private readonly array $excluded, private readonly Closure $scanner, private readonly Closure $images, private readonly int $actor ) {
		new PrivateBlobStore( $root, $excluded, array( 'application/pdf' ), 1048576 );
		$this->root     = str_replace( '\\', '/', realpath( $root ) );
		$this->deadline = microtime( true ) + 120;
		if ( $actor < 1 ) {
			throw new RuntimeException( 'Processamento privado indisponível.' );
		}
	}

	/** Source is a caller-owned verified handle or a regular local upload. */
	public function prepare( mixed $source ): array {
		if ( ++$this->count > 8 || microtime( true ) >= $this->deadline ) {
			throw new RuntimeException( 'Limite de ficheiros por envio ultrapassado.' );
		}
		new PrivateBlobStore( $this->root, $this->excluded, array( 'application/pdf' ), 1048576 );
		if ( is_link( $this->root . '/capacity.lock' ) ) {
			throw new RuntimeException( 'Quarentena indisponível.' );
		}
		$lock = @fopen( $this->root . '/capacity.lock', 'c+b' );
		if ( false === $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( is_resource( $lock ) ) {
				fclose( $lock );
			}
			throw new RuntimeException( 'Quarentena indisponível.' );
		}
		try {
			$stat = fstat( $lock );
			if ( false === $stat || 0100000 !== ( $stat['mode'] & 0170000 ) || 1 !== $stat['nlink'] ) {
				throw new RuntimeException( 'Quarentena indisponível.' );
			}
			$entries = scandir( $this->root );
			if ( false === $entries || count( $entries ) >= 103 || disk_free_space( $this->root ) < 67108864 ) {
				throw new RuntimeException( 'Capacidade da quarentena indisponível.' );
			}
			$id  = bin2hex( random_bytes( 16 ) );
			$job = $this->root . '/' . $id;
			if ( ! mkdir( $job, 0700 ) ) {
				throw new RuntimeException( 'Quarentena indisponível.' );
			}
		} catch ( Throwable $error ) {
			flock( $lock, LOCK_UN );
			fclose( $lock );
			throw $error;
		}
		try {
			$this->event( $job, 'received', array() );
			$path = $job . '/source';
			$this->copy( $source, $path );
			$hash = hash_file( 'sha256', $path );
			$this->event(
				$job,
				'quarantined',
				array(
					'sha256' => $hash,
					'size'   => filesize( $path ),
				)
			);
			$hashes = array( 'original' => $this->scan( $path, $job ) );
			$mime   = ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $path );
			if ( ! in_array( $mime, array( 'application/pdf', 'image/png', 'image/jpeg' ), true ) ) {
				throw new RuntimeException( 'Tipo de ficheiro recusado.' );
			}
			$paths = array( 'original' => $path );
			if ( 'application/pdf' !== $mime ) {
				( $this->images )( $path, $job, $this->deadline );
				foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
					$derived = $job . '/' . $variant . '.png';
					if ( ! is_file( $derived ) || is_link( $derived ) || filesize( $derived ) < 1 || filesize( $derived ) > 1048576 ) {
						throw new RuntimeException( 'Derivado privado indisponível.' );
					}
					$hashes[ $variant ] = $this->scan( $derived, $job );
					$paths[ $variant ]  = $derived;
				}
			}
			if ( ! hash_equals( $hash, hash_file( 'sha256', $path ) ) ) {
				throw new RuntimeException( 'Cópia de quarentena alterada.' );
			}
			foreach ( $paths as $variant => $file ) {
				if ( ! hash_equals( $hashes[ $variant ], hash_file( 'sha256', $file ) ) || microtime( true ) >= $this->deadline ) {
					throw new RuntimeException( 'Processamento alterado ou expirado.' );
				}
			}
			$this->event( $job, 'processed', array( 'hashes' => $hashes ) );
			return array(
				'job'    => $job,
				'paths'  => $paths,
				'hashes' => $hashes,
			);
		} catch ( Throwable $error ) {
			$this->event( $job, 'not_admitted', array() );
			throw new RuntimeException( 'Ficheiro não admitido. A cópia fica em quarentena.', 0, $error );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	public function prepared( array $prepared, array $ids ): void {
		$this->event( $prepared['job'], 'prepared_for_registration', array( 'blobs' => $ids ) );
	}

	private function scan( string $path, string $job ): string {
		if ( microtime( true ) >= $this->deadline ) {
			throw new RuntimeException( 'Orçamento de processamento esgotado.' );
		}
		$before = hash_file( 'sha256', $path );
		$status = ( $this->scanner )( $path, $job, $this->deadline );
		$this->event( $job, in_array( $status, array( 'clean', 'rejected', 'inconclusive' ), true ) ? $status : 'inconclusive', array( 'sha256' => $before ) );
		if ( 'clean' !== $status || ! is_file( $path ) || is_link( $path ) || ! hash_equals( $before, hash_file( 'sha256', $path ) ) ) {
			throw new RuntimeException( 'Análise antimalware não aprovada.' );
		}
		return $before;
	}

	private function copy( mixed $source, string $path ): void {
		$owned = is_string( $source );
		if ( $owned && ( is_link( $source ) || ! is_file( $source ) ) ) {
			throw new RuntimeException( 'Fonte inválida.' );
		}
		$input  = $owned ? @fopen( $source, 'rb' ) : $source;
		$output = false;
		try {
			$stat = is_resource( $input ) ? fstat( $input ) : false;
			if ( false === $stat || 0100000 !== ( $stat['mode'] & 0170000 ) || 1 !== $stat['nlink'] || $stat['size'] < 1 || $stat['size'] > 1048576 || $this->bytes + $stat['size'] > 8388608 || ! rewind( $input ) ) {
				throw new RuntimeException( 'Fonte ou limite inválido.' );
			}
			$output = @fopen( $path, 'xb' );
			if ( false === $output ) {
				throw new RuntimeException( 'Quarentena indisponível.' );
			}
			$copied = stream_copy_to_stream( $input, $output, 1048577 );
			if ( $copied !== $stat['size'] || fstat( $input )['size'] !== $stat['size'] || ! fflush( $output ) || ! fsync( $output ) ) {
				throw new RuntimeException( 'Cópia incompleta.' );
			}
			$this->bytes += $copied;
		} finally {
			if ( $owned && is_resource( $input ) ) {
				fclose( $input );
			}
			if ( is_resource( $output ) ) {
				fclose( $output );
			}
		}
	}

	private function event( string $job, string $event, array $details ): void {
		if ( dirname( $job ) !== $this->root || str_replace( '\\', '/', ( false === realpath( $job ) ? '' : realpath( $job ) ) ) !== $job || is_link( $job ) || is_link( $job . '/events.jsonl' ) ) {
			throw new RuntimeException( 'Auditoria de quarentena indisponível.' );
		}
		$line   = json_encode(
			array(
				'event'    => $event,
				'actor_id' => $this->actor,
				'at'       => time(),
				'details'  => $details,
			),
			JSON_THROW_ON_ERROR
		) . "\n";
		$handle = @fopen( $job . '/events.jsonl', 'ab' );
		if ( false === $handle ) {
			throw new RuntimeException( 'Auditoria de quarentena indisponível.' );
		}
		try {
			if ( ! flock( $handle, LOCK_EX ) || strlen( $line ) !== fwrite( $handle, $line ) || ! fflush( $handle ) || ! fsync( $handle ) ) {
				throw new RuntimeException( 'Auditoria de quarentena indisponível.' );
			}
		} finally {
			fclose( $handle );
		}
	}
}
