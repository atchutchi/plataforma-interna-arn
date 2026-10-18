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
	 * Savepoint privado que prova que a transacção pertence a esta ligação.
	 *
	 * @var string|null
	 */
	private ?string $transaction_guard = null;

	/**
	 * Sequência local para nomes de savepoint sem colisões.
	 *
	 * @var int
	 */
	private int $guard_sequence = 0;

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
			$this->transaction_guard = null;
			$this->guard_sequence    = 0;
			return true;
		} catch ( Throwable ) {
			$this->transaction_guard = null;
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
	 * Observa apenas as fronteiras que esta ligação controla directamente.
	 *
	 * @param string $query SQL.
	 * @return bool|int|\mysqli_result|null
	 */
	public function query( $query ) {
		$sql     = is_string( $query ) ? $query : '';
		$command = self::transactionCommand( $sql );

		if ( 'begin' === $command && null !== $this->transaction_guard ) {
			$this->last_error = 'Operação transaccional recusada.';
			return false;
		}

		$result = parent::query( $query );
		if ( false === $result || '' !== $this->last_error ) {
			if ( in_array( $command, array( 'commit', 'rollback' ), true ) ) {
				$this->transaction_guard = null;
			}
			return $result;
		}

		if ( 'begin' === $command ) {
			if ( ! $this->installTransactionGuard() ) {
				$this->directStatement( 'ROLLBACK' );
				$this->transaction_guard = null;
				$this->last_error        = 'Operação transaccional não confirmada.';
				return false;
			}
			return $result;
		}

		if ( in_array( $command, array( 'commit', 'rollback' ), true ) ) {
			$this->transaction_guard = null;
			if ( self::chainsTransaction( $sql ) && ! $this->installTransactionGuard() ) {
				$this->directStatement( 'ROLLBACK' );
				$this->last_error = 'Operação transaccional não confirmada.';
				return false;
			}
		}

		return $result;
	}

	/**
	 * Confirma o estado desta ligação sem variável específica de MariaDB.
	 *
	 * @throws RuntimeException Quando a ligação ou o estado não podem ser garantidos.
	 */
	public function inTransaction(): bool {
		if ( ! $this->hasDedicatedConnection() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		if ( ! $this->sessionAutocommit() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- A ligação dedicada não admite autocommit desligado.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		if ( null !== $this->transaction_guard ) {
			$this->validateTransactionGuard();
			return true;
		}
		return $this->probeTransactionAbsence();
	}

	/**
	 * Instala o savepoint privado depois de START ou de AND CHAIN.
	 */
	private function installTransactionGuard(): bool {
		if ( ! $this->hasDedicatedConnection() ) {
			$this->transaction_guard = 'arn_tx_test_guard';
			return true;
		}
		$name   = $this->nextGuardName( 'owned' );
		$result = $this->directStatement( 'SAVEPOINT `' . $name . '`' );
		if ( ! $result['success'] ) {
			return false;
		}
		$this->transaction_guard = $name;
		return true;
	}

	/**
	 * RELEASE seguido de SAVEPOINT prova que a guarda continua na transacção.
	 *
	 * @throws RuntimeException Quando a guarda desapareceu ou não pode ser recriada.
	 */
	private function validateTransactionGuard(): void {
		$name    = (string) $this->transaction_guard;
		$release = $this->directStatement( 'RELEASE SAVEPOINT `' . $name . '`' );
		if ( ! $release['success'] ) {
			$this->transaction_guard = null;
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		$restore = $this->directStatement( 'SAVEPOINT `' . $name . '`' );
		if ( ! $restore['success'] ) {
			$this->transaction_guard = null;
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
	}

	/**
	 * Fora de transacção, SAVEPOINT é descartado e RELEASE devolve 1305.
	 * Se RELEASE funcionar, existe uma transacção externa não pertencente à API.
	 *
	 * @throws RuntimeException Quando existe transacção externa ou falha de sonda.
	 */
	private function probeTransactionAbsence(): bool {
		$name = $this->nextGuardName( 'probe' );
		$save = $this->directStatement( 'SAVEPOINT `' . $name . '`' );
		if ( ! $save['success'] ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		$release = $this->directStatement( 'RELEASE SAVEPOINT `' . $name . '`' );
		if ( $release['success'] ) {
			// A transacção externa permanece activa, mas não é adoptada pela ligação.
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		if ( 1305 === $release['errno'] ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
		throw new RuntimeException( 'Estado transaccional indisponível.' );
	}

	/**
	 * Lê o autocommit da própria sessão sem passar pelo wrapper observado.
	 *
	 * @throws RuntimeException Quando a leitura falha.
	 */
	private function sessionAutocommit(): bool {
		$result = $this->directQuery( 'SELECT @@session.autocommit' );
		if ( ! $result['success'] || ! $result['result'] instanceof \mysqli_result ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		$row = $result['result']->fetch_row();
		$result['result']->free();
		if ( ! is_array( $row ) || ! isset( $row[0] ) || ! in_array( (string) $row[0], array( '0', '1' ), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		return '1' === (string) $row[0];
	}

	/**
	 * Executa instrução directa e devolve apenas sucesso e código do motor.
	 *
	 * @param string $sql Instrução sem dados externos.
	 * @return array{success:bool,errno:int}
	 */
	private function directStatement( string $sql ): array {
		$result = $this->directQuery( $sql );
		if ( $result['result'] instanceof \mysqli_result ) {
			$result['result']->free();
		}
		return array(
			'success' => $result['success'],
			'errno'   => $result['errno'],
		);
	}

	/**
	 * Executa uma query directa no handle dedicado, sem reconnect e sem wpdb.
	 *
	 * @param string $sql Instrução controlada internamente.
	 * @return array{success:bool,errno:int,result:bool|\mysqli_result}
	 */
	private function directQuery( string $sql ): array {
		if ( ! $this->hasDedicatedConnection() ) {
			return array(
				'success' => false,
				'errno'   => 0,
				'result'  => false,
			);
		}
		$reporting = ( new \mysqli_driver() )->report_mode; // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Estado global preservado.
		mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- A falha é convertida em estado fechado.
		try {
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- Query interna no handle dedicado, sem dados externos.
			$result = $this->dbh->query( $sql );
			return array(
				'success' => false !== $result,
				'errno'   => (int) $this->dbh->errno,
				'result'  => $result,
			);
		} catch ( \mysqli_sql_exception $exception ) {
			return array(
				'success' => false,
				'errno'   => (int) $exception->getCode(),
				'result'  => false,
			);
		} finally {
			mysqli_report( $reporting ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo global.
		}
	}

	/**
	 * Confirma que a sessão mysqli original continua disponível.
	 */
	private function hasDedicatedConnection(): bool {
		return isset( $this->ready, $this->dbh ) && true === $this->ready && $this->dbh instanceof \mysqli;
	}

	/**
	 * Nome interno seguro e inferior ao limite de identificadores do motor.
	 *
	 * @param string $purpose owned ou probe.
	 */
	private function nextGuardName( string $purpose ): string {
		++$this->guard_sequence;
		$thread = $this->hasDedicatedConnection() ? (int) $this->dbh->thread_id : 0;
		return 'arn_' . $purpose . '_' . dechex( $thread ) . '_' . dechex( $this->guard_sequence );
	}

	/**
	 * Identifica apenas início e fim explícitos da transacção.
	 *
	 * @param string $sql SQL.
	 */
	private static function transactionCommand( string $sql ): string {
		$normalized = strtoupper( trim( (string) preg_replace( '/\s+/', ' ', $sql ) ) );
		if ( 1 === preg_match( '/^(START\s+TRANSACTION|BEGIN)(\s|$)/', $normalized ) ) {
			return 'begin';
		}
		if ( 1 === preg_match( '/^COMMIT(\s|$)/', $normalized ) ) {
			return 'commit';
		}
		if ( 1 === preg_match( '/^ROLLBACK(?!\s+TO\b)(\s|$)/', $normalized ) ) {
			return 'rollback';
		}
		return 'other';
	}

	/**
	 * COMMIT/ROLLBACK AND CHAIN inicia outra transacção na mesma sessão.
	 *
	 * @param string $sql SQL.
	 */
	private static function chainsTransaction( string $sql ): bool {
		return 1 === preg_match( '/\bAND\s+(?:NO\s+)?CHAIN\b/i', $sql );
	}
}
