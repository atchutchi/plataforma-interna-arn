<?php
/** Exact independent task schema.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable Squiz.Commenting -- Internal SQL contract is specified in the F1-12 plan and integration tests.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
/** Never adopts partial layouts, extra indexes or incompatible storage engines. */
final class TaskSchema {
	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_]{1,40}\z/', $database->prefix ) ) {
			throw new RuntimeException( 'Prefixo de tarefas inválido.' );
		}
	}

	public function table( string $part ): string {
		if ( ! in_array( $part, array( 'tasks', 'comments', 'audit', 'notifications', 'schema' ), true ) ) {
			throw new RuntimeException( 'Tabela de tarefas inválida.' );
		}
		$table = $this->database->prefix . 'arn_tasks' . ( 'tasks' === $part ? '' : '_' . $part );
		if ( strlen( $table ) > 64 ) {
			throw new RuntimeException( 'Identificador SQL excessivo.' );
		}
		return $table;
	}

	private function definitions(): array {
		$n               = array( 'bigint unsigned', false, null, false );
		$nullable_number = array( 'bigint unsigned', true, null, false );
		$text            = static fn( int $length, bool $nullable = false ): array => array( 'varchar(' . $length . ')', $nullable, 'ascii_bin', false );
		$hash            = array( 'char(64)', false, 'ascii_bin', false );
		$long            = array( 'longtext', false, 'utf8mb4_bin', false );
		return array(
			'tasks'         => array(
				'id'                => array( 'bigint unsigned', false, null, true ),
				'author_id'         => $n,
				'unit_id'           => $text( 64 ),
				'assignee_id'       => $n,
				'revision'          => $n,
				'state'             => $text( 16 ),
				'due_at'            => $nullable_number,
				'updated_at'        => $n,
				'create_request_id' => array( 'char(32)', false, 'ascii_bin', false ),
				'payload'           => $long,
				'digest'            => $hash,
			),
			'comments'      => array(
				'id'          => array( 'bigint unsigned', false, null, true ),
				'task_id'     => $n,
				'revision'    => $n,
				'actor_id'    => $n,
				'occurred_at' => $n,
				'body'        => $long,
			),
			'audit'         => array(
				'task_id'     => $n,
				'revision'    => $n,
				'event'       => $text( 32 ),
				'actor_id'    => $n,
				'occurred_at' => $n,
				'reason'      => array( 'varchar(1000)', false, 'utf8mb4_bin', false ),
				'before_hash' => $hash,
				'after_hash'  => $hash,
				'payload'     => $long,
			),
			'notifications' => array(
				'id'           => array( 'bigint unsigned', false, null, true ),
				'task_id'      => $n,
				'revision'     => $n,
				'recipient_id' => $n,
				'kind'         => $text( 32 ),
				'created_at'   => $n,
				'read_at'      => $nullable_number,
			),
			'schema'        => array(
				'id'      => $n,
				'version' => $n,
			),
		);
	}

	private function indexes( string $part ): array {
		$primary = match ( $part ) {
			'audit' => array( 'task_id', 'revision' ),
			default => array( 'id' ),
		};
		$result = array( 'PRIMARY' => array( true, $primary ) );
		if ( 'tasks' === $part ) {
			$result['create_request'] = array( true, array( 'create_request_id' ) );
			$result['own_list']       = array( false, array( 'assignee_id', 'state', 'due_at', 'id' ) );
			$result['team_list']      = array( false, array( 'unit_id', 'state', 'due_at', 'id' ) );
		}
		if ( 'comments' === $part ) {
			$result['task_revision'] = array( true, array( 'task_id', 'revision' ) );
		}
		if ( 'notifications' === $part ) {
			$result['delivery']       = array( true, array( 'task_id', 'revision', 'recipient_id', 'kind' ) );
			$result['recipient_read'] = array( false, array( 'recipient_id', 'read_at', 'id' ) );
		}
		return $result;
	}

	public function install(): void {
		if ( ! Configuration::current() || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
			throw new RuntimeException( 'Instalação de tarefas recusada.' );
		}
		if ( $this->database->inTransaction() ) {
			throw new RuntimeException( 'Instalação de tarefas exige ligação livre.' );
		}
		$missing = array();
		foreach ( $this->definitions() as $part => $fields ) {
			$exists = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table( $part ) ) );
			if ( ! $exists ) {
				$missing[] = $part;
			} else {
				$this->assertTable( $part, $fields );
			}
		}
		if ( $missing && count( $missing ) !== count( $this->definitions() ) ) {
			throw new RuntimeException( 'Instalação de tarefas parcial recusada.' );
		}
		foreach ( $missing as $part ) {
			$columns = array();
			foreach ( $this->definitions()[ $part ] as $name => [$type, $nullable, $collation, $auto] ) {
				$sql = $this->database->prepare( '%i ', $name ) . $type;
				if ( null !== $collation ) {
					$sql .= ' CHARACTER SET ' . ( 'ascii_bin' === $collation ? 'ascii' : 'utf8mb4' ) . ' COLLATE ' . $collation;
				}
				$columns[] = $sql . ( $nullable ? ' NULL' : ' NOT NULL' ) . ( $auto ? ' AUTO_INCREMENT' : '' );
			}
			foreach ( $this->indexes( $part ) as $name => [$unique, $fields] ) {
				$quoted    = array_map( fn( string $field ): string => $this->database->prepare( '%i', $field ), $fields );
				$columns[] = ( 'PRIMARY' === $name ? 'PRIMARY KEY' : ( $unique ? 'UNIQUE KEY ' : 'KEY ' ) . $this->database->prepare( '%i', $name ) ) . ' (' . implode( ',', $quoted ) . ')';
			}
			$this->execute( $this->database->prepare( 'CREATE TABLE %i (', $this->table( $part ) ) . implode( ',', $columns ) . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin' . ( 'schema' === $part ? ' SELECT 1 AS id, 1 AS version' : '' ) );
		}
		$this->assertReady();
	}

	public function assertReady(): void {
		if ( ! Configuration::current() ) {
			throw new RuntimeException( 'Schema de tarefas indisponível.' );
		}
		foreach ( $this->definitions() as $part => $fields ) {
			$this->assertTable( $part, $fields );
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT id,version FROM %i', $this->table( 'schema' ) ) );
		if ( 1 !== count( $rows ) || '1' !== (string) $rows[0]->id || '1' !== (string) $rows[0]->version ) {
			throw new RuntimeException( 'Schema de tarefas incompatível.' );
		}
	}

	private function assertTable( string $part, array $expected ): void {
		$table  = $this->table( $part );
		$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE || 'utf8mb4_bin' !== $engine[0]->TABLE_COLLATION ) {
			throw new RuntimeException( 'Motor ou collation de tarefas incompatível.' );
		}
		$fields = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
		if ( count( $fields ) !== count( $expected ) ) {
			throw new RuntimeException( 'Colunas de tarefas incompatíveis.' );
		}
		foreach ( $fields as $field ) {
			$actual = array( preg_replace( '/\bbigint\([0-9]+\)/', 'bigint', $field->Type ), 'YES' === $field->Null, $field->Collation, 'auto_increment' === $field->Extra );
			if ( null !== $field->Default || ! isset( $expected[ $field->Field ] ) || $expected[ $field->Field ] !== $actual || ! in_array( $field->Extra, array( '', 'auto_increment' ), true ) ) {
				throw new RuntimeException( 'Tipos de tarefas incompatíveis.' );
			}
		}
		$indexes = array();
		foreach ( $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) ) as $index ) {
			if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
				throw new RuntimeException( 'Índice de tarefas incompatível.' );
			}
			$indexes[ $index->Key_name ]['unique']                                = '0' === (string) $index->Non_unique;
			$indexes[ $index->Key_name ]['columns'][ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		$required = $this->indexes( $part );
		if ( count( $required ) !== count( $indexes ) ) {
			throw new RuntimeException( 'Chaves de tarefas incompatíveis.' );
		}
		foreach ( $required as $name => $definition ) {
			$columns = $indexes[ $name ]['columns'] ?? array();
			ksort( $columns );
			if ( array( $indexes[ $name ]['unique'] ?? null, array_values( $columns ) ) !== $definition ) {
				throw new RuntimeException( 'Chave de tarefas incompatível.' );
			}
		}
		if ( $this->rows( $this->database->prepare( 'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND REFERENCED_TABLE_NAME IS NOT NULL', $table ) ) ) {
			throw new RuntimeException( 'Referências de tarefas incompatíveis.' );
		}
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Metadata de tarefas indisponível.' );
		}
		return $rows;
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Schema de tarefas indisponível.' );
		}
	}
}
