<?php
/**
 * Explicit schema for the local document catalog.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag

/** Installs only the four dedicated tables and validates WordPress posts. */
final class CatalogSchema {
	private const ERROR   = 'Esquema documental indisponível.';
	private const VERSION = 1;

	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $database->prefix ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	/** Closed SQL identifier catalog. */
	public function table( string $suffix ): string {
		return match ( $suffix ) {
			'documents' => $this->database->prefix . 'arn_documents',
			'versions' => $this->database->prefix . 'arn_document_versions',
			'audit' => $this->database->prefix . 'arn_document_audit',
			'schema' => $this->database->prefix . 'arn_document_schema',
			'posts' => $this->database->prefix . 'posts',
			default => throw new RuntimeException( self::ERROR ),
		};
	}

	/** Explicit DDL; never invoked by a request read. */
	public function install(): void {
		$this->assertPosts();
		$ddl = array(
			'documents' => 'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL, scope_type VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, scope_targets_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, revision BIGINT UNSIGNED NOT NULL, published_version BIGINT UNSIGNED NULL, archived TINYINT UNSIGNED NOT NULL, created_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(id), KEY state_revision (archived,revision)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'versions'  => 'CREATE TABLE %i (document_id BIGINT UNSIGNED NOT NULL, number BIGINT UNSIGNED NOT NULL, metadata_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, blob_id CHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, sha256 CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, mime VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, created_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(document_id,number), UNIQUE KEY blob_id (blob_id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'audit'     => 'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, document_id BIGINT UNSIGNED NOT NULL, revision BIGINT UNSIGNED NOT NULL, event VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, occurred_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(id), UNIQUE KEY document_revision (document_id,revision)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'schema'    => 'CREATE TABLE %i (id INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
		);
		foreach ( $ddl as $suffix => $sql ) {
			if ( array() === $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table( $suffix ) ) ) ) {
				$this->execute( $this->database->prepare( $sql, $this->table( $suffix ) ) );
			}
		}
		if ( array() === $this->rows( $this->database->prepare( 'SELECT id,version FROM %i', $this->table( 'schema' ) ) ) ) {
			$this->execute( $this->database->prepare( 'INSERT INTO %i (id,version) VALUES (1,%d)', $this->table( 'schema' ), self::VERSION ) );
		}
		$this->assertReady();
	}

	/** Check engines, columns, indexes and singleton before transactions. */
	public function assertReady(): void {
		$this->assertPosts();
		$definitions = array(
			'documents' => array(
				'id'                 => 'bigint unsigned',
				'scope_type'         => 'varchar(16)',
				'scope_targets_json' => 'longtext',
				'revision'           => 'bigint unsigned',
				'published_version'  => 'bigint unsigned',
				'archived'           => 'tinyint unsigned',
				'created_at'         => 'bigint unsigned',
			),
			'versions'  => array(
				'document_id'   => 'bigint unsigned',
				'number'        => 'bigint unsigned',
				'metadata_json' => 'longtext',
				'blob_id'       => 'char(32)',
				'sha256'        => 'char(64)',
				'size_bytes'    => 'bigint unsigned',
				'mime'          => 'varchar(32)',
				'actor_id'      => 'bigint unsigned',
				'created_at'    => 'bigint unsigned',
			),
			'audit'     => array(
				'id'          => 'bigint unsigned',
				'document_id' => 'bigint unsigned',
				'revision'    => 'bigint unsigned',
				'event'       => 'varchar(16)',
				'actor_id'    => 'bigint unsigned',
				'reason'      => 'varchar(500)',
				'occurred_at' => 'bigint unsigned',
			),
			'schema'    => array(
				'id'      => 'int unsigned',
				'version' => 'int unsigned',
			),
		);
		$required    = array(
			'documents' => array( 'PRIMARY:1:id:0', 'state_revision:1:archived:1', 'state_revision:2:revision:1' ),
			'versions'  => array( 'PRIMARY:1:document_id:0', 'PRIMARY:2:number:0', 'blob_id:1:blob_id:0' ),
			'audit'     => array( 'PRIMARY:1:id:0', 'document_revision:1:document_id:0', 'document_revision:2:revision:0' ),
			'schema'    => array( 'PRIMARY:1:id:0' ),
		);
		foreach ( $definitions as $suffix => $columns ) {
			$this->assertEngine( $this->table( $suffix ) );
			$actual = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $this->table( $suffix ) ) );
			if ( count( $actual ) !== count( $columns ) ) {
				throw new RuntimeException( self::ERROR );
			}
			foreach ( $actual as $field ) {
				$type     = preg_replace( '/^(bigint|int|tinyint)\([0-9]+\)/', '$1', strtolower( $field->Type ) );
				$nullable = 'documents' === $suffix && 'published_version' === $field->Field;
				$extra    = 'audit' === $suffix && 'id' === $field->Field ? 'auto_increment' : '';
				if ( ( $columns[ $field->Field ] ?? null ) !== $type || ( $nullable ? 'YES' : 'NO' ) !== $field->Null || $extra !== $field->Extra || null !== $field->Default || ( in_array( $field->Field, array( 'blob_id', 'sha256', 'scope_type', 'mime', 'event' ), true ) && 'utf8mb4_bin' !== $field->Collation ) || ( in_array( $field->Field, array( 'scope_targets_json', 'metadata_json', 'reason' ), true ) && 'utf8mb4_bin' !== $field->Collation ) ) {
					throw new RuntimeException( self::ERROR );
				}
			}
			$indexes   = $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $this->table( $suffix ) ) );
			$signature = array();
			foreach ( $indexes as $index ) {
				if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
					throw new RuntimeException( self::ERROR );
				}
				$signature[] = $index->Key_name . ':' . $index->Seq_in_index . ':' . $index->Column_name . ':' . $index->Non_unique;
			}
			if ( $required[ $suffix ] !== $signature ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$version = $this->rows( $this->database->prepare( 'SELECT id,version FROM %i', $this->table( 'schema' ) ) );
		if ( 1 !== count( $version ) || '1' !== (string) $version[0]->id || (string) self::VERSION !== (string) $version[0]->version ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	/** WordPress anchor must be transactional with its standard identity indexes. */
	private function assertPosts(): void {
		$this->assertEngine( $this->table( 'posts' ) );
		$fields = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $this->table( 'posts' ) ) );
		$names  = array_column( $fields, 'Field' );
		foreach ( array( 'ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_status', 'post_type', 'post_name', 'post_modified', 'post_modified_gmt' ) as $field ) {
			if ( ! in_array( $field, $names, true ) ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$types = array();
		foreach ( $fields as $field ) {
			$types[ $field->Field ] = array( preg_replace( '/^bigint\([0-9]+\)/', 'bigint', strtolower( $field->Type ) ), $field->Extra );
		}
		if ( ( $types['ID'] ?? null ) !== array( 'bigint unsigned', 'auto_increment' ) || ( $types['post_type'] ?? null ) !== array( 'varchar(20)', '' ) || ( $types['post_status'] ?? null ) !== array( 'varchar(20)', '' ) || ( $types['post_title'] ?? null ) !== array( 'text', '' ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$indexes = $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $this->table( 'posts' ) ) );
		$present = array();
		$unique  = array();
		foreach ( $indexes as $index ) {
			if ( 'BTREE' !== $index->Index_type || ( null !== $index->Sub_part && 'post_name' !== $index->Column_name ) ) {
				throw new RuntimeException( self::ERROR );
			}
			$present[ $index->Key_name ][ (int) $index->Seq_in_index ] = $index->Column_name;
			$unique[ $index->Key_name ]                                = (string) $index->Non_unique;
		}
		$required = array(
			'PRIMARY'          => array( 1 => 'ID' ),
			'post_name'        => array( 1 => 'post_name' ),
			'type_status_date' => array(
				1 => 'post_type',
				2 => 'post_status',
				3 => 'post_date',
				4 => 'ID',
			),
			'post_parent'      => array( 1 => 'post_parent' ),
			'post_author'      => array( 1 => 'post_author' ),
		);
		foreach ( $required as $name => $columns ) {
			if ( ( $present[ $name ] ?? null ) !== $columns ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		if ( '0' !== ( $unique['PRIMARY'] ?? '' ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function assertEngine( string $table ): void {
		$rows = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( 1 !== count( $rows ) || 'InnoDB' !== $rows[0]->ENGINE ) {
			throw new RuntimeException( self::ERROR );
		}
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
