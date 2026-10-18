<?php
/**
 * Escritores concorrentes da regressão MySQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.WP.AlternativeFunctions
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/mysql-transaction-bootstrap.php';

$worker_role = getenv( 'ARN_TX_WORKER_ROLE' );
$table       = getenv( 'ARN_TX_WORKER_TABLE' );
$barrier     = getenv( 'ARN_TX_WORKER_BARRIER' );
if (
	! is_string( $worker_role ) ||
	! in_array( $worker_role, array( 'holder', 'waiter' ), true ) ||
	! is_string( $table ) ||
	'' === $table ||
	! is_string( $barrier ) ||
	'' === $barrier
) {
	throw new RuntimeException( 'Configuração do worker incompleta.' );
}

$db = arn_tx_connect();
try {
	if ( 0 !== $db->query( 'START TRANSACTION' ) ) {
		throw new RuntimeException( 'START falhou.' );
	}

	if ( 'holder' === $worker_role ) {
		if ( false === $db->query( 'UPDATE `' . $table . '` SET v = v + 1 WHERE id = 1' ) ) {
			throw new RuntimeException( 'Holder não obteve o lock.' );
		}
		file_put_contents( $barrier . '.locked', '1', LOCK_EX );
		$deadline = microtime( true ) + 20;
		while ( ! is_file( $barrier . '.waiter-attempting' ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'Waiter não iniciou.' );
			}
			usleep( 25000 );
		}
		while ( ! is_file( $barrier . '.release' ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'Holder não foi libertado.' );
			}
			usleep( 25000 );
		}
	} else {
		$deadline = microtime( true ) + 20;
		while ( ! is_file( $barrier . '.locked' ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'Holder não bloqueou a linha.' );
			}
			usleep( 25000 );
		}
		file_put_contents( $barrier . '.waiter-attempting', '1', LOCK_EX );
		if ( false === $db->query( 'UPDATE `' . $table . '` SET v = v + 10 WHERE id = 1' ) ) {
			throw new RuntimeException( 'Waiter não actualizou.' );
		}
		file_put_contents( $barrier . '.waiter-updated', '1', LOCK_EX );
	}

	if ( 0 !== $db->query( 'COMMIT' ) ) {
		throw new RuntimeException( 'COMMIT falhou.' );
	}
	file_put_contents( $barrier . '.' . $worker_role . '.result', 'committed', LOCK_EX );
} catch ( Throwable $exception ) {
	try {
		$db->query( 'ROLLBACK' );
	} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Limpeza após perda deliberada da ligação.
		// A ligação pode já estar indisponível durante a limpeza.
	}
	file_put_contents( $barrier . '.' . $worker_role . '.result', 'failed', LOCK_EX );
	fwrite( STDERR, $exception->getMessage() . PHP_EOL );
	exit( 1 );
} finally {
	$db->close();
}
