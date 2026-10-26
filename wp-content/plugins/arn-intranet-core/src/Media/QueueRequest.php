<?php
/** Strict HTTP input contract, before touching the private queue.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class QueueRequest {
	public static function parse( string $method, array $get, array $post, array $files, callable $nonce, callable $uploaded ): array {
		if ( array( 'page' => 'arn-media-queue' ) !== $get ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		if ( 'GET' === $method && array() === $post && array() === $files ) {
			return array( 'action' => 'list' ); }
		if ( 'POST' !== $method || ! isset( $post['nonce'], $post['action'] ) || ! is_string( $post['nonce'] ) || true !== $nonce( $post['nonce'] ) ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		$keys = array_keys( $post );
		sort( $keys );
		if ( in_array( $post['action'], array( 'supervisor_stop', 'supervisor_ack' ), true ) ) {
			if ( array( 'action', 'nonce', 'reason', 'revision' ) !== $keys || array() !== $files || ! is_string( $post['reason'] ) || ! is_string( $post['revision'] ) || 1 !== preg_match( '/^[1-9][0-9]{0,3}$/D', $post['revision'] ) || (int) $post['revision'] > 1000 ) {
				throw new RuntimeException( 'Decisão inválida.' ); }
			return $post;
		}
		if ( 'enqueue' === $post['action'] ) {
			if ( array( 'action', 'nonce', 'request_id' ) !== $keys || ! is_string( $post['request_id'] ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $post['request_id'] ) || array( 'media' ) !== array_keys( $files ) || ! is_array( $files['media'] ) ) {
				throw new RuntimeException( 'Lote inválido.' ); }
			$upload = $files['media'];
			if ( array_diff( array_keys( $upload ), array( 'name', 'full_path', 'type', 'tmp_name', 'error', 'size' ) ) || ! isset( $upload['tmp_name'], $upload['error'], $upload['size'] ) || ! is_array( $upload['tmp_name'] ) || ! array_is_list( $upload['tmp_name'] ) || count( $upload['tmp_name'] ) < 1 || count( $upload['tmp_name'] ) > 8 ) {
				throw new RuntimeException( 'Lote inválido.' ); }
			foreach ( $upload as $values ) {
				if ( ! is_array( $values ) || array_keys( $values ) !== array_keys( $upload['tmp_name'] ) ) {
					throw new RuntimeException( 'Lote inválido.' ); }
			}
			foreach ( $upload['tmp_name'] as $i => $path ) {
				if ( ! is_string( $path ) || UPLOAD_ERR_OK !== $upload['error'][ $i ] || ! is_int( $upload['size'][ $i ] ) || $upload['size'][ $i ] < 1 || $upload['size'][ $i ] > 1048576 || true !== $uploaded( $path ) ) {
					throw new RuntimeException( 'Cada ficheiro deve ter até 1 MiB e ter sido enviado por este formulário.' ); }
			}
			return array(
				'action'     => 'enqueue',
				'sources'    => $upload['tmp_name'],
				'request_id' => $post['request_id'],
			);
		}
		if ( array( 'action', 'batch', 'item', 'nonce', 'reason', 'revision' ) !== $keys || array() !== $files || ! is_string( $post['action'] ) || ! in_array( $post['action'], array( 'retry', 'hold', 'cancel', 'recover', 'archive' ), true ) || ! is_string( $post['batch'] ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $post['batch'] ) || ! is_string( $post['revision'] ) || 1 !== preg_match( '/^[1-9][0-9]{0,2}$/D', $post['revision'] ) || ! is_string( $post['item'] ) || ! in_array( $post['item'], array( '-1', '0', '1', '2', '3', '4', '5', '6', '7' ), true ) || ! is_string( $post['reason'] ) ) {
			throw new RuntimeException( 'Decisão inválida.' ); }
		return $post;
	}
}
