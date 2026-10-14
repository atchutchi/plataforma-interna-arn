<?php
/** Instalação explícita de unidades no laboratório.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Storage;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
/** Guarda e instalação explícita do esquema local. */
final class LocalUnitInstaller {
	/**
	 * Confirma ambiente, flag e capacidade do actor.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function allowed(): bool {
		return defined( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) && true === ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE && 'local' === wp_get_environment_type() && get_current_user_id() > 0 && current_user_can( 'manage_options' );
	}
	/**
	 * Abre o repositório dedicado após a guarda local.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function repository(): WpdbLocalUnitRepository {
		if ( ! self::allowed() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Unidades locais indisponíveis.' );
		}
		global $wpdb;
		$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$database->set_prefix( $wpdb->prefix );
		return new WpdbLocalUnitRepository( $database );
	}
	/**
	 * Instala explicitamente o esquema por CLI.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function install(): void {
		if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! self::allowed() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Instalação exclusiva do administrador local por CLI.' );
		}
		self::repository()->installSchema();
	}
}
