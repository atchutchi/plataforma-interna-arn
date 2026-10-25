<?php
/** Revalidate immutable scanner results before importing into the delivery store.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class PreparedBatch {
	private array $results = array();
	private readonly string $root;

	public function __construct( array $state, string $root, private readonly array $excluded, int $now ) {
		new PrivateBlobStore( $root, $excluded, array( 'application/pdf' ), 1048576 );
		$this->root = str_replace( '\\', '/', realpath( $root ) );
		foreach ( $state['items'] as $i => $item ) {
			$at = 0;
			foreach ( $state['history'] as $event ) {
				if ( 'processed' === $event['type'] && $i === $event['item'] ) {
					$at = $event['at']; }
			}
			if ( 'ready' !== $item['status'] || $at < 1 || $at > $now || $now - $at > 86400 ) {
				throw new RuntimeException( 'Análise em falta ou expirada. Pede nova análise.' ); }
			$result = $item['result'];
			$job    = $this->root . '/' . $result['attempt'];
			new PrivateBlobStore( $job, $excluded, array( 'application/pdf' ), 1048576 );
			$paths = array();
			foreach ( $result['hashes'] as $variant => $hash ) {
				$path = $job . '/' . ( 'original' === $variant ? 'source' : $variant . '.png' );
				self::verify( $path, $hash );
				$paths[ $variant ] = $path;
			}
			$this->results[] = array(
				'job'    => $job,
				'paths'  => $paths,
				'hashes' => $result['hashes'],
			);
		}
	}

	private static function verify( string $path, string $hash ): void {
		clearstatcache( true, $path );
		$stat = is_file( $path ) ? lstat( $path ) : false;
		if ( false === $stat || is_link( $path ) || 1 !== $stat['nlink'] || $stat['size'] < 1 || $stat['size'] > 1048576 || ! hash_equals( $hash, hash_file( 'sha256', $path ) ) ) {
			throw new RuntimeException( 'Resultado técnico alterado.' ); }
	}

	public function prepare( mixed $source ): array {
		new PrivateBlobStore( $this->root, $this->excluded, array( 'application/pdf' ), 1048576 );
		$owned = is_string( $source );
		if ( $owned && ( is_link( $source ) || ! is_file( $source ) ) ) {
			throw new RuntimeException( 'Original inválido.' ); }
		$handle = $owned ? fopen( $source, 'rb' ) : $source;
		try {
			$stat = is_resource( $handle ) ? fstat( $handle ) : false;
			if ( false === $stat || 1 !== $stat['nlink'] || 0100000 !== ( $stat['mode'] & 0170000 ) || $stat['size'] < 1 || $stat['size'] > 1048576 || ! rewind( $handle ) ) {
				throw new RuntimeException( 'Original inválido.' ); }
			$bytes = stream_get_contents( $handle, 1048577 );
			$hash  = hash( 'sha256', $bytes );
			foreach ( $this->results as $key => $result ) {
				if ( $result['hashes']['original'] !== $hash ) {
					continue; }
				new PrivateBlobStore( $result['job'], $this->excluded, array( 'application/pdf' ), 1048576 );
				foreach ( $result['paths'] as $variant => $path ) {
					self::verify( $path, $result['hashes'][ $variant ] ); }
				unset( $this->results[ $key ] );
				return $result;
			}
			throw new RuntimeException( 'Original não pertence a esta análise.' );
		} finally {
			if ( $owned && is_resource( $handle ) ) {
				fclose( $handle ); }
		}
	}

	public function prepared( array $prepared, array $ids ): void {
		// Registration and its exact descriptors are audited by the native SQL repository.
	}
}
