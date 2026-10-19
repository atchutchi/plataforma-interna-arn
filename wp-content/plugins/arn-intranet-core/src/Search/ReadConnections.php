<?php
/** Owns only the dedicated connections created for one search adapter call.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Search;

use Arn\Intranet\Identity\IdentityWpdb;

// phpcs:disable Squiz.Commenting -- No SQL, transaction changes or global connection reuse.
final class ReadConnections {
	private array $connections = array();
	public function open(): IdentityWpdb {
		global $wpdb;
		$db                  = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$this->connections[] = $db;
		$db->set_prefix( $wpdb->prefix );
		return $db;
	}
	public function close(): void {
		foreach ( $this->connections as $connection ) {
			$connection->close(); }
		$this->connections = array();
	}
	public function __destruct() {
		$this->close(); }
}
