<?php
/** Admission of new bytes inside the consumer transaction.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Identity\IdentityWpdb;
use DomainException;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Retained descriptors must come from the consumer's locked previous version. */
final class UploadAdmission {
	public function __construct( private readonly IdentityWpdb $database ) {}

	public function policy(): UploadPolicy {
		if ( ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'Admissão exige a transacção do consumidor.' );
		}
		if ( Configuration::current() ) {
			return ( new SettingsReader( $this->database ) )->locked()->policy;
		}
		// Existing installations retain structural defaults until explicitly installed.
		// A disabled or broken installed module must never silently widen its policy.
		$schema = new SettingsSchema( $this->database );
		$rows   = $this->database->get_results( $this->database->prepare( 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (%s,%s,%s)', $schema->table( 'state' ), $schema->table( 'audit' ), $schema->table( 'schema' ) ) );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error || array() !== $rows || ( defined( 'ARN_ALLOW_LOCAL_CMS_SETTINGS' ) && false !== ARN_ALLOW_LOCAL_CMS_SETTINGS ) ) {
			throw new RuntimeException( 'Política de ficheiros indisponível.' );
		}
		return UploadPolicy::defaults();
	}

	public static function assertAllowed( UploadPolicy $policy, string $destination, BlobDescriptor $blob, ?BlobDescriptor $previous = null ): void {
		if ( null !== $previous ) {
			if ( $previous->id === $blob->id || $previous->sha256 !== $blob->sha256 || $previous->size !== $blob->size || $previous->mime !== $blob->mime || ! UploadPolicy::defaults()->allows( $destination, $blob ) ) {
				throw new DomainException( 'Cópia de ficheiro anterior incompatível.' );
			}
			return;
		}
		if ( ! $policy->allows( $destination, $blob ) ) {
			throw new DomainException( 'O ficheiro ultrapassa os limites ou tipos actualmente permitidos.' );
		}
	}
}
