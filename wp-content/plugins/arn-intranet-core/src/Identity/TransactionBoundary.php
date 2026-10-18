<?php
/**
 * Estado transaccional controlado da ligação dedicada.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use RuntimeException;

/**
 * Mantém apenas estados iniciados pela ligação dedicada. Não tenta inferir
 * transacções através de contadores ou variáveis específicas do motor.
 */
final class TransactionBoundary {

	/** Estado sem ligação utilizável. */
	private const UNAVAILABLE = 'unavailable';

	/** Ligação pronta, sem transacção. */
	private const IDLE = 'idle';

	/** Transacção controlada activa. */
	private const ACTIVE = 'active';

	/** Transacção que exige rollback. */
	private const FAILED = 'failed';

	/** Operação de início. */
	private const BEGIN = 'begin';

	/** Operação de commit. */
	private const COMMIT = 'commit';

	/** Operação de rollback. */
	private const ROLLBACK = 'rollback';

	/** Criação de savepoint. */
	private const SAVEPOINT = 'savepoint';

	/** Rollback até savepoint. */
	private const ROLLBACK_TO = 'rollback_to';

	/** Libertação de savepoint. */
	private const RELEASE_SAVEPOINT = 'release_savepoint';

	/** Configuração de isolamento permitida. */
	private const SET_ISOLATION = 'set_isolation';

	/** Consulta de leitura. */
	private const READ_STATEMENT = 'read_statement';

	/** Consulta DML de escrita. */
	private const WRITE_STATEMENT = 'write_statement';

	/** Consulta DDL, apenas fora da transacção. */
	private const DDL_STATEMENT = 'ddl_statement';

	/** Consulta fora do contrato fechado. */
	private const OTHER_STATEMENT = 'other_statement';

	/**
	 * Estado actual da fronteira.
	 *
	 * @var string
	 */
	private string $state = self::UNAVAILABLE;

	/**
	 * Operação preparada e ainda não confirmada.
	 *
	 * @var string|null
	 */
	private ?string $pending = null;

	/** Marca uma ligação nova, normalizada e sem transacção. */
	public function markReady(): void {
		$this->state   = self::IDLE;
		$this->pending = null;
	}

	/** Invalida a fronteira após fecho ou perda da ligação. */
	public function markUnavailable(): void {
		$this->state   = self::UNAVAILABLE;
		$this->pending = null;
	}

	/**
	 * Valida um comando e devolve a forma a enviar ao servidor.
	 *
	 * @param string $sql SQL solicitado.
	 * @return string SQL canónico ou original autorizado.
	 * @throws RuntimeException Quando a fronteira não é segura.
	 */
	public function prepare( string $sql ): string {
		if ( self::UNAVAILABLE === $this->state || null !== $this->pending ) {
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}

		$statement = self::classify( $sql );
		$kind      = $statement['kind'];

		if ( self::FAILED === $this->state && self::ROLLBACK !== $kind ) {
			throw new RuntimeException( 'Transacção requer rollback.' );
		}

		switch ( $kind ) {
			case self::BEGIN:
				$this->requireState( self::IDLE, 'Transacção aninhada recusada.' );
				$prepared = 'START TRANSACTION';
				break;

			case self::COMMIT:
				$this->requireState( self::ACTIVE, 'Commit sem transacção recusado.' );
				$prepared = 'COMMIT AND NO CHAIN NO RELEASE';
				break;

			case self::ROLLBACK:
				if ( ! in_array( $this->state, array( self::ACTIVE, self::FAILED ), true ) ) {
					throw new RuntimeException( 'Rollback sem transacção recusado.' );
				}
				$prepared = 'ROLLBACK AND NO CHAIN NO RELEASE';
				break;

			case self::SAVEPOINT:
			case self::ROLLBACK_TO:
			case self::RELEASE_SAVEPOINT:
				$this->requireState( self::ACTIVE, 'Savepoint sem transacção recusado.' );
				$prepared = $statement['sql'];
				break;

			case self::SET_ISOLATION:
				$this->requireState( self::IDLE, 'Isolamento durante transacção recusado.' );
				$prepared = 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED';
				break;

			case self::READ_STATEMENT:
			case self::WRITE_STATEMENT:
				$prepared = $statement['sql'];
				break;

			case self::DDL_STATEMENT:
				$this->requireState( self::IDLE, 'DDL durante transacção recusado.' );
				$prepared = $statement['sql'];
				break;

			case self::OTHER_STATEMENT:
				throw new RuntimeException( 'Comando SQL fora do contrato recusado.' );

			default:
				throw new RuntimeException( 'Fronteira transaccional recusada.' );
		}

		$this->pending = $kind;
		return $prepared;
	}

