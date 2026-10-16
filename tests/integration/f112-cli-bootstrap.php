<?php
/**
 * Explicit SQL-test bootstrap, loaded before WordPress.
 *
 * @package ArnIntranet
 */

if ( 'cli' !== PHP_SAPI || ! class_exists( 'WP_CLI' ) ) {
	return;
}
if ( ! defined( 'ARN_F112_SQL_HARNESS' ) ) {
	define( 'ARN_F112_SQL_HARNESS', true );
}
// Keep automatic background housekeeping out of a strict before/after SQL test.
// This changes no database row, scheduled event, application gate or wp-config.
WP_CLI::add_wp_hook( 'pre_get_ready_cron_jobs', static fn(): array => array(), PHP_INT_MAX );
