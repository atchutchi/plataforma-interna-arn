<?php
/** Guard for the private news preview on the existing Notícias page.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use WP_Post;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.NonceVerification.Recommended
/** Runs before the frontend login redirect and the editorial block. */
final class NewsPreviewController {
	/** Already authorised preview projection for this request.
	 *
	 * @var array|null
	 */
	private static ?array $model = null;

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'protect' ), -4 );
	}

	public static function requested(): bool {
		return array_key_exists( 'arn_news_preview', $_GET );
	}

	public static function current(): ?array {
		return self::$model;
	}

	public static function protect(): void {
		if ( ! self::requested() ) {
			return;
		}
		$id   = $_GET['arn_news_preview'];
		$page = get_page_by_path( 'noticias' );
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_string( $id ) || 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $id )
			|| ! $page instanceof WP_Post || ! is_page() || get_queried_object_id() !== $page->ID ) {
			self::notFound();
		}
		self::$model = NewsPortal::model( (int) $id );
		if ( null === self::$model ) {
			self::notFound();
		}
		nocache_headers();
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
	}

	private static function notFound(): never {
		status_header( 404 );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: 0' );
		exit;
	}
}
