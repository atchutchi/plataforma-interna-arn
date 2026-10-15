<?php
/** Published detail guard before the frontend login redirect.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.NonceVerification.Recommended
/** A published route never invokes the editorial preview permission. */
final class NewsPublishedController {
	/** Authorised model, populated only by the early route guard.
	 *
	 * @var array|null
	 */
	private static ?array $model = null;

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'protect' ), -5 );
	}

	public static function current(): ?array {
		return self::$model;
	}

	public static function requestId( array $input, string $method, bool $news_page ): ?int {
		$id = $input['arn_news'] ?? null;
		return 'GET' === $method && $news_page && is_string( $id ) && 1 === preg_match( '/^[1-9][0-9]{0,9}$/D', $id )
			&& ! array_key_exists( 'arn_news_preview', $input ) && null !== NewsPublishedPortal::filters( $input ) ? (int) $id : null;
	}

	public static function protect(): void {
		self::$model = null;
		$page        = get_page_by_path( 'noticias' );
		$news_page   = $page instanceof \WP_Post && is_page() && get_queried_object_id() === $page->ID;
		if ( array_key_exists( 'arn_news', $_GET ) ) {
			$id = self::requestId( wp_unslash( $_GET ), $_SERVER['REQUEST_METHOD'] ?? '', $news_page );
			if ( null === $id ) {
				self::notFound();
			}
			self::$model = NewsPublishedPortal::detail( $id );
			if ( null === self::$model ) {
				self::notFound();
			}
		}
		if ( $news_page || is_front_page() || null !== self::$model ) {
			nocache_headers();
			header( 'Cache-Control: no-store, private' );
			header( 'X-Content-Type-Options: nosniff' );
		}
	}

	private static function notFound(): never {
		status_header( 404 );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: 0' );
		exit;
	}
}
