<?php
/** Relatórios operacionais privados, sem saída de identificadores.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Escrita exclusiva na raiz com ACL validada. Erros constantes.
final class PrivateWorkspacePreflightReport {
	public function __construct( private readonly string $root, private readonly string $public_root, private readonly array $repositories ) {}
	public static function configured(): self {
		if ( ! defined( 'ARN_WORKSPACE_PRIVATE_DIR' ) || ! is_string( ARN_WORKSPACE_PRIVATE_DIR ) || ! defined( 'ABSPATH' ) ) {
			throw new RuntimeException( 'Relatório Workspace indisponível.' );
		}
		return new self( ARN_WORKSPACE_PRIVATE_DIR, ABSPATH, array( dirname( __DIR__, 6 ) ) );
	}
	public function save( array $report ): string {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Não expor caminhos nos avisos nativos.
		set_error_handler(
			static function (): never {
				throw new RuntimeException();
			}
		);
		$handle = null;
		try {
			$privacy = new PrivateWorkspaceSource( $this->root, $this->public_root, $this->repositories );
			$root    = $privacy->protectedPath( $this->root, true );
			$bytes   = json_encode( $report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE );
			if ( strlen( $bytes ) > 524288 ) {
				throw new RuntimeException(); }
			$path   = $root . DIRECTORY_SEPARATOR . 'preflight-' . gmdate( 'YmdHis' ) . '-' . bin2hex( random_bytes( 8 ) ) . '.json';
			$handle = fopen( $path, 'x+b' );
			if ( false === $handle ) {
				throw new RuntimeException(); }
			if ( 'Windows' !== PHP_OS_FAMILY ) {
				chmod( $path, 0600 ); }
			$privacy->protectedPath( $path );
			if ( fwrite( $handle, $bytes ) !== strlen( $bytes ) || ! fflush( $handle ) ) {
				throw new RuntimeException(); }
			return $path;
		} catch ( Throwable ) {
			throw new RuntimeException( 'Relatório Workspace indisponível.' );
		} finally {
			if ( is_resource( $handle ) ) {
				fclose( $handle );
			}restore_error_handler();
		}
	}
}
