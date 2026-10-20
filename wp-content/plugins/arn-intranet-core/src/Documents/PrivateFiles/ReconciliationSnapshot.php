<?php
/** Bounded SQL snapshots for read-only file reconciliation.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Arn\Intranet\Documents\Catalog\CatalogSchema;
use Arn\Intranet\News\NewsSchema;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** No DDL, row repair, content selection, or audit insertion. */
final class ReconciliationSnapshot {
	public function __construct( private readonly IdentityWpdb $database ) {}

	public function read(): array {
		$db = $this->database;
		$db->suppress_errors( true );
		if ( $db->inTransaction() ) {
			throw new RuntimeException( 'Diagnóstico indisponível.' );
		}
		$files = new FileSchema( $db );
		$files->assertReady();
		$catalog       = new CatalogSchema( $db );
		$news          = new NewsSchema( $db );
		$has_documents = $this->installed( $catalog, array( 'documents', 'versions', 'audit', 'schema' ) );
		$has_news      = $this->installed( $news, array( 'news', 'versions', 'assets', 'audit', 'notifications', 'schema', 'schema_history' ) );
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' );
		$this->execute( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' );
		try {
			$result = array(
				'files'     => $this->rows( $db->prepare( 'SELECT * FROM %i ORDER BY blob_id LIMIT 1001', $files->table( 'files' ) ) ),
				'audit'     => $this->rows( $db->prepare( 'SELECT blob_id,event,actor_id,occurred_at FROM %i ORDER BY id LIMIT 1001', $files->table( 'audit' ) ) ),
				'documents' => null,
				'news'      => null,
			);
			if ( $has_documents ) {
				$result['documents'] = $this->rows( $db->prepare( 'SELECT v.blob_id,v.sha256,v.size_bytes,v.mime,v.document_id AS object_id,v.number AS version,v.actor_id,v.created_at,d.id AS parent_id FROM %i v LEFT JOIN %i d ON d.id=v.document_id ORDER BY v.document_id,v.number LIMIT 1001', $catalog->table( 'versions' ), $catalog->table( 'documents' ) ) );
			}
			if ( $has_news ) {
				$result['news'] = $this->rows( $db->prepare( 'SELECT a.blob_id,a.sha256,a.size_bytes,a.mime,a.id AS object_id,1 AS version,v.actor_id,v.created_at,n.id AS parent_id,v.number AS parent_version FROM %i a LEFT JOIN %i v ON v.article_id=a.article_id AND v.number=a.version LEFT JOIN %i n ON n.id=a.article_id ORDER BY a.id LIMIT 1001', $news->table( 'assets' ), $news->table( 'versions' ), $news->table( 'news' ) ) );
			}
			return $result;
		} finally {
			$this->execute( 'ROLLBACK' );
		}
	}

	private function installed( object $schema, array $names ): bool {
		$count = 0;
		foreach ( $names as $name ) {
			$count += count( $this->rows( $this->database->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $schema->table( $name ) ) ) );
		}
		if ( 0 === $count ) {
			return false;
		}
		if ( count( $names ) !== $count ) {
			throw new RuntimeException( 'Diagnóstico indisponível.' );
		}
		$schema->assertReady();
		return true;
	}

	private function rows( string $query ): array {
		$rows = $this->database->get_results( $query, ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error || count( $rows ) > 1000 ) {
			throw new RuntimeException( 'Diagnóstico indisponível ou limite atingido.' );
		}
		return $rows;
	}

	private function execute( string $query ): void {
		if ( false === $this->database->query( $query ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Diagnóstico indisponível.' );
		}
	}
}
