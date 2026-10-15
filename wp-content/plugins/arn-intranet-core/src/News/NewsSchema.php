<?php
/** Explicit, closed schema for private local news.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/** Installs the six dedicated tables and rejects incompatible existing structures. */
final class NewsSchema {
	private const ERROR   = 'Esquema de notícias indisponível.';
	private const VERSION = 2;

	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $database->prefix ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	public function table( string $suffix ): string {
		return match ( $suffix ) {
			'news' => $this->database->prefix . 'arn_news',
			'versions' => $this->database->prefix . 'arn_news_versions',
			'assets' => $this->database->prefix . 'arn_news_assets',
			'audit' => $this->database->prefix . 'arn_news_audit',
			'notifications' => $this->database->prefix . 'arn_news_notifications',
			'schema' => $this->database->prefix . 'arn_news_schema',
			'schema_history' => $this->database->prefix . 'arn_news_schema_history',
			'posts' => $this->database->prefix . 'posts',
			default => throw new RuntimeException( self::ERROR ),
		};
	}

	/** Called only by an explicit local installer or exclusive integration test. */
	public function install(): void {
		if ( array() !== $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table( 'schema' ) ) ) ) {
			$this->assertReady();
			return;
		}
		$this->assertPosts();
		( new FileSchema( $this->database ) )->assertReady();
		( new AuthorizationSchema( $this->database ) )->validate();
		$ddl = $this->ddl();
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

	public function upgrade( int $actor_id, int $at ): array {
		return ( new NewsMigration( $this->database, $this ) )->upgrade( $actor_id, $at );
	}

	public function ddl(): array {
		return array(
			'news'           => 'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL, author_id BIGINT UNSIGNED NOT NULL, review_queue VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, revision BIGINT UNSIGNED NOT NULL, submitted_version BIGINT UNSIGNED NULL, submitted_at BIGINT UNSIGNED NULL, created_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(id), KEY author_revision (author_id,revision), KEY queue_state (review_queue,submitted_version)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'versions'       => 'CREATE TABLE %i (article_id BIGINT UNSIGNED NOT NULL, number BIGINT UNSIGNED NOT NULL, title VARCHAR(800) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, body LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, category VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, created_at BIGINT UNSIGNED NOT NULL, audience_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL, PRIMARY KEY(article_id,number)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'assets'         => 'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, article_id BIGINT UNSIGNED NOT NULL, version BIGINT UNSIGNED NOT NULL, kind VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, position TINYINT UNSIGNED NOT NULL, blob_id CHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, sha256 CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, mime VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, description VARCHAR(1200) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, PRIMARY KEY(id), UNIQUE KEY blob_id (blob_id), UNIQUE KEY slot (article_id,version,kind,position)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'audit'          => 'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, article_id BIGINT UNSIGNED NOT NULL, revision BIGINT UNSIGNED NOT NULL, event VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, reason VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, occurred_at BIGINT UNSIGNED NOT NULL, version BIGINT UNSIGNED NOT NULL, PRIMARY KEY(id), UNIQUE KEY article_revision (article_id,revision)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'notifications'  => 'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, article_id BIGINT UNSIGNED NOT NULL, version BIGINT UNSIGNED NOT NULL, recipient_id BIGINT UNSIGNED NOT NULL, created_at BIGINT UNSIGNED NOT NULL, read_at BIGINT UNSIGNED NULL, revision BIGINT UNSIGNED NOT NULL, kind VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, PRIMARY KEY(id), UNIQUE KEY delivery (article_id,revision,recipient_id,kind), KEY recipient_state (recipient_id,read_at,id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'schema_history' => 'CREATE TABLE %i (id INT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, occurred_at BIGINT UNSIGNED NOT NULL, articles BIGINT UNSIGNED NOT NULL, versions BIGINT UNSIGNED NOT NULL, audit_events BIGINT UNSIGNED NOT NULL, notifications BIGINT UNSIGNED NOT NULL, before_sha CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, after_sha CHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
			'schema'         => 'CREATE TABLE %i (id INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL, PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin',
		);
	}

	public function assertReady(): void {
		$this->assertLayout( 6 );
		$this->assertVersion( 2 );
	}

	public function assertVersion( int $expected ): void {
		$version = $this->rows( $this->database->prepare( 'SELECT id,version FROM %i', $this->table( 'schema' ) ) );
		if ( 1 !== count( $version ) || '1' !== (string) $version[0]->id || (string) $expected !== (string) $version[0]->version ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	/** Exact ordered DDL preparation stages, never a permissive schema probe. */
	public function assertLayout( int $stage ): void {
		if ( $stage < 0 || $stage > 6 ) {
			throw new RuntimeException( self::ERROR );
		}
		$this->assertPosts();
		( new FileSchema( $this->database ) )->assertReady();
		( new AuthorizationSchema( $this->database ) )->validate();
		$definitions = array(
			'news'          => array(
				'id'                => 'bigint unsigned',
				'author_id'         => 'bigint unsigned',
				'review_queue'      => 'varchar(64)',
				'revision'          => 'bigint unsigned',
				'submitted_version' => 'bigint unsigned',
				'submitted_at'      => 'bigint unsigned',
				'created_at'        => 'bigint unsigned',
			),
			'versions'      => array(
				'article_id' => 'bigint unsigned',
				'number'     => 'bigint unsigned',
				'title'      => 'varchar(800)',
				'body'       => 'longtext',
				'category'   => 'varchar(32)',
				'actor_id'   => 'bigint unsigned',
				'created_at' => 'bigint unsigned',
			),
			'assets'        => array(
				'id'          => 'bigint unsigned',
				'article_id'  => 'bigint unsigned',
				'version'     => 'bigint unsigned',
				'kind'        => 'varchar(16)',
				'position'    => 'tinyint unsigned',
				'blob_id'     => 'char(32)',
				'sha256'      => 'char(64)',
				'size_bytes'  => 'bigint unsigned',
				'mime'        => 'varchar(32)',
				'description' => 'varchar(1200)',
			),
			'audit'         => array(
				'id'          => 'bigint unsigned',
				'article_id'  => 'bigint unsigned',
				'revision'    => 'bigint unsigned',
				'event'       => 'varchar(16)',
				'actor_id'    => 'bigint unsigned',
				'reason'      => 'varchar(500)',
				'occurred_at' => 'bigint unsigned',
			),
			'notifications' => array(
				'id'           => 'bigint unsigned',
				'article_id'   => 'bigint unsigned',
				'version'      => 'bigint unsigned',
				'recipient_id' => 'bigint unsigned',
				'created_at'   => 'bigint unsigned',
				'read_at'      => 'bigint unsigned',
			),
			'schema'        => array(
				'id'      => 'int unsigned',
				'version' => 'int unsigned',
			),
		);
		$required    = array(
			'news'          => array( 'PRIMARY:1:id:0', 'author_revision:1:author_id:1', 'author_revision:2:revision:1', 'queue_state:1:review_queue:1', 'queue_state:2:submitted_version:1' ),
			'versions'      => array( 'PRIMARY:1:article_id:0', 'PRIMARY:2:number:0' ),
			'assets'        => array( 'PRIMARY:1:id:0', 'blob_id:1:blob_id:0', 'slot:1:article_id:0', 'slot:2:version:0', 'slot:3:kind:0', 'slot:4:position:0' ),
			'audit'         => array( 'PRIMARY:1:id:0', 'article_revision:1:article_id:0', 'article_revision:2:revision:0' ),
			'notifications' => array( 'PRIMARY:1:id:0', 'delivery:1:article_id:0', 'delivery:2:version:0', 'delivery:3:recipient_id:0', 'recipient_state:1:recipient_id:1', 'recipient_state:2:read_at:1', 'recipient_state:3:id:1' ),
			'schema'        => array( 'PRIMARY:1:id:0' ),
		);
		if ( $stage >= 1 ) {
			$definitions['versions']['audience_json'] = 'longtext';
		}
		if ( $stage >= 2 ) {
			$definitions['audit']['version'] = 'bigint unsigned';
		}
		if ( $stage >= 3 ) {
			$definitions['notifications']['revision'] = 'bigint unsigned';
			$definitions['notifications']['kind']     = 'varchar(16)';
			$required['notifications']                = array( 'PRIMARY:1:id:0', 'delivery:1:article_id:0', 'delivery:2:revision:0', 'delivery:3:recipient_id:0', 'delivery:4:kind:0', 'recipient_state:1:recipient_id:1', 'recipient_state:2:read_at:1', 'recipient_state:3:id:1' );
		}
		if ( $stage >= 4 ) {
			$definitions['schema_history'] = array(
				'id'            => 'int unsigned',
				'actor_id'      => 'bigint unsigned',
				'occurred_at'   => 'bigint unsigned',
				'articles'      => 'bigint unsigned',
				'versions'      => 'bigint unsigned',
				'audit_events'  => 'bigint unsigned',
				'notifications' => 'bigint unsigned',
				'before_sha'    => 'char(64)',
				'after_sha'     => 'char(64)',
			);
			$required['schema_history']    = array( 'PRIMARY:1:id:0' );
		} elseif ( array() !== $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table( 'schema_history' ) ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		foreach ( $definitions as $suffix => $columns ) {
			$table = $this->table( $suffix );
			$this->assertEngine( $table );
			$actual = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
			if ( count( $actual ) !== count( $columns ) ) {
				throw new RuntimeException( self::ERROR );
			}
			foreach ( $actual as $field ) {
				$type     = preg_replace( '/^(bigint|int|tinyint)\([0-9]+\)/', '$1', strtolower( $field->Type ) );
				$nullable = in_array( $suffix . ':' . $field->Field, array( 'news:submitted_version', 'news:submitted_at', 'notifications:read_at', 'versions:audience_json' ), true ) || ( $stage < 5 && 'audit:version' === $suffix . ':' . $field->Field ) || ( $stage < 6 && in_array( $suffix . ':' . $field->Field, array( 'notifications:revision', 'notifications:kind' ), true ) );
				$extra    = in_array( $suffix . ':' . $field->Field, array( 'assets:id', 'audit:id', 'notifications:id' ), true ) ? 'auto_increment' : '';
				$is_text  = preg_match( '/^(varchar|char|longtext)/', $type );
				if ( ( $columns[ $field->Field ] ?? null ) !== $type || ( $nullable ? 'YES' : 'NO' ) !== $field->Null || $extra !== $field->Extra || null !== $field->Default || ( $is_text && 'utf8mb4_bin' !== $field->Collation ) ) {
					throw new RuntimeException( self::ERROR );
				}
			}
			$signature = array();
			foreach ( $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) ) as $index ) {
				if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
					throw new RuntimeException( self::ERROR );
				}
				$signature[] = $index->Key_name . ':' . $index->Seq_in_index . ':' . $index->Column_name . ':' . $index->Non_unique;
			}
			sort( $signature );
			sort( $required[ $suffix ] );
			if ( $signature !== $required[ $suffix ] ) {
				throw new RuntimeException( self::ERROR );
			}
		}
	}

	private function assertPosts(): void {
		$this->assertEngine( $this->table( 'posts' ) );
		$fields  = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $this->table( 'posts' ) ) );
		$by_name = array();
		foreach ( $fields as $field ) {
			$by_name[ $field->Field ] = $field;
		}
		foreach ( array(
			'ID'                => 'bigint unsigned',
			'post_author'       => 'bigint unsigned',
			'post_date'         => 'datetime',
			'post_date_gmt'     => 'datetime',
			'post_content'      => 'longtext',
			'post_title'        => 'text',
			'post_status'       => 'varchar(20)',
			'post_type'         => 'varchar(20)',
			'post_name'         => 'varchar(200)',
			'post_modified'     => 'datetime',
			'post_modified_gmt' => 'datetime',
		) as $name => $expected ) {
			$type = isset( $by_name[ $name ] ) ? preg_replace( '/^bigint\([0-9]+\)/', 'bigint', strtolower( $by_name[ $name ]->Type ) ) : null;
			if ( $expected !== $type ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		if ( 'auto_increment' !== $by_name['ID']->Extra ) {
			throw new RuntimeException( self::ERROR );
		}
		$indexes = $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $this->table( 'posts' ) ) );
		$found   = array();
		foreach ( $indexes as $index ) {
			$found[ $index->Key_name ][ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		foreach ( array(
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
		) as $name => $columns ) {
			if ( ( $found[ $name ] ?? null ) !== $columns ) {
				throw new RuntimeException( self::ERROR );
			}
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
