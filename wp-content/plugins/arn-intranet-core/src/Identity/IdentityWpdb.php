<?php
/**
 * Ligação WordPress dedicada que preserva a fronteira transaccional.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use RuntimeException;
use Throwable;

/**
 * Instanciar explicitamente com configuração protegida e prefixo do site.
 * Não reutilizar a ligação global nem efectuar queries de outros módulos.
 */
final class IdentityWpdb extends \wpdb {

	/**
	 * Regra pura inicializada antes do primeiro uso.
	 *
	 * @var TransactionBoundary|null
	 */
	private ?TransactionBoundary $transaction_boundary = null;

	/**
	 * Profundidade das leituras internas feitas pelo wpdb.
	 *
	 * @var int
	 */
	private int $query_depth = 0;

	/**
	 * Guardas instaladas no filtro query durante chamadas aninhadas.
	 *
	 * @var array<int,callable>
	 */
	private array $query_guards = array();

	/**
	 * Indica que um filtro tentou mudar a consulta antes da execução.
	 *
	 * @var bool
	 */
	private bool $pre_execution_rejection = false;

	/**
	 * Impede qualquer nova ligação depois da tentativa inicial.
	 *
	 * @var bool
	 */
	private bool $connection_attempted = false;

	/**
	 * Permite ao construtor nativo preparar a sessão uma única vez.
	 *
	 * @var bool
	 */
	private bool $connection_initializing = false;

