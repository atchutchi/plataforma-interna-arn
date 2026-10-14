<?php
/** Reduced model for the private theme preview.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use WP_Post;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Never passes an Article aggregate or audit history to the theme. */
final class NewsPortal {
	public static function categories(): array {
		return array(
			'circular'             => 'Circular',
			'nota-interna'         => 'Nota interna',
			'despacho'             => 'Despacho',
			'comunicacao-sindical' => 'Comunicação sindical',
		);
	}

	public static function url( int $id ): string {
		$page = get_page_by_path( 'noticias' );
		$base = $page instanceof WP_Post ? get_permalink( $page ) : home_url( '/noticias/' );
		return add_query_arg( 'arn_news_preview', $id, $base );
	}

	/** Reads server actor and projects only the one version which policy made visible. */
	public static function model( int $id ): ?array {
		$article = ( new LocalNewsroom() )->findVisible( $id );
		$actor   = ( new CurrentNewsActor() )->read();
		if ( null === $article || null === $actor ) {
			return null;
		}
		return self::project( $article, $article->author_id === $actor[1], static fn( string $blob ): string => add_query_arg( 'arn_news_media', $blob, home_url( '/' ) ) );
	}

	/** Pure projection. An editor can receive no unpublished history from this value. */
	public static function project( Article $article, bool $own, callable $media_url ): ?array {
		$number = $own ? count( $article->versions ) : $article->submitted_version;
		if ( null === $number ) {
			return null;
		}
		$version = $article->version( $number );
		if ( null === $version ) {
			return null;
		}
		$content = $version->content;
		$asset   = static fn( Asset $item ): array => array(
			'description' => $item->description,
			'url'         => $media_url( $item->blob->id ),
			'mime'        => $item->blob->mime,
		);
		return array(
			'id'          => $article->id,
			'state'       => $article->pending() ? 'Pendente' : 'Rascunho',
			'version'     => $version->number,
			'title'       => $content->title,
			'body'        => $content->body,
			'category'    => self::categories()[ $content->category ],
			'featured'    => null === $content->featured ? null : $asset( $content->featured ),
			'gallery'     => array_map( $asset, $content->gallery ),
			'attachments' => array_map( $asset, $content->attachments ),
		);
	}
}
