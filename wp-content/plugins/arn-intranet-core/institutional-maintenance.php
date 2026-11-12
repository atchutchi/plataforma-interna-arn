<?php
/** Incluir no wp-config antes de wp-settings.php, com caminho privado configurado.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Saída constante sem dados privados.
if ( ! defined( 'ABSPATH' ) || defined( 'WPINC' ) || ! defined( 'ARN_MIGRATION_STATE_DIR' ) || ! is_string( ARN_MIGRATION_STATE_DIR ) ) {
	throw new RuntimeException( 'Guarda institucional deve preceder o WordPress.' );
}
require_once __DIR__ . '/src/Identity/Institutional/MaintenanceGate.php';
try {
	$arn_operator = false;
	if ( 'cli' === PHP_SAPI && defined( 'WP_CLI' ) && true === WP_CLI && class_exists( 'WP_CLI' ) ) {
		$arn_arguments = \WP_CLI::get_runner()->arguments;
		$arn_operator  = in_array( array_slice( $arn_arguments, 0, 3 ), array( array( 'arn', 'institutional', 'apply' ), array( 'arn', 'institutional', 'release' ) ), true );
	}
	$GLOBALS['arn_institutional_maintenance_gate'] = new \Arn\Intranet\Identity\Institutional\MaintenanceGate( ARN_MIGRATION_STATE_DIR, ABSPATH, $arn_operator );
} catch ( Throwable ) {
	if ( 'cli' === PHP_SAPI ) {
		fwrite( STDERR, "Manutenção institucional activa.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Antes do carregamento WordPress.
		exit( 1 ); }
	http_response_code( 503 );
	header( 'Retry-After: 300' );
	header( 'Cache-Control: no-store' );
	exit( 'Manutenção institucional activa.' );
}
unset( $arn_operator, $arn_arguments );
