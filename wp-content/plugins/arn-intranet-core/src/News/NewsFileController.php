<?php
/** Private media route for authorised news versions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.NonceVerification.Recommended, Squiz.Commenting.FunctionComment

/** No static URL or WordPress media attachment exposes these bytes. */
final class NewsFileController {
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'serve' ), -3 );
	}

	public static function serve(): void {
		if ( ! array_key_exists( 'arn_news_media', $_GET ) ) {
			return;
		}
		$id      = $_GET['arn_news_media'];
		$variant = $_GET['arn_media_variant'] ?? 'original';
		$result  = 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && is_string( $id ) && is_string( $variant ) ? ( new LocalNewsroom() )->file( $id, $variant ) : null;
		if ( null === $result || ! is_resource( $result['handle'] ?? null ) ) {
			self::notFound();
		}
		$asset     = $result['asset'];
		$blob      = $result['blob'];
		$extension = match ( $blob->mime ) {
			'image/png' => '.png', 'image/jpeg' => '.jpg', 'application/pdf' => '.pdf', default => '',
		};
		if ( '' === $extension || $blob->size < 1 || ( 'attachment' !== $asset->kind && 'application/pdf' === $blob->mime ) ) {
			fclose( $result['handle'] );
			self::notFound();
		}
		header( 'Content-Type: ' . $blob->mime );
		header( 'Content-Length: ' . $blob->size );
		header( 'Content-Disposition: ' . ( 'attachment' === $asset->kind ? 'attachment' : 'inline' ) . '; filename="noticia-' . $result['version']->number . $extension . '"' );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		try {
			fpassthru( $result['handle'] );
		} finally {
			fclose( $result['handle'] );
		}
		exit;
	}

	private static function notFound(): never {
		status_header( 404 );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		header( 'Content-Length: 0' );
		exit;
	}
}
