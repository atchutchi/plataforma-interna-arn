<?php
/** Explicit CLI-only foundation for isolated task SQL tests.
 *
 * @package ArnIntranet
 */

if ( 'cli' !== PHP_SAPI || ! class_exists( 'WP_CLI' ) ) {
	return;
}
if ( ! defined( 'ARN_TASKS_SQL_HARNESS' ) ) {
	define( 'ARN_TASKS_SQL_HARNESS', true );
}
WP_CLI::add_wp_hook( 'pre_get_ready_cron_jobs', static fn(): array => array(), PHP_INT_MAX );
WP_CLI::add_wp_hook( 'pre_wp_mail', static fn(): bool => false, PHP_INT_MAX );
