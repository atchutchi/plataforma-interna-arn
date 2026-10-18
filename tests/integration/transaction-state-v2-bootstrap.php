<?php
/**
 * Arranque isolado da prova transaccional v2.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.GlobalVariablesOverride, WordPress.NamingConventions.PrefixAllGlobals, WordPress.PHP.DevelopmentFunctions, WordPress.DB.RestrictedFunctions, Squiz.Commenting, Universal.Operators.DisallowShortTernary, Generic.CodeAnalysis.UnusedFunctionParameter

use Arn\Intranet\Autoloader;
use Arn\Intranet\Identity\IdentityWpdb;

$GLOBALS['arn_tx_v2_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted = 1 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$GLOBALS['arn_tx_v2_filters'][ $hook ][ (int) $priority ][] = $callback;
		return true;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value ) {
		if ( empty( $GLOBALS['arn_tx_v2_filters'][ $hook ] ) ) {
			return $value;
		}
		$priorities = $GLOBALS['arn_tx_v2_filters'][ $hook ];
		ksort( $priorities );
		foreach ( $priorities as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$value = $callback( $value );
			}
		}
		return $value;
	}
}
if ( ! function_exists( 'remove_filter' ) ) {
	function remove_filter( $hook, $callback, $priority = 10 ): bool {
		if ( empty( $GLOBALS['arn_tx_v2_filters'][ $hook ][ (int) $priority ] ) ) {
			return false;
		}
		$GLOBALS['arn_tx_v2_filters'][ $hook ][ (int) $priority ] = array_values(
			array_filter(
				$GLOBALS['arn_tx_v2_filters'][ $hook ][ (int) $priority ],
				static fn( $registered ): bool => $registered !== $callback
			)
		);
		return true;
	}
}
if ( ! function_exists( 'has_filter' ) ) {
	function has_filter( $hook, $callback = false ) {
		if ( empty( $GLOBALS['arn_tx_v2_filters'][ $hook ] ) ) {
			return false;
		}
		if ( false === $callback ) {
			return true;
		}
		foreach ( $GLOBALS['arn_tx_v2_filters'][ $hook ] as $callbacks ) {
			if ( in_array( $callback, $callbacks, true ) ) {
				return true;
			}
		}
		return false;
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $message, $domain = 'default' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return $message;
	}
}
if ( ! function_exists( 'wp_load_translations_early' ) ) {
	function wp_load_translations_early(): void {}
}
if ( ! function_exists( 'wp_die' ) ) {
	function wp_die( $message = '' ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		throw new RuntimeException( 'wp_die foi chamado.' );
	}
}
if ( ! function_exists( 'is_multisite' ) ) {
	function is_multisite(): bool {
		return false;
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ): int {
		return abs( (int) $maybeint );
	}
}
if ( ! function_exists( 'wp_debug_backtrace_summary' ) ) {
	function wp_debug_backtrace_summary( $ignore_class = '' ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return '';
	}
}

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', true );
}
if ( ! defined( 'WP_DEBUG_DISPLAY' ) ) {
	define( 'WP_DEBUG_DISPLAY', false );
}
if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', __DIR__ );
}
if ( ! defined( 'DB_CHARSET' ) ) {
	define( 'DB_CHARSET', 'utf8mb4' );
}
if ( ! defined( 'AUTH_SALT' ) ) {
	define( 'AUTH_SALT', 'arn-transaction-state-v2-test' );
}

$core = $argv[1] ?? getenv( 'ARN_TX_V2_WPDB' ) ?: '';
if ( ! is_string( $core ) || ! is_file( $core ) ) {
	throw new RuntimeException( 'Indica o ficheiro class-wpdb.php.' );
}
require $core;
require dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src/Autoloader.php';
Autoloader::register( dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src' );

/** Abre a ligação principal de ensaio. */
function arn_tx_v2_connect(): IdentityWpdb {
	$user = getenv( 'ARN_TX_V2_USER' );
	$pass = getenv( 'ARN_TX_V2_PASSWORD' );
	$name = getenv( 'ARN_TX_V2_NAME' );
	$host = getenv( 'ARN_TX_V2_HOST' );
	if ( ! is_string( $user ) || '' === $user || ! is_string( $pass ) || ! is_string( $name ) || '' === $name || ! is_string( $host ) || '' === $host ) {
		throw new RuntimeException( 'ARN_TX_V2_* incompleto.' );
	}
	$database = new IdentityWpdb( $user, $pass, $name, $host );
	$database->suppress_errors( true );
	return $database;
}

/** Obtém o handle principal apenas para provar comandos fora de IdentityWpdb::query(). */
function arn_tx_v2_handle( IdentityWpdb $database ): mysqli {
	$property = new ReflectionProperty( wpdb::class, 'dbh' );
	$handle   = $property->getValue( $database );
	if ( ! $handle instanceof mysqli ) {
		throw new RuntimeException( 'Handle principal indisponível.' );
	}
	return $handle;
}
