<?php
/**
 * Relatório privado e limitado de colisões com vínculos anteriores.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\LocalIdentityLink;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Destino privado fixo, bloqueio exclusivo, sem dados pessoais ou detalhes nos erros.
final class PrivateWorkspaceConflictReporter {
	public function __construct( private readonly string $root, private readonly string $public_root, private readonly array $repositories ) {}
	public static function configured(): self {
		if ( ! defined( 'ARN_WORKSPACE_PRIVATE_DIR' ) || ! is_string( ARN_WORKSPACE_PRIVATE_DIR ) || ! defined( 'ABSPATH' ) ) {
			throw new RuntimeException( 'Relatório Workspace indisponível.' );}
		return new self( ARN_WORKSPACE_PRIVATE_DIR, ABSPATH, array( dirname( __DIR__, 6 ) ) );
	}
	public function record( string $registration_id, LocalIdentityLink $link, int $now ): void {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Sem saída de caminhos privados.
		set_error_handler(
			static function (): never {
				throw new RuntimeException();
			}
		);
		try {
			if ( 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $registration_id ) || $now < 1 ) {
				throw new RuntimeException();}
			$privacy = new PrivateWorkspaceSource( $this->root, $this->public_root, $this->repositories );
			$root    = $privacy->protectedPath( $this->root, true );
			$path    = $root . DIRECTORY_SEPARATOR . 'claim-conflicts.json';
			if ( ! file_exists( $path ) ) {
				$created = fopen( $path, 'x+b' );
				if ( false === $created ) {
					throw new RuntimeException();
				}fclose( $created );
				if ( 'Windows' !== PHP_OS_FAMILY ) {
					chmod( $path, 0600 );}
			}
			$path   = $privacy->protectedPath( $path );
			$handle = fopen( $path, 'r+b' );
			if ( false === $handle ) {
				throw new RuntimeException();}
			try {
				if ( ! flock( $handle, LOCK_EX ) ) {
					throw new RuntimeException();
				}$stat = fstat( $handle );
				if ( ! is_array( $stat ) || $stat['size'] > 65536 || $stat['nlink'] > 1 ) {
					throw new RuntimeException();}
				$bytes = stream_get_contents( $handle, 65537 );
				$rows  = '' === $bytes ? array() : json_decode( $bytes, true, 512, JSON_THROW_ON_ERROR );
				if ( ! is_array( $rows ) || count( $rows ) > 73 ) {
					throw new RuntimeException();}
				$key = hash( 'sha256', $registration_id . "\0" . $link->issuer . "\0" . $link->subject );
				if ( array_key_exists( $key, $rows ) ) {
					return;}
				$rows[ $key ] = array(
					'registration_id'  => $registration_id,
					'existing_user_id' => $link->user_id,
					'first_seen'       => $now,
					'code'             => 'existing_pair_preserved',
				);
				$encoded      = json_encode( $rows, JSON_THROW_ON_ERROR );
				if ( strlen( $encoded ) > 65536 ) {
					throw new RuntimeException();}
				rewind( $handle );
				if ( fwrite( $handle, $encoded ) !== strlen( $encoded ) || ! ftruncate( $handle, strlen( $encoded ) ) || ! fflush( $handle ) ) {
					throw new RuntimeException();}
			} finally {
				flock( $handle, LOCK_UN );
				fclose( $handle );}
		} catch ( Throwable ) {
			throw new RuntimeException( 'Relatório Workspace indisponível.' );
		} finally {
			restore_error_handler();}
	}
}
