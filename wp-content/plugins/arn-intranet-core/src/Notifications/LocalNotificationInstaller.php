<?php
/** Instalação explícita exclusiva do laboratório.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Notifications;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
/** Nunca ligada à activação ou a pedidos HTTP. */
final class LocalNotificationInstaller {
	/** Abre ligação dedicada com prefixo corrente.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public static function repository(): WpdbNotificationRepository {
		global $wpdb;
		$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$database->set_prefix( $wpdb->prefix );
		return new WpdbNotificationRepository( $database );
	}
	/** Instala apenas o contrato ausente e recusa incompatibilidades.
	 *
	 * @throws RuntimeException Quando a fronteira ou a persistência recusa a operação.
	 */
	public static function install(): void {
		if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) ) {
		 // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem estática CLI.
			throw new RuntimeException( 'Instalação permitida apenas por administrador no laboratório local.' );
		}
		self::repository()->installSchema();
	}
}
