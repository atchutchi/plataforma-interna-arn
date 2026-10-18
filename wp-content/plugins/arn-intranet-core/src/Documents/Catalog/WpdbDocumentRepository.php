<?php
/**
 * Atomic persistence of the local documentary aggregate.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Documents\PrivateFiles\FileRecord;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment

/** Reads are internal primitives; a caller must apply DocumentPolicy. */
final class WpdbDocumentRepository {
	private const ERROR = 'Persistência documental indisponível.';
	private readonly CatalogSchema $schema;
	private readonly FileSchema $file_schema;
	private readonly AuthorizationSchema $authorization_schema;
	private readonly WpdbFileRegistry $registry;

	public function __construct( private readonly IdentityWpdb $database, private readonly DocumentAuthority $authority ) {
		$database->suppress_errors( true );
		$this->schema               = new CatalogSchema( $database );
		$this->file_schema          = new FileSchema( $database );
		$this->authorization_schema = new AuthorizationSchema( $database );
		$this->registry             = new WpdbFileRegistry( $database );
	}

	/** Create a private generic WordPress anchor and version one atomically. */
	public function create( Scope $scope, Revision $initial, string $reason ): Document {
		$this->reason( $reason );
		if ( 1 !== $initial->number ) {
			throw new RuntimeException( self::ERROR );
		}
		return $this->transaction(
			function () use ( $scope, $initial, $reason ): Document {
				$actor = $this->authority->lockAndAssert( $scope, $initial->metadata->recipient_ids );
				if ( $initial->actor_id !== $actor ) {
					throw new RuntimeException( self::ERROR );
				}
				$now = gmdate( 'Y-m-d H:i:s', $initial->created_at );
				$this->execute( $this->database->prepare( "INSERT INTO %i (post_author,post_date,post_date_gmt,post_content,post_title,post_status,post_type,post_name,post_modified,post_modified_gmt) VALUES (%d,%s,%s,'','Documento privado','private','arn_documento','',%s,%s)", $this->schema->table( 'posts' ), $actor, $now, $now, $now, $now ), 1 );
				$id = (int) $this->database->insert_id;
				if ( $id < 1 ) {
					throw new RuntimeException( self::ERROR );
				}
				$doc = Document::create( $id, $scope, $initial );
				$this->execute( $this->database->prepare( 'INSERT INTO %i (id,scope_type,scope_targets_json,revision,published_version,archived,created_at) VALUES (%d,%s,%s,1,NULL,0,%d)', $this->schema->table( 'documents' ), $id, $scope->type->name, $this->json( $scope->targets ), $initial->created_at ), 1 );
				$this->insertVersion( $id, $initial );
				$this->audit( $id, 1, 'created', $actor, $reason, $initial->created_at );
				return $doc;
			}
		);
	}

	/** Append a fully independent private original. */
	public function revise( int $id, Revision $next, int $expected, string $reason ): Document {
		$this->reason( $reason );
		$scope = $this->preflightScope( $id );
		return $this->transaction(
			function () use ( $id, $next, $expected, $reason, $scope ): Document {
				$actor = $this->authority->lockAndAssert( $scope, $next->metadata->recipient_ids );
				if ( $actor !== $next->actor_id ) {
					throw new RuntimeException( self::ERROR );
				}
				$before = $this->lockedDocument( $id, $scope );
				$after  = $before->revise( $next, $expected );
				$this->insertVersion( $id, $next );
				$this->execute( $this->database->prepare( 'UPDATE %i SET revision=%d WHERE id=%d AND revision=%d', $this->schema->table( 'documents' ), $after->revision, $id, $expected ), 1 );
				$this->audit( $id, $after->revision, 'revised', $actor, $reason, $next->created_at );
				return $after;
			}
		);
	}

	/** Publish only the latest version after checking active recipients. */
	public function publish( int $id, int $version, int $expected, string $reason ): Document {
		$this->reason( $reason );
		$scope    = $this->preflightScope( $id );
		$audience = $this->preflightRecipients( $id, $version );
		return $this->transaction(
			function () use ( $id, $version, $expected, $reason, $scope, $audience ): Document {
				$actor  = $this->authority->lockAndAssert( $scope, $audience );
				$before = $this->lockedDocument( $id, $scope );
				if ( $audience !== $before->version( $version )?->metadata->recipient_ids ) {
					throw new RuntimeException( self::ERROR );
				}
				$after = $before->publish( $version, $expected );
				$this->authority->assertActiveRecipients( $audience );
				$this->execute( $this->database->prepare( 'UPDATE %i SET revision=%d,published_version=%d WHERE id=%d AND revision=%d', $this->schema->table( 'documents' ), $after->revision, $version, $id, $expected ), 1 );
				$this->audit( $id, $after->revision, 'published', $actor, $reason, time() );
				return $after;
			}
		);
	}

	/** Terminal archive does not create a delivery to suspended recipients. */
	public function archive( int $id, int $expected, string $reason ): Document {
		$this->reason( $reason );
		$scope = $this->preflightScope( $id );
		return $this->transaction(
			function () use ( $id, $expected, $reason, $scope ): Document {
				$actor  = $this->authority->lockAndAssert( $scope, array() );
				$before = $this->lockedDocument( $id, $scope );
				$after  = $before->archive( $expected );
				$this->execute( $this->database->prepare( 'UPDATE %i SET revision=%d,archived=1 WHERE id=%d AND revision=%d', $this->schema->table( 'documents' ), $after->revision, $id, $expected ), 1 );
				$this->audit( $id, $after->revision, 'archived', $actor, $reason, time() );
				return $after;
			}
		);
	}

