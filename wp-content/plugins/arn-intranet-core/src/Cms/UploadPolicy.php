<?php
/** Closed policies for admission of new files.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting -- Typed immutable value with a closed field contract.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Existing blobs retain their original storage reader and authorization. */
final readonly class UploadPolicy {
	public const MAX_BYTES     = 1048576;
	private const DESTINATIONS = array(
		'news_image'      => array( 'image/png', 'image/jpeg' ),
		'news_attachment' => array( 'application/pdf', 'image/png', 'image/jpeg' ),
		'document'        => array( 'application/pdf', 'image/png', 'image/jpeg' ),
	);

	private function __construct( private array $values ) {}

	public static function defaults(): self {
		$values = array();
		foreach ( self::DESTINATIONS as $destination => $mimes ) {
			$values[ $destination ] = array(
				'max_bytes' => self::MAX_BYTES,
				'mimes'     => $mimes,
			);
		}
		return new self( $values );
	}

	public static function fromArray( array $values ): self {
		if ( count( $values ) !== count( self::DESTINATIONS ) || array_diff( array_keys( $values ), array_keys( self::DESTINATIONS ) ) ) {
			throw new InvalidArgumentException( 'Destinos de ficheiros inválidos.' );
		}
		$normalized = array();
		foreach ( self::DESTINATIONS as $destination => $allowed ) {
			$value = $values[ $destination ];
			if ( ! is_array( $value ) || 2 !== count( $value ) || array_diff( array_keys( $value ), array( 'max_bytes', 'mimes' ) ) || ! is_int( $value['max_bytes'] ?? null ) || $value['max_bytes'] < 1 || $value['max_bytes'] > self::MAX_BYTES || ! is_array( $value['mimes'] ?? null ) || ! array_is_list( $value['mimes'] ) || array() === $value['mimes'] ) {
				throw new InvalidArgumentException( 'Limites de ficheiros inválidos.' );
			}
			$seen = array();
			foreach ( $value['mimes'] as $mime ) {
				if ( ! is_string( $mime ) || ! in_array( $mime, $allowed, true ) || in_array( $mime, $seen, true ) ) {
					throw new InvalidArgumentException( 'Tipos de ficheiros inválidos.' );
				}
				$seen[] = $mime;
			}
			$normalized[ $destination ] = array(
				'max_bytes' => $value['max_bytes'],
				'mimes'     => array_values( array_intersect( $allowed, $seen ) ),
			);
		}
		return new self( $normalized );
	}

	public function toArray(): array {
		return $this->values;
	}

	/** A copied and inspected descriptor is authoritative, never a browser MIME. */
	public function allows( string $destination, BlobDescriptor $blob ): bool {
		$policy = $this->values[ $destination ] ?? null;
		return null !== $policy && $blob->size <= $policy['max_bytes'] && in_array( $blob->mime, $policy['mimes'], true );
	}
}
