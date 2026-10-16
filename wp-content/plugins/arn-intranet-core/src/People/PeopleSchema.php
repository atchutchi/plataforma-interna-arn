<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Explicit People schema with fail-closed metadata checks. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
final class PeopleSchema {
	private readonly string $prefix;

	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_]{1,40}\z/D', $database->prefix ) ) {
			throw new RuntimeException( 'Prefixo People inválido.' );
		}
		$this->prefix = $database->prefix;
	}

	public function table( string $part ): string {
		if ( ! in_array( $part, array( 'people', 'audit', 'schema' ), true ) ) {
			throw new RuntimeException( 'Tabela People inválida.' );
		}
		return $this->prefix . 'arn_people' . ( 'people' === $part ? '' : '_' . $part );
	}

	private function definitions(): array {
		$number = array( 'bigint unsigned', false, null, false );
		$text   = static fn( int $n, string $collation = 'utf8mb4_bin' ): array => array( "varchar($n)", false, $collation, false );
		$long   = array( 'longtext', false, 'utf8mb4_bin', false );
		return array(
			'people' => array(
				'user_id'    => $number,
				'code'       => $text( 64, 'ascii_bin' ),
				'name'       => $text( 120 ),
				'email'      => $text( 254, 'ascii_bin' ),
				'job'        => $text( 100 ),
				'unit_id'    => $text( 64, 'ascii_bin' ),
				'phone'      => $text( 32 ),
				'office'     => $text( 100 ),
				'bio'        => $long,
				'revision'   => $number,
				'updated_at' => $number,
				'digest'     => $text( 64, 'ascii_bin' ),
			),
			'audit'  => array(
				'id'             => array( 'bigint unsigned', false, null, true ),
				'user_id'        => $number,
				'actor_id'       => $number,
				'event'          => $text( 32, 'ascii_bin' ),
				'revision'       => $number,
				'occurred_at'    => $number,
				'changed_fields' => $long,
				'before_hash'    => $text( 64, 'ascii_bin' ),
				'after_hash'     => $text( 64, 'ascii_bin' ),
				'reason'         => $text( 100 ),
			),
			'schema' => array(
				'id'      => $number,
				'version' => $number,
			),
		);
	}

	public function install(): void {
		$missing = array();
		foreach ( $this->definitions() as $part => $fields ) {
			$exists = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table( $part ) ) );
			if ( array() === $exists ) {
				$missing[] = $part;
			} else {
				$this->assertTable( $part, $fields );
			}
		}
		if ( array() !== $missing && count( $missing ) !== count( $this->definitions() ) ) {
			throw new RuntimeException( 'Instalação People parcial recusada.' );
		}
		foreach ( $missing as $part ) {
			$columns = array();
			foreach ( $this->definitions()[ $part ] as $name => [$type, $nullable, $collation, $auto] ) {
				$sql = $this->database->prepare( '%i ', $name ) . $type;
				if ( null !== $collation ) {
					$sql .= ' CHARACTER SET ' . ( 'ascii_bin' === $collation ? 'ascii' : 'utf8mb4' ) . ' COLLATE ' . $collation;
				}
				$columns[] = $sql . ' NOT NULL' . ( $auto ? ' AUTO_INCREMENT' : '' );
			}
			$columns[] = 'PRIMARY KEY (' . ( 'people' === $part ? 'user_id' : 'id' ) . ')';
			if ( 'audit' === $part ) {
				$columns[] = 'UNIQUE KEY user_revision (user_id, revision)';
			}
			$this->execute( $this->database->prepare( 'CREATE TABLE %i (', $this->table( $part ) ) . implode( ', ', $columns ) . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin' . ( 'schema' === $part ? ' SELECT 1 AS id, 1 AS version' : '' ) );
		}
		$this->validate();
	}

	public function validate(): void {
		foreach ( $this->definitions() as $part => $fields ) {
			$this->assertTable( $part, $fields );
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT id, version FROM %i', $this->table( 'schema' ) ) );
		if ( 1 !== count( $rows ) || '1' !== (string) $rows[0]->id || '1' !== (string) $rows[0]->version ) {
			throw new RuntimeException( 'Versão People inválida.' );
		}
	}

	private function assertTable( string $part, array $expected ): void {
		$table   = $this->table( $part );
		$engines = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( 1 !== count( $engines ) || 'InnoDB' !== $engines[0]->ENGINE ) {
			throw new RuntimeException( 'Motor People inválido.' );
		}
		$fields = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
		if ( count( $fields ) !== count( $expected ) ) {
			throw new RuntimeException( 'Colunas People inválidas.' );
		}
		foreach ( $fields as $field ) {
			$actual = array( preg_replace( '/\bbigint\([0-9]+\)/', 'bigint', $field->Type ), 'YES' === $field->Null, $field->Collation, 'auto_increment' === $field->Extra );
			if ( null !== $field->Default || ! isset( $expected[ $field->Field ] ) || $expected[ $field->Field ] !== $actual || ! in_array( $field->Extra, array( '', 'auto_increment' ), true ) ) {
				throw new RuntimeException( 'Tipo People inválido.' );
			}
		}
		$indexes = array();
		foreach ( $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) ) as $index ) {
			if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
				throw new RuntimeException( 'Índice People inválido.' );
			}
			$indexes[ $index->Key_name ]['unique']                                = '0' === (string) $index->Non_unique;
			$indexes[ $index->Key_name ]['columns'][ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		$required = array( 'PRIMARY' => array( true, array( 'people' === $part ? 'user_id' : 'id' ) ) );
		if ( 'audit' === $part ) {
			$required['user_revision'] = array( true, array( 'user_id', 'revision' ) );
		}
		if ( count( $indexes ) !== count( $required ) ) {
			throw new RuntimeException( 'Chaves People inválidas.' );
		}
		foreach ( $required as $name => $want ) {
			$columns = $indexes[ $name ]['columns'] ?? array();
			ksort( $columns );
			if ( array( $indexes[ $name ]['unique'] ?? null, array_values( $columns ) ) !== $want ) {
				throw new RuntimeException( 'Chave People inválida.' );
			}
		}
		$foreign = $this->rows( $this->database->prepare( 'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND REFERENCED_TABLE_NAME IS NOT NULL', $table ) );
		if ( array() !== $foreign ) {
			throw new RuntimeException( 'Referência People inválida.' );
		}
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Metadata People indisponível.' );
		}
		return $rows;
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Schema People indisponível.' );
		}
	}
}
