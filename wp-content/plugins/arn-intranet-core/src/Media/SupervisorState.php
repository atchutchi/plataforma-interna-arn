<?php
/** Closed supervision transitions, independent from business publication.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class SupervisorState {
	public static function apply( ?array $state, array $event ): array {
		if ( array_keys( $event ) !== array( 'type', 'actor', 'at', 'data' ) || ! is_string( $event['type'] ) || ! is_int( $event['actor'] ) || $event['actor'] < 1 || ! is_int( $event['at'] ) || $event['at'] < 1 || ! is_array( $event['data'] ) ) {
			throw new RuntimeException( 'Evento de supervisão inválido.' ); }
		$d = $event['data'];
		if ( 'start' === $event['type'] ) {
			if ( null !== $state && in_array( $state['status'], array( 'waiting', 'working' ), true ) ) {
				throw new RuntimeException( 'Execução anterior sem conclusão.' ); }
			if ( array_keys( $d ) !== array( 'run', 'cycles', 'interval', 'limit' ) || ! is_string( $d['run'] ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $d['run'] ) || ! is_int( $d['cycles'] ) || $d['cycles'] < 1 || $d['cycles'] > 10 || ! is_int( $d['interval'] ) || $d['interval'] < 10 || $d['interval'] > 300 || ! is_int( $d['limit'] ) || $d['limit'] < 1 || $d['limit'] > 3 ) {
				throw new RuntimeException( 'Limites de supervisão inválidos.' ); }
			$state = array_merge(
				$d,
				array(
					'revision'  => $state['revision'] ?? 0,
					'actor'     => $event['actor'],
					'status'    => 'waiting',
					'cycle'     => 0,
					'processed' => 0,
					'stop'      => false,
					'next_at'   => $event['at'],
					'history'   => $state['history'] ?? array(),
				)
			);
		} else {
			if ( null === $state || ! in_array( $state['status'], array( 'waiting', 'working' ), true ) || $event['actor'] !== $state['actor'] ) {
				throw new RuntimeException( 'Execução indisponível.' ); }
			switch ( $event['type'] ) {
				case 'begin':
					if ( array() !== $d || 'waiting' !== $state['status'] || $state['stop'] || $state['cycle'] >= $state['cycles'] || $event['at'] < $state['next_at'] ) {
						throw new RuntimeException( 'Ciclo inválido.' ); }
					++$state['cycle'];
					$state['status']  = 'working';
					$state['next_at'] = null;
					break;
				case 'complete':
					if ( array_keys( $d ) !== array( 'count' ) || 'working' !== $state['status'] || ! is_int( $d['count'] ) || $d['count'] < 0 || $d['count'] > $state['limit'] ) {
						throw new RuntimeException( 'Resultado de ciclo inválido.' ); }
					$state['processed'] += $d['count'];
					$state['status']     = 'waiting';
					$state['next_at']    = $event['at'] + $state['interval'];
					break;
				case 'ack':
				case 'stop':
					if ( array_keys( $d ) !== array( 'reason' ) || ! is_string( $d['reason'] ) || strlen( trim( $d['reason'] ) ) < 10 || strlen( $d['reason'] ) > 1200 || 1 !== preg_match( '/^.{10,300}$/usD', $d['reason'] ) || ( 'stop' === $event['type'] && $state['stop'] ) ) {
						throw new RuntimeException( 'Pedido de paragem inválido.' ); }
					$state['stop'] = true;
					if ( 'ack' === $event['type'] ) {
						$state['status']  = 'interrupted';
						$state['next_at'] = null;
					}
					break;
				case 'finish':
					if ( array_keys( $d ) !== array( 'outcome' ) || ! in_array( $d['outcome'], array( 'finished', 'stopped', 'failed', 'interrupted' ), true ) || ( 'finished' === $d['outcome'] && ( 'waiting' !== $state['status'] || $state['cycle'] !== $state['cycles'] || $state['stop'] ) ) || ( 'stopped' === $d['outcome'] && ( ! $state['stop'] || 'working' === $state['status'] ) ) || ( 'interrupted' === $d['outcome'] && ! $state['stop'] ) ) {
						throw new RuntimeException( 'Conclusão inválida.' ); }
					$state['status']  = $d['outcome'];
					$state['next_at'] = null;
					break;
				default:
					throw new RuntimeException( 'Operação inválida.' );
			}
		}
		++$state['revision'];
		$state['at']        = $event['at'];
		$state['history'][] = $event;
		return $state;
	}
}
