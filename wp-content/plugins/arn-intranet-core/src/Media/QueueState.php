<?php
/** Closed state machine for a technical quarantine batch.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class QueueState {
	public static function apply( ?array $state, array $event ): array {
		if ( array_keys( $event ) !== array( 'type', 'actor', 'at', 'item', 'reason', 'data' ) || ! is_int( $event['actor'] ) || $event['actor'] < 1 || ! is_int( $event['at'] ) || $event['at'] < 1 || ! is_int( $event['item'] ) || ! is_string( $event['reason'] ) || ! is_array( $event['data'] ) ) {
			throw new RuntimeException( 'Diário de fila inválido.' );
		}
		$type = $event['type'];
		if ( null === $state ) {
			if ( 'queued' !== $type || -1 !== $event['item'] || '' !== $event['reason'] || ! array_is_list( $event['data'] ) || count( $event['data'] ) < 1 || count( $event['data'] ) > 8 ) {
				throw new RuntimeException( 'Lote inválido.' );
			}
			$items = array();
			foreach ( $event['data'] as $row ) {
				if ( ! is_array( $row ) || array_keys( $row ) !== array( 'id', 'sha256', 'size', 'mime' ) ) {
					throw new RuntimeException( 'Original inválido.' ); }
				$blob = new BlobDescriptor( ...$row );
				if ( $blob->size > 1048576 || in_array( $blob->id, array_column( array_column( $items, 'blob' ), 'id' ), true ) ) {
					throw new RuntimeException( 'Limite de originais inválido.' ); }
				$items[] = array(
					'blob'     => $row,
					'status'   => 'queued',
					'attempts' => 0,
					'retries'  => 0,
					'result'   => array(),
				);
			}
			return array(
				'revision'   => 1,
				'created_at' => $event['at'],
				'updated_at' => $event['at'],
				'owner'      => $event['actor'],
				'archived'   => false,
				'items'      => $items,
				'history'    => array( $event ),
			);
		}
		if ( $state['archived'] || $event['at'] < $state['updated_at'] || $state['revision'] >= 200 ) {
			throw new RuntimeException( 'Transição indisponível.' ); }
		if ( in_array( $type, array( 'retry', 'hold', 'cancel', 'recover', 'archive' ), true ) ) {
			if ( preg_match_all( '/./us', trim( $event['reason'] ) ) < 10 || preg_match_all( '/./us', $event['reason'] ) > 300 || preg_match( '/[<>\x00-\x1f]/u', $event['reason'] ) || ! preg_match( '//u', $event['reason'] ) || array() !== $event['data'] ) {
				throw new RuntimeException( 'Indica um motivo entre 10 e 300 caracteres, sem marcação.' ); }
		} elseif ( '' !== $event['reason'] ) {
			throw new RuntimeException( 'Evento inválido.' );
		}
		if ( 'archive' === $type ) {
			if ( -1 !== $event['item'] || array_intersect( array( 'queued', 'processing' ), array_column( $state['items'], 'status' ) ) ) {
				throw new RuntimeException( 'O lote ainda tem trabalho pendente.' ); }
			$state['archived'] = true;
		} else {
			$i = $event['item'];
			if ( ! isset( $state['items'][ $i ] ) ) {
				throw new RuntimeException( 'Entrada inválida.' ); }
			$item = &$state['items'][ $i ];
			$from = $item['status'];
			$next = match ( $type ) {
				'claim' => 'queued' === $from && $item['attempts'] < 3 ? 'processing' : null,
				'processed' => 'processing' === $from ? 'ready' : null,
				'failed', 'recover' => 'processing' === $from ? 'held' : null,
				'hold' => in_array( $from, array( 'queued', 'ready' ), true ) ? 'held' : null,
				'retry' => 'held' === $from && $item['attempts'] < 3 && $item['retries'] < 3 ? 'queued' : null,
				'cancel' => in_array( $from, array( 'queued', 'held', 'ready' ), true ) ? 'cancelled' : null,
				default => null,
			};
			if ( null === $next || ( 'processed' !== $type && array() !== $event['data'] ) ) {
				throw new RuntimeException( 'Transição indisponível.' ); }
			if ( 'processed' === $type ) {
				$data     = $event['data'];
				$variants = 'application/pdf' === $item['blob']['mime'] ? array( 'original' ) : array( 'original', 'thumbnail', 'preview' );
				if ( array_keys( $data ) !== array( 'attempt', 'hashes' ) || ! is_string( $data['attempt'] ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $data['attempt'] ) || ! is_array( $data['hashes'] ) || array_keys( $data['hashes'] ) !== $variants || $data['hashes']['original'] !== $item['blob']['sha256'] ) {
					throw new RuntimeException( 'Resultado inválido.' ); }
				foreach ( $data['hashes'] as $hash ) {
					if ( ! is_string( $hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $hash ) ) {
						throw new RuntimeException( 'Resultado inválido.' ); }
				}
				$item['result'] = $data;
			} else {
				$item['result'] = array();
			}
			$item['status']    = $next;
			$item['attempts'] += 'claim' === $type ? 1 : 0;
			$item['retries']  += 'retry' === $type ? 1 : 0;
			unset( $item );
		}
		++$state['revision'];
		$state['updated_at'] = $event['at'];
		$state['history'][]  = $event;
		return $state;
	}
}
