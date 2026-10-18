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
	 * Observador separado da sessão principal.
	 *
	 * @var \mysqli|null
	 */
	private ?\mysqli $transaction_observer = null;

	/**
	 * Uma falha do observador é terminal para esta instância.
	 *
	 * @var bool
	 */
	private bool $transaction_observer_failed = false;

	/**
	 * Converte falhas iniciais em erro estático, sem saída nem wp_die.
	 * O construtor nativo chama este método mesmo quando WP_DEBUG está activo.
	 *
	 * @param bool $allow_bail Argumento nativo, deliberadamente ignorado.
	 * @return true Ligação dedicada pronta.
	 * @throws RuntimeException Quando a ligação inicial não está disponível.
	 */
	public function db_connect( $allow_bail = true ) {
		$show_errors      = $this->hide_errors();
		$suppress_errors  = $this->suppress_errors( true );
		$mysqli_reporting = ( new \mysqli_driver() )->report_mode; // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Preserva o modo global que o core altera durante db_connect.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Impede saída de warnings com credenciais durante a ligação dedicada.
		set_error_handler(
			static function (): never {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante, sem dados da ligação.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
		);
		try {
			if ( ! parent::db_connect( false ) || ! $this->ready ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
			return true;
		} catch ( Throwable ) {
			try {
				$this->close();
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Uma falha de limpeza também recebe apenas a mensagem estática abaixo.
				// Não propagar detalhes internos de uma ligação parcialmente aberta.
			}
			$this->error      = null;
			$this->last_error = '';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- A causa original não é propagada porque pode conter dados protegidos.
			throw new RuntimeException( 'Ligação dedicada indisponível.' );
		} finally {
			restore_error_handler();
			mysqli_report( $mysqli_reporting ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo anterior, sem afectar a ligação WordPress principal.
			$this->show_errors( $show_errors );
			$this->suppress_errors( $suppress_errors );
		}
	}

	/**
	 * WordPress chama este método antes de repetir queries numa nova ligação.
	 * Recusar evita que uma escrita seja repetida fora da transacção perdida.
	 *
	 * @param bool $allow_bail Argumento WordPress, deliberadamente ignorado.
	 * @return false A operação actual deve falhar, sem reconnect ou wp_die.
	 */
	public function check_connection( $allow_bail = true ) {
		return false;
	}

	/**
	 * Confirma o estado real da sessão principal a partir de outra sessão.
	 *
	 * A própria consulta de introspecção não pode correr na sessão observada,
	 * porque essa leitura também pode criar um evento transaccional efémero.
	 *
	 * @return bool True apenas para uma transacção ACTIVE comprovada.
	 * @throws RuntimeException Quando o servidor não permite uma conclusão segura.
	 */
	public function inTransaction(): bool {
		$statement = null;
		try {
			if ( ! $this->ready || ! $this->dbh instanceof \mysqli ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}
			$thread_id = (int) $this->dbh->thread_id;
			if ( $thread_id < 1 ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}

			$observer = $this->transactionObserver();
			if ( (int) $observer->thread_id === $thread_id ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}

			$sql       = <<<'SQL'
SELECT
    target.INSTRUMENTED,
    instrument.ENABLED,
    consumer.ENABLED,
    current_event.STATE,
    current_event.END_EVENT_ID
FROM performance_schema.threads AS target
JOIN performance_schema.setup_instruments AS instrument
  ON instrument.NAME = 'transaction'
JOIN performance_schema.setup_consumers AS consumer
  ON consumer.NAME = 'events_transactions_current'
LEFT JOIN performance_schema.events_transactions_current AS current_event
  ON current_event.THREAD_ID = target.THREAD_ID
WHERE target.PROCESSLIST_ID = ?
SQL;
			$statement = $observer->prepare( $sql );
			if ( ! $statement || ! $statement->bind_param( 'i', $thread_id ) || ! $statement->execute() ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}

			$thread_instrumented = null;
			$instrument_enabled  = null;
			$consumer_enabled    = null;
			$transaction_state   = null;
			$end_event_id        = null;
			if ( ! $statement->bind_result( $thread_instrumented, $instrument_enabled, $consumer_enabled, $transaction_state, $end_event_id ) ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}

			$rows = array();
			while ( $statement->fetch() ) {
				$rows[] = array(
					'thread'     => $thread_instrumented,
					'instrument' => $instrument_enabled,
					'consumer'   => $consumer_enabled,
					'state'      => $transaction_state,
					'end'        => $end_event_id,
				);
			}
			if ( 1 !== count( $rows ) ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}
			if ( ! $this->ready || ! $this->dbh instanceof \mysqli || (int) $this->dbh->thread_id !== $thread_id ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}

			$row = $rows[0];
			if ( 'YES' !== $row['thread'] || 'YES' !== $row['instrument'] || 'YES' !== $row['consumer'] ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}
			if ( null === $row['state'] && null === $row['end'] ) {
				return false;
			}
			if ( 'ACTIVE' === $row['state'] && null === $row['end'] ) {
				return true;
			}
			if ( in_array( $row['state'], array( 'COMMITTED', 'ROLLED BACK' ), true ) && null !== $row['end'] ) {
				return false;
			}
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		} catch ( Throwable ) {
			$this->disableTransactionObserver();
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante; nunca propagar SQL, host ou credenciais.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		} finally {
			if ( $statement instanceof \mysqli_stmt ) {
				$statement->close();
			}
		}
	}

	/**
	 * Fecha a sessão de observação antes da sessão principal.
	 *
	 * @return bool Resultado do fecho da ligação principal.
	 */
	public function close() {
		$this->disableTransactionObserver();
		return parent::close();
	}

	/**
	 * Abre uma única sessão observadora com a mesma configuração protegida.
	 * Uma falha posterior não provoca nova tentativa automática.
	 *
	 * @return \mysqli Ligação observadora distinta.
	 * @throws RuntimeException Quando a sessão não pode ser aberta de forma segura.
	 */
	private function transactionObserver(): \mysqli {
		if ( $this->transaction_observer_failed ) {
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		if ( $this->transaction_observer instanceof \mysqli ) {
			return $this->transaction_observer;
		}

		$observer  = null;
		$reporting = ( new \mysqli_driver() )->report_mode; // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Preserva configuração global.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Converte warnings da ligação observadora em erro estático.
		set_error_handler(
			static function (): never {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}
		);
		mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- A classe converte qualquer falha em erro estático.
		try {
			$observer = mysqli_init(); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_init -- Segunda sessão necessária para não observar a própria consulta.
			if ( ! $observer instanceof \mysqli ) {
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}

			$host    = $this->dbhost;
			$port    = null;
			$socket  = null;
			$is_ipv6 = false;
			$parsed  = $this->parse_db_host( $this->dbhost );
			if ( $parsed ) {
				[ $host, $port, $socket, $is_ipv6 ] = $parsed;
			}
			if ( $is_ipv6 && extension_loaded( 'mysqlnd' ) ) {
				$host = '[' . $host . ']';
			}
			$client_flags = defined( 'MYSQL_CLIENT_FLAGS' ) ? MYSQL_CLIENT_FLAGS : 0;
			if ( ! mysqli_real_connect( $observer, $host, $this->dbuser, $this->dbpassword, null, null === $port ? null : (int) $port, $socket, $client_flags ) || 0 !== $observer->connect_errno ) { // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_real_connect -- Replica a configuração do wpdb sem reconnect.
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}
			$this->transaction_observer = $observer;
			return $observer;
		} catch ( Throwable ) {
			if ( $observer instanceof \mysqli ) {
				try {
					$observer->close();
				} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Falha de limpeza fica redigida.
					// Sem propagação de detalhes da ligação.
				}
			}
			$this->transaction_observer_failed = true;
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		} finally {
			mysqli_report( $reporting ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura configuração global.
			restore_error_handler();
		}
	}

	/**
	 * Fecha e invalida o observador sem tentar reconectar.
	 */
	private function disableTransactionObserver(): void {
		if ( $this->transaction_observer instanceof \mysqli ) {
			try {
				$this->transaction_observer->close();
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A falha de fecho não pode expor dados.
				// Nenhuma acção adicional é segura nesta ligação.
			}
		}
		$this->transaction_observer        = null;
		$this->transaction_observer_failed = true;
	}
}
