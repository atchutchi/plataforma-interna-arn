<?php
/** Bounded selection over rows already authorised by the native module.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

// phpcs:disable Squiz.Commenting
final class ServiceSelection {
	public static function valid( string $purpose, string $query, int $page ): bool {
		return in_array( $purpose, array( 'home', 'search' ), true ) && $page >= 1 && $page <= 100 && mb_check_encoding( $query, 'UTF-8' ) && mb_strlen( $query ) <= 100;
	}
	public static function select( array $rows, string $purpose, string $query, int $page ): array {
		if ( ! self::valid( $purpose, $query, $page ) ) {
			throw new \InvalidArgumentException( 'Projecção inválida.' ); }
		$rows = array_values( array_filter( $rows, static fn( array $r ): bool => ( 'home' !== $purpose || ( 'own' === $r['view'] && $r['pending'] ) ) && ( '' === $query || false !== mb_stripos( $r['reference'] . ' ' . $r['title'], $query ) ) ) );
		usort( $rows, static fn( array $a, array $b ): int => array( -$a['updated_at'], -$a['id'] ) <=> array( -$b['updated_at'], -$b['id'] ) );
		$size = 'home' === $purpose ? 3 : 20;
		return array(
			'total' => count( $rows ),
			'items' => array_map( static fn( array $r ): array => array_intersect_key( $r, array_flip( array( 'id', 'title', 'reference', 'state', 'updated_at', 'view' ) ) ), array_slice( $rows, ( $page - 1 ) * $size, $size ) ),
		);
	}
}
