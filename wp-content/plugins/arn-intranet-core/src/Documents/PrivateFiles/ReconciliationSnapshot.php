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
use Arn\Intranet\Tasks\{Task, TaskSchema};

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
		$tasks         = new TaskSchema( $db );
		$has_tasks     = $this->installed( $tasks, array( 'tasks', 'comments', 'audit', 'notifications', 'schema' ) );
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
				'tasks'     => null,
			);
			if ( $has_documents ) {
				$result['documents'] = $this->rows( $db->prepare( 'SELECT v.blob_id,v.sha256,v.size_bytes,v.mime,v.document_id AS object_id,v.number AS version,v.actor_id,v.created_at,d.id AS parent_id FROM %i v LEFT JOIN %i d ON d.id=v.document_id ORDER BY v.document_id,v.number LIMIT 1001', $catalog->table( 'versions' ), $catalog->table( 'documents' ) ) );
			}
			if ( $has_news ) {
				$result['news'] = $this->rows( $db->prepare( 'SELECT a.blob_id,a.sha256,a.size_bytes,a.mime,a.id AS object_id,1 AS version,v.actor_id,v.created_at,n.id AS parent_id,v.number AS parent_version FROM %i a LEFT JOIN %i v ON v.article_id=a.article_id AND v.number=a.version LEFT JOIN %i n ON n.id=a.article_id ORDER BY a.id LIMIT 1001', $news->table( 'assets' ), $news->table( 'versions' ), $news->table( 'news' ) ) );
			}
			if ( $has_tasks ) {
				$result['tasks'] = array();
				$events          = array();
				foreach ( $this->rows( $db->prepare( 'SELECT task_id,payload FROM %i ORDER BY task_id,revision LIMIT 1001', $tasks->table( 'audit' ) ) ) as $event ) {
					$events[ $event['task_id'] ][] = json_decode( $event['payload'], true, 512, JSON_THROW_ON_ERROR );
				}
				$comments = array();
				foreach ( $this->rows( $db->prepare( 'SELECT task_id,revision,actor_id,occurred_at,body FROM %i ORDER BY task_id,revision LIMIT 1001', $tasks->table( 'comments' ) ) ) as $comment ) {
					$comments[ $comment['task_id'] ][] = array(
						'revision'    => (int) $comment['revision'],
						'actor_id'    => (int) $comment['actor_id'],
						'occurred_at' => (int) $comment['occurred_at'],
						'body'        => $comment['body'],
					);
				}
				foreach ( $this->rows( $db->prepare( 'SELECT id,payload FROM %i ORDER BY id LIMIT 1001', $tasks->table( 'tasks' ) ) ) as $row ) {
					$task = Task::restore( json_decode( $row['payload'], true, 512, JSON_THROW_ON_ERROR ), $events[ $row['id'] ] ?? array(), $comments[ $row['id'] ] ?? array() );
					if ( $task->id !== (int) $row['id'] ) {
						throw new RuntimeException( 'Tarefa incompatível.' ); }
					foreach ( $task->attachments as $item ) {
						$event = $task->history[ $item->revision - 1 ];
						foreach ( array( 'original' => $item->blob ) + $item->blob->derivatives as $variant => $blob ) {
							$result['tasks'][] = array(
								'variant'    => $variant,
								'blob_id'    => $blob->id,
								'sha256'     => $blob->sha256,
								'size_bytes' => $blob->size,
								'mime'       => $blob->mime,
								'object_id'  => $task->id,
								'version'    => $item->revision,
								'actor_id'   => $event['actor_id'],
								'created_at' => $event['occurred_at'],
								'parent_id'  => $task->id,
							);
						}
					}
				}
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
