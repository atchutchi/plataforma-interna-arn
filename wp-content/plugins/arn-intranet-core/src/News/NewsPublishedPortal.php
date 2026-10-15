<?php
/** Current approved news projection for internal readers.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Never passes editorial aggregates or recipient identifiers to the theme. */
final class NewsPublishedPortal {
	public static function project( Article $article, callable $media_url, callable $author_name, callable $detail_url ): ?array {
		if ( null === $article->published_version || NewsState::Archived === $article->state ) {
			return null;
		}
		$version = $article->version( $article->published_version );
		if ( null === $version || null === $version->content->audience || null === $article->published_at ) {
			return null;
		}
		$content = $version->content;
		$asset   = static fn( Asset $item ): array => array(
			'description' => $item->description,
			'url'         => $media_url( $item->blob->id ),
			'mime'        => $item->blob->mime,
		);
		return array(
			'id'           => $article->id,
			'title'        => $content->title,
			'body'         => $content->body,
			'category'     => $content->category,
			'author'       => $author_name( $article->author_id ),
			'published_at' => $article->published_at,
			'audience'     => match ( $content->audience->kind ) {
				'all' => 'Todos os colaboradores', 'units' => 'Unidades seleccionadas', 'people' => 'Pessoas seleccionadas',
			},
			'featured'     => null === $content->featured ? null : $asset( $content->featured ),
			'gallery'      => array_map( $asset, $content->gallery ),
			'attachments'  => array_map( $asset, $content->attachments ),
			'detail_url'   => $detail_url( $article->id ),
		);
	}

	/** Strict filters are validated before accessing the current actor or repository. */
	public static function filters( array $input ): ?array {
		$filters = array();
		foreach ( array( 'q', 'category', 'arn_from', 'arn_to', 'arn_page' ) as $key ) {
			$value = array_key_exists( $key, $input ) ? $input[ $key ] : ( 'arn_page' === $key ? '1' : '' );
			if ( ! is_string( $value ) ) {
				return null;
			}
			$filters[ $key ] = $value;
		}
		if ( strlen( $filters['q'] ) > 160 || ! mb_check_encoding( $filters['q'], 'UTF-8' ) || preg_match( '/[\x00-\x1f\x7f]/', $filters['q'] )
			|| ( '' !== $filters['category'] && ! isset( NewsPortal::categories()[ $filters['category'] ] ) )
			|| 1 !== preg_match( '/^[1-9][0-9]{0,5}$/D', $filters['arn_page'] ) ) {
			return null;
		}
		foreach ( array( 'arn_from', 'arn_to' ) as $key ) {
			if ( '' === $filters[ $key ] ) {
				continue;
			}
			if ( 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $filters[ $key ] ) ) {
				return null;
			}
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $filters[ $key ], new \DateTimeZone( 'UTC' ) );
			if ( false === $date || $date->format( 'Y-m-d' ) !== $filters[ $key ] || '0000' === substr( $filters[ $key ], 0, 4 ) ) {
				return null;
			}
		}
		if ( '' !== $filters['arn_from'] && '' !== $filters['arn_to'] && $filters['arn_from'] > $filters['arn_to'] ) {
			return null;
		}
		$filters['q'] = trim( $filters['q'] );
		return $filters;
	}

	public static function url( int $id ): string {
		$page = get_page_by_path( 'noticias' );
		$base = $page instanceof \WP_Post ? get_permalink( $page ) : home_url( '/noticias/' );
		return add_query_arg( array( 'arn_news' => $id ), $base );
	}

	public static function mediaUrl( string $id ): string {
		return add_query_arg( array( 'arn_news_media' => $id ), home_url( '/' ) );
	}

	public static function authorName( int $id ): string {
		$author = get_userdata( $id );
		return false === $author ? 'Autor indisponível' : $author->display_name;
	}

	public static function detail( int $id, ?LocalNewsroom $room = null ): ?array {
		$article = ( $room ?? new LocalNewsroom() )->findPublished( $id );
		return null === $article ? null : self::project( $article, self::mediaUrl( ... ), self::authorName( ... ), self::url( ... ) );
	}

	public static function model( array $input, ?LocalNewsroom $room = null, ?callable $media_url = null, ?callable $author_name = null, ?callable $detail_url = null ): array {
		$base    = array(
			'view'           => 'news',
			'published_news' => true,
			'available'      => false,
			'items'          => array(),
			'categories'     => NewsPortal::categories(),
			'counts'         => array_fill_keys( array_keys( NewsPortal::categories() ), 0 ),
			'query'          => '',
			'category'       => '',
			'from'           => '',
			'to'             => '',
			'page'           => 1,
			'pages'          => 0,
			'total'          => 0,
		);
		$filters = self::filters( $input );
		if ( null === $filters ) {
			return $base;
		}
		$articles = ( $room ?? new LocalNewsroom() )->published();
		if ( null === $articles ) {
			return $base;
		}
		$base['available'] = true;
		$base['query']     = $filters['q'];
		$base['category']  = $filters['category'];
		$base['from']      = $filters['arn_from'];
		$base['to']        = $filters['arn_to'];
		$base['page']      = (int) $filters['arn_page'];
		foreach ( $articles as $article ) {
			$item = self::project( $article, $media_url ?? self::mediaUrl( ... ), $author_name ?? self::authorName( ... ), $detail_url ?? self::url( ... ) );
			if ( null === $item ) {
				continue;
			}
			$date = gmdate( 'Y-m-d', $item['published_at'] );
			if ( ( '' !== $base['query'] && false === mb_stripos( $item['title'] . ' ' . $item['body'], $base['query'] ) )
				|| ( '' !== $base['category'] && $base['category'] !== $item['category'] )
				|| ( '' !== $base['from'] && $date < $base['from'] ) || ( '' !== $base['to'] && $date > $base['to'] ) ) {
				continue;
			}
			++$base['counts'][ $item['category'] ];
			$base['items'][] = $item;
		}
		usort( $base['items'], static fn( array $a, array $b ): int => array( $b['published_at'], $b['id'] ) <=> array( $a['published_at'], $a['id'] ) );
		$base['total'] = count( $base['items'] );
		$base['pages'] = (int) ceil( $base['total'] / 10 );
		$base['items'] = array_slice( $base['items'], ( $base['page'] - 1 ) * 10, 10 );
		return $base;
	}
}
