<?php
/** Bounded federated pagination over already-authorised module pages.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Search;

use Closure;
use Throwable;

// phpcs:disable Squiz.Commenting -- Sources apply authority before returning items or totals.
final class FederatedSearch {
	public const SOURCES = array(
		'directory'          => array(
			'type'  => 'directory',
			'label' => 'Funcionário',
			'size'  => 10,
		),
		'news'               => array(
			'type'  => 'news',
			'label' => 'Notícia',
			'size'  => 10,
		),
		'documents'          => array(
			'type'  => 'documents',
			'label' => 'Documento',
			'size'  => 10,
		),
		'communications'     => array(
			'type'  => 'communications',
			'label' => 'Comunicado',
			'size'  => 20,
		),
		'calendar'           => array(
			'type'  => 'calendar',
			'label' => 'Evento',
			'size'  => 20,
		),
		'calendar_history'   => array(
			'type'  => 'calendar',
			'label' => 'Evento passado',
			'size'  => 20,
		),
		'calendar_cancelled' => array(
			'type'  => 'calendar',
			'label' => 'Evento cancelado',
			'size'  => 20,
		),
		'tasks'              => array(
			'type'  => 'tasks',
			'label' => 'Tarefa',
			'size'  => 20,
		),
	);

	public function __construct( private readonly Closure $fetch ) {}

	public function model( array $input ): array {
		$query = Query::parse( $input );
		$model = array(
			'view'     => 'search',
			'invalid'  => null === $query,
			'searched' => false,
			'partial'  => false,
			'query'    => $query?->q ?? '',
			'type'     => $query?->type ?? '',
			'page'     => $query?->page ?? 1,
			'pages'    => 0,
			'total'    => 0,
			'items'    => array(),
		);
		if ( null === $query || '' === $query->q ) {
			return $model; }
		$model['searched'] = true;
		$sources           = array();
		foreach ( self::SOURCES as $key => $definition ) {
			if ( '' !== $query->type && $query->type !== $definition['type'] ) {
				continue; }
			$first = $this->fetch( $key, $query, 1 );
			if ( null !== $first ) {
				$sources[ $key ] = array(
					'total' => $first['total'],
					'cache' => array( 1 => $first['items'] ),
				);
			}
		}
		// A failed later page removes that source and recalculates the complete window.
		$maximum_attempts = count( self::SOURCES );
		for ( $attempt = 0; $attempt <= $maximum_attempts; ++$attempt ) {
			$total = 0;
			foreach ( $sources as $key => $source ) {
				if ( $source['total'] > PHP_INT_MAX - $total ) {
					unset( $sources[ $key ] );
					$model['partial'] = true;
					continue;
				}
				$total += $source['total'];
			}
			$model['total'] = $total;
			$model['pages'] = intdiv( $total, 10 ) + ( 0 !== $total % 10 ? 1 : 0 );
			$items          = $this->window( $sources, $query );
			if ( null !== $items ) {
				$model['items'] = $items;
				break;
			}
			$model['partial'] = true;
		}
		return $model;
	}

	private function window( array &$sources, Query $query ): ?array {
		$offset = ( $query->page - 1 ) * 10;
		$items  = array();
		foreach ( $sources as $key => &$source ) {
			if ( $offset >= $source['total'] ) {
				$offset -= $source['total'];
				continue; }
			$remaining = min( 10 - count( $items ), $source['total'] - $offset );
			$size      = self::SOURCES[ $key ]['size'];
			while ( $remaining > 0 ) {
				$page = intdiv( $offset, $size ) + 1;
				if ( ! isset( $source['cache'][ $page ] ) ) {
					$next = $this->fetch( $key, $query, $page );
					if ( null === $next || $next['total'] !== $source['total'] ) {
						unset( $sources[ $key ] );
						return null;
					}
					$source['cache'][ $page ] = $next['items'];
				}
				$chunk      = array_slice( $source['cache'][ $page ], $offset % $size, $remaining );
				$items      = array_merge( $items, $chunk );
				$offset    += count( $chunk );
				$remaining -= count( $chunk );
			}
			if ( 10 === count( $items ) ) {
				break; }
			$offset = 0;
		}
		return $items;
	}

	private function fetch( string $key, Query $query, int $page ): ?array {
		try {
			$list = ( $this->fetch )( $key, $query, $page );
			$size = self::SOURCES[ $key ]['size'];
			if ( ! is_array( $list ) || ! is_int( $list['total'] ?? null ) || $list['total'] < 0 || ! is_array( $list['items'] ?? null ) || ! array_is_list( $list['items'] )
				|| count( $list['items'] ) !== min( $size, max( 0, $list['total'] - ( $page - 1 ) * $size ) ) ) {
				return null; }
			$items = array();
			foreach ( $list['items'] as $item ) {
				if ( ! is_array( $item ) || ! is_string( $item['title'] ?? null ) || '' === trim( $item['title'] ) || ! self::safeUrl( $item['url'] ?? null ) ) {
					return null; }
				$items[] = array(
					'title' => $item['title'],
					'url'   => $item['url'],
					'type'  => self::SOURCES[ $key ]['label'],
				);
			}
			return array(
				'total' => $list['total'],
				'items' => $items,
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	private static function safeUrl( mixed $url ): bool {
		if ( ! is_string( $url ) || '' === $url || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
			return false; }
		if ( str_starts_with( $url, '/' ) ) {
			return ! str_starts_with( $url, '//' ); }
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure URL validation.
		return false !== filter_var( $url, FILTER_VALIDATE_URL ) && is_array( $parts ) && ! isset( $parts['user'] ) && ! isset( $parts['pass'] ) && in_array( $parts['scheme'] ?? '', array( 'https', 'http' ), true );
	}
}
