<?php
/**
 * Schema exclusivo de referências a ficheiros.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
/** Instala e audita o contrato das três tabelas exclusivas. */
final class FileSchema {
	private const ERROR   = 'Registo de ficheiros indisponível.';
	private const VERSION = 1;

	/**
	 * Fixa a ligação dedicada e valida o prefixo.
	 *
	 * @param IdentityWpdb $database Ligação exclusiva.
	 * @throws RuntimeException Quando o prefixo é inválido.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $database->prefix ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	/**
	 * Expõe apenas as três tabelas deste módulo.
	 *
	 * @param string $name Nome lógico fechado.
	 * @return string Nome SQL qualificado pelo prefixo.
	 * @throws RuntimeException Quando o nome é inválido.
	 */
	public function table( string $name ): string {
		return match ( $name ) {
			'files' => $this->database->prefix . 'arn_private_files',
			'audit' => $this->database->prefix . 'arn_private_file_audit',
			'schema' => $this->database->prefix . 'arn_private_file_schema',
			default => throw new RuntimeException( self::ERROR ),
		};
	}

	/**
	 * Instala explicitamente as tabelas ausentes.
	 *
	 * @throws RuntimeException Quando uma tabela existente não cumpre o contrato.
	 */
	public function install(): void {
		$ddl = array(
			'files'  => 'CREATE TABLE %i (blob_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, size_bytes BIGINT UNSIGNED NOT NULL, mime VARCHAR(32) NOT NULL, object_type VARCHAR(64) NOT NULL, object_id BIGINT UNSIGNED NOT NULL, version BIGINT UNSIGNED NOT NULL, variant VARCHAR(16) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, created_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(blob_id), UNIQUE KEY object_version_variant (object_type,object_id,version,variant)) ENGINE=InnoDB',
			'audit'  => 'CREATE TABLE %i (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, blob_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL, event VARCHAR(32) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, occurred_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(id), KEY blob_id (blob_id)) ENGINE=InnoDB',
			'schema' => 'CREATE TABLE %i (version INT UNSIGNED NOT NULL, PRIMARY KEY(version)) ENGINE=InnoDB',
		);
		foreach ( $ddl as $name => $sql ) {
			$rows = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table( $name ) ) );
			if ( array() === $rows ) {
				$this->execute( $this->database->prepare( $sql, $this->table( $name ) ) );
			}
		}
		$versions = $this->rows( $this->database->prepare( 'SELECT version FROM %i', $this->table( 'schema' ) ) );
		if ( array() === $versions ) {
			$this->execute( $this->database->prepare( 'INSERT INTO %i (version) VALUES (%d)', $this->table( 'schema' ), self::VERSION ) );
		}
		$this->assertReady();
	}

	/**
	 * Confirma a versão e a estrutura exacta do schema.
	 *
	 * @throws RuntimeException Quando engine, colunas, índices ou versão divergem.
	 */
	public function assertReady(): void {
		$expected = array(
			'files'  => array(
				'blob_id'     => 'char(32)',
				'sha256'      => 'char(64)',
				'size_bytes'  => 'bigint unsigned',
				'mime'        => 'varchar(32)',
				'object_type' => 'varchar(64)',
				'object_id'   => 'bigint unsigned',
				'version'     => 'bigint unsigned',
				'variant'     => 'varchar(16)',
				'actor_id'    => 'bigint unsigned',
				'created_at'  => 'bigint unsigned',
			),
			'audit'  => array(
				'id'          => 'bigint unsigned',
				'blob_id'     => 'char(32)',
				'event'       => 'varchar(32)',
				'actor_id'    => 'bigint unsigned',
				'occurred_at' => 'bigint unsigned',
			),
			'schema' => array( 'version' => 'int unsigned' ),
		);
		foreach ( $expected as $name => $columns ) {
			$table  = $this->table( $name );
			$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
			if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE ) {
				throw new RuntimeException( self::ERROR ); }
			$actual = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
			if ( count( $columns ) !== count( $actual ) ) {
				throw new RuntimeException( self::ERROR ); }
			foreach ( $actual as $field ) {
				$type               = preg_replace( '/^(bigint|int)\([0-9]+\)/', '$1', strtolower( $field->Type ) );
				$expected_extra     = 'audit' === $name && 'id' === $field->Field ? 'auto_increment' : '';
				$expected_collation = in_array( $field->Field, array( 'blob_id', 'sha256' ), true ) ? 'ascii_bin' : null;
				if ( ! isset( $columns[ $field->Field ] ) || $columns[ $field->Field ] !== $type || 'NO' !== $field->Null || null !== $field->Default || $expected_extra !== $field->Extra || ( null !== $expected_collation && $expected_collation !== $field->Collation ) ) {
					throw new RuntimeException( self::ERROR ); }
			}
			$indexes = $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) );
			foreach ( $indexes as $index ) {
				if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
					throw new RuntimeException( self::ERROR );
				}
			}
			$signature = array_map( static fn ( $row ): string => $row->Key_name . ':' . $row->Seq_in_index . ':' . $row->Column_name . ':' . $row->Non_unique, $indexes );
			$required  = match ( $name ) {
				'files' => array( 'PRIMARY:1:blob_id:0', 'object_version_variant:1:object_type:0', 'object_version_variant:2:object_id:0', 'object_version_variant:3:version:0', 'object_version_variant:4:variant:0' ),
				'audit' => array( 'PRIMARY:1:id:0', 'blob_id:1:blob_id:1' ),
				'schema' => array( 'PRIMARY:1:version:0' ),
			};
			if ( $required !== $signature ) {
				throw new RuntimeException( self::ERROR ); }
		}
		$versions = $this->rows( $this->database->prepare( 'SELECT version FROM %i', $this->table( 'schema' ) ) );
		if ( 1 !== count( $versions ) || self::VERSION !== (int) $versions[0]->version ) {
			throw new RuntimeException( self::ERROR ); }
	}

	/**
	 * Lê metadados SQL sem cache.
	 *
	 * @param string $sql Query preparada.
	 * @return array Linhas da ligação dedicada.
	 * @throws RuntimeException Quando o SQL falha.
	 */
	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR ); }
		return $rows;
	}

	/**
	 * Executa DDL ou inicialização explícita.
	 *
	 * @param string $sql Query preparada.
	 * @throws RuntimeException Quando o SQL falha.
	 */
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR ); }
	}
}
