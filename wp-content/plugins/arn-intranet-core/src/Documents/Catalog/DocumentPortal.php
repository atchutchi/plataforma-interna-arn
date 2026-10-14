<?php
/**
 * Local, authorised projection for the document collections.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

/** Never passes an aggregate, draft or audience to a front-end template. */
final class DocumentPortal {
	private const CATEGORIES = array(
		'circular'             => 'Circular',
		'nota-interna'         => 'Nota interna',
		'despacho'             => 'Despacho',
		'regulamento'          => 'Regulamento',
		'comunicacao-sindical' => 'Comunicação sindical',
	);

	/**
	 * Select a safe current publication without drafts, history or audiences.
	 *
	 * @param Document $document Authorised internal aggregate.
	 * @return array<string,mixed>|null
	 */
	public static function published( Document $document ): ?array {
		if ( $document->archived || null === $document->published_version ) {
			return null;
		}
		$version = $document->version( $document->published_version );
		if ( null === $version ) {
			return null;
		}
		$metadata = $version->metadata;
		return array(
			'id'          => (string) $document->id,
			'title'       => $metadata->title,
			'summary'     => $metadata->summary,
			'category'    => $metadata->category,
			'reference'   => $metadata->source_reference,
			'owner'       => $metadata->owner_label,
			'version'     => $version->number,
			'review_on'   => $metadata->review_on,
			'valid_from'  => $metadata->valid_from,
			'valid_until' => $metadata->valid_until,
			'mime'        => $version->blob->mime,
		);
	}

	/**
	 * Builds only the current published projection for the requested collection.
	 *
	 * @param string                    $view Collection slug.
	 * @param array<string,mixed>       $input GET filters.
	 * @param LocalDocumentCatalog|null $catalog Authorised runtime.
	 * @return array<string,mixed>
	 */
	public static function model( string $view, array $input, ?LocalDocumentCatalog $catalog = null ): array {
		$base = array(
			'view'       => $view,
			'catalog'    => true,
			'available'  => false,
			'items'      => array(),
			'categories' => self::CATEGORIES,
			'query'      => '',
			'category'   => '',
			'year'       => '',
			'page'       => 1,
			'pages'      => 0,
			'total'      => 0,
			'selected'   => null,
		);
		if ( ! in_array( $view, array( 'documents', 'regulations' ), true ) ) {
			return $base;
		}
		$q        = $input['q'] ?? '';
		$category = $input['category'] ?? '';
		$page     = $input['arn_page'] ?? '1';
		$id       = $input['arn_doc'] ?? '';
		if ( ! is_string( $q ) || strlen( $q ) > 160 || ! is_string( $category ) || ( '' !== $category && ! isset( self::CATEGORIES[ $category ] ) )
			|| ! is_string( $page ) || 1 !== preg_match( '/^[1-9][0-9]{0,2}$/D', $page )
			|| ! is_string( $id ) || ( '' !== $id && 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $id ) ) ) {
			return $base;
		}
		$catalog = $catalog ?? new LocalDocumentCatalog();
		$all     = $catalog->visible();
		if ( null === $all ) {
			return $base;
		}
		$base['available'] = true;
		$base['query']     = trim( $q );
		$base['category']  = $category;
		$base['page']      = (int) $page;
		foreach ( $all as $document ) {
			// The runtime aggregate contains every version. Only this projection reaches the theme.
			$item = self::published( $document );
			if ( null === $item ) {
				continue;
			}
			$version = $document->version( $document->published_version );
			if ( ( 'regulations' === $view && 'regulamento' !== $item['category'] )
				|| ( '' !== $category && $category !== $item['category'] ) ) {
				continue;
			}
			if ( '' !== $base['query'] && false === mb_stripos( $item['title'] . ' ' . $item['summary'] . ' ' . $item['reference'], $base['query'] ) ) {
				continue;
			}
			$item['detail_url']   = add_query_arg(
				array(
					'q'        => $base['query'],
					'category' => $category,
					'arn_page' => $page,
					'arn_doc'  => $document->id,
				),
				get_permalink()
			);
			$item['download_url'] = add_query_arg( 'arn_document_file', $version->blob->id, home_url( '/' ) );
			$base['items'][]      = $item;
		}
		$base['total'] = count( $base['items'] );
		$base['pages'] = (int) ceil( $base['total'] / 10 );
		if ( '' !== $id ) {
			foreach ( $base['items'] as $item ) {
				if ( $item['id'] === $id ) {
					$base['selected'] = $item;
					break;
				}
			}
		}
		$base['items'] = array_slice( $base['items'], ( $base['page'] - 1 ) * 10, 10 );
		return $base;
	}
}
