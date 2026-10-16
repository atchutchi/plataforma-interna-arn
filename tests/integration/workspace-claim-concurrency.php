<?php
/**
 * Dois processos sobrepostos num bloqueio observado no servidor.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Identity\PreRegistration\PreRegistration;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Barreira e processos pertencem ao ensaio local fictício.
function arn_workspace_claim_concurrency( IdentityWpdb $db, PreRegistration $record, callable $assert ): void {
	$launcher = realpath( (string) ( $_SERVER['argv'][0] ?? '' ) );
	if ( false === $launcher ) {
		throw new RuntimeException( 'Launcher ausente.' );}
	$directory = sys_get_temp_dir() . '/arn_workspace_claim_' . bin2hex( random_bytes( 8 ) );
	mkdir( $directory, 0700 );
	$workers = array();
	$owned   = array();
	$barrier = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$barrier->set_prefix( $db->prefix );
	$complete = false;
	try {
		$payload = $directory . '/payload.json';
		$owned[] = $payload;
		file_put_contents(
			$payload,
			json_encode(
				array(
					'prefix'  => $db->prefix,
					'user_id' => $record->user_id,
					'email'   => $record->primary_email,
				),
				JSON_THROW_ON_ERROR
			),
			LOCK_EX
		);
		$assert( false !== $barrier->query( 'START TRANSACTION' ), 'barreira inicia' );
		$assert( $record->id === $barrier->get_var( $barrier->prepare( 'SELECT id FROM %i WHERE id=%s FOR UPDATE', $db->prefix . 'arn_workspace_accounts', $record->id ) ), 'cadastro bloqueado antes dos escritores' );
		for ( $i = 0;$i < 2;++$i ) {
			$ready  = $directory . '/ready-' . $i;
			$result = $directory . '/result-' . $i;
			$stdout = $directory . '/stdout-' . $i;
			$stderr = $directory . '/stderr-' . $i;
			array_push( $owned, $ready, $result, $stdout, $stderr );
			$command  = array( PHP_BINARY, $launcher );
			$override = getenv( 'ARN_WORKSPACE_CLI_REQUIRE' );
			if ( is_string( $override ) && '' !== $override ) {
				$command[] = '--require=' . $override;}
			array_push( $command, '--path=' . ABSPATH, '--user=' . get_current_user_id(), 'eval-file', __DIR__ . '/workspace-claim-worker.php', $payload, $ready, $result, (string) $i );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Processos CLI fictícios, sem shell.
			$process = proc_open(
				$command,
				array( array( 'pipe', 'r' ), array( 'file', $stdout, 'a' ), array( 'file', $stderr, 'a' ) ),
				$pipes,
				dirname( __DIR__, 2 ),
				null,
				array(
					'bypass_shell'     => true,
					'create_no_window' => true,
				)
			); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Dois escritores CLI locais, sem shell.
			if ( ! is_resource( $process ) ) {
				throw new RuntimeException( 'Escritor indisponível.' );
			}fclose( $pipes[0] );
			$workers[] = array(
				'process' => $process,
				'ready'   => $ready,
				'result'  => $result,
				'exit'    => null,
			);
		}
		$deadline    = microtime( true ) + 30;
		$connections = array();
		do {
			$connections = array();
			foreach ( $workers as$worker ) {
				if ( is_file( $worker['result'] ) ) {
					throw new RuntimeException( 'Escritor terminou antes do bloqueio, consultar prova privada.' );
				}if ( is_file( $worker['ready'] ) ) {
					$id = (int) file_get_contents( $worker['ready'] );
					if ( $id > 0 ) {
						$connections[] = $id;
					}
				}
			}if ( 2 === count( array_unique( $connections ) ) ) {
				break;
			}usleep( 50000 );
		} while ( microtime( true ) < $deadline );
		$assert( 2 === count( array_unique( $connections ) ), 'dois processos e ligações diferentes' );
		$deadline = microtime( true ) + 15;
		$waiting  = 0;
		do {
			$waiting = (int) $db->get_var( $db->prepare( 'SELECT COUNT(DISTINCT t.trx_mysql_thread_id) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id IN (%d,%d)', $connections[0], $connections[1] ) );
			if ( 2 === $waiting ) {
				break;
			}usleep( 200000 );
		} while ( microtime( true ) < $deadline );
		$assert( 2 === $waiting, 'ambos os escritores observados em espera SQL' );
		$assert( false !== $barrier->query( 'COMMIT' ), 'barreira libertada após sobreposição' );
		$deadline = microtime( true ) + 30;
		do {
			$running = false;
			foreach ( $workers as&$worker ) {
				$status = proc_get_status( $worker['process'] );
				if ( $status['running'] ) {
					$running = true;
				} elseif ( null === $worker['exit'] ) {
					$worker['exit'] = $status['exitcode'];
				}
			}unset( $worker );
			if ( ! $running ) {
				break;
			}usleep( 50000 );
		} while ( microtime( true ) < $deadline );
		$assert( ! $running, 'escritores terminam' );
		$results = array();
		$exits   = array();
		foreach ( $workers as&$worker ) {
			$closed            = proc_close( $worker['process'] );
			$worker['process'] = null;
			$exits[]           = -1 === $closed ? $worker['exit'] : $closed;
			$data              = json_decode( file_get_contents( $worker['result'] ), true, 8, JSON_THROW_ON_ERROR );
			$results[]         = $data['result'];
		}unset( $worker );
		sort( $results );
		sort( $exits );
		$assert( array( 'committed', 'conflict' ) === $results && array( 0, 3 ) === $exits, 'um commit e um conflito provado, sem aceitar falha técnica' );
		$complete = true;
	} finally {
		$barrier->query( 'ROLLBACK' );
		$barrier->close();
		foreach ( $workers as$worker ) {
			if ( is_resource( $worker['process'] ) ) {
				$status = proc_get_status( $worker['process'] );
				if ( $status['running'] ) {
					proc_terminate( $worker['process'] );
				}proc_close( $worker['process'] );}
		}
		foreach ( $owned as$path ) {
			if ( is_file( $path ) && realpath( dirname( $path ) ) === realpath( $directory ) && ( $complete || 'payload.json' === basename( $path ) ) ) {
				unlink( $path );
			}
		}if ( $complete ) {
			rmdir( $directory );}
	}
}
