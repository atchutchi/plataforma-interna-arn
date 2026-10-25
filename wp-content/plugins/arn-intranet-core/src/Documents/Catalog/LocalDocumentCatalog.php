<?php
/**
 * Local-only runtime for the document catalog.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Documents\PrivateFiles\ImportBatch;
use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Arn\Intranet\Media\LocalMediaProcessing;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment

/** Exact gates before any business data is read or written. */
final class LocalDocumentCatalog {
	private readonly Closure $repository_factory;
	private readonly Closure $store_factory;
	private readonly Closure $gate;

	/** Dependencies are deferred until every local gate passes. */
	public function __construct( private readonly CurrentDocumentActor $current = new CurrentDocumentActor(), ?Closure $repository_factory = null, ?Closure $store_factory = null, ?Closure $gate = null ) {
		$this->repository_factory = $repository_factory ?? static function (): array {
			global $wpdb;
			$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$db->set_prefix( $wpdb->prefix );
			return array( new WpdbDocumentRepository( $db, new WpdbDocumentAuthority( $db, new CurrentDocumentActor() ) ), new WpdbFileRegistry( $db ) );
		};
		$this->store_factory      = $store_factory ?? static fn(): PrivateBlobStore => LocalPrivateFiles::store();
		$this->gate               = $gate ?? static fn(): bool => self::configured();
	}

	/** Fail closed before any query or blob operation. */
	private function requireConfigured(): void {
		if ( true !== ( $this->gate )() ) {
			throw new RuntimeException( 'Catálogo documental indisponível.' );
		}
	}

	/** Documents visible to the current fictitious user, filtered before counting. */
	public function visible( ?string $category = null ): ?array {
		if ( true !== ( $this->gate )() ) {
			return null;
		}
		$actor = $this->current->read();
		if ( null === $actor ) {
			return null;
		}
		try {
			[$snapshot, , $session] = $actor;
			[$repository]           = ( $this->repository_factory )();
			$visible                = array();
			foreach ( $repository->all() as $document ) {
				$number  = DocumentPolicy::canManage( $document, $snapshot, $session, time() ) ? count( $document->versions ) : $document->published_version;
				$version = null === $number ? null : $document->version( $number );
				if ( null !== $version && DocumentPolicy::canRead( $document, $number, $snapshot, $session, time() ) && ( null === $category || $version->metadata->category === $category ) ) {
					$visible[] = $document;
				}
			}
			return $visible;
		} catch ( Throwable ) {
			return null;
		}
	}

