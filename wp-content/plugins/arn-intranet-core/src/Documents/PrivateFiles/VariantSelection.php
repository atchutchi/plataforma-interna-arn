<?php
/** Variants are resolved only after original object/version authorization.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

// phpcs:disable Squiz.Commenting
/** No variant ID creates an independent audience. */
final class VariantSelection {
	public static function select( WpdbFileRegistry $registry, FileRecord $original, string $variant ): ?BlobDescriptor {
		if ( 'original' === $variant ) {
			return $original->blob;
		}
		if ( ! in_array( $variant, array( 'thumbnail', 'preview' ), true ) || ! in_array( $original->blob->mime, array( 'image/png', 'image/jpeg', 'application/pdf' ), true ) ) {
			return null;
		}
		$derived = $registry->findForObject( $original->object_type, $original->object_id, $original->version, $variant );
		if ( null === $derived ) {
			// Existing images remain readable without retroactive processing or rewriting.
			return 'application/pdf' === $original->blob->mime ? null : $original->blob;
		}
		return 'image/png' === $derived->blob->mime && $derived->actor_id === $original->actor_id && $derived->created_at === $original->created_at ? $derived->blob : null;
	}
}
