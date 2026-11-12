<?php
/** Diagnóstico CLI reservado ao administrador técnico.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Sem opção apply e sem entrada HTTP. */
final class MigrationCommand {
	public function preflight( array $args, array $options ): void {
		$database = null;
		try {
			if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || is_multisite() || ! current_user_can( 'manage_options' ) || $args || $options ) {
				throw new RuntimeException();
			}
			global $wpdb;
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $wpdb->prefix );
			$result = ( new MigrationPreflight( $database ) )->inspect();
			\WP_CLI::line( wp_json_encode( $result, JSON_THROW_ON_ERROR ) );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Diagnóstico recusado. Confirmar autoridade CLI e compatibilidade do schema. Nenhuma migração aplicada.' );
		} finally {
			if ( null !== $database ) {
				$database->close();
			}
		}
	}
}
