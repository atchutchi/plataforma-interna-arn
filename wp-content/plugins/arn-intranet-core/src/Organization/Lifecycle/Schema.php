<?php
/**
 * Schema explícito da persistência de ciclo profissional fictício.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Organization\Lifecycle;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// Identificadores preparados, metadata sem cache e erros exclusivamente constantes.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// Os campos de metadata conservam os nomes definidos pelo servidor SQL.
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/** Valida contratos completos antes de aceitar as três tabelas. */
final class Schema {

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
			throw new RuntimeException( 'Prefixo de ciclo profissional fictício inválido.' );
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
		if ( ! in_array( $suffix, array( 'header', 'batches', 'audit' ), true ) ) {
			throw new RuntimeException( 'Tabela local inválida.' ); }
		return $this->prefix . 'arn_lifecycle_' . $suffix;
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
			'header'  => array(
				'id'       => $number,
				'version'  => $number,
				'revision' => $number,
				'payload'  => $json,
			),
			'batches' => array(
				'id'      => $text( 64 ),
				'payload' => $json,
			),
			'audit'   => array(
				'id'            => $id,
				'actor_id'      => $number,
				'event'         => $text( 32 ),
				'revision'      => $number,
				'previous_json' => $json,
				'new_json'      => $json,
				'reason'        => $text( 500, 'utf8mb4' ),
				'occurred_at'   => $number,
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
				if ( 'header' === $suffix ) {
					$this->assertVersion();
				}
			}
		}
		if ( array() !== $missing && count( $missing ) !== count( $this->definitions() ) ) {
			throw new RuntimeException( 'Instalação parcial de ciclo profissional fictício recusada.' );
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
			$parts[] = 'PRIMARY KEY (id)';
			if ( 'audit' === $suffix ) {
				$parts[] = 'UNIQUE KEY global_revision (revision)'; }
			// A versão nasce na mesma instrução DDL, evitando uma janela com singleton vazio.
			$initial = 'header' === $suffix ? " SELECT 1 AS id, 1 AS version, 0 AS revision, '[]' AS payload" : '';
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
		$rows = $this->rows( $this->database->prepare( 'SELECT id, version FROM %i', $this->table( 'header' ) ) );
		if ( 1 !== count( $rows ) || '1' !== (string) $rows[0]->id || '1' !== (string) $rows[0]->version ) {
			throw new RuntimeException( 'Versão de ciclo profissional fictício inválida.' );
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
			throw new RuntimeException( 'Motor de ciclo profissional fictício inválido.' );
		}
		$fields = $this->rows( $this->database->prepare( 'SHOW FULL COLUMNS FROM %i', $table ) );
		if ( count( $fields ) !== count( $expected ) ) {
			throw new RuntimeException( 'Colunas de ciclo profissional fictício inválidas.' );
		}
		foreach ( $fields as $field ) {
			$actual = array( preg_replace( '/\b(bigint|tinyint)\([0-9]+\)/', '$1', $field->Type ), 'YES' === $field->Null, $field->Collation, 'auto_increment' === $field->Extra );
			if ( null !== $field->Default || ( $expected[ $field->Field ] ?? null ) !== $actual || ! in_array( $field->Extra, array( '', 'auto_increment' ), true ) ) {
				throw new RuntimeException( 'Tipo de ciclo profissional fictício inválido.' );
			}
		}
		$indexes = array();
		foreach ( $this->rows( $this->database->prepare( 'SHOW INDEX FROM %i', $table ) ) as $index ) {
			if ( null !== $index->Sub_part || 'BTREE' !== $index->Index_type ) {
				throw new RuntimeException( 'Índice de ciclo profissional fictício inválido.' );
			}
			$indexes[ $index->Key_name ]['unique']                                = '0' === (string) $index->Non_unique;
			$indexes[ $index->Key_name ]['columns'][ (int) $index->Seq_in_index ] = $index->Column_name;
		}
		$required = array( 'PRIMARY' => array( true, array( 'id' ) ) );
		if ( 'audit' === $suffix ) {
			$required['global_revision'] = array( true, array( 'revision' ) ); }
		if ( count( $indexes ) !== count( $required ) ) {
			throw new RuntimeException( 'Chaves de ciclo profissional fictício inválidas.' );
		}
		foreach ( $required as $name => $definition ) {
			$index   = $indexes[ $name ] ?? array();
			$columns = $index['columns'] ?? array();
			ksort( $columns );
			if ( array( $index['unique'] ?? null, array_values( $columns ) ) !== $definition ) {
				throw new RuntimeException( 'Chave de ciclo profissional fictício inválida.' );
			}
		}
		$foreign = $this->rows( $this->database->prepare( 'SELECT (k.REFERENCED_TABLE_SCHEMA = DATABASE()) AS target_local, k.COLUMN_NAME, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = DATABASE() AND k.TABLE_NAME = %s AND k.REFERENCED_TABLE_NAME IS NOT NULL', $table ) );
		if ( array() !== $foreign ) {
			throw new RuntimeException( 'Referência de ciclo profissional fictício inesperada.' ); }
	}

	/**
	 * Executa DDL preparado sem resultados parciais aceites.
	 *
	 * @param string $sql Comando interno.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Schema de ciclo profissional fictício indisponível.' );
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
			throw new RuntimeException( 'Metadata de ciclo profissional fictício indisponível.' );
		}
		return $result;
	}
}
