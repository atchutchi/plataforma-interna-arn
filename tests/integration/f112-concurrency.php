<?php
/** Test-only two-process overlap with an aggregate barrier and real lock proofs.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
use Arn\Intranet\Identity\IdentityWpdb;

function arn_f112_concurrency( IdentityWpdb $db, string $module, int $id, array $roster, int $actor, array $session, int $now, array $content, int $admin, callable $assert ): void {
	$launcher = realpath( (string) ( $_SERVER['argv'][0] ?? '' ) );
	if ( false === $launcher || ! is_file( $launcher ) ) {
		throw new RuntimeException( 'Launcher de concorrência indisponível.' );
	}
	$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_f112_worker_' . bin2hex( random_bytes( 8 ) );
	if ( ! mkdir( $directory, 0700 ) ) {
		throw new RuntimeException( 'Directório de concorrência indisponível.' );
	}
	$workers  = array();
	$owned    = array();
	$complete = false;
	$barrier  = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$barrier->set_prefix( $db->prefix );
	$barrier->suppress_errors( true );
	try {
		$payload = $directory . '/payload.json';
		$bytes   = json_encode(
			array(
				'prefix'   => $db->prefix,
				'module'   => $module,
				'id'       => $id,
				'expected' => 2,
				'actor'    => $actor,
				'session'  => $session,
				'roster'   => $roster,
				'now'      => $now,
				'content'  => $content,
			),
			JSON_THROW_ON_ERROR
		);
		$handle  = fopen( $payload, 'x' );
		if ( false === $handle ) {
			throw new RuntimeException( 'Payload de concorrência indisponível.' );
		}
		try {
			$assert( strlen( $bytes ) === fwrite( $handle, $bytes ) && fflush( $handle ), 'payload privado completo' );
		} finally {
			fclose( $handle );
		}
		$owned[] = $payload;
		$table   = $db->prefix . ( 'communications' === $module ? 'arn_comm_objects' : 'arn_calendar_objects' );
		$assert( false !== $barrier->query( 'START TRANSACTION' ), 'barreira iniciada' );
		$assert( (string) $id === (string) $barrier->get_var( $barrier->prepare( 'SELECT id FROM %i WHERE id=%d FOR UPDATE', $table, $id ) ), 'objecto bloqueado antes de lançar os escritores' );
		for ( $index = 0; $index < 2; ++$index ) {
			$ready  = $directory . '/ready-' . $index . '.json';
			$result = $directory . '/result-' . $index . '.json';
			$stdout = $directory . '/stdout-' . $index . '.log';
			$stderr = $directory . '/stderr-' . $index . '.log';
			array_push( $owned, $ready, $ready . '.tmp', $result, $result . '.tmp', $result . '.phase', $result . '.query', $stdout, $stderr );
			$command  = array( PHP_BINARY, $launcher );
			$override = getenv( 'ARN_F112_CLI_REQUIRE' );
			if ( is_string( $override ) && '' !== $override ) {
				$assert( is_file( $override ), 'override CLI local explícito' );
				$command[] = '--require=' . $override;
			}
			array_push( $command, '--path=' . ABSPATH, '--user=' . $admin, 'eval-file', __DIR__ . '/f112-worker.php', $payload, $ready, $result );
			$descriptors = array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'file', $stdout, 'a' ),
				2 => array( 'file', $stderr, 'a' ),
			);
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Two exclusive local CLI writers, never a shell or production command.
			$process = proc_open( $command, $descriptors, $pipes, dirname( __DIR__, 2 ), null, array( 'bypass_shell' => true ) );
			if ( ! is_resource( $process ) ) {
				throw new RuntimeException( 'Escritor concorrente indisponível.' );
			}
			fclose( $pipes[0] );
			$workers[] = array(
				'process' => $process,
				'ready'   => $ready,
				'result'  => $result,
				'exit'    => null,
			);
		}
		$deadline    = microtime( true ) + 40;
		$connections = array();
		do {
			$connections = array();
			foreach ( $workers as $worker ) {
				if ( is_file( $worker['result'] ) ) {
					throw new RuntimeException( 'Escritor terminou antes da barreira, consultar sinais privados.' );
				}
				if ( is_file( $worker['ready'] ) ) {
					$signal = json_decode( file_get_contents( $worker['ready'] ), true, 8, JSON_THROW_ON_ERROR );
					if ( 'ready' !== ( $signal['phase'] ?? null ) || ! is_int( $signal['connection_id'] ?? null ) || $signal['connection_id'] < 1 ) {
						throw new RuntimeException( 'Sinal de escritor inválido.' );
					}
					$connections[] = $signal['connection_id'];
				}
			}
			if ( 2 === count( $connections ) ) {
				break;
			}
			usleep( 50000 );
		} while ( microtime( true ) < $deadline );
		$assert( 2 === count( array_unique( $connections ) ), 'dois processos carregados em ligações diferentes' );
		$deadline = microtime( true ) + 20;
		$waiting  = 0;
		do {
			$waiting = (int) $db->get_var( $db->prepare( 'SELECT COUNT(DISTINCT t.trx_mysql_thread_id) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id IN (%d,%d)', $connections[0], $connections[1] ) );
			if ( '' !== $db->last_error ) {
				throw new RuntimeException( 'Observação de locks indisponível.' );
			}
			if ( 2 === $waiting ) {
				break;
			}
			// MariaDB 11.8 trx0i_s.cc requires >100ms without a cache read.
			// Polling at 50ms can keep the same stale lock snapshot forever.
			usleep( 200000 );
		} while ( microtime( true ) < $deadline );
		if ( 2 !== $waiting ) {
			$states       = $db->get_results( $db->prepare( 'SELECT ID,COMMAND,STATE FROM information_schema.PROCESSLIST WHERE ID IN (%d,%d)', $connections[0], $connections[1] ), ARRAY_A );
			$transactions = $db->get_results( $db->prepare( 'SELECT trx_mysql_thread_id,trx_state FROM information_schema.INNODB_TRX WHERE trx_mysql_thread_id IN (%d,%d)', $connections[0], $connections[1] ), ARRAY_A );
			$phases       = array();
			foreach ( $workers as $worker ) {
				$phase                                     = is_file( $worker['result'] . '.phase' ) ? json_decode( file_get_contents( $worker['result'] . '.phase' ), true, 8, JSON_THROW_ON_ERROR ) : array( 'phase' => 'before-authority' );
				$phase['statement_shape_without_literals'] = is_file( $worker['result'] . '.query' ) ? file_get_contents( $worker['result'] . '.query' ) : null;
				$phases[]                                  = $phase;
			}
			WP_CLI::log(
				'F112 lock diagnostic: ' . json_encode(
					array(
						'waiting'      => $waiting,
						'states'       => $states,
						'transactions' => $transactions,
						'phases'       => $phases,
					),
					JSON_THROW_ON_ERROR
				)
			);
		}
		$assert( 2 === $waiting, 'ambos os escritores sobrepostos em locks reais' );
		$assert( false !== $barrier->query( 'COMMIT' ), 'barreira libertada depois da sobreposição' );
		$deadline = microtime( true ) + 30;
		do {
			$running = false;
			foreach ( $workers as &$worker ) {
				$status = proc_get_status( $worker['process'] );
				if ( $status['running'] ) {
					$running = true;
				} elseif ( null === $worker['exit'] ) {
					$worker['exit'] = $status['exitcode'];
				}
			}
			unset( $worker );
			if ( ! $running ) {
				break;
			}
			usleep( 50000 );
		} while ( microtime( true ) < $deadline );
		$assert( ! $running, 'escritores terminam dentro do prazo' );
		$results = array();
		$exits   = array();
		foreach ( $workers as &$worker ) {
			$closed            = proc_close( $worker['process'] );
			$worker['process'] = null;
			$exits[]           = -1 === $closed ? $worker['exit'] : $closed;
			$data              = json_decode( file_get_contents( $worker['result'] ), true, 8, JSON_THROW_ON_ERROR );
			$results[]         = $data['result'];
		}
		unset( $worker );
		sort( $results );
		sort( $exits );
		$assert( array( 'committed', 'conflict' ) === $results && array( 0, 3 ) === $exits, 'um commit e um conflito CAS, sem aceitar falha técnica como recusa' );
		$complete = true;
	} finally {
		$barrier->query( 'ROLLBACK' );
		$barrier->close();
		foreach ( $workers as $worker ) {
			if ( is_resource( $worker['process'] ) ) {
				$status = proc_get_status( $worker['process'] );
				if ( $status['running'] ) {
					proc_terminate( $worker['process'] );
				}
				proc_close( $worker['process'] );
			}
		}
		foreach ( $owned as $path ) {
			if ( is_file( $path ) && realpath( dirname( $path ) ) === realpath( $directory ) && ( $complete || 'payload.json' === basename( $path ) ) ) {
				unlink( $path );
			}
		}
		if ( $complete && 1 === preg_match( '/\Aarn_f112_worker_[a-f0-9]{16}\z/', basename( $directory ) ) ) {
			rmdir( $directory );
		} elseif ( ! $complete ) {
			WP_CLI::log( 'Diagnóstico privado de worker, sem payload: ' . $directory );
		}
	}
}
