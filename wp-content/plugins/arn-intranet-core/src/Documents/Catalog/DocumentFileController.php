<?php
/**
 * Private document delivery.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

// phpcs:disable WordPress.WP.AlternativeFunctions,WordPress.Security.NonceVerification.Recommended
/** Resolves the object and version before opening a verified handle. */
final class DocumentFileController {
	/** Register before the existing private file and editorial routes. */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'serve' ), -2 );
	}

	/** Deliver a verified private handle for a currently authorised object and version. */
	public static function serve(): void {
		if ( ! array_key_exists( 'arn_document_file', $_GET ) ) {
			return;
		}
		$id      = $_GET['arn_document_file'];
		$variant = $_GET['arn_media_variant'] ?? 'original';
		$result  = 'GET' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && is_string( $id ) && is_string( $variant ) ? ( new LocalDocumentCatalog() )->file( $id, $variant ) : null;
		if ( null === $result || ! is_resource( $result['handle'] ?? null ) ) {
			self::notFound();
		}
		$blob      = $result['blob'];
		$extension = match ( $blob->mime ) {
			'application/pdf' => '.pdf', 'image/png' => '.png', 'image/jpeg' => '.jpg', default => '',
		};
		if ( '' === $extension || $blob->size < 1 ) {
			fclose( $result['handle'] );
			self::notFound();
		}
		header( 'Content-Type: ' . $blob->mime );
		header( 'Content-Length: ' . $blob->size );
		header( 'Content-Disposition: attachment; filename="documento-v' . $result['version']->number . $extension . '"' );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		try {
			fpassthru( $result['handle'] );
		} finally {
			fclose( $result['handle'] );
		}
		exit;
	}

	/** Same empty response for missing, denied and corrupted files. */
	private static function notFound(): never {
		status_header( 404 );
		header( 'Cache-Control: no-store, private' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Length: 0' );
		exit;
	}
}
