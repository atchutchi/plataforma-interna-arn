<?php
/** Contrato SQL independente de pré-cadastro Workspace.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable Squiz.Commenting -- Contrato SQL interno tipado e coberto pelo plano Workspace e ensaios exclusivos.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
/** Recusa estrutura parcial, índices adicionais e motores incompatíveis. */
final class PreRegistrationSchema {
	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_]{1,40}\z/', $database->prefix ) ) {
			throw new RuntimeException( 'Prefixo de pré-cadastros inválido.' );
		}
	}

	public function table( string $part ): string {
		if ( ! in_array( $part, array( 'accounts', 'audit', 'schema' ), true ) ) {
			throw new RuntimeException( 'Tabela de pré-cadastros inválida.' );
		}
		$table = $this->database->prefix . 'arn_workspace_' . $part;
		if ( strlen( $table ) > 64 ) {
			throw new RuntimeException( 'Identificador SQL excessivo.' );
		}
		return $table;
	}

	public function hasSchema(): bool {
		$count = 0;
		foreach ( array( 'accounts', 'audit', 'schema' ) as $part ) {
			$count += count( $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table( $part ) ) ) ); }
		if ( 0 === $count ) {
			return false; }
		if ( 3 !== $count ) {
			throw new RuntimeException( 'Pré-cadastro parcialmente preparado.' ); }
		$this->assertReady();
		return true;
	}

	public function nativeTable( string $part ): string {
		if ( ! in_array( $part, array( 'users', 'usermeta' ), true ) ) {
			throw new RuntimeException( 'Tabela nativa recusada.' ); }
		return $this->database->prefix . $part;
	}

	public function assertNativeTables(): void {
		$expected = array(
			'users'    => array(
				'ID'                  => 'bigint unsigned',
				'user_login'          => 'varchar(60)',
				'user_pass'           => 'varchar(255)',
				'user_nicename'       => 'varchar(50)',
				'user_email'          => 'varchar(100)',
				'user_url'            => 'varchar(100)',
				'user_registered'     => 'datetime',
				'user_activation_key' => 'varchar(255)',
				'user_status'         => 'int',
				'display_name'        => 'varchar(250)',
			),
			'usermeta' => array(
				'umeta_id'   => 'bigint unsigned',
				'user_id'    => 'bigint unsigned',
				'meta_key'   => 'varchar(255)',
				'meta_value' => 'longtext',
			),
		);
		foreach ( $expected as $part => $columns ) {
			$table  = $this->nativeTable( $part );
			$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
			if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE ) {
				throw new RuntimeException( 'Tabelas nativas sem contrato transaccional.' ); }
			$actual = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
			if ( count( $actual ) !== count( $columns ) ) {
				throw new RuntimeException( 'Estrutura nativa inesperada.' ); }
			foreach ( $actual as $field ) {
				$type = preg_replace( '/\b(bigint|int)\([0-9]+\)/', '$1', $field->Type );
				if ( ( $columns[ $field->Field ] ?? null ) !== $type ) {
					throw new RuntimeException( 'Campo nativo incompatível.' ); }
			}
		}
	}

	private function definitions(): array {
		$n    = array( 'bigint unsigned', false, null, false );
		$text = static fn( int $length, bool $nullable = false ): array => array( 'varchar(' . $length . ')', $nullable, 'ascii_bin', false );
		$hash = array( 'char(64)', false, 'ascii_bin', false );
		$id   = array( 'char(32)', false, 'ascii_bin', false );
		return array(
			'accounts' => array(
				'id'               => $id,
				'user_id'          => $n,
				'display_name'     => array( 'varchar(250)', false, 'utf8mb4_bin', false ),
				'primary_email'    => $text( 100 ),
				'state'            => $text( 16 ),
				'revision'         => $n,
				'authorized_by'    => $n,
				'source_sha256'    => $hash,
				'inventory_sha256' => $hash,
				'observed_at'      => $n,
				'expires_at'       => $n,
				'disabled_from'    => $text( 16, true ),
			),
			'audit'    => array(
				'id'               => array( 'bigint unsigned', false, null, true ),
				'registration_id'  => $id,
				'actor_id'         => $n,
				'action'           => $text( 24 ),
				'revision'         => $n,
				'previous_state'   => $text( 16, true ),
				'new_state'        => $text( 16 ),
				'source_sha256'    => $hash,
				'inventory_sha256' => $hash,
				'reason'           => array( 'varchar(500)', false, 'utf8mb4_bin', false ),
				'occurred_at'      => $n,
			),
			'schema'   => array(
				'id'      => $n,
				'version' => $n,
			),
		);
	}

	private function indexes( string $part ): array {
		$result = array( 'PRIMARY' => array( true, array( 'id' ) ) );
		if ( 'accounts' === $part ) {
			$result['user_id']       = array( true, array( 'user_id' ) );
			$result['primary_email'] = array( true, array( 'primary_email' ) );}
		if ( 'audit' === $part ) {
			$result['account_revision'] = array( true, array( 'registration_id', 'revision' ) );}
		return $result;
	}
	public function install(): void {
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->assertNativeTables();
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
			throw new RuntimeException( 'Instalação de pré-cadastros parcial recusada.' );
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
				$columns[] = ( 'PRIMARY' === $name ? 'PRIMARY KEY' : 'UNIQUE KEY ' . $this->database->prepare( '%i', $name ) ) . ' (' . implode( ',', $quoted ) . ')';
			}
			$this->execute( $this->database->prepare( 'CREATE TABLE %i (', $this->table( $part ) ) . implode( ',', $columns ) . ') ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin' . ( 'schema' === $part ? ' SELECT 1 AS id, 1 AS version' : '' ) );
		}
		$this->assertReady();
	}

	public function assertReady(): void {
		$this->assertNativeTables();
		foreach ( $this->definitions() as $part => $fields ) {
			$this->assertTable( $part, $fields );
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT id,version FROM %i', $this->table( 'schema' ) ) );
		if ( 1 !== count( $rows ) || '1' !== (string) $rows[0]->id || '1' !== (string) $rows[0]->version ) {
			throw new RuntimeException( 'Schema de pré-cadastros incompatível.' );
		}
	}

	private function assertTable( string $part, array $expected ): void {
		$table  = $this->table( $part );
		$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE || 'utf8mb4_bin' !== $engine[0]->TABLE_COLLATION ) {
			throw new RuntimeException( 'Motor ou collation de pré-cadastros incompatível.' );
		}
		$fields = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
		if ( count( $fields ) !== count( $expected ) ) {
			throw new RuntimeException( 'Colunas de pré-cadastros incompatíveis.' );
		}
		foreach ( $fields as $field ) {
			$actual = array( preg_replace( '/\bbigint\([0-9]+\)/', 'bigint', $field->Type ), 'YES' === $field->Null, $field->Collation, 'auto_increment' === $field->Extra );
			if ( null !== $field->Default || ! isset( $expected[ $field->Field ] ) || $expected[ $field->Field ] !== $actual || ! in_array( $field->Extra, array( '', 'auto_increment' ), true ) ) {
				throw new RuntimeException( 'Tipos de pré-cadastros incompatíveis.' );
			}
		}
		$indexes = array();
		foreach ( $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) ) as $index ) {
			if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
				throw new RuntimeException( 'Índice de pré-cadastros incompatível.' );
			}
			$indexes[ $index->Key_name ]['unique']                                = '0' === (string) $index->Non_unique;
			$indexes[ $index->Key_name ]['columns'][ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		$required = $this->indexes( $part );
		if ( count( $required ) !== count( $indexes ) ) {
			throw new RuntimeException( 'Chaves de pré-cadastros incompatíveis.' );
		}
		foreach ( $required as $name => $definition ) {
			$columns = $indexes[ $name ]['columns'] ?? array();
			ksort( $columns );
			if ( array( $indexes[ $name ]['unique'] ?? null, array_values( $columns ) ) !== $definition ) {
				throw new RuntimeException( 'Chave de pré-cadastros incompatível.' );
			}
		}
		if ( $this->rows( $this->database->prepare( 'SELECT REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s AND REFERENCED_TABLE_NAME IS NOT NULL', $table ) ) ) {
			throw new RuntimeException( 'Referências de pré-cadastros incompatíveis.' );
		}
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Metadata de pré-cadastros indisponível.' );
		}
		return $rows;
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Schema de pré-cadastros indisponível.' );
		}
	}
}
