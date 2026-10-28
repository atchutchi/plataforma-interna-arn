<?php
/** Explicit leave resources reuse, but never modify, the request fixture accounts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Requests\LocalRoster;
use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.DB -- Bound schema preflight and explicit CLI creation.
final class LocalInstaller {
	public function install(): array {
		if ( ! Configuration::current() || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || ! \Arn\Intranet\Requests\InstallationState::ready() ) {
			throw new RuntimeException( 'Instalação fictícia de férias recusada.' );
		}
		global $wpdb;
		$roster   = LocalRoster::resolve();
		$repeat   = InstallationState::exists( $wpdb->prefix );
		$pages    = new Pages();
		$page_ids = $pages->preflight( $repeat );
		if ( $repeat && ! InstallationState::ready() ) {
			throw new RuntimeException( 'Instalação anterior incompleta.' );
		}
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $wpdb->prefix );
		$db->suppress_errors( true );
		try {
			$schema = new Schema( $db );
			$tables = $db->get_col( $db->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $wpdb->prefix . 'arn_leave' ) . '%' ) );
			if ( ! is_array( $tables ) || '' !== $db->last_error || ( ! $repeat && $tables ) ) {
				throw new RuntimeException( 'Instalação parcial recusada.' );
			}
			$schema->install();
			if ( ! $repeat ) {
				$page_ids = $pages->install();
				$users    = array();
				foreach ( LocalRoster::definitions()as$label => $person ) {
							$ids = array_keys( array_filter( $roster, static fn( array $r ): bool=>$r['login'] === $person['login'] ) );
					if ( count( $ids ) !== 1 ) {
						throw new RuntimeException( 'Elenco indisponível.' );
					}$users[ $label ] = $ids[0];
				}InstallationState::write( $wpdb->prefix, $users, $page_ids );}
			return $page_ids;
		} finally {
			$db->close();}
	}
}