	/**
	 * Valida uma leitura auxiliar executada pelo wpdb enquanto prepara a
	 * consulta exterior. Não altera o estado nem o comando pendente.
	 *
	 * @param string $sql SQL auxiliar.
	 * @return string SQL sem comentários ordinários ou terminador final.
	 * @throws RuntimeException Quando a leitura não é segura.
	 */
	public function prepareNestedRead( string $sql ): string {
		if ( in_array( $this->state, array( self::UNAVAILABLE, self::FAILED ), true ) ) {
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		$statement = self::classify( $sql );
		if ( self::READ_STATEMENT !== $statement['kind'] ) {
			throw new RuntimeException( 'Consulta interna não autorizada.' );
		}
		return $statement['sql'];
	}

	/**
	 * Cancela uma consulta recusada antes de chegar ao motor.
	 *
	 * @throws RuntimeException Quando não existe consulta preparada.
	 */
	public function cancelPrepared(): void {
		if ( null === $this->pending ) {
			throw new RuntimeException( 'Comando transaccional pendente ausente.' );
		}
		$this->pending = null;
	}

	/**
	 * Confirma o resultado do comando preparado.
	 *
	 * @param bool $succeeded O motor aceitou o comando sem erro.
	 * @param bool $connection_lost A ligação ficou indisponível.
	 * @throws RuntimeException Quando não existe comando pendente.
	 */
	public function confirm( bool $succeeded, bool $connection_lost = false ): void {
		if ( null === $this->pending ) {
			throw new RuntimeException( 'Comando transaccional pendente ausente.' );
		}

		$kind          = $this->pending;
		$this->pending = null;

		if ( $connection_lost ) {
			$this->state = self::UNAVAILABLE;
			return;
		}

		if ( ! $succeeded ) {
			if ( self::ACTIVE === $this->state || in_array( $kind, array( self::COMMIT, self::ROLLBACK ), true ) ) {
				$this->state = self::FAILED;
			}
			return;
		}

		if ( self::BEGIN === $kind ) {
			$this->state = self::ACTIVE;
			return;
		}

		if ( in_array( $kind, array( self::COMMIT, self::ROLLBACK ), true ) ) {
			$this->state = self::IDLE;
		}
	}

	/**
	 * Indica se existe uma transacção controlada e utilizável.
	 *
	 * @return bool Verdadeiro quando existe transacção controlada activa.
	 * @throws RuntimeException Quando o estado não é seguro.
	 */
	public function inTransaction(): bool {
		if ( in_array( $this->state, array( self::UNAVAILABLE, self::FAILED ), true ) || null !== $this->pending ) {
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		return self::ACTIVE === $this->state;
	}

	/**
	 * Confirma o estado exigido pela operação.
	 *
	 * @param string $expected Estado obrigatório.
	 * @param string $message Mensagem estática de recusa.
	 * @throws RuntimeException Quando o estado não corresponde.
	 */
	private function requireState( string $expected, string $message ): void {
		if ( $expected !== $this->state ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem interna fechada do domínio.
			throw new RuntimeException( $message );
		}
	}

	/**
	 * Classifica uma instrução SQL segura.
	 *
	 * @param string $sql SQL solicitado.
	 * @return array{kind:string,sql:string} Classificação e SQL normalizado.
	 * @throws RuntimeException Quando a instrução não é segura.
	 */
	private static function classify( string $sql ): array {
		$cleaned = self::sanitize( $sql );
		if ( '' === $cleaned ) {
			throw new RuntimeException( 'Comando SQL vazio.' );
		}

		$normalized = strtoupper( trim( (string) preg_replace( '/\\s+/', ' ', $cleaned ) ) );

		if ( 1 === preg_match( '/^(START TRANSACTION|BEGIN(?: WORK)?)$/D', $normalized ) ) {
			return array(
				'kind' => self::BEGIN,
				'sql'  => 'START TRANSACTION',
			);
		}
		if ( 1 === preg_match( '/^COMMIT(?: WORK)?$/D', $normalized ) ) {
			return array(
				'kind' => self::COMMIT,
				'sql'  => 'COMMIT',
			);
		}
		if ( 1 === preg_match( '/^ROLLBACK(?: WORK)?$/D', $normalized ) ) {
			return array(
				'kind' => self::ROLLBACK,
				'sql'  => 'ROLLBACK',
			);
		}
		if ( 1 === preg_match( '/^SAVEPOINT [A-Z0-9_]+$/D', $normalized ) ) {
			return array(
				'kind' => self::SAVEPOINT,
				'sql'  => $cleaned,
			);
		}
		if ( 1 === preg_match( '/^ROLLBACK(?: WORK)? TO(?: SAVEPOINT)? [A-Z0-9_]+$/D', $normalized ) ) {
			return array(
				'kind' => self::ROLLBACK_TO,
				'sql'  => $cleaned,
			);
		}
		if ( 1 === preg_match( '/^RELEASE SAVEPOINT [A-Z0-9_]+$/D', $normalized ) ) {
			return array(
				'kind' => self::RELEASE_SAVEPOINT,
				'sql'  => $cleaned,
			);
		}
		if ( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' === $normalized ) {
			return array(
				'kind' => self::SET_ISOLATION,
				'sql'  => $cleaned,
			);
		}

		if (
			1 === preg_match( '/^(START TRANSACTION|BEGIN|COMMIT|ROLLBACK)\\b/D', $normalized ) ||
			1 === preg_match(
				'/^SET(?: (?:SESSION|LOCAL))? (?:@@(?:(?:SESSION|LOCAL)\\.)?)?AUTOCOMMIT\\s*(?:=|:=)/D',
				$normalized
			) ||
			1 === preg_match( '/^SET(?: (?:SESSION|LOCAL))? TRANSACTION\\b/D', $normalized ) ||
			1 === preg_match( '/^(XA|LOCK TABLES|UNLOCK TABLES|CALL|PREPARE|EXECUTE|DEALLOCATE PREPARE)\\b/D', $normalized )
		) {
			throw new RuntimeException( 'Fronteira transaccional não suportada.' );
		}

		if ( 1 === preg_match( '/^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\\b/D', $normalized ) ) {
			return array(
				'kind' => self::READ_STATEMENT,
				'sql'  => $cleaned,
			);
		}
		if ( 1 === preg_match( '/^(INSERT|UPDATE|DELETE|REPLACE)\\b/D', $normalized ) ) {
			return array(
				'kind' => self::WRITE_STATEMENT,
				'sql'  => $cleaned,
			);
		}
		if ( 1 === preg_match( '/^(CREATE|ALTER|DROP|RENAME|TRUNCATE)\\b/D', $normalized ) ) {
			return array(
				'kind' => self::DDL_STATEMENT,
				'sql'  => $cleaned,
			);
		}

		return array(
			'kind' => self::OTHER_STATEMENT,
			'sql'  => $cleaned,
		);
	}

	/**
	 * Remove comentários ordinários, rejeita comentários executáveis e impede
	 * múltiplas instruções. O conteúdo entre aspas é preservado integralmente.
	 *
	 * @param string $sql SQL solicitado.
	 * @return string SQL sem comentários ordinários ou terminador final.
	 * @throws RuntimeException Quando encontra sintaxe ambígua ou insegura.
	 */
	private static function sanitize( string $sql ): string {
		$length     = strlen( $sql );
		$output     = '';
		$quote      = null;
		$terminated = false;

		for ( $index = 0; $index < $length; ++$index ) {
			$char = $sql[ $index ];

			if ( null !== $quote ) {
				$output .= $char;
				if ( '\\' === $char && $index + 1 < $length ) {
					$output .= $sql[ ++$index ];
					continue;
				}
				if ( $char === $quote ) {
					if ( $index + 1 < $length && $sql[ $index + 1 ] === $quote ) {
						$output .= $sql[ ++$index ];
						continue;
					}
					$quote = null;
				}
				continue;
			}

			if ( in_array( $char, array( "'", '"', '`' ), true ) ) {
				if ( $terminated ) {
					throw new RuntimeException( 'Múltiplas instruções SQL recusadas.' );
				}
				$quote   = $char;
				$output .= $char;
				continue;
			}

			if ( '/' === $char && $index + 1 < $length && '*' === $sql[ $index + 1 ] ) {
				$prefix = substr( $sql, $index, 4 );
				if (
					str_starts_with( $prefix, '/*!' ) ||
					0 === strcasecmp( $prefix, '/*M!' ) ||
					str_starts_with( $prefix, '/*+' )
				) {
					throw new RuntimeException( 'Comentário SQL executável recusado.' );
				}
				$end = strpos( $sql, '*/', $index + 2 );
				if ( false === $end ) {
					throw new RuntimeException( 'Comentário SQL incompleto.' );
				}
				$output .= ' ';
				$index   = $end + 1;
				continue;
			}

			if (
				'#' === $char ||
				(
					'-' === $char &&
					$index + 2 < $length &&
					'-' === $sql[ $index + 1 ] &&
					ord( $sql[ $index + 2 ] ) <= 32
				)
			) {
				$end = strcspn( $sql, "\r\n", $index );
				if ( $end >= $length - $index ) {
					break;
				}
				$index  += $end - 1;
				$output .= ' ';
				continue;
			}

			if ( ';' === $char ) {
				$terminated = true;
				continue;
			}

			if ( $terminated ) {
				if ( ! ctype_space( $char ) ) {
					throw new RuntimeException( 'Múltiplas instruções SQL recusadas.' );
				}
				continue;
			}

			$output .= $char;
		}

		if ( null !== $quote ) {
			throw new RuntimeException( 'Literal SQL incompleto.' );
		}

		return trim( $output );
	}
}
