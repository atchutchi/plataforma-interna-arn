<?php
/**
 * Configuração JSON externa à raiz pública e ao repositório.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use RuntimeException;
use Throwable;

/** Leitura explícita sem executar PHP fornecido por configuração. */
final class ProtectedOidcConfiguration {

	/**
	 * Carrega apenas o ficheiro privado indicado pelo operador local.
	 *
	 * @param string $file             Ficheiro JSON externo.
	 * @param string $public_root      Raiz servida pelo WordPress.
	 * @param string $repository_root  Raiz da cópia Git.
	 * @param string $environment_type Ambiente WordPress efectivo.
	 * @return OidcConfiguration|null Configuração activa ou desactivada.
	 * @throws RuntimeException Quando a configuração protegida é inválida.
	 */
	public static function load( string $file, string $public_root, string $repository_root, string $environment_type ): ?OidcConfiguration {
		try {
			$resolved = realpath( $file );
			if ( false === $resolved || ! is_file( $resolved ) || is_link( $file ) || ! is_readable( $resolved ) ) {
				throw new RuntimeException();
			}
			foreach ( array( $public_root, $repository_root ) as $root ) {
				$resolved_root = realpath( $root );
				if ( false === $resolved_root || self::inside( $resolved, $resolved_root ) ) {
					throw new RuntimeException();
				}
			}
			if ( filesize( $resolved ) > 65536 || ( 'Windows' !== PHP_OS_FAMILY && 0 !== ( fileperms( $resolved ) & 0077 ) ) ) {
				throw new RuntimeException();
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Configuração local protegida, nunca URL.
			$values = json_decode( file_get_contents( $resolved ), true, 512, JSON_THROW_ON_ERROR );
			if ( ! is_array( $values ) ) {
				throw new RuntimeException();
			}
			return OidcConfiguration::fromEnvironment(
				static function ( string $name ) use ( $values ): string|false {
					$value = $values[ $name ] ?? false;
					return is_string( $value ) ? $value : false;
				},
				$environment_type
			);
		} catch ( Throwable ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante, sem causa ou valores de configuração.
			throw new RuntimeException( 'Configuração protegida OIDC indisponível.' );
		}
	}

	/**
	 * Compara caminhos resolvidos com fronteira de directório.
	 *
	 * @param string $file Ficheiro resolvido.
	 * @param string $root Raiz resolvida.
	 */
	private static function inside( string $file, string $root ): bool {
		$file = str_replace( '\\', '/', $file );
		$root = rtrim( str_replace( '\\', '/', $root ), '/' );
		if ( 'Windows' === PHP_OS_FAMILY ) {
			$file = strtolower( $file );
			$root = strtolower( $root );
		}
		return $file === $root || str_starts_with( $file, $root . '/' );
	}
}
