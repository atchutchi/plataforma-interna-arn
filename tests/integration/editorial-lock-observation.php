<?php
/** Real SQL regression for the shared InnoDB metadata cache idle window.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$assert   = static function ( bool $ok ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'A regressão da observação de locks não confirmou a prova esperada.' );
	}
};
$holder   = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$writer   = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$observer = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
foreach ( array( $holder, $writer, $observer ) as $database ) {
	$database->suppress_errors( true );
}
$table      = 'arn_ed_' . bin2hex( random_bytes( 5 ) ) . '_cache_probe';
$connection = (int) $writer->get_var( 'SELECT CONNECTION_ID()' );
$sql        = $observer->prepare( 'SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX t ON t.trx_id=w.requesting_trx_id WHERE t.trx_mysql_thread_id=%d', $connection );
try {
	$assert( $connection > 0 && false !== $holder->query( $holder->prepare( 'CREATE TABLE %i (id INT PRIMARY KEY, value INT NOT NULL) ENGINE=InnoDB', $table ) ) );
	$assert( 1 === $holder->query( $holder->prepare( 'INSERT INTO %i VALUES (1,0)', $table ) ) );
	$assert( false !== $holder->query( 'START TRANSACTION' ) );
	$assert( '0' === (string) $holder->get_var( $holder->prepare( 'SELECT value FROM %i WHERE id=1 FOR UPDATE', $table ) ) && '' === $holder->last_error );
	$assert( false !== $writer->query( 'START TRANSACTION' ) );
	usleep( 160000 );
	$assert( '0' === (string) $observer->get_var( $sql ) && '' === $observer->last_error );
	$assert( $writer->dbh instanceof mysqli && true === $writer->dbh->query( 'UPDATE `' . $table . '` SET value=1 WHERE id=1', MYSQLI_ASYNC ) );
	$fast    = array();
	$gaps    = array();
	$last    = hrtime( true );
	$healthy = true;
	for ( $index = 0; $index < 16; ++$index ) {
		usleep( 20000 );
		$gaps[]  = (int) ( ( hrtime( true ) - $last ) / 1000000 );
		$fast[]  = (int) $observer->get_var( $sql );
		$healthy = $healthy && '' === $observer->last_error;
		$last    = hrtime( true );
	}
	$read          = array( $writer->dbh );
	$driver_errors = array();
	$reject        = array();
	$ready         = mysqli_poll( $read, $driver_errors, $reject, 0, 0 ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_poll -- wpdb não oferece sondagem de escrita assíncrona real.
	// No INFORMATION_SCHEMA read in this interval. The holder keeps its row lock.
	usleep( 160000 );
	$fresh   = $observer->get_var( $sql );
	$healthy = $healthy && '' === $observer->last_error;
	WP_CLI::log(
		'Editorial metadata cache: ' . wp_json_encode(
			array(
				'fast_samples'             => count( $fast ),
				'fast_max_wait_count'      => max( $fast ),
				'client_idle_gap_ms'       => max( $gaps ),
				'stale_control_observed'   => array( 0 ) === array_values( array_unique( $fast ) ) && 0 === $ready && '1' === (string) $fresh,
				'writer_ready_during_hold' => $ready,
				'wait_seen_after_idle'     => '1' === (string) $fresh,
				'observer_queries_healthy' => $healthy,
			)
		)
	);
	// Scheduling can refresh the fast samples legitimately. Only the positive
	// control is mandatory: a held writer and its real wait after the idle window.
	$assert( $healthy && 0 === $ready && '1' === (string) $fresh );
	$assert( false !== $holder->query( 'ROLLBACK' ) );
	$read          = array( $writer->dbh );
	$driver_errors = array();
	$reject        = array();
	$assert( 1 === mysqli_poll( $read, $driver_errors, $reject, 2 ) && true === $writer->dbh->reap_async_query() ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_poll -- Só recolhe a escrita exclusiva depois de libertar o lock.
	$assert( false !== $writer->query( 'ROLLBACK' ) );
	WP_CLI::success( 'Escritor bloqueado, lock observado após intervalo de actualização da cache e escrita concluída apenas depois da libertação do detentor. Sondagem rápida registada como diagnóstico.' );
} finally {
	$holder->query( 'ROLLBACK' );
	$writer->close();
	$holder->query( $holder->prepare( 'DROP TABLE IF EXISTS %i', $table ) );
	$holder->close();
	$observer->close();
}
