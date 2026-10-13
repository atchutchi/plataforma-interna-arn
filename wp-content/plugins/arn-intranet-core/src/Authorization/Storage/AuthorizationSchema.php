<?php
/**
 * Schema explícito da persistência de autorização.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// Identificadores preparados, metadata sem cache e erros exclusivamente constantes.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Os campos de metadata conservam os nomes definidos pelo servidor SQL.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/** Valida contratos completos antes de aceitar as quatro tabelas. */
final class AuthorizationSchema {

	/**
	 * Prefixo validado e fixado no momento da construção.
	 *
	 * @var string
	 */
	private readonly string $prefix;

	/**
	 * Liga apenas à base dedicada do repositório.
	 *
	 * @param IdentityWpdb $database Ligação exclusiva.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $database->prefix ) ) {
			throw new RuntimeException( 'Prefixo de autorização inválido.' );
		}
		$this->prefix = $database->prefix;
	}

	/**
	 * Identificador limitado e preparado pelo chamador.
	 *
	 * @param string $suffix Sufixo interno fechado.
	 * @return string
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function table( string $suffix ): string {
		return $this->prefix . 'arn_authz_' . $suffix;
	}

	/**
	 * Define tipos, nulabilidade, collation e autoincremento.
	 *
	 * @return array
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function definitions(): array {
		$number = array( 'bigint unsigned', false, null, false );
		$id     = array( 'bigint unsigned', false, null, true );
		$text   = static fn( int $length, string $charset = 'ascii' ): array => array( 'varchar(' . $length . ')', false, $charset . '_bin', false );
		$json   = array( 'longtext', false, 'utf8mb4_bin', false );
		return array(
			'accounts' => array(
				'user_id'    => $number,
				'state'      => $text( 16 ),
				'revision'   => $number,
				'created_at' => $number,
				'updated_at' => $number,
			),
			'grants'   => array(
				'id'           => $id,
				'user_id'      => $number,
				'role'         => $text( 32 ),
				'scope_type'   => $text( 16 ),
				'targets_json' => $json,
				'starts_at'    => $number,
				'expires_at'   => array( 'bigint unsigned', true, null, false ),
				'revoked'      => array( 'tinyint unsigned', false, null, false ),
			),
			'audit'    => array(
				'id'            => $id,
				'user_id'       => $number,
				'actor_id'      => $number,
				'event'         => $text( 32 ),
				'revision'      => $number,
				'previous_json' => array( 'longtext', true, 'utf8mb4_bin', false ),
				'new_json'      => $json,
				'reason'        => $text( 500, 'utf8mb4' ),
				'decision_ref'  => $text( 120 ),
				'occurred_at'   => $number,
			),
			'schema'   => array(
				'id'      => $number,
				'version' => $number,
			),
		);
	}

	/**
	 * Instala apenas tabelas ausentes, recusando incompatibilidades existentes.
	 *
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function install(): void {
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$missing = array();
		foreach ( $this->definitions() as $suffix => $fields ) {
			$exists = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $this->table( $suffix ) ) );
			if ( array() === $exists ) {
				$missing[] = $suffix;
			} else {
				$this->assertTable( $suffix, $fields );
				if ( 'schema' === $suffix ) {
					$this->assertVersion();
				}
			}
		}
		foreach ( $missing as $suffix ) {
			$parts = array();
			foreach ( $this->definitions()[ $suffix ] as $name => $definition ) {
				list( $type, $nullable, $collation, $auto ) = $definition;
				$fragment                                   = '%i ' . $type;
				if ( null !== $collation ) {
					$fragment .= ' CHARACTER SET ' . ( 'ascii_bin' === $collation ? 'ascii' : 'utf8mb4' ) . ' COLLATE ' . $collation;
				}
				$fragment .= $nullable ? ' NULL' : ' NOT NULL';
				$fragment .= $auto ? ' AUTO_INCREMENT' : '';
				$parts[]   = $this->database->prepare( $fragment, $name );
			}
			$parts[] = 'PRIMARY KEY (' . ( 'accounts' === $suffix ? 'user_id' : 'id' ) . ')';
			if ( in_array( $suffix, array( 'grants', 'audit' ), true ) ) {
				$parts[] = 'KEY user_history (user_id, id)';
			}
			if ( 'audit' === $suffix ) {
				$parts[] = 'UNIQUE KEY user_revision (user_id, revision)';
			}
			if ( 'grants' === $suffix ) {
				$hash    = substr( hash( 'sha256', $this->table( $suffix ) ), 0, 24 );
				$parts[] = $this->database->prepare( 'CONSTRAINT %i FOREIGN KEY (user_id) REFERENCES %i (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT', 'az_fk_' . $hash, $this->table( 'accounts' ) );
				$parts[] = $this->database->prepare( 'CONSTRAINT %i CHECK (JSON_VALID(targets_json))', 'az_json_' . $hash );
			}
			// A versão nasce na mesma instrução DDL, evitando uma janela com singleton vazio.
			$initial = 'schema' === $suffix ? ' SELECT 1 AS id, 1 AS version' : '';
			$this->execute( $this->database->prepare( 'CREATE TABLE %i (', $this->table( $suffix ) ) . implode( ', ', $parts ) . ') ENGINE=InnoDB DEFAULT CHARACTER SET ascii COLLATE ascii_bin' . $initial );
		}
		$this->validate();
	}

	/**
	 * Recusa schema ausente ou alterado. Nunca instala durante leituras.
	 *
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function validate(): void {
		foreach ( $this->definitions() as $suffix => $fields ) {
			$this->assertTable( $suffix, $fields );
		}
		$this->assertVersion();
	}

	/**
	 * Exige singleton de versão conhecida.
	 *
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function assertVersion(): void {
		$rows = $this->rows( $this->database->prepare( 'SELECT id, version FROM %i', $this->table( 'schema' ) ) );
		if ( 1 !== count( $rows ) || '1' !== (string) $rows[0]->id || '1' !== (string) $rows[0]->version ) {
			throw new RuntimeException( 'Versão de autorização inválida.' );
		}
	}

	/**
	 * Verifica estrutura, índices completos e referências restritas.
	 *
	 * @param string $suffix Sufixo interno.
	 * @param array  $expected Campos esperados.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function assertTable( string $suffix, array $expected ): void {
		$table   = $this->table( $suffix );
		$engines = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
		if ( 1 !== count( $engines ) || 'InnoDB' !== $engines[0]->ENGINE ) {
			throw new RuntimeException( 'Motor de autorização inválido.' );
		}
		$fields = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
		if ( count( $fields ) !== count( $expected ) ) {
			throw new RuntimeException( 'Colunas de autorização inválidas.' );
		}
		foreach ( $fields as $field ) {
			$actual = array( preg_replace( '/\b(bigint|tinyint)\([0-9]+\)/', '$1', $field->Type ), 'YES' === $field->Null, $field->Collation, 'auto_increment' === $field->Extra );
			if ( ( $expected[ $field->Field ] ?? null ) !== $actual || ! in_array( $field->Extra, array( '', 'auto_increment' ), true ) ) {
				throw new RuntimeException( 'Tipo de autorização inválido.' );
			}
		}
		$indexes = array();
		foreach ( $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) ) as $index ) {
			if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
				throw new RuntimeException( 'Índice de autorização inválido.' );
			}
			$indexes[ $index->Key_name ]['unique']                                = '0' === (string) $index->Non_unique;
			$indexes[ $index->Key_name ]['columns'][ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		$required = array( 'PRIMARY' => array( true, array( 'accounts' === $suffix ? 'user_id' : 'id' ) ) );
		if ( in_array( $suffix, array( 'grants', 'audit' ), true ) ) {
			$required['user_history'] = array( false, array( 'user_id', 'id' ) );
		}
		if ( 'audit' === $suffix ) {
			$required['user_revision'] = array( true, array( 'user_id', 'revision' ) );
		}
		if ( count( $indexes ) !== count( $required ) ) {
			throw new RuntimeException( 'Chaves de autorização inválidas.' );
		}
		foreach ( $required as $name => $definition ) {
			$index   = $indexes[ $name ] ?? array();
			$columns = $index['columns'] ?? array();
			ksort( $columns );
			if ( array( $index['unique'] ?? null, array_values( $columns ) ) !== $definition ) {
				throw new RuntimeException( 'Chave de autorização inválida.' );
			}
		}
		$foreign = $this->rows( $this->database->prepare( 'SELECT (k.REFERENCED_TABLE_SCHEMA = DATABASE()) AS target_local, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = %s AND k.REFERENCED_TABLE_NAME IS NOT NULL', $table ) );
		if ( 'grants' !== $suffix ) {
			if ( array() !== $foreign ) {
				throw new RuntimeException( 'Referência de autorização inesperada.' );
			}
			return;
		}
		if ( 1 !== count( $foreign ) || '1' !== (string) $foreign[0]->target_local || 'user_id' !== $foreign[0]->COLUMN_NAME || $this->table( 'accounts' ) !== $foreign[0]->REFERENCED_TABLE_NAME || 'user_id' !== $foreign[0]->REFERENCED_COLUMN_NAME || 'RESTRICT' !== $foreign[0]->UPDATE_RULE || 'RESTRICT' !== $foreign[0]->DELETE_RULE ) {
			throw new RuntimeException( 'Referência de autorização inválida.' );
		}
		// t.* inclui ENFORCED onde existe (MySQL), sem consultar uma coluna ausente em MariaDB.
		$checks = $this->rows( $this->database->prepare( "SELECT c.CHECK_CLAUSE, t.* FROM information_schema.CHECK_CONSTRAINTS c JOIN information_schema.TABLE_CONSTRAINTS t ON t.CONSTRAINT_SCHEMA = c.CONSTRAINT_SCHEMA AND t.CONSTRAINT_NAME = c.CONSTRAINT_NAME WHERE t.TABLE_SCHEMA = DATABASE() AND t.TABLE_NAME = %s AND t.CONSTRAINT_TYPE = 'CHECK'", $table ) );
		if ( 1 !== count( $checks ) || 'json_validtargets_json' !== preg_replace( '/[\s`()]/', '', strtolower( $checks[0]->CHECK_CLAUSE ) ) || ( property_exists( $checks[0], 'ENFORCED' ) && 'YES' !== $checks[0]->ENFORCED ) ) {
			throw new RuntimeException( 'Validação JSON de autorização inválida.' );
		}
	}

	/**
	 * Executa DDL preparado sem resultados parciais aceites.
	 *
	 * @param string $sql Comando interno.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Schema de autorização indisponível.' );
		}
	}

	/**
	 * Lê metadata, recusando resultados ausentes por erro.
	 *
	 * @param string $sql Consulta preparada.
	 * @return array
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function rows( string $sql ): array {
		$result = $this->database->get_results( $sql );
		if ( ! is_array( $result ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Metadata de autorização indisponível.' );
		}
		return $result;
	}
}
