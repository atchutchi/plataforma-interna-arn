<?php
/** Strict internal aggregate and asset hydration, without caller authorization.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment

/** Reconstitutes persisted articles and verifies their private asset references. */
final class NewsHydrator {
	private const ERROR = 'Notícia persistida inválida.';
	private readonly NewsSchema $schema;
	private readonly FileSchema $file_schema;
	public function __construct( private readonly IdentityWpdb $database ) {
		$this->schema      = new NewsSchema( $database );
		$this->file_schema = new FileSchema( $database );
	}
	public function hydrate( object $row, ?array $legacy_versions = null ): Article {
		$post = $this->one( $this->database->prepare( 'SELECT post_author,post_status,post_type,post_content,post_title FROM %i WHERE ID=%d', $this->schema->table( 'posts' ), (int) $row->id ) );
		if ( null === $post || (int) $post->post_author !== (int) $row->author_id || 'private' !== $post->post_status || 'arn_noticia' !== $post->post_type || '' !== $post->post_content || 'Notícia privada' !== $post->post_title ) {
			throw new RuntimeException( self::ERROR );
		}
		$versions    = array();
		$asset_count = 0;
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE article_id=%d ORDER BY number', $this->schema->table( 'versions' ), (int) $row->id ) ) as $version ) {
			$assets    = array(
				'featured'   => null,
				'gallery'    => array(),
				'attachment' => array(),
			);
			$positions = array(
				'featured'   => 0,
				'gallery'    => 0,
				'attachment' => 0,
			);
			foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE article_id=%d AND version=%d ORDER BY kind,position', $this->schema->table( 'assets' ), (int) $row->id, (int) $version->number ) ) as $asset_row ) {
				++$asset_count;
				$kind = $asset_row->kind;
				if ( ! array_key_exists( $kind, $positions ) || (int) $asset_row->position !== $positions[ $kind ]++ ) {
					throw new RuntimeException( self::ERROR );
				}
				$this->assertFile( $asset_row );
				$asset = $this->assetValue( $asset_row );
				if ( 'featured' === $kind ) {
					if ( 0 !== (int) $asset_row->position || null !== $assets['featured'] ) {
						throw new RuntimeException( self::ERROR );
					}
					$assets['featured'] = $asset;
				} else {
					$assets[ $kind ][] = $asset;
				}
			}
			$versions[] = new Version( (int) $version->number, new Content( $version->title, $version->body, $version->category, $assets['featured'], $assets['gallery'], $assets['attachment'], null === $legacy_versions ? AudienceCodec::decode( $version->audience_json ) : null ), (int) $version->actor_id, (int) $version->created_at );
		}
		$total_assets = (int) $this->database->get_var( $this->database->prepare( 'SELECT COUNT(*) FROM %i WHERE article_id=%d', $this->schema->table( 'assets' ), (int) $row->id ) );
		if ( '' !== $this->database->last_error || $total_assets !== $asset_count ) {
			throw new RuntimeException( self::ERROR );
		}
		$transitions = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE article_id=%d ORDER BY revision', $this->schema->table( 'audit' ), (int) $row->id ) ) as $event ) {
			$number        = null === $legacy_versions ? (int) $event->version : ( $legacy_versions[ (int) $event->id ] ?? 0 );
			$transitions[] = new EditorialTransition( (int) $event->revision, $event->event, $number, (int) $event->actor_id, (int) $event->occurred_at, $event->reason );
		}
		return new Article( (int) $row->id, (int) $row->author_id, Scope::queues( array( $row->review_queue ) ), (int) $row->revision, $versions, null === $row->submitted_version ? null : (int) $row->submitted_version, null === $row->submitted_at ? null : (int) $row->submitted_at, $transitions );
	}

	public function assetValue( object $row ): Asset {
		return new Asset( $row->kind, new BlobDescriptor( $row->blob_id, $row->sha256, (int) $row->size_bytes, $row->mime ), $row->description );
	}

	public function assertFile( object $asset ): void {
		$file = $this->one( $this->database->prepare( 'SELECT * FROM %i WHERE blob_id=%s', $this->file_schema->table( 'files' ), $asset->blob_id ) );
		if ( null === $file || 'news_asset' !== $file->object_type || (int) $file->object_id !== (int) $asset->id || '1' !== (string) $file->version || 'original' !== $file->variant || $file->sha256 !== $asset->sha256 || (int) $file->size_bytes !== (int) $asset->size_bytes || $file->mime !== $asset->mime ) {
			throw new RuntimeException( self::ERROR );
		}
		$audit = $this->one( $this->database->prepare( 'SELECT event,actor_id,occurred_at FROM %i WHERE blob_id=%s', $this->file_schema->table( 'audit' ), $asset->blob_id ) );
		if ( null === $audit || 'file_registered' !== $audit->event || (int) $audit->actor_id !== (int) $file->actor_id || (int) $audit->occurred_at !== (int) $file->created_at ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function one( string $sql ): ?object {
		$rows = $this->rows( $sql );
		if ( count( $rows ) > 1 ) {
			throw new RuntimeException( self::ERROR );
		}
		return $rows[0] ?? null;
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR );
		}
		return $rows;
	}
}
