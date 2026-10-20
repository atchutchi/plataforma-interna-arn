<?php
/** Two native-session writers must really wait, then one CAS wins.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.PHP.DiscouragedPHPFunctions
function arn_tasks_concurrency( IdentityWpdb $database, array $roster, int $actor, string $cookie, int $task_id, int $expected, int $now, int $admin, callable $assert ): void {
	$launcher = realpath( (string) ( $_SERVER['argv'][0] ?? '' ) );
	if ( false === $launcher || ! is_file( $launcher ) ) {
		throw new RuntimeException( 'Launcher de concorrência indisponível.' ); }
	$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_tasks_worker_' . bin2hex( random_bytes( 8 ) );
	if ( ! mkdir( $directory, 0700 ) ) {
		throw new RuntimeException( 'Directório de concorrência indisponível.' ); }
	$holder = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$holder->set_prefix( $database->prefix );
	$holder->suppress_errors( true );
	$workers = array();
	$owned   = array();
	try {
		$payload = $directory . '/payload.json';
		$owned[] = $payload;
		$bytes   = json_encode(
			array(
				'prefix'   => $database->prefix,
				'roster'   => $roster,
				'actor'    => $actor,
				'cookie'   => $cookie,
				'id'       => $task_id,
				'expected' => $expected,
				'now'      => $now,
			),
			JSON_THROW_ON_ERROR
		);
		if ( strlen( $bytes ) !== file_put_contents( $payload, $bytes, LOCK_EX ) ) {
			throw new RuntimeException( 'Payload concorrente indisponível.' ); }
		$first_account = min( array_keys( $roster ) );
		$assert( false !== $holder->query( 'START TRANSACTION' ), 'barreira transaccional própria' );
		$assert( (string) $first_account === (string) $holder->get_var( $holder->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $database->prefix . 'arn_authz_accounts', $first_account ) ), 'barreira detém conta realmente bloqueada' );
		for ( $index = 0; $index < 2; ++$index ) {
			$ready  = $directory . '/ready-' . $index . '.json';
			$result = $directory . '/result-' . $index . '.json';
			$stdout = $directory . '/stdout-' . $index . '.log';
			$stderr = $directory . '/stderr-' . $index . '.log';
			array_push( $owned, $ready, $ready . '.tmp', $result, $result . '.tmp', $stdout, $stderr );
			$command = array( PHP_BINARY, $launcher, '--require=' . __DIR__ . '/tasks-cli-bootstrap.php', '--path=' . ABSPATH, '--user=' . $admin, 'eval-file', __DIR__ . '/tasks-worker.php', $payload, $ready, $result );
			$process = proc_open(
				$command,
				array(
					0 => array( 'pipe', 'r' ),
					1 => array( 'file', $stdout, 'x' ),
					2 => array( 'file', $stderr, 'x' ),
				),
				$pipes,
				dirname( __DIR__, 2 ),
				null,
				array( 'bypass_shell' => true )
			);
			if ( ! is_resource( $process ) ) {
				throw new RuntimeException( 'Escritor concorrente indisponível.' ); }
			fclose( $pipes[0] );
			$workers[] = array(
				'process' => $process,
				'ready'   => $ready,
				'result'  => $result,
				'stderr'  => $stderr,
				'exit'    => null,
			);
		}
		$connections = array();
		$connected   = 0;
		$deadline    = microtime( true ) + 40;
		while ( $connected < 2 && microtime( true ) < $deadline ) {
			$connections = array();
			foreach ( $workers as $worker ) {
				if ( is_file( $worker['result'] ) ) {
					throw new RuntimeException( 'Escritor terminou antes da espera.' ); }
				if ( is_file( $worker['ready'] ) ) {
					$data = json_decode( file_get_contents( $worker['ready'] ), true, 8, JSON_THROW_ON_ERROR );
					if ( ! is_int( $data['connection_id'] ?? null ) || $data['connection_id'] < 1 ) {
						throw new RuntimeException( 'Sinal de ligação inválido.' ); }
					$connections[] = $data['connection_id'];
				}
			}
			$connected = count( $connections );
			if ( $connected < 2 ) {
				usleep( 100000 ); }
		}
		$assert( 2 === count( array_unique( $connections ) ), 'duas ligações distintas prontas' );
		$waiting  = false;
		$deadline = microtime( true ) + 15;
		while ( ! $waiting && microtime( true ) < $deadline ) {
			$waiting = '2' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(DISTINCT t.trx_mysql_thread_id) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id IN (%d,%d)', ...$connections ) );
			if ( '' !== $database->last_error ) {
				throw new RuntimeException( 'Observador de concorrência indisponível.' ); }
			if ( ! $waiting ) {
				usleep( 150000 ); }
		}
		$assert( $waiting, 'dois escritores efectivamente bloqueados no motor' );
		$assert( false !== $holder->query( 'ROLLBACK' ), 'barreira libertada só após prova de espera' );
		$deadline = microtime( true ) + 40;
		do {
			$done = true;
			foreach ( $workers as &$worker ) {
				$status = proc_get_status( $worker['process'] );
				if ( $status['running'] ) {
					$done = false;
				} elseif ( null === $worker['exit'] ) {
					$worker['exit'] = $status['exitcode']; }
			}
			unset( $worker );
			if ( ! $done ) {
				usleep( 100000 ); }
		} while ( ! $done && microtime( true ) < $deadline );
		$assert( $done, 'escritores terminaram sem timeout' );
		$results = array();
		foreach ( $workers as &$worker ) {
			$close             = proc_close( $worker['process'] );
			$worker['process'] = null;
			$assert( 0 === ( $worker['exit'] ?? $close ) && is_file( $worker['result'] ) && '' === file_get_contents( $worker['stderr'] ), 'resultado técnico de cada escritor íntegro' );
			$results[] = json_decode( file_get_contents( $worker['result'] ), true, 8, JSON_THROW_ON_ERROR )['result'] ?? '';
		}
		unset( $worker );
		sort( $results );
		$assert( array( 'committed', 'conflict' ) === $results, 'uma confirmação e uma recusa CAS' );
	} finally {
		$holder->query( 'ROLLBACK' );
		$holder->close();
		foreach ( $workers as $worker ) {
			if ( is_resource( $worker['process'] ) ) {
				proc_terminate( $worker['process'] );
				proc_close( $worker['process'] ); }
		}
		foreach ( $owned as $file ) {
			if ( is_file( $file ) && dirname( $file ) === $directory && ! is_link( $file ) ) {
				unlink( $file ); }
		}
		rmdir( $directory );
	}
}
