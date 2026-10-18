<?php
/**
 * Primeira regressão RED da guarda transaccional portátil.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, WordPress.DB.RestrictedFunctions, WordPress.Security.EscapeOutput

require __DIR__ . '/mysql-transaction-bootstrap.php';

$checks = 0;
$assert = static function ( bool $condition, string $name ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou guarda transaccional: ' . $name );
	}
	++$checks;
};

$db = arn_tx_connect();
try {
	$assert( method_exists( $db, 'inTransaction' ), 'API inTransaction existe' );
	$assert( ! $db->inTransaction(), 'sessão inicial fora de transacção' );
	$assert( false !== $db->query( 'START TRANSACTION' ), 'START aceite' );
	$assert( $db->inTransaction(), 'START observado' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK aceite' );
	$assert( ! $db->inTransaction(), 'ROLLBACK observado' );
	echo 'PASS: guarda transaccional inicial, ' . $checks . ' verificações, motor=' . (string) $db->get_var( 'SELECT VERSION()' ) . PHP_EOL;
} finally {
	$db->close();
}
