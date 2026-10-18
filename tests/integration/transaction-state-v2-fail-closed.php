<?php
/**
 * Prova que o observador recusa estados não comprováveis.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, Squiz.Commenting

require __DIR__ . '/transaction-state-v2-bootstrap.php';

$database = arn_tx_v2_connect();
try {
	try {
		$database->inTransaction();
	} catch ( RuntimeException $exception ) {
		if ( 'Estado transaccional indisponível.' !== $exception->getMessage() ) {
			throw new RuntimeException( 'A falha expôs uma mensagem inesperada.' );
		}
		echo 'PASS: estado transaccional recusado de forma fechada.' . PHP_EOL;
		exit( 0 );
	}
	throw new RuntimeException( 'O estado indisponível foi tratado como seguro.' );
} finally {
	$database->close();
}
