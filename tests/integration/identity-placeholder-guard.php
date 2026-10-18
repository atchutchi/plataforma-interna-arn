<?php
/**
 * Regressão da guarda SQL com o escape interno de placeholders do wpdb.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Identity\IdentityWpdb;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Rótulos constantes do ensaio CLI.

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio permitido apenas no laboratório por administrador.' );
}

$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$database->suppress_errors( true );
$checks   = 0;
$assert   = static function ( bool $condition, string $name ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou placeholder dedicado: ' . $name );
	}
	++$checks;
};
$mutation = null;

try {
	$prefix = 'arn_placeholder_' . bin2hex( random_bytes( 5 ) ) . '_';
	$sql    = $database->prepare(
		'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s',
		$database->esc_like( $prefix ) . '%'
	);

	$assert( str_contains( $sql, $database->placeholder_escape() ), 'prepare conserva marcador interno antes da query' );
	$tables = $database->get_col( $sql );
	$assert( is_array( $tables ), 'consulta preparada devolve uma coleção' );
	$assert( array() === $tables, 'prefixo exclusivo não encontra tabelas' );
	$assert( '' === $database->last_error, 'escape interno não é tratado como alteração externa' );
	$assert( is_string( $database->last_query ) && ! str_contains( $database->last_query, $database->placeholder_escape() ), 'SQL executado não contém o marcador interno' );
	$assert( str_contains( $database->last_query, '%' ), 'SQL executado conserva o wildcard literal' );

	$mutation = static function ( string $query ): string {
		return str_replace( 'information_schema.TABLES', 'information_schema.COLUMNS', $query );
	};
	add_filter( 'query', $mutation, 10 );
	$result = $database->query( $sql );
	$assert( false === $result, 'alteração externa continua recusada' );
	$assert( 'Alteração da consulta dedicada recusada.' === $database->last_error, 'recusa externa mantém mensagem estática' );
	remove_filter( 'query', $mutation, 10 );
	$mutation = null;

	WP_CLI::success( sprintf( 'Guarda de placeholder dedicada validada com %d verificações.', $checks ) );
} finally {
	if ( null !== $mutation ) {
		remove_filter( 'query', $mutation, 10 );
	}
	$database->close();
}