	/** Return a document only if its current view is authorised. */
	public function findVisible( int $id ): ?Document {
		if ( true !== ( $this->gate )() ) {
			return null;
		}
		$actor = $this->current->read();
		if ( null === $actor ) {
			return null;
		}
		try {
			[$snapshot, , $session] = $actor;
			[$repository]           = ( $this->repository_factory )();
			$document               = $repository->find( $id );
			if ( null === $document ) {
				return null;
			}
			$number = DocumentPolicy::canManage( $document, $snapshot, $session, time() ) ? count( $document->versions ) : $document->published_version;
			return null !== $number && DocumentPolicy::canRead( $document, $number, $snapshot, $session, time() ) ? $document : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	/** History is visible only to a manager of the persisted scope. */
	public function historyVisible( int $id ): ?array {
		if ( true !== ( $this->gate )() ) {
			return null;
		}
		$actor = $this->current->read();
		if ( null === $actor ) {
			return null;
		}
		try {
			[$snapshot, , $session] = $actor;
			[$repository]           = ( $this->repository_factory )();
			$document               = $repository->find( $id );
			return null !== $document && DocumentPolicy::canManage( $document, $snapshot, $session, time() ) ? $repository->history( $id ) : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	/** Import a server-selected upload and create a draft. */
	public function create( Scope $scope, Metadata $metadata, string $source, string $reason, ?\Arn\Intranet\Media\PreparedBatch $prepared = null ): Document {
		$this->requireConfigured();
		$actor = $this->current->read();
		if ( null === $actor || ! DocumentPolicy::canManageScope( $scope, $actor[0], $actor[2], time() ) ) {
			throw new RuntimeException( 'Operação documental não autorizada.' );
		}
		return ( new ImportBatch( ( $this->store_factory )(), $prepared ?? LocalMediaProcessing::pipeline( $actor[1] ) ) )->run(
			function ( ImportBatch $batch ) use ( $source, $scope, $metadata, $actor, $reason ): Document {
				$blob         = $batch->importFile( $source );
				[$repository] = ( $this->repository_factory )();
				return $repository->create( $scope, new Revision( 1, $metadata, $blob, $actor[1], time() ), $reason );
			},
			fn(): WpdbFileRegistry => ( $this->repository_factory )()[1]
		);
	}

	/** Append a version, duplicating the verified original if no upload exists. */
	public function revise( int $id, Metadata $metadata, ?string $source, int $expected, string $reason, ?\Arn\Intranet\Media\PreparedBatch $prepared = null ): Document {
		$this->requireConfigured();
		$actor = $this->current->read();
		if ( null === $actor ) {
			throw new RuntimeException( 'Operação documental não autorizada.' );
		}
		[$repository] = ( $this->repository_factory )();
		$before       = $repository->find( $id );
		if ( null === $before || ! DocumentPolicy::canManage( $before, $actor[0], $actor[2], time() ) ) {
			throw new RuntimeException( 'Operação documental não autorizada.' );
		}
		return ( new ImportBatch( ( $this->store_factory )(), $prepared ?? LocalMediaProcessing::pipeline( $actor[1] ) ) )->run(
			function ( ImportBatch $batch ) use ( $source, $before, $repository, $id, $metadata, $actor, $expected, $reason ): Document {
				$blob = null === $source ? $batch->duplicate( $before->version( count( $before->versions ) )->blob ) : $batch->importFile( $source );
				return $repository->revise( $id, new Revision( count( $before->versions ) + 1, $metadata, $blob, $actor[1], time() ), $expected, $reason, null === $source ? $before->version( count( $before->versions ) )->blob->id : null );
			},
			fn(): WpdbFileRegistry => ( $this->repository_factory )()[1]
		);
	}

	/** Repository revalidates publication authority and audience under locks. */
	public function publish( int $id, int $version, int $expected, string $reason ): Document {
		$this->requireConfigured();
		[$repository] = ( $this->repository_factory )();
		return $repository->publish( $id, $version, $expected, $reason );
	}

	/** Repository revalidates archive authority under locks. */
	public function archive( int $id, int $expected, string $reason ): Document {
		$this->requireConfigured();
		[$repository] = ( $this->repository_factory )();
		return $repository->archive( $id, $expected, $reason );
	}

	/** Resolve and verify a private original by opaque ID after access checks. */
	public function file( string $blob_id, string $variant = 'original' ): ?array {
		if ( ! in_array( $variant, array( 'original', 'thumbnail', 'preview' ), true ) || true !== ( $this->gate )() || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $blob_id ) ) {
			return null;
		}
		$actor = $this->current->read();
		if ( null === $actor ) {
			return null;
		}
		try {
			[$repository, $registry] = ( $this->repository_factory )();
			$record                  = $registry->find( $blob_id );
			if ( null === $record || 'document' !== $record->object_type || 'original' !== $record->variant ) {
				return null;
			}
			$document = $repository->find( $record->object_id );
			$version  = $document?->version( $record->version );
			if ( null === $version || $version->blob->id !== $blob_id || ! DocumentPolicy::canRead( $document, $record->version, $actor[0], $actor[2], time() ) ) {
				return null;
			}
			$tuple = $registry->findForObject( 'document', $record->object_id, $record->version, 'original' );
			if ( $tuple?->blob->id !== $blob_id || $record->blob->sha256 !== $version->blob->sha256 || $record->blob->size !== $version->blob->size || $record->blob->mime !== $version->blob->mime ) {
				return null;
			}
			$selected = \Arn\Intranet\Documents\PrivateFiles\VariantSelection::select( $registry, $record, $variant );
			if ( null === $selected ) {
				return null;
			}
			return array(
				'handle'   => ( $this->store_factory )()->open( $selected ),
				'blob'     => $selected,
				'document' => $document,
				'version'  => $version,
			);
		} catch ( Throwable ) {
			return null;
		}
	}
	public static function configured(): bool {
		return 'local' === wp_get_environment_type()
			&& defined( 'ARN_ALLOW_LOCAL_DOCUMENT_CATALOG' ) && true === ARN_ALLOW_LOCAL_DOCUMENT_CATALOG
			&& defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) && true === ARN_ALLOW_LOCAL_PRIVATE_FILES
			&& defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) && true === ARN_ALLOW_FICTIONAL_LOCAL_ACCESS
			&& defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) && true === ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE;
	}
}
