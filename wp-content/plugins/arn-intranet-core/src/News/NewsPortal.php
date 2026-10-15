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
		return self::project(
			$article,
			$article->author_id === $actor[1],
			static fn( string $blob ): string => add_query_arg( 'arn_news_media', $blob, home_url( '/' ) ),
			static fn( int $article_id ): string => add_query_arg(
				array(
					'page'       => 'arn-newsroom',
					'article_id' => $article_id,
				),
				admin_url( 'admin.php' )
			)
		);
	}

	/** Pure projection. An editor can receive no unpublished history from this value. */
	public static function project( Article $article, bool $own, callable $media_url, ?callable $cms_url = null ): ?array {
		$projection = NewsroomProjection::article( $article, $own );
		if ( null === $projection ) {
			return null;
		}
		$number  = $projection['version'];
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
		$model   = array(
			'id'          => $article->id,
			'state'       => NewsroomProjection::stateLabel( $projection['state'] ),
			'version'     => $version->number,
			'title'       => $content->title,
			'body'        => $content->body,
			'category'    => self::categories()[ $content->category ],
			'featured'    => null === $content->featured ? null : $asset( $content->featured ),
			'gallery'     => array_map( $asset, $content->gallery ),
			'attachments' => array_map( $asset, $content->attachments ),
		);
		if ( null !== $cms_url ) {
			$model['cms_url'] = $cms_url( $article->id );
		}
		return $model;
	}
}