	/** Internal unauthorised read; caller filters with DocumentPolicy. */
	public function find( int $id ): ?Document {
		$this->schema->assertReady();
		if ( $id < 1 ) {
			return null;
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d', $this->schema->table( 'documents' ), $id ) );
		return array() === $rows ? null : $this->hydrate( $rows[0] );
	}

	/** Internal complete listing; caller filters before count and pagination. */
	public function all(): array {
		$this->schema->assertReady();
		$documents = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i ORDER BY id', $this->schema->table( 'documents' ) ) ) as $row ) {
			$documents[] = $this->hydrate( $row );
		}
		return $documents;
	}

	/** Internal audit read in ascending revision order. */
	public function history( int $id ): array {
		$this->schema->assertReady();
		if ( $id < 1 ) {
			return array();
		}
		return $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE document_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $id ) );
	}

	private function preflightScope( int $id ): Scope {
		$this->schema->assertReady();
		if ( $id < 1 ) {
			throw new RuntimeException( self::ERROR );
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT scope_type,scope_targets_json FROM %i WHERE id=%d', $this->schema->table( 'documents' ), $id ) );
		if ( 1 !== count( $rows ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return $this->scope( $rows[0] );
	}

	private function preflightRecipients( int $id, int $version ): array {
		$rows = $this->rows( $this->database->prepare( 'SELECT metadata_json FROM %i WHERE document_id=%d AND number=%d', $this->schema->table( 'versions' ), $id, $version ) );
		if ( 1 !== count( $rows ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return $this->metadata( $rows[0]->metadata_json )->recipient_ids;
	}

	private function lockedDocument( int $id, Scope $preflight ): Document {
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', $this->schema->table( 'documents' ), $id ) );
		if ( 1 !== count( $rows ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$locked_scope = $this->scope( $rows[0] );
		if ( $locked_scope->type !== $preflight->type || $locked_scope->targets !== $preflight->targets ) {
			throw new RuntimeException( self::ERROR );
		}
		return $this->hydrate( $rows[0] );
	}

	private function hydrate( object $row ): Document {
		$versions = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE document_id=%d ORDER BY number', $this->schema->table( 'versions' ), (int) $row->id ) ) as $version ) {
			$versions[] = new Revision( (int) $version->number, $this->metadata( $version->metadata_json ), new BlobDescriptor( $version->blob_id, $version->sha256, (int) $version->size_bytes, $version->mime ), (int) $version->actor_id, (int) $version->created_at );
		}
		return new Document( (int) $row->id, $this->scope( $row ), (int) $row->revision, $versions, null === $row->published_version ? null : (int) $row->published_version, '1' === (string) $row->archived );
	}

	private function scope( object $row ): Scope {
		$targets = json_decode( $row->scope_targets_json, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $targets ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return match ( $row->scope_type ) {
			'Units' => Scope::units( $targets ),
			'Institutional' => array() === $targets ? Scope::institutional() : throw new RuntimeException( self::ERROR ),
			default => throw new RuntimeException( self::ERROR ),
		};
	}

	private function metadata( string $encoded ): Metadata {
		$data = json_decode( $encoded, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $data ) || array_keys( $data ) !== array( 'title', 'summary', 'category', 'source_reference', 'owner_label', 'review_on', 'valid_from', 'valid_until', 'recipient_ids' ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return new Metadata( ...array_values( $data ) );
	}

	private function insertVersion( int $id, Revision $version ): void {
		$m    = $version->metadata;
		$json = $this->json(
			array(
				'title'            => $m->title,
				'summary'          => $m->summary,
				'category'         => $m->category,
				'source_reference' => $m->source_reference,
				'owner_label'      => $m->owner_label,
				'review_on'        => $m->review_on,
				'valid_from'       => $m->valid_from,
				'valid_until'      => $m->valid_until,
				'recipient_ids'    => $m->recipient_ids,
			)
		);
		$b    = $version->blob;
		$this->execute( $this->database->prepare( 'INSERT INTO %i (document_id,number,metadata_json,blob_id,sha256,size_bytes,mime,actor_id,created_at) VALUES (%d,%d,%s,%s,%s,%d,%s,%d,%d)', $this->schema->table( 'versions' ), $id, $version->number, $json, $b->id, $b->sha256, $b->size, $b->mime, $version->actor_id, $version->created_at ), 1 );
		$this->registry->registerInTransaction( new FileRecord( $b, 'document', $id, $version->number, 'original', $version->actor_id, $version->created_at ) );
	}

	private function audit( int $id, int $revision, string $event, int $actor, string $reason, int $time ): void {
		$this->execute( $this->database->prepare( 'INSERT INTO %i (document_id,revision,event,actor_id,reason,occurred_at) VALUES (%d,%d,%s,%d,%s,%d)', $this->schema->table( 'audit' ), $id, $revision, $event, $actor, $reason, $time ), 1 );
	}

	private function transaction( callable $operation ): Document {
		if ( $this->database->inTransaction() ) {
			throw new RuntimeException( self::ERROR );
		}
		$this->schema->assertReady();
		$this->file_schema->assertReady();
		$this->authorization_schema->validate();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$result = $operation();
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( self::ERROR );
		}
	}

	private function reason( string $reason ): void {
		if ( strlen( $reason ) < 1 || strlen( $reason ) > 500 || 1 !== preg_match( '//u', $reason ) || 1 === preg_match( '/\p{Cc}|\A\s|\s\z/u', $reason ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function json( array $value ): string {
		return json_encode( $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Strict failure is required for atomic persistence.
	}

	private function execute( string $sql, ?int $affected = null ): void {
		$result = $this->database->query( $sql );
		if ( false === $result || '' !== $this->database->last_error || ( null !== $affected && $result !== $affected ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function rows( string $sql ): array {
		$result = $this->database->get_results( $sql );
		if ( ! is_array( $result ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR );
		}
		return $result;
	}
}
