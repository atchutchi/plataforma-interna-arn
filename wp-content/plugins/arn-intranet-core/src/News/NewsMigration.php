<?php
/** Explicit resumable schema 1 to 2 migration.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment, WordPress.WP.AlternativeFunctions.json_encode_json_encode

/** DDL is deliberately outside transactions; only exact sequential preparation is resumed. */
final class NewsMigration {
	private const ERROR = 'Migração de notícias indisponível.';
	public function __construct( private readonly IdentityWpdb $database, private readonly NewsSchema $schema ) {}

	public function upgrade( int $actor_id, int $at ): array {
		if ( $actor_id < 1 || $at < 0 || $this->database->inTransaction() ) {
			throw new RuntimeException( self::ERROR );
		}
		$key = 'arn-news-upgrade-' . hash( 'sha256', $this->database->prefix );
		$key = substr( $key, 0, 64 );
		if ( '1' !== (string) $this->database->get_var( $this->database->prepare( 'SELECT GET_LOCK(%s,0)', $key ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		try {
			$marker = $this->rows( $this->database->prepare( 'SELECT id,version FROM %i', $this->schema->table( 'schema' ) ) );
			if ( 1 !== count( $marker ) || '1' !== (string) $marker[0]->id ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( '2' === (string) $marker[0]->version ) {
				$this->schema->assertReady();
				$history = $this->rows( $this->database->prepare( 'SELECT * FROM %i', $this->schema->table( 'schema_history' ) ) );
				if ( 1 === count( $history ) ) {
					return $this->result( $history[0], 2 );
				}
				if ( array() !== $history ) {
					throw new RuntimeException( self::ERROR );
				}
				return array(
					'from'          => 2,
					'to'            => 2,
					'articles'      => 0,
					'versions'      => 0,
					'audit_events'  => 0,
					'notifications' => 0,
					'before_sha'    => '',
					'after_sha'     => '',
				);
			}
			$this->schema->assertVersion( 1 );
			$stage  = $this->stage();
			$data   = $this->legacy( $stage );
			$before = $this->fingerprint();
			$steps  = array(
				1 => $this->database->prepare( 'ALTER TABLE %i ADD audience_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL', $this->schema->table( 'versions' ) ),
				2 => $this->database->prepare( 'ALTER TABLE %i ADD version BIGINT UNSIGNED NULL', $this->schema->table( 'audit' ) ),
				3 => $this->database->prepare( 'ALTER TABLE %i ADD revision BIGINT UNSIGNED NULL, ADD kind VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL, DROP INDEX delivery, ADD UNIQUE KEY delivery (article_id,revision,recipient_id,kind)', $this->schema->table( 'notifications' ) ),
				4 => $this->database->prepare( $this->schema->ddl()['schema_history'], $this->schema->table( 'schema_history' ) ),
			);
			foreach ( $steps as $next => $sql ) {
				if ( $stage < $next ) {
					$this->execute( $sql );
					$this->schema->assertLayout( $next );
				}
			}
			// Filling added columns is atomic and repeatable. Original columns never change.
			$this->execute( 'START TRANSACTION' );
			foreach ( $data['audit'] as $id => $version ) {
				$this->execute( $this->database->prepare( 'UPDATE %i SET version=%d WHERE id=%d', $this->schema->table( 'audit' ), $version, $id ) );
			}
			foreach ( $data['notices'] as $id => $revision ) {
				$this->execute( $this->database->prepare( "UPDATE %i SET revision=%d,kind='submitted' WHERE id=%d", $this->schema->table( 'notifications' ), $revision, $id ) );
			}
			$this->execute( 'COMMIT' );
			if ( $stage < 5 ) {
				$this->execute( $this->database->prepare( 'ALTER TABLE %i MODIFY version BIGINT UNSIGNED NOT NULL', $this->schema->table( 'audit' ) ) );
			}
			if ( $stage < 6 ) {
				$this->execute( $this->database->prepare( 'ALTER TABLE %i MODIFY revision BIGINT UNSIGNED NOT NULL, MODIFY kind VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL', $this->schema->table( 'notifications' ) ) );
			}
			$this->schema->assertLayout( 6 );
			$this->legacy( 6 );
			$after = $this->fingerprint();
			if ( $before !== $after ) {
				throw new RuntimeException( self::ERROR );
			}
			$this->execute( 'START TRANSACTION' );
			$this->execute( $this->database->prepare( 'INSERT INTO %i (id,actor_id,occurred_at,articles,versions,audit_events,notifications,before_sha,after_sha) VALUES (1,%d,%d,%d,%d,%d,%d,%s,%s)', $this->schema->table( 'schema_history' ), $actor_id, $at, $data['articles'], $data['versions'], count( $data['audit'] ), count( $data['notices'] ), $before, $after ) );
			$this->execute( $this->database->prepare( 'UPDATE %i SET version=2 WHERE id=1 AND version=1', $this->schema->table( 'schema' ) ) );
			$this->schema->assertReady();
			$this->execute( 'COMMIT' );
			return array(
				'from'          => 1,
				'to'            => 2,
				'articles'      => $data['articles'],
				'versions'      => $data['versions'],
				'audit_events'  => count( $data['audit'] ),
				'notifications' => count( $data['notices'] ),
				'before_sha'    => $before,
				'after_sha'     => $after,
			);
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( self::ERROR );
		} finally {
			$this->database->query( $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
	}

	private function stage(): int {
		for ( $stage = 0; $stage <= 6; ++$stage ) {
			try {
				$this->schema->assertLayout( $stage );
				return $stage;
			} catch ( RuntimeException ) {
				continue;
			}
		}
		throw new RuntimeException( self::ERROR );
	}

	private function legacy( int $stage ): array {
		$articles    = $this->rows( $this->database->prepare( 'SELECT * FROM %i ORDER BY id', $this->schema->table( 'news' ) ) );
		$events      = $this->rows( $this->database->prepare( 'SELECT * FROM %i ORDER BY article_id,revision', $this->schema->table( 'audit' ) ) );
		$audits      = array();
		$submissions = array();
		$known       = array_column( $articles, null, 'id' );
		$numbers     = array();
		foreach ( $events as $event ) {
			$id = (int) $event->article_id;
			if ( ! isset( $known[ $id ] ) || isset( $submissions[ $id ] ) || ! in_array( $event->event, array( 'created', 'revised', 'submitted' ), true ) ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( 'submitted' === $event->event ) {
				$submissions[ $id ] = $event;
			} else {
				$numbers[ $id ] = ( $numbers[ $id ] ?? 0 ) + 1;
			}
			$number = $numbers[ $id ] ?? 0;
			if ( $stage >= 2 && null !== $event->version && (int) $event->version !== $number ) {
				throw new RuntimeException( self::ERROR );
			}
			$audits[ (int) $event->id ] = $number;
		}
		$versions = $this->rows( $this->database->prepare( 'SELECT * FROM %i ORDER BY article_id,number', $this->schema->table( 'versions' ) ) );
		foreach ( $versions as $version ) {
			if ( ! isset( $known[ $version->article_id ] ) || ( $stage >= 1 && null !== $version->audience_json ) ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		foreach ( $this->rows( $this->database->prepare( 'SELECT article_id FROM %i', $this->schema->table( 'assets' ) ) ) as $asset ) {
			if ( ! isset( $known[ $asset->article_id ] ) ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		foreach ( $articles as $row ) {
			$article = ( new NewsHydrator( $this->database ) )->hydrate( $row, $audits );
			if ( (int) $row->created_at !== $article->version( 1 )->created_at ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$notices = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i', $this->schema->table( 'notifications' ) ) ) as $notice ) {
			$event = $submissions[ (int) $notice->article_id ] ?? null;
			if ( null === $event || (int) $notice->version !== $audits[ (int) $event->id ] || (int) $notice->created_at !== (int) $event->occurred_at || (int) $notice->recipient_id < 1 || ( null !== $notice->read_at && (int) $notice->read_at < (int) $notice->created_at )
				|| ( $stage >= 3 && ( ( null !== $notice->revision && (int) $notice->revision !== (int) $event->revision ) || ( null !== $notice->kind && 'submitted' !== $notice->kind ) ) ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$notices[ (int) $notice->id ] = (int) $event->revision;
		}
		if ( $stage >= 4 && array() !== $this->rows( $this->database->prepare( 'SELECT id FROM %i', $this->schema->table( 'schema_history' ) ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return array(
			'articles' => count( $articles ),
			'versions' => count( $versions ),
			'audit'    => $audits,
			'notices'  => $notices,
		);
	}

	/** Hash original news columns only; added columns are validated independently. */
	private function fingerprint(): string {
		$columns = array(
			'news'          => 'id,author_id,review_queue,revision,submitted_version,submitted_at,created_at',
			'versions'      => 'article_id,number,title,body,category,actor_id,created_at',
			'assets'        => 'id,article_id,version,kind,position,blob_id,sha256,size_bytes,mime,description',
			'audit'         => 'id,article_id,revision,event,actor_id,reason,occurred_at',
			'notifications' => 'id,article_id,version,recipient_id,created_at,read_at',
		);
		$data    = array();
		foreach ( $columns as $name => $select ) {
			$data[ $name ] = $this->rows( $this->database->prepare( 'SELECT ' . $select . ' FROM %i ORDER BY 1,2', $this->schema->table( $name ) ) );
		}
		return hash( 'sha256', json_encode( $data, JSON_THROW_ON_ERROR ) );
	}

	private function result( object $history, int $from ): array {
		return array(
			'from'          => $from,
			'to'            => 2,
			'articles'      => (int) $history->articles,
			'versions'      => (int) $history->versions,
			'audit_events'  => (int) $history->audit_events,
			'notifications' => (int) $history->notifications,
			'before_sha'    => $history->before_sha,
			'after_sha'     => $history->after_sha,
		);
	}
	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR );
		}
		return $rows;
	}
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR );
		}
	}
}
