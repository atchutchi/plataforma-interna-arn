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
	 * Estado transaccional conhecido desta sessão.
	 *
	 * @var bool
	 */
	private bool $in_transaction = false;

	/**
	 * Autocommit observado nesta sessão.
	 *
	 * @var bool
	 */
	private bool $autocommit = true;

	/**
	 * Falso quando a introspecção ou uma fronteira deixam de ser garantidas.
	 *
	 * @var bool
	 */
	private bool $state_known = true;

	/**
	 * Contagens Com_* observadas através de query().
	 *
	 * @var array{Com_begin:int,Com_commit:int,Com_rollback:int,Com_rollback_to_savepoint:int}
	 */
	private array $seen_commands = array(
		'Com_begin'                 => 0,
		'Com_commit'                => 0,
		'Com_rollback'              => 0,
		'Com_rollback_to_savepoint' => 0,
	);

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
		$connected = false;
		try {
			if ( ! parent::db_connect( false ) || ! $this->ready ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
			$connected = true;
		} catch ( Throwable ) {
			$this->state_known = false;
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
		$this->resetTransactionTracking();
		if ( ! $connected || ! $this->state_known ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Ligação dedicada indisponível.' );
		}
		return true;
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
	 * Observa START/COMMIT/ROLLBACK, DDL e autocommit emitidos por esta ligação.
	 * O modo mysqli STRICT não pode abortar o retorno false do wpdb.
	 *
	 * @param string $query SQL.
	 * @return bool|int|\mysqli_result|null
	 * @throws Throwable Quando o transporte falha fora de um erro SQL.
	 */
	public function query( $query ) {
		$reporting = ( new \mysqli_driver() )->report_mode; // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Isola o STRICT global durante o comando desta ligação.
		mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Falhas SQL voltam a false, sem excepções nem reconnect.
		try {
			$result = parent::query( $query );
		} catch ( \mysqli_sql_exception $exception ) {
			$this->last_error = $exception->getMessage();
			if ( in_array( (int) $exception->getCode(), array( 2002, 2003, 2006, 2013 ), true ) ) {
				$this->state_known    = false;
				$this->in_transaction = false;
			}
			$result = false;
		} catch ( Throwable $exception ) {
			$this->state_known = false;
			mysqli_report( $reporting ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo global antes de propagar.
			throw $exception;
		} finally {
			mysqli_report( $reporting ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo global da aplicação.
		}
		$sql = is_string( $this->last_query ) && '' !== $this->last_query ? $this->last_query : ( is_string( $query ) ? $query : '' );
		$this->observe( $sql, false !== $result && '' === $this->last_error );
		return $result;
	}

	/**
	 * Estado transaccional da ligação dedicada. Recusa o que não consegue garantir.
	 *
	 * @throws RuntimeException Quando a ligação, o autocommit implícito ou a introspecção falham.
	 */
	public function inTransaction(): bool {
		if ( ! $this->ready || ! $this->dbh instanceof \mysqli || ! $this->state_known ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		$session = $this->sessionSnapshot();
		if ( $session['autocommit'] !== $this->autocommit || $session['commands'] !== $this->seen_commands ) {
			$this->state_known = false;
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		if ( ! $this->autocommit && ! $this->in_transaction ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		return $this->in_transaction;
	}

	/**
	 * Reinicia o rastreio após uma ligação nova e alinhada com o servidor.
	 */
	private function resetTransactionTracking(): void {
		$this->in_transaction = false;
		$this->autocommit     = true;
		$this->state_known    = true;
		$this->seen_commands  = array(
			'Com_begin'                 => 0,
			'Com_commit'                => 0,
			'Com_rollback'              => 0,
			'Com_rollback_to_savepoint' => 0,
		);
		try {
			$session              = $this->sessionSnapshot();
			$this->autocommit     = $session['autocommit'];
			$this->seen_commands  = $session['commands'];
			$this->in_transaction = false;
			if ( ! $this->autocommit ) {
				$this->state_known = false;
			}
		} catch ( Throwable ) {
			$this->state_known = false;
		}
	}

	/**
	 * Actualiza o estado conhecido depois de cada SQL desta ligação.
	 *
	 * @param string $sql SQL efectivamente enviado.
	 * @param bool   $succeeded Transporte e motor aceitaram o comando.
	 */
	private function observe( string $sql, bool $succeeded ): void {
		if ( $this->connectionLost() ) {
			$this->state_known    = false;
			$this->in_transaction = false;
			return;
		}
		$kind = self::classify( $sql );
		if ( 'other' === $kind ) {
			return;
		}
		if ( ! $succeeded ) {
			if ( in_array( $kind, array( 'commit', 'rollback', 'ddl', 'autocommit_on', 'autocommit_off', 'unknown' ), true ) ) {
				$this->state_known = false;
			}
			return;
		}
		switch ( $kind ) {
			case 'begin':
				$this->in_transaction = true;
				$this->resyncSessionCounters();
				return;
			case 'commit':
				$this->in_transaction = false;
				$this->resyncSessionCounters();
				return;
			case 'rollback':
				$this->in_transaction = false;
				$this->resyncSessionCounters();
				return;
			case 'rollback_to':
				$this->resyncSessionCounters();
				return;
			case 'autocommit_off':
				$this->autocommit = false;
				$this->resyncSessionCounters();
				return;
			case 'autocommit_on':
				$this->autocommit     = true;
				$this->in_transaction = false;
				$this->resyncSessionCounters();
				return;
			case 'ddl':
				$this->in_transaction = false;
				$this->resyncSessionCounters();
				return;
			default:
				$this->state_known = false;
		}
	}

	/**
	 * Alinha as contagens Com_* com o servidor após uma fronteira desta ligação.
	 */
	private function resyncSessionCounters(): void {
		try {
			$session             = $this->sessionSnapshot();
			$this->seen_commands = $session['commands'];
			if ( $session['autocommit'] !== $this->autocommit ) {
				$this->state_known = false;
			}
		} catch ( Throwable ) {
			$this->state_known = false;
		}
	}

	/**
	 * Autocommit e Com_* da sessão, sem PROCESS, sem variável só da MariaDB e sem wpdb::query.
	 *
	 * @return array{autocommit:bool,commands:array{Com_begin:int,Com_commit:int,Com_rollback:int,Com_rollback_to_savepoint:int}}
	 * @throws RuntimeException Quando a introspecção falha.
	 */
	private function sessionSnapshot(): array {
		if ( ! $this->dbh instanceof \mysqli ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		$reporting = ( new \mysqli_driver() )->report_mode; // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Introspecção directa, sem STRICT global.
		mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Falha de introspecção é recusa, não excepção pública.
		try {
			$autocommit = $this->sessionScalar( 'SELECT @@session.autocommit' );
			if ( ! in_array( $autocommit, array( '0', '1' ), true ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}
			// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- Introspecção da sessão dedicada, fora de wpdb::query.
			$status = $this->dbh->query( "SHOW SESSION STATUS WHERE Variable_name IN ('Com_begin','Com_commit','Com_rollback','Com_rollback_to_savepoint')" );
			if ( ! $status instanceof \mysqli_result ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Estado transaccional indisponível.' );
			}
			$values = array();
			foreach ( $status as $row ) {
				if ( ! is_array( $row ) ) {
					$status->free();
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
					throw new RuntimeException( 'Estado transaccional indisponível.' );
				}
				$name  = isset( $row['Variable_name'] ) ? (string) $row['Variable_name'] : ( isset( $row['variable_name'] ) ? (string) $row['variable_name'] : '' );
				$value = isset( $row['Value'] ) ? (string) $row['Value'] : ( isset( $row['value'] ) ? (string) $row['value'] : '' );
				if ( ! isset( $this->seen_commands[ $name ] ) || 1 !== preg_match( '/^\d+$/D', $value ) ) {
					$status->free();
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
					throw new RuntimeException( 'Estado transaccional indisponível.' );
				}
				$values[ $name ] = (int) $value;
			}
			$status->free();
			foreach ( array( 'Com_begin', 'Com_commit', 'Com_rollback' ) as $required ) {
				if ( ! isset( $values[ $required ] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
					throw new RuntimeException( 'Estado transaccional indisponível.' );
				}
			}
			if ( ! isset( $values['Com_rollback_to_savepoint'] ) ) {
				$values['Com_rollback_to_savepoint'] = $this->seen_commands['Com_rollback_to_savepoint'];
			}
			return array(
				'autocommit' => '1' === $autocommit,
				'commands'   => $values,
			);
		} catch ( \mysqli_sql_exception ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		} finally {
			mysqli_report( $reporting ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo global.
		}
	}

	/**
	 * Leitura escalar directa nesta sessão mysqli.
	 *
	 * @param string $sql SELECT de uma coluna.
	 * @throws RuntimeException Quando a introspecção falha.
	 */
	private function sessionScalar( string $sql ): string {
		// phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_query -- Leitura escalar da sessão dedicada, fora de wpdb::query.
		$result = $this->dbh->query( $sql );
		if ( ! $result instanceof \mysqli_result ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		$row = $result->fetch_row();
		$result->free();
		if ( ! is_array( $row ) || ! isset( $row[0] ) || ! is_scalar( $row[0] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Estado transaccional indisponível.' );
		}
		return (string) $row[0];
	}

	/**
	 * Perda da sessão mysqli, sem reconnect.
	 */
	private function connectionLost(): bool {
		if ( ! $this->ready || ! $this->dbh instanceof \mysqli ) {
			return true;
		}
		$errno = (int) $this->dbh->errno;
		return in_array( $errno, array( 2002, 2003, 2006, 2013 ), true );
	}

	/**
	 * Classifica fronteiras transaccionais. O que não for reconhecido deixa de ser garantido.
	 *
	 * @param string $sql SQL.
	 */
	private static function classify( string $sql ): string {
		$stripped   = preg_replace( '/\/\*.*?\*\//s', ' ', $sql ) ?? $sql;
		$stripped   = preg_replace( '/#.*$/m', ' ', $stripped ) ?? $stripped;
		$stripped   = preg_replace( '/--\s.*$/m', ' ', $stripped ) ?? $stripped;
		$normalized = strtoupper( trim( (string) preg_replace( '/\s+/', ' ', $stripped ) ) );
		if ( '' === $normalized ) {
			return 'other';
		}
		if ( 1 === preg_match( '/^START\s+TRANSACTION\b|^BEGIN\b/', $normalized ) ) {
			return 'begin';
		}
		if ( 1 === preg_match( '/^ROLLBACK\s+TO(\s+SAVEPOINT)?\s+\S/', $normalized ) ) {
			return 'rollback_to';
		}
		if ( 1 === preg_match( '/^COMMIT\b/', $normalized ) ) {
			return 'commit';
		}
		if ( 1 === preg_match( '/^ROLLBACK\b/', $normalized ) ) {
			return 'rollback';
		}
		if ( 1 === preg_match( '/^SET(\s+SESSION)?\s+(AUTOCOMMIT|@@(SESSION\.)?AUTOCOMMIT)\s*=\s*0\b/', $normalized ) ) {
			return 'autocommit_off';
		}
		if ( 1 === preg_match( '/^SET(\s+SESSION)?\s+(AUTOCOMMIT|@@(SESSION\.)?AUTOCOMMIT)\s*=\s*1\b/', $normalized ) ) {
			return 'autocommit_on';
		}
		if ( 1 === preg_match( '/^(CREATE|ALTER|DROP|RENAME|TRUNCATE)\b|^LOCK\s+TABLES\b|^UNLOCK\s+TABLES\b/', $normalized ) ) {
			return 'ddl';
		}
		if ( 1 === preg_match( '/^XA\b/', $normalized ) ) {
			return 'unknown';
		}
		return 'other';
	}
}
