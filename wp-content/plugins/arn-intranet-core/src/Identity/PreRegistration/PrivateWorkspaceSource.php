<?php
/**
 * Lê bytes privados limitados e verifica a sua integridade.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity\PreRegistration;

use RuntimeException;
use Throwable;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Fonte local privada com leitura limitada, sem URLs.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante sem detalhes privados.
/**
 * Fronteira de ficheiros para CLI, separada da validação pura.
 */
final class PrivateWorkspaceSource {
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string   $private_root Raiz privada explícita.
	 * @param string   $public_root Document root.
	 * @param string[] $repositories Checkouts a excluir.
	 */
	public function __construct( private readonly string $private_root, private readonly string $public_root, private readonly array $repositories ) {}
	/**
	 * Valida também os destinos de relatórios privados, sem ler payload.
	 *
	 * @param string $path Caminho absoluto existente.
	 * @param bool   $directory Exigir directório em vez de ficheiro regular.
	 * @return string Caminho físico validado.
	 * @throws RuntimeException Quando o destino não é privado.
	 */
	public function protectedPath( string $path, bool $directory = false ): string {
		try {
			$root   = self::resolved( $this->private_root );
			$target = self::resolved( $path );
			if ( ! self::inside( $target, $root ) || ! is_dir( $root ) || ( $directory ? ! is_dir( $target ) : ! is_file( $target ) ) ) {
				throw new RuntimeException();}
			foreach ( array_merge( array( $this->public_root ), $this->repositories )as$excluded ) {
				if ( self::inside( $root, self::resolved( $excluded, false ) ) || self::inside( $target, self::resolved( $excluded, false ) ) ) {
					throw new RuntimeException();}
			}
			self::permissions( $root );
			if ( $target !== $root ) {
				self::permissions( $target );}
			$stat = stat( $target );
			if ( ! is_array( $stat ) || ( ! $directory && ( $stat['nlink'] > 1 || ( $stat['mode'] & 0170000 ) !== 0100000 ) ) ) {
				throw new RuntimeException();}
			return $target;
		} catch ( Throwable ) {
			throw new RuntimeException( 'Destino privado Workspace indisponível.' );}
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $inventory_path Selecção privada.
	 * @param string $source_path Origem privada.
	 * @param string $inventory_sha256 Hash esperado.
	 * @param string $source_sha256 Hash esperado.
	 * @param string $domain Domínio.
	 * @param int    $expected_count População.
	 * @param int    $now Relógio UTC.
	 * @return WorkspaceBatch Lote validado.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	public static function load( string $inventory_path, string $source_path, string $inventory_sha256, string $source_sha256, string $domain, int $expected_count, int $now ): WorkspaceBatch {
		if ( ! defined( 'ARN_WORKSPACE_PRIVATE_DIR' ) || ! is_string( ARN_WORKSPACE_PRIVATE_DIR ) || ! defined( 'ABSPATH' ) ) {
			throw new RuntimeException( 'Fonte privada Workspace indisponível.' ); }
		return ( new self( ARN_WORKSPACE_PRIVATE_DIR, ABSPATH, array( dirname( __DIR__, 6 ) ) ) )->read( $inventory_path, $source_path, $inventory_sha256, $source_sha256, $domain, $expected_count, $now );
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $inventory_path Selecção privada.
	 * @param string $source_path Origem privada.
	 * @param string $inventory_sha256 Hash esperado.
	 * @param string $source_sha256 Hash esperado.
	 * @param string $domain Domínio.
	 * @param int    $expected_count População.
	 * @param int    $now Relógio UTC.
	 * @return WorkspaceBatch Lote sem efeitos de aplicação.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	public function read( string $inventory_path, string $source_path, string $inventory_sha256, string $source_sha256, string $domain, int $expected_count, int $now ): WorkspaceBatch {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Converte warnings de leitura privada em erro genérico, sem caminhos ou conteúdo.
		set_error_handler(
			static function (): never {
				throw new RuntimeException();
			}
		);
		try {
			$root = self::resolved( $this->private_root );
			foreach ( array_merge( array( $this->public_root ), $this->repositories ) as $excluded ) {
				$excluded = self::resolved( $excluded, false );
				if ( self::inside( $root, $excluded ) ) {
					throw new RuntimeException(); }
			}
			self::permissions( $root );
			$inventory = $this->bytes( $inventory_path, $inventory_sha256, $root );
			$source    = $this->bytes( $source_path, $source_sha256, $root );
			return WorkspaceBatch::validate( $inventory, $source, $source_sha256, $inventory_sha256, $domain, $expected_count, $now );
		} catch ( Throwable ) {
			throw new RuntimeException( 'Fonte privada Workspace indisponível.' );
		} finally {
			restore_error_handler();
		}
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $path Ficheiro local.
	 * @param string $hash SHA-256 esperado.
	 * @param string $root Raiz validada.
	 * @return array JSON dos mesmos bytes provados.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	private function bytes( string $path, string $hash, string $root ): array {
		$path = self::resolved( $path );
		if ( ! self::inside( $path, $root ) || ! is_file( $path ) || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $hash ) ) {
			throw new RuntimeException(); }
		foreach ( array_merge( array( $this->public_root ), $this->repositories ) as $excluded ) {
			if ( self::inside( $path, self::resolved( $excluded, false ) ) ) {
				throw new RuntimeException(); }
		}
		self::permissions( $path );
		$handle = fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new RuntimeException(); }
		try {
			if ( ! flock( $handle, LOCK_SH ) ) {
				throw new RuntimeException(); }
			$stat = fstat( $handle );
			if ( ! is_array( $stat ) || $stat['size'] < 1 || $stat['size'] > 524288 || $stat['nlink'] > 1 || ( $stat['mode'] & 0170000 ) !== 0100000 ) {
				throw new RuntimeException(); }
			$bytes = stream_get_contents( $handle, 524289 );
			if ( ! is_string( $bytes ) || strlen( $bytes ) !== $stat['size'] || ! hash_equals( $hash, hash( 'sha256', $bytes ) ) ) {
				throw new RuntimeException(); }
			$decoded = json_decode( $bytes, true, 512, JSON_THROW_ON_ERROR );
			if ( ! is_array( $decoded ) ) {
				throw new RuntimeException(); }
			return $decoded;
		} finally {
			fclose( $handle );
		}
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $path Caminho absoluto sem redireccionamentos.
	 * @param bool   $reject_git Recusar pertença a qualquer checkout Git.
	 * @return string Caminho físico.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	private static function resolved( string $path, bool $reject_git = true ): string {
		if ( 1 !== preg_match( 'Windows' === PHP_OS_FAMILY ? '/\A[A-Za-z]:[\\\\\/]/' : '/\A\//', $path ) ) {
			throw new RuntimeException(); }
		$resolved = realpath( $path );
		if ( false === $resolved || self::normal( $resolved ) !== self::normal( $path ) ) {
			throw new RuntimeException(); }
		$cursor = $resolved;
		while ( true ) {
			if ( is_link( $cursor ) || ( $reject_git && file_exists( $cursor . '/.git' ) ) ) {
				throw new RuntimeException(); }
			$parent = dirname( $cursor );
			if ( $parent === $cursor ) {
				break; }
			$cursor = $parent;
		}
		return $resolved;
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $file Caminho.
	 * @param string $root Raiz.
	 * @return bool Pertença física.
	 */
	private static function inside( string $file, string $root ): bool {
		$file = self::normal( $file );
		$root = self::normal( $root );
		return $file === $root || str_starts_with( $file, $root . '/' );
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $path Caminho físico.
	 * @return string Caminho comparável.
	 */
	private static function normal( string $path ): string {
		$path = rtrim( str_replace( '\\', '/', $path ), '/' );
		return 'Windows' === PHP_OS_FAMILY ? strtolower( $path ) : $path;
	}
	/**
	 * Valores de entrada e resultado.
	 *
	 * @param string $path Entrada privada já resolvida.
	 * @throws \RuntimeException Quando a validação ou a leitura protegida falha.
	 */
	private static function permissions( string $path ): void {
		if ( 'Windows' !== PHP_OS_FAMILY ) {
			if ( 0 !== ( fileperms( $path ) & 0077 ) ) {
				throw new RuntimeException(); }
			return;
		}
		$script                                = '$ErrorActionPreference="Stop"; $env:PSModulePath=$PSHOME+"\Modules"; try { Import-Module Microsoft.PowerShell.Security -ErrorAction Stop; $allowed=@([System.Security.Principal.WindowsIdentity]::GetCurrent().User.Value,"S-1-5-18","S-1-5-32-544"); $acl=Get-Acl -LiteralPath $env:ARN_WORKSPACE_ACL_PATH; $owner=$acl.GetOwner([System.Security.Principal.SecurityIdentifier]).Value; if($owner -notin $allowed){exit 1}; foreach($rule in $acl.GetAccessRules($true,$true,[System.Security.Principal.SecurityIdentifier])){if($rule.AccessControlType -eq "Allow" -and $rule.IdentityReference.Value -notin $allowed){exit 1}}; Write-Output "OK"; exit 0 } catch { exit 1 }';
		$environment                           = getenv();
		$environment['ARN_WORKSPACE_ACL_PATH'] = $path;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Consulta ACL local sem interpolar caminhos ou payloads em código de shell.
		$process = proc_open(
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- UTF-16LE exigido por EncodedCommand, evita interpolação e quoting de argumentos Windows.
			array( 'powershell.exe', '-NoLogo', '-NoProfile', '-NonInteractive', '-EncodedCommand', base64_encode( mb_convert_encoding( $script, 'UTF-16LE', 'UTF-8' ) ) ),
			array( array( 'pipe', 'r' ), array( 'pipe', 'w' ), array( 'pipe', 'w' ) ),
			$pipes,
			null,
			$environment,
			array(
				'bypass_shell'     => true,
				'create_no_window' => true,
			)
		);
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException(); }
		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );
		$deadline = microtime( true ) + 5;
		$output   = '';
		$status   = proc_get_status( $process );
		while ( $status['running'] && microtime( true ) < $deadline ) {
			$output .= stream_get_contents( $pipes[1], 16 );
			stream_get_contents( $pipes[2], 1024 );
			usleep( 10000 );
			$status = proc_get_status( $process ); }
		if ( $status['running'] ) {
			proc_terminate( $process ); }
		$output .= stream_get_contents( $pipes[1], 16 );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );
		if ( $status['running'] || 0 !== $status['exitcode'] || 'OK' !== trim( $output ) ) {
			throw new RuntimeException(); }
	}
}
