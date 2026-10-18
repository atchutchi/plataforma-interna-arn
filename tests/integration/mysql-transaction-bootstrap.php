<?php
/**
 * Arranque isolado da ligação dedicada contra MySQL de ensaio.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals
// phpcs:disable WordPress.PHP.DevelopmentFunctions, Generic.CodeAnalysis.UnusedFunctionParameter

use Arn\Intranet\Autoloader;
use Arn\Intranet\Identity\IdentityWpdb;

$GLOBALS['arn_tx_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Regista um filtro no ensaio isolado.
	 *
	 * @param string   $hook Gancho.
	 * @param callable $callback Função.
	 * @param int      $priority Prioridade.
	 * @param int      $accepted Número de argumentos.
	 * @return bool Verdadeiro após registar.
	 */
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) {
		$GLOBALS['arn_tx_filters'][ $hook ][ (int) $priority ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'remove_filter' ) ) {
	/**
	 * Remove um filtro do ensaio isolado.
	 *
	 * @param string   $hook Gancho.
	 * @param callable $callback Função.
	 * @param int      $priority Prioridade.
	 * @return bool Verdadeiro quando encontrou o filtro.
	 */
	function remove_filter( $hook, $callback, $priority = 10 ): bool {
		if ( empty( $GLOBALS['arn_tx_filters'][ $hook ][ (int) $priority ] ) ) {
			return false;
		}
		$GLOBALS['arn_tx_filters'][ $hook ][ (int) $priority ] = array_values(
			array_filter(
				$GLOBALS['arn_tx_filters'][ $hook ][ (int) $priority ],
				static fn( $registered ): bool => $registered !== $callback
			)
		);
		return true;
	}
}
if ( ! function_exists( 'has_filter' ) ) {
	/**
	 * Confirma a presença de um filtro no ensaio isolado.
	 *
	 * @param string         $hook Gancho.
	 * @param callable|false $callback Função opcional.
	 * @return bool|int Prioridade ou falso.
	 */
	function has_filter( $hook, $callback = false ) {
		if ( empty( $GLOBALS['arn_tx_filters'][ $hook ] ) ) {
			return false;
		}
		if ( false === $callback ) {
			return true;
		}
		foreach ( $GLOBALS['arn_tx_filters'][ $hook ] as $priority => $callbacks ) {
			if ( in_array( $callback, $callbacks, true ) ) {
				return (int) $priority;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Aplica os filtros registados.
	 *
	 * @param string $hook Gancho.
	 * @param mixed  $value Valor inicial.
	 * @return mixed Valor filtrado.
	 */
	function apply_filters( $hook, $value ) {
		$priorities = $GLOBALS['arn_tx_filters'][ $hook ] ?? array();
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value );
			}
		}
		return $value;
	}
}
if ( ! function_exists( '__' ) ) {
	/**
	 * Devolve a mensagem literal no ensaio.
	 *
	 * @param string $message Mensagem.
	 * @param string $domain Domínio ignorado.
	 * @return string Mensagem original.
	 */
	function __( $message, $domain = 'default' ): string {
		return $message;
	}
}
if ( ! function_exists( 'wp_load_translations_early' ) ) {
	/** Não carrega traduções no ensaio isolado. */
	function wp_load_translations_early(): void {}
}
if ( ! function_exists( 'wp_die' ) ) {
	/**
	 * Converte qualquer terminação HTML em erro controlado.
	 *
	 * @param string $message Mensagem ignorada.
	 * @throws RuntimeException Sempre.
	 */
	function wp_die( $message = '' ): void {
		throw new RuntimeException( 'wp_die foi chamado.' );
	}
}
if ( ! function_exists( 'is_multisite' ) ) {
	/**
	 * Indica que o ensaio não usa multisite.
	 *
	 * @return bool Falso neste ensaio.
	 */
	function is_multisite(): bool {
		return false;
	}
}
if ( ! function_exists( 'absint' ) ) {
	/**
	 * Normaliza um inteiro não negativo.
	 *
	 * @param mixed $value Valor.
	 * @return int Inteiro absoluto.
	 */
	function absint( $value ): int {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'wp_debug_backtrace_summary' ) ) {
	/**
	 * Omite o resumo de stack no ensaio.
	 *
	 * @param string $ignore_class Classe ignorada.
	 * @return string Texto vazio.
	 */
	function wp_debug_backtrace_summary( $ignore_class = '' ): string {
		return '';
	}
}

foreach (
	array(
		'WP_DEBUG'         => true,
		'WP_DEBUG_DISPLAY' => false,
		'WP_CONTENT_DIR'   => __DIR__,
		'DB_CHARSET'       => 'utf8mb4',
		'AUTH_SALT'        => 'arn-tx-test-salt',
	) as $name => $value
) {
	if ( ! defined( $name ) ) {
		define( $name, $value );
	}
}

$core = $argv[1] ?? getenv( 'ARN_TX_WPDB' );
$core = is_string( $core ) ? $core : '';
if ( ! is_string( $core ) || ! is_file( $core ) ) {
	throw new RuntimeException( 'Indica o ficheiro class-wpdb.php.' );
}
require $core;
require dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src/Autoloader.php';
Autoloader::register( dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src' );

/**
 * Abre a ligação dedicada usando apenas variáveis de ambiente do job.
 *
 * @return IdentityWpdb Ligação isolada.
 * @throws RuntimeException Quando a configuração está incompleta.
 */
function arn_tx_connect(): IdentityWpdb {
	$user = getenv( 'ARN_TX_TEST_USER' );
	$pass = getenv( 'ARN_TX_TEST_PASSWORD' );
	$name = getenv( 'ARN_TX_TEST_NAME' );
	$host = getenv( 'ARN_TX_TEST_HOST' );
	if (
		! is_string( $user ) ||
		'' === $user ||
		! is_string( $pass ) ||
		! is_string( $name ) ||
		'' === $name ||
		! is_string( $host ) ||
		'' === $host
	) {
		throw new RuntimeException( 'ARN_TX_TEST_* incompleto.' );
	}
	$database = new IdentityWpdb( $user, $pass, $name, $host );
	$database->suppress_errors( true );
	return $database;
}

/**
 * Abre uma ligação observadora privilegiada apenas no contentor de CI.
 *
 * @return IdentityWpdb Ligação isolada para performance_schema.
 * @throws RuntimeException Quando a password de ensaio não foi fornecida.
 */
function arn_tx_connect_root(): IdentityWpdb {
	$pass = getenv( 'ARN_TX_ROOT_PASSWORD' );
	$name = getenv( 'ARN_TX_TEST_NAME' );
	$host = getenv( 'ARN_TX_TEST_HOST' );
	if (
		! is_string( $pass ) ||
		! is_string( $name ) ||
		'' === $name ||
		! is_string( $host ) ||
		'' === $host
	) {
		throw new RuntimeException( 'ARN_TX_ROOT_PASSWORD incompleto.' );
	}
	$database = new IdentityWpdb( 'root', $pass, $name, $host );
	$database->suppress_errors( true );
	return $database;
}
