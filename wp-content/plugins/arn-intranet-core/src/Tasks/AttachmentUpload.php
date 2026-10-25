<?php
/** Closed single-file multipart shape, checked after nonce and task authority.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

// phpcs:disable Squiz.Commenting
final class AttachmentUpload {
	public static function source( array $files, callable $is_uploaded ): ?string {
		if ( array( 'attachment' ) !== array_keys( $files ) || ! is_array( $files['attachment'] ) ) {
			return null;
		}
		$file = $files['attachment'];
		if ( array_diff( array_keys( $file ), array( 'name', 'full_path', 'type', 'tmp_name', 'error', 'size' ) ) || ! is_string( $file['tmp_name'] ?? null ) || '' === $file['tmp_name'] || UPLOAD_ERR_OK !== ( $file['error'] ?? null ) || ! is_int( $file['size'] ?? null ) || $file['size'] < 1 || $file['size'] > 1048576 ) {
			return null;
		}
		foreach ( array( 'name', 'type', 'full_path' ) as $key ) {
			if ( isset( $file[ $key ] ) && ! is_string( $file[ $key ] ) ) {
				return null;
			}
		}
		return true === $is_uploaded( $file['tmp_name'] ) ? $file['tmp_name'] : null;
	}
}
