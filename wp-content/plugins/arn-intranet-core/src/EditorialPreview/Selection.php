<?php
/**
 * Filtros fechados e ordenação editorial.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\EditorialPreview;

use InvalidArgumentException;

/** Selecciona conteúdos sem alterar a fonte. */
final class Selection {
	/**
	 * Valida os parâmetros recebidos.
	 *
	 * @param array<string,mixed> $input Entrada GET.
	 * @return array{q:string,category:string,year:string,paged:int,item:string} Filtros.
	 * @throws InvalidArgumentException Filtro inválido.
	 */
	public static function query( array $input ): array {
		foreach ( array( 'q', 'category', 'year', 'paged', 'item' ) as $key ) {
			if ( isset( $input[ $key ] ) && ! is_string( $input[ $key ] ) ) {
				throw new InvalidArgumentException( 'Filtro inválido.' );
			}
		}
		$q        = (string) ( $input['q'] ?? '' );
		$category = (string) ( $input['category'] ?? '' );
		$year     = (string) ( $input['year'] ?? '' );
		$paged    = (string) ( $input['paged'] ?? '1' );
		$item     = (string) ( $input['item'] ?? '' );
		if ( strlen( $q ) > 160 || ( '' !== $category && ! isset( Manifest::CATEGORIES[ $category ] ) ) || ( '' !== $year && ! preg_match( '/^\d{4}$/D', $year ) ) || ! preg_match( '/^[1-9][0-9]{0,2}$/D', $paged ) || ( '' !== $item && ! preg_match( '/^[a-z0-9][a-z0-9-]{0,79}$/D', $item ) ) ) {
			throw new InvalidArgumentException( 'Filtro inválido.' );
		}
		return array(
			'q'        => trim( $q ),
			'category' => $category,
			'year'     => $year,
			'paged'    => (int) $paged,
			'item'     => $item,
		);
	}

	/**
	 * Filtra e ordena os itens disponíveis.
	 *
	 * @param array<int,array<string,mixed>> $items Itens.
	 * @param string                         $view Vista.
	 * @param array<string,mixed>            $query Filtros.
	 * @return array<int, array<string,mixed>> Itens selecionados.
	 */
	public static function filter( array $items, string $view, array $query ): array {
		$result = array_values(
			array_filter(
				$items,
				static function ( array $item ) use ( $view, $query ): bool {
					if ( 'news' === $view && 'regulamento' === $item['category'] ) {
						return false;
					}
					if ( 'regulations' === $view && 'regulamento' !== $item['category'] ) {
						return false;
					}
					if ( ! empty( $query['category'] ) && $query['category'] !== $item['category'] ) {
						return false;
					}
					if ( ! empty( $query['year'] ) && ( null === $item['date'] || ! str_starts_with( $item['date'], $query['year'] ) ) ) {
						return false;
					}
					$haystack = implode( ' ', array( $item['title'], $item['reference'], $item['summary'], implode( ' ', $item['body'] ) ) );
					return empty( $query['q'] ) || false !== mb_stripos( $haystack, $query['q'] );
				}
			)
		);
		usort(
			$result,
			static function ( array $a, array $b ): int {
				$date_order = null === $a['date'] ? ( null === $b['date'] ? 0 : 1 ) : ( null === $b['date'] ? -1 : strcmp( $b['date'], $a['date'] ) );
				return 0 !== $date_order ? $date_order : strcmp( $a['id'], $b['id'] );
			}
		);
		return $result;
	}
}
