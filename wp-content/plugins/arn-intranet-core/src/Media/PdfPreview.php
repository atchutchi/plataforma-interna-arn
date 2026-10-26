<?php
/** Read-only availability check through the existing authorised file readers.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Closure;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
/** No registry query or static file URL bypasses the consumer's current policy. */
final class PdfPreview {
	public static function url( string $original, string $mime, ?Closure $reader = null ): ?string {
		if ( 'application/pdf' !== $mime ) {
			return null;
		}
		$result = null;
		try {
			parse_str( (string) parse_url( $original, PHP_URL_QUERY ), $query );
			$kind = match ( array_keys( $query ) ) {
				array( 'arn_document_file' ) => 'document',
				array( 'arn_news_media' ) => 'news',
				array( 'arn_task', 'arn_task_file' ) => 'task',
				default => null,
			};
			$id = $query['arn_document_file'] ?? $query['arn_news_media'] ?? $query['arn_task_file'] ?? null;
			if ( null === $kind || ! is_string( $id ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) || ( 'task' === $kind && ( ! is_string( $query['arn_task'] ) || 1 !== preg_match( '/^[1-9][0-9]{0,8}$/D', $query['arn_task'] ) ) ) ) {
				return null;
			}
			if ( null === $reader && ! str_starts_with( $original, home_url( '/' ) . '?' ) ) {
				return null;
			}
			$reader ??= static fn( string $type, string $blob, int $task ): ?array => match ( $type ) {
				'document' => ( new \Arn\Intranet\Documents\Catalog\LocalDocumentCatalog() )->file( $blob, 'preview' ),
				'news' => ( new \Arn\Intranet\News\LocalNewsroom() )->file( $blob, 'preview' ),
				'task' => ( new \Arn\Intranet\Tasks\Portal() )->file( $task, $blob, 'preview' ),
			};
			$result   = $reader( $kind, $id, (int) ( $query['arn_task'] ?? 0 ) );
			return is_resource( $result['handle'] ?? null ) && 'image/png' === ( $result['blob']->mime ?? null ) ? VariantUrl::image( $original, 'preview' ) : null;
		} catch ( Throwable ) {
			return null;
		} finally {
			if ( is_resource( $result['handle'] ?? null ) ) {
				fclose( $result['handle'] );
			}
		}
	}
}
