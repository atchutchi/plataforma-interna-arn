<?php
/** Download only through the current task authority, including variants.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

// phpcs:disable Squiz.Commenting, WordPress.Security.NonceVerification.Recommended, WordPress.WP.AlternativeFunctions
final class AttachmentController {
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'serve' ), -30 );
	}

	public static function serve(): void {
		if ( ! array_key_exists( 'arn_ticket_file', $_GET ) ) {
			return;
		}
		$id      = Input::id( $_GET['arn_ticket'] ?? null );
		$blob    = $_GET['arn_ticket_file'];
		$variant = $_GET['arn_media_variant'] ?? 'original';
		if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || null === $id || ! is_string( $blob ) || ! is_string( $variant ) || array_diff( array_keys( $_GET ), array( 'arn_ticket_file', 'arn_ticket', 'arn_media_variant' ) ) || $_POST || $_FILES ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Reject all mutation input on the read-only route.
			Controller::refuse();
		}
		$result = ( new Portal() )->file( $id, $blob, $variant );
		if ( null === $result || ! is_resource( $result['handle'] ?? null ) ) {
			Controller::refuse();
		}
		$file      = $result['blob'];
		$extension = match ( $file->mime ) {
			'application/pdf' => '.pdf', 'image/png' => '.png', 'image/jpeg' => '.jpg' };
		Controller::privateHeaders();
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		header( 'Content-Type: ' . $file->mime );
		header( 'Content-Length: ' . $file->size );
		header( 'Content-Disposition: attachment; filename="ticket-' . $id . '-r' . $result['revision'] . $extension . '"' );
		try {
			fpassthru( $result['handle'] );
		} finally {
			fclose( $result['handle'] );
		}
		exit;
	}
}
