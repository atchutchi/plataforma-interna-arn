<?php
/**
 * Autoloader PSR-4 do plugin.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet;

/**
 * Carrega classes do namespace Arn\Intranet a partir de src/. Não depende do Composer.
 */
final class Autoloader {

	private const PREFIX = 'Arn\\Intranet\\';

	/**
	 * Regista o autoloader uma única vez.
	 *
	 * @param string $base_dir Diretório src/ do plugin.
	 */
	public static function register( string $base_dir ): void {
		static $registered = false;

		if ( $registered ) {
			return;
		}

		$registered = true;
		$base_dir   = rtrim( $base_dir, '/\\' ) . DIRECTORY_SEPARATOR;

		spl_autoload_register(
			static function ( string $class_name ) use ( $base_dir ): void {
				if ( 0 !== strncmp( $class_name, self::PREFIX, strlen( self::PREFIX ) ) ) {
					return;
				}

				$relative = substr( $class_name, strlen( self::PREFIX ) );
				$file     = $base_dir . str_replace( '\\', DIRECTORY_SEPARATOR, $relative ) . '.php';

				if ( is_file( $file ) ) {
					require $file;
				}
			}
		);
	}
}
