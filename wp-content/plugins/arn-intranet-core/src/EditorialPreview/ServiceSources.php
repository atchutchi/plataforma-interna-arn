<?php
/** Composition through native service authority, without SQL or permissions here.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

// phpcs:disable Squiz.Commenting
final class ServiceSources {
	private static function portal( string $module ): object {
		return match ( $module ) {
			'requests' => new \Arn\Intranet\Requests\Portal(), 'tickets' => new \Arn\Intranet\Support\Portal(), default => throw new \InvalidArgumentException( 'Serviço indisponível.' ) };
	}
	private static function urls( string $module ): array {
		$items = match ( $module ) {
			'requests' => \Arn\Intranet\Requests\Navigation::destinations(), 'tickets' => \Arn\Intranet\Support\Navigation::destinations(), default => array() };
		return array_map( static fn( array $item ): string => $item['url'], $items );
	}
	public static function home( string $module ): ?array {
		return self::portal( $module )->projection( 'home' );
	}
	public static function search( string $module, string $query, int $page ): ?array {
		$urls = self::urls( $module );
		if ( ! $urls ) {
			return null; }
		$list = self::portal( $module )->projection( 'search', $query, $page );
		if ( null === $list ) {
			return null; }
		$items = array();
		foreach ( $list['items'] as $row ) {
			$base = $urls[ $module . '_' . $row['view'] ] ?? null;
			if ( null === $base ) {
				return null; }
			$items[] = array(
				'title' => $row['title'],
				'url'   => add_query_arg( 'entry', $row['id'], $base ),
			);
		}
		return array(
			'total' => $list['total'],
			'items' => $items,
		);
	}
	public static function notices( string $module ): ?array {
		$urls = self::urls( $module );
		if ( ! $urls ) {
			return null; }
		$items = array();
		foreach ( self::portal( $module )->notices() as $row ) {
			$base = $urls[ $module . '_' . $row['view'] ] ?? null;
			if ( null === $base ) {
				return null; }
			$object  = $row[ 'requests' === $module ? 'request' : 'ticket' ];
			$event   = 'requests' === $module ? \Arn\Intranet\Requests\View::operationLabel( $row['kind'] ) : \Arn\Intranet\Support\View::operationLabel( $row['kind'] );
			$items[] = array(
				'id'         => $row['id'],
				'title'      => $object->title,
				'event'      => $event,
				'created_at' => $row['created_at'],
				'read_at'    => $row['read_at'],
				'url'        => add_query_arg( 'entry', $object->id, $base ),
			);
		}
		$base = $urls[ $module . '_team' ] ?? $urls[ $module . '_own' ];
		return array(
			'items' => $items,
			'url'   => add_query_arg( 'notices', '1', $base ),
		);
	}
}
