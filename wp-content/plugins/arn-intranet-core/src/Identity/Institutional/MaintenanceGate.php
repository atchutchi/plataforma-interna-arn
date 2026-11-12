<?php
/** Guarda precoce, com leitores partilhados e migração exclusiva.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class MaintenanceGate {
	private mixed $handle   = null;
	private bool $exclusive = false;
	public readonly string $directory;
	public function __construct( string $directory, string $webroot, private readonly bool $operator ) {
		$real = realpath( $directory );
		$root = realpath( $webroot );
		if ( false === $real || false === $root || ! is_dir( $real ) || is_link( $directory ) || str_starts_with( strtolower( str_replace( '\\', '/', $real ) ) . '/', strtolower( str_replace( '\\', '/', $root ) ) . '/' ) ) {
			throw new RuntimeException( 'Directório de manutenção inválido.' );
		}
		$this->directory = $real;
		clearstatcache( true, $real . '/maintenance' );
		if ( is_link( $real . '/gate.lock' ) || is_link( $real . '/maintenance' ) ) {
			throw new RuntimeException( 'Guarda de manutenção inválida.' );
		}
		$this->handle = fopen( $real . '/gate.lock', 'c+b' );
		if ( false === $this->handle || ! flock( $this->handle, LOCK_SH | LOCK_NB ) || ( ! $operator && file_exists( $real . '/maintenance' ) ) ) {
			$this->close();
			throw new RuntimeException( 'Manutenção institucional activa.' );
		}
	}
	public function exclusive(): void {
		if ( ! $this->operator || ! is_resource( $this->handle ) ) {
			throw new RuntimeException( 'Operador de manutenção obrigatório.' );
		}
		clearstatcache( true, $this->directory . '/maintenance' );
		if ( ! file_exists( $this->directory . '/maintenance' ) ) {
			$marker = fopen( $this->directory . '/maintenance', 'x+b' );
			if ( false === $marker ) {
				throw new RuntimeException( 'Não foi possível fechar a manutenção.' ); }
			$durable = fflush( $marker ) && fsync( $marker );
			fclose( $marker );
			if ( ! $durable ) {
				throw new RuntimeException( 'Manutenção não persistida.' ); }
		}
		flock( $this->handle, LOCK_UN );
		if ( ! flock( $this->handle, LOCK_EX | LOCK_NB ) ) {
			throw new RuntimeException( 'Existem operações em curso. Manutenção mantida.' );
		}
		$this->exclusive = true;
	}
	public function assertExclusive(): void {
		if ( ! $this->exclusive || ! is_resource( $this->handle ) || ! file_exists( $this->directory . '/maintenance' ) ) {
			throw new RuntimeException( 'Manutenção exclusiva indisponível.' );
		}
	}
	public function releaseMaintenance(): void {
		$this->assertExclusive();
		if ( ! unlink( $this->directory . '/maintenance' ) ) {
			throw new RuntimeException( 'Manutenção não libertada.' );
		}
	}
	public function close(): void {
		if ( is_resource( $this->handle ) ) {
			flock( $this->handle, LOCK_UN );
			fclose( $this->handle ); }
		$this->handle    = null;
		$this->exclusive = false;
	}
	public function __destruct() {
		$this->close(); }
}
