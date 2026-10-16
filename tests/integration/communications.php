<?php
/**
 * Exclusive communications SQL acceptance.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI ) {
	return;
}
require_once __DIR__ . '/f112-fixture.php';
arn_f112_storage_test( 'communications' );
