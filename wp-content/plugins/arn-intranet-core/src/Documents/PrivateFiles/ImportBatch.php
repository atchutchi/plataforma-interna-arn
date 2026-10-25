<?php
/** Lifecycle of private copies created by one mutation attempt.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Closure;
use RuntimeException;
use Throwable;
use Arn\Intranet\Media\MediaPipeline;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionCommentThrowTag

/** Never remove existing files or infer SQL rollback from a transport error. */
final class ImportBatch {
	private array $created = array();
	private bool $started  = false;
	private bool $running  = false;

	public function __construct( public readonly PrivateBlobStore $store, private readonly MediaPipeline|\Arn\Intranet\Media\PreparedBatch|null $pipeline = null ) {}

	/** Register ownership before any subsequent content validation can fail. */
	public function importFile( string $source ): BlobDescriptor {
		$this->assertRunning();
		if ( null !== $this->pipeline ) {
			return $this->processed( $source );
		}
		$blob            = $this->store->importFile( $source );
		$this->created[] = $blob;
		return $blob;
	}

	public function duplicate( BlobDescriptor $source ): BlobDescriptor {
		$this->assertRunning();
		if ( null !== $this->pipeline ) {
			$handle = $this->store->open( $source );
			try {
				$blob = $this->processed( $handle );
				if ( $blob->sha256 !== $source->sha256 || $blob->size !== $source->size || $blob->mime !== $source->mime ) {
					throw new RuntimeException( 'Cópia anterior alterada.' );
				}
				return $blob;
			} finally {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			}
		}
		$blob            = $this->store->duplicate( $source );
		$this->created[] = $blob;
		return $blob;
	}

	private function processed( mixed $source ): BlobDescriptor {
		$prepared = $this->pipeline->prepare( $source );
		$blobs    = array();
		foreach ( $prepared['paths'] as $variant => $path ) {
			$blob            = $this->store->importFile( $path );
			$this->created[] = $blob;
			if ( $blob->sha256 !== $prepared['hashes'][ $variant ] || ( 'original' !== $variant && 'image/png' !== $blob->mime ) ) {
				throw new RuntimeException( 'Cópia processada alterada.' );
			}
			$blobs[ $variant ] = $blob;
		}
		$this->pipeline->prepared( $prepared, array_map( static fn( BlobDescriptor $blob ): string => $blob->id, $blobs ) );
		$original = $blobs['original'];
		unset( $blobs['original'] );
		// Ownership remains with the exact descriptors tracked above for safe rollback.
		return new BlobDescriptor( $original->id, $original->sha256, $original->size, $original->mime, $blobs );
	}

	/** Fresh registry connection resolves even a commit with a lost response. */
	public function run( Closure $operation, Closure $registry_factory ): mixed {
		if ( $this->started ) {
			throw new RuntimeException( 'Tentativa de importação indisponível.' );
		}
		$this->started = true;
		$this->running = true;
		try {
			$result = $operation( $this );
			foreach ( $this->created as $blob ) {
				$this->store->releaseCreated( $blob );
			}
			return $result;
		} catch ( Throwable $original ) {
			$this->cleanup( $registry_factory );
			throw $original;
		} finally {
			$this->running = false;
		}
	}

	private function cleanup( Closure $registry_factory ): void {
		foreach ( $this->created as $blob ) {
			try {
				$registry = $registry_factory();
				if ( ! $registry instanceof WpdbFileRegistry ) {
					continue;
				}
				$registry->discardIfUnregistered( $blob->id, fn() => $this->store->discardCreated( $blob ) );
				$this->store->releaseCreated( $blob );
			} catch ( Throwable ) {
				// Uncertain outcome: retain this copy and still try the remaining copies.
				continue;
			}
		}
	}

	private function assertRunning(): void {
		if ( ! $this->running ) {
			throw new RuntimeException( 'Tentativa de importação indisponível.' );
		}
	}
}