	/**
	 * Converte falhas iniciais em erro estático, sem saída nem wp_die.
	 *
	 * @param bool $allow_bail Argumento nativo, deliberadamente ignorado.
	 * @return true Ligação dedicada pronta.
	 * @throws RuntimeException Quando a ligação inicial não está disponível.
	 */
	public function db_connect( $allow_bail = true ) {
		if ( $this->connection_attempted ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Reconexão da ligação dedicada recusada.' );
		}
		$this->connection_attempted    = true;
		$this->connection_initializing = true;

		$show_errors      = $this->hide_errors();
		$suppress_errors  = $this->suppress_errors( true );
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Preserva o modo global.
		$mysqli_reporting = ( new \mysqli_driver() )->report_mode;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Impede warnings com dados da ligação.
		set_error_handler(
			static function (): never {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
		);

		try {
			if ( ! parent::db_connect( false ) || ! $this->ready || ! $this->dbh instanceof \mysqli ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
			$this->normalizeSession();
			$this->boundary()->markReady();
			return true;
		} catch ( Throwable ) {
			$this->boundary()->markUnavailable();
			try {
				parent::close();
			} catch ( Throwable ) {
				// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Limpeza sem propagar detalhes.
				// Não propagar detalhes de uma ligação parcialmente aberta.
			}
			$this->ready      = false;
			$this->error      = null;
			$this->last_error = '';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Ligação dedicada indisponível.' );
		} finally {
			$this->connection_initializing = false;
			restore_error_handler();
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo global.
			mysqli_report( $mysqli_reporting );
			$this->show_errors( $show_errors );
			$this->suppress_errors( $suppress_errors );
		}
	}

	/**
	 * Nunca reconecta nem repete uma escrita depois de perder a sessão.
	 *
	 * @param bool $allow_bail Argumento WordPress, deliberadamente ignorado.
	 * @return false A operação deve falhar fechada.
	 */
	public function check_connection( $allow_bail = true ) {
		return false;
	}

	/**
	 * Executa SQL sob a fronteira controlada. As fronteiras usam a API mysqli
	 * e não passam pelo filtro global query.
	 *
	 * @param string $query Consulta solicitada.
	 * @return int|bool Resultado compatível com wpdb::query().
	 */
	public function query( $query ) {
		if ( ! is_string( $query ) || '' === trim( $query ) ) {
			$this->last_error = 'Consulta dedicada inválida.';
			return false;
		}

		if ( $this->query_depth > 0 ) {
			try {
				$nested = $this->boundary()->prepareNestedRead( $query );
			} catch ( Throwable ) {
				$this->last_error = 'Consulta interna recusada pela fronteira transaccional.';
				return false;
			}
			++$this->query_depth;
			try {
				return $this->executeParentQuery( $nested );
			} finally {
				--$this->query_depth;
			}
		}

		try {
			$prepared = $this->boundary()->prepare( $query );
		} catch ( Throwable ) {
			$this->last_error = 'Consulta recusada pela fronteira transaccional.';
			return false;
		}

		if ( $this->isBoundaryCommand( $prepared ) ) {
			return $this->executeBoundaryCommand( $prepared );
		}

		$this->pre_execution_rejection = false;
		++$this->query_depth;
		try {
			$result = $this->executeParentQuery( $prepared );
		} catch ( Throwable ) {
			--$this->query_depth;
			if ( $this->pre_execution_rejection ) {
				$this->pre_execution_rejection = false;
				try {
					$this->boundary()->cancelPrepared();
				} catch ( Throwable ) {
					$this->boundary()->markUnavailable();
				}
				$this->last_error = 'Alteração da consulta dedicada recusada.';
				return false;
			}
			$this->confirmQuery( false );
			$this->last_error = 'Consulta dedicada indisponível.';
			return false;
		}
		--$this->query_depth;

		$success = false !== $result && '' === $this->last_error;
		$this->confirmQuery( $success );
		return $result;
	}

	/**
	 * Devolve o estado controlado da transacção.
	 *
	 * @return bool Verdadeiro quando existe transacção activa.
	 * @throws RuntimeException Quando o estado deixou de ser seguro.
	 */
	public function inTransaction(): bool {
		return $this->boundary()->inTransaction();
	}

	/**
	 * Fecha a ligação e invalida a fronteira.
	 *
	 * @return bool Resultado nativo do fecho.
	 */
	public function close() {
		$this->boundary()->markUnavailable();
		$closed       = parent::close();
		$this->ready = false;
		return $closed;
	}

	/**
	 * Impede mudanças directas do charset depois da inicialização.
	 *
	 * @param mixed       $dbh Ligação nativa usada pelo construtor WordPress.
	 * @param string|null $charset Charset.
	 * @param string|null $collate Collation.
	 */
	public function set_charset( $dbh, $charset = null, $collate = null ) {
		$this->assertConnectionInitialization();
		parent::set_charset( $dbh, $charset, $collate );
	}

	/**
	 * Permite ao construtor WordPress normalizar os modos uma única vez.
	 *
	 * @param array $modes Modos SQL pedidos pelo construtor WordPress.
	 * @throws RuntimeException Depois da inicialização da ligação.
	 */
	public function set_sql_mode( $modes = array() ) {
		$this->assertConnectionInitialization();
		parent::set_sql_mode( $modes );
	}

	/**
	 * Impede troca directa da base depois da inicialização.
	 *
	 * @param string $db Base seleccionada.
	 * @param mixed  $dbh Ligação nativa.
	 */
	public function select( $db, $dbh = null ) {
		$this->assertConnectionInitialization();
		parent::select( $db, $dbh );
	}

	/**
	 * Impede acesso externo ao mysqli pela compatibilidade mágica do wpdb.
	 *
	 * @param string $name Propriedade pedida.
	 * @return mixed Valor permitido.
	 */
	public function __get( $name ) {
		if ( 'dbh' === $name ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Acesso directo à ligação dedicada recusado.' );
		}
		return parent::__get( $name );
	}

	/**
	 * Impede que isset exponha o handle nativo.
	 *
	 * @param string $name Propriedade consultada.
	 * @return bool Resultado para propriedades permitidas.
	 */
	public function __isset( $name ) {
		return 'dbh' === $name ? false : parent::__isset( $name );
	}

	/**
	 * @param string $name Propriedade alterada.
	 * @param mixed  $value Valor delegado quando permitido.
	 */
	public function __set( $name, $value ) {
		if ( 'dbh' === $name ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Acesso directo à ligação dedicada recusado.' );
		}
		parent::__set( $name, $value );
	}

	/**
	 * Impede a remoção externa do handle nativo.
	 *
	 * @param string $name Propriedade removida.
	 */
	public function __unset( $name ) {
		if ( 'dbh' === $name ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Acesso directo à ligação dedicada recusado.' );
		}
		parent::__unset( $name );
	}

	/**
	 * Executa parent::query() com uma guarda final que recusa qualquer
	 * alteração feita por filtros WordPress antes de a SQL chegar ao motor.
	 *
	 * @param string $sql Consulta já validada.
	 * @return int|bool Resultado wpdb.
	 */
	private function executeParentQuery( string $sql ): int|bool {
		$filters_available = function_exists( 'add_filter' ) && function_exists( 'remove_filter' );
		$previous          = end( $this->query_guards );
		$previous          = false === $previous ? null : $previous;

		if ( $filters_available && null !== $previous ) {
			remove_filter( 'query', $previous, PHP_INT_MAX );
		}

		$guard = function ( $filtered ) use ( $sql ) {
			if ( ! is_string( $filtered ) || $filtered !== $sql ) {
				$this->pre_execution_rejection = true;
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Alteração da consulta dedicada recusada.' );
			}
			return $filtered;
		};

		$this->query_guards[] = $guard;
		if ( $filters_available ) {
			add_filter( 'query', $guard, PHP_INT_MAX, 1 );
		}

		try {
			return parent::query( $sql );
		} finally {
			if ( $filters_available ) {
				remove_filter( 'query', $guard, PHP_INT_MAX );
			}
			array_pop( $this->query_guards );
			if ( $filters_available && null !== $previous ) {
				add_filter( 'query', $previous, PHP_INT_MAX, 1 );
			}
		}
	}

	/**
	 * Inicializa a regra sem depender do construtor do wpdb.
	 *
	 * @return TransactionBoundary Fronteira da ligação.
	 */
	private function boundary(): TransactionBoundary {
		return $this->transaction_boundary ??= new TransactionBoundary();
	}

	/**
	 * Normaliza uma sessão nova e nunca adopta uma transacção externa.
	 *
	 * @throws RuntimeException Quando a sessão não fica num estado conhecido.
	 */
	private function normalizeSession(): void {
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Isola o modo global.
		$reporting = ( new \mysqli_driver() )->report_mode;
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Falha convertida em mensagem estática.
		mysqli_report( MYSQLI_REPORT_OFF );
		try {
			if ( ! $this->dbh->autocommit( true ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}

			$modes = $this->dbh->query( 'SELECT @@SESSION.sql_mode' );
			if ( ! $modes instanceof \mysqli_result ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
			$row = $modes->fetch_row();
			$modes->free();
			if ( ! is_array( $row ) || ! array_key_exists( 0, $row ) || ! is_scalar( $row[0] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}

			$normalised = array();
			foreach ( array_filter( explode( ',', strtoupper( (string) $row[0] ) ), 'strlen' ) as $mode ) {
				$mode = trim( $mode );
				if ( 1 !== preg_match( '/^[A-Z0-9_]+$/D', $mode ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
					throw new RuntimeException( 'Ligação dedicada indisponível.' );
				}
				if ( 'NO_BACKSLASH_ESCAPES' !== $mode ) {
					$normalised[] = $mode;
				}
			}

			$sql_mode = sprintf(
				"SET SESSION sql_mode = '%s'",
				implode( ',', array_values( array_unique( $normalised ) ) )
			);
			if (
				true !== $this->dbh->query( $sql_mode ) ||
				true !== $this->dbh->query( 'SET SESSION completion_type = 0' )
			) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
		} finally {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo global.
			mysqli_report( $reporting );
		}
	}

	/**
	 * Recusa métodos públicos que contornariam a fronteira após o arranque.
	 *
	 * @throws RuntimeException Depois da inicialização da ligação.
	 */
	private function assertConnectionInitialization(): void {
		if ( ! $this->connection_initializing ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Alteração directa da sessão dedicada recusada.' );
		}
	}

	/**
	 * Distingue comandos canónicos executados directamente.
	 *
	 * @param string $sql Consulta preparada.
	 * @return bool Verdadeiro para fronteiras e savepoints.
	 */
	private function isBoundaryCommand( string $sql ): bool {
		return in_array(
			$sql,
			array(
				'START TRANSACTION',
				'COMMIT AND NO CHAIN NO RELEASE',
				'ROLLBACK AND NO CHAIN NO RELEASE',
				'SET TRANSACTION ISOLATION LEVEL READ COMMITTED',
			),
			true
		) || 1 === preg_match( '/^(SAVEPOINT|ROLLBACK(?: WORK)? TO|RELEASE SAVEPOINT)\b/i', $sql );
	}

	/**
	 * Executa uma fronteira canónica sem filtros SQL.
	 *
	 * @param string $sql Fronteira ou savepoint canónico.
	 * @return int|false Zero quando confirmada, false quando recusada.
	 */
	private function executeBoundaryCommand( string $sql ): int|false {
		$this->flush();
		$this->last_query = $sql;
		$this->func_call  = "\$db->query(\"$sql\")";
		++$this->num_queries;

		if ( ! $this->ready || ! $this->dbh instanceof \mysqli ) {
			$this->confirmQuery( false );
			$this->last_error = 'Ligação dedicada indisponível.';
			return false;
		}

		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Isola o modo global.
		$reporting = ( new \mysqli_driver() )->report_mode;
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Falha convertida em resultado wpdb.
		mysqli_report( MYSQLI_REPORT_OFF );
		try {
			$result = match ( $sql ) {
				'START TRANSACTION'                => $this->dbh->begin_transaction(),
				'COMMIT AND NO CHAIN NO RELEASE' => $this->dbh->commit(
					MYSQLI_TRANS_COR_AND_NO_CHAIN | MYSQLI_TRANS_COR_NO_RELEASE
				),
				'ROLLBACK AND NO CHAIN NO RELEASE' => $this->dbh->rollback(
					MYSQLI_TRANS_COR_AND_NO_CHAIN | MYSQLI_TRANS_COR_NO_RELEASE
				),
				default                             => $this->dbh->query( $sql ),
			};
		} catch ( Throwable ) {
			$result = false;
		} finally {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo global.
			mysqli_report( $reporting );
		}

		$success = true === $result;
		$this->confirmQuery( $success );
		$this->last_error = $success ? '' : 'Fronteira transaccional não confirmada.';
		return $success ? 0 : false;
	}

	/**
	 * Confirma o resultado sem propagar detalhes da ligação.
	 *
	 * @param bool $success Resultado do motor.
	 */
	private function confirmQuery( bool $success ): void {
		try {
			$this->boundary()->confirm( $success, $this->connectionLost() );
		} catch ( Throwable ) {
			$this->boundary()->markUnavailable();
		}
	}

	/**
	 * Detecta perda efectiva sem reconnect.
	 *
	 * @return bool Verdadeiro quando a ligação deixou de ser utilizável.
	 */
	private function connectionLost(): bool {
		if ( ! $this->ready || ! $this->dbh instanceof \mysqli ) {
			return true;
		}
		return in_array( (int) $this->dbh->errno, array( 2002, 2003, 2006, 2013 ), true );
	}
}
