<?php
/**
 * Segundo processo real para sobreposição transaccional.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.WP.GlobalVariablesOverride -- Barreiras de ficheiro do ensaio exclusivo.
require __DIR__ . '/mysql-transaction-bootstrap.php';

$role    = getenv( 'ARN_TX_WORKER_ROLE' );
$table   = getenv( 'ARN_TX_WORKER_TABLE' );
$barrier = getenv( 'ARN_TX_WORKER_BARRIER' );
if ( ! in_array( $role, array( 'holder', 'waiter' ), true ) || ! is_string( $table ) || 1 !== preg_match( '/^[A-Za-z0-9_]+$/', $table ) || ! is_string( $barrier ) || '' === $barrier ) {
	fwrite( STDERR, "FAIL worker args\n" );
	exit( 1 );
}
$ready   = $barrier . '.ready';
$release = $barrier . '.release';
$result  = $barrier . '.' . $role . '.result';
$db      = arn_tx_connect();
$db->suppress_errors( true );
try {
	if ( 'holder' === $role ) {
		if ( false === $db->query( 'START TRANSACTION' ) || ! $db->inTransaction() ) {
			throw new RuntimeException( 'holder start' );
		}
		if ( false === $db->query( 'UPDATE `' . $table . '` SET v = v + 1 WHERE id = 1' ) ) {
			throw new RuntimeException( 'holder update' );
		}
		if ( false === file_put_contents( $ready, 'ready', LOCK_EX ) ) {
			throw new RuntimeException( 'holder ready' );
		}
		$deadline = microtime( true ) + 20;
		while ( ! is_file( $release ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'holder timeout' );
			}
			usleep( 50000 );
		}
		if ( false === $db->query( 'COMMIT' ) || $db->inTransaction() ) {
			throw new RuntimeException( 'holder commit' );
		}
		file_put_contents( $result, 'committed', LOCK_EX );
	} else {
		$deadline = microtime( true ) + 20;
		while ( ! is_file( $ready ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'waiter ready timeout' );
			}
			usleep( 50000 );
		}
		if ( false === $db->query( 'START TRANSACTION' ) || ! $db->inTransaction() ) {
			throw new RuntimeException( 'waiter start' );
		}
		if ( false === file_put_contents( $barrier . '.waiter-started', '1', LOCK_EX ) ) {
			throw new RuntimeException( 'waiter started' );
		}
		if ( false === $db->query( 'UPDATE `' . $table . '` SET v = v + 10 WHERE id = 1' ) ) {
			throw new RuntimeException( 'waiter update' );
		}
		if ( false === $db->query( 'COMMIT' ) || $db->inTransaction() ) {
			throw new RuntimeException( 'waiter commit' );
		}
		file_put_contents( $result, 'committed', LOCK_EX );
	}
	$db->close();
	exit( 0 );
} catch ( Throwable $error ) {
	$db->query( 'ROLLBACK' );
	file_put_contents( $result, 'rejected:' . $error->getMessage(), LOCK_EX );
	$db->close();
	exit( 1 );
}
