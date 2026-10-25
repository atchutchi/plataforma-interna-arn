<?php
/** Immutable private task attachment, reconstructed only from audited data.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final readonly class Attachment {

	public function __construct( public BlobDescriptor $blob, public string $label, public int $revision ) {
		if ( $revision < 2 || $blob->size > 1048576 || false !== strpbrk( $label, '<>' ) || PlainText::value( $label, 160, false, true ) !== $label ) {
			throw new InvalidArgumentException( 'Anexo de tarefa inválido.' );
		}
	}

	public function toArray(): array {
		return array(
			'blob'     => self::describe( $this->blob ),
			'label'    => $this->label,
			'revision' => $this->revision,
		);
	}

	public static function fromArray( array $value ): self {
		if ( 3 !== count( $value ) || array_diff( array_keys( $value ), array( 'blob', 'label', 'revision' ) ) || ! is_array( $value['blob'] ?? null ) || ! is_string( $value['label'] ?? null ) || ! is_int( $value['revision'] ?? null ) ) {
			throw new InvalidArgumentException( 'Anexo de tarefa inválido.' );
		}
		return new self( self::restoreBlob( $value['blob'] ), $value['label'], $value['revision'] );
	}

	private static function describe( BlobDescriptor $blob ): array {
		return array(
			'id'          => $blob->id,
			'sha256'      => $blob->sha256,
			'size'        => $blob->size,
			'mime'        => $blob->mime,
			'derivatives' => array_map( self::describe( ... ), $blob->derivatives ),
		);
	}

	private static function restoreBlob( array $value, bool $derived = false ): BlobDescriptor {
		if ( 5 !== count( $value ) || array_diff( array_keys( $value ), array( 'id', 'sha256', 'size', 'mime', 'derivatives' ) ) || ! is_string( $value['id'] ?? null ) || ! is_string( $value['sha256'] ?? null ) || ! is_int( $value['size'] ?? null ) || ! is_string( $value['mime'] ?? null ) || ! is_array( $value['derivatives'] ?? null ) || ( $derived && array() !== $value['derivatives'] ) ) {
			throw new InvalidArgumentException( 'Descritor de anexo inválido.' );
		}
		$variants = array();
		foreach ( $value['derivatives'] as $key => $item ) {
			if ( ! is_array( $item ) ) {
				throw new InvalidArgumentException( 'Variante de anexo inválida.' );
			}
			$variants[ $key ] = self::restoreBlob( $item, true );
		}
		return new BlobDescriptor( $value['id'], $value['sha256'], $value['size'], $value['mime'], $variants );
	}
}
