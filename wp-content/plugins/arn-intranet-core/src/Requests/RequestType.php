<?php
/** Versioned fictional request type. No institutional defaults.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Closed domain values with constant errors.
final readonly class RequestType {

	public function __construct( public int $id, public int $revision, public string $name, public string $unit, public int $reviewer, public bool $active, public string $title_label, public string $body_label, public string $instructions, public bool $attachments ) {
		if ( $id < 1 || $revision < 1 || $revision > 1000 || $reviewer < 1 || ! in_array( $unit, array( 'LAB-A', 'LAB-B' ), true ) || ! str_starts_with( $name, 'Pedido fictício ' ) ) {
			throw new InvalidArgumentException( 'Tipo de pedido inválido.' );
		}
		foreach ( array( array( $name, 120, false, true ), array( $title_label, 80, false, true ), array( $body_label, 80, false, true ), array( $instructions, 500, true, false ) ) as [$text, $max, $multi, $required] ) {
			if ( PlainText::value( $text, $max, $multi, $required ) !== $text || str_contains( $text, '<' ) || str_contains( $text, '>' ) ) {
				throw new InvalidArgumentException( 'Texto do tipo inválido.' );
			}
		}
	}

	public function data(): array {
		return get_object_vars( $this );
	}

	public static function restore( array $data ): self {
		$keys = array( 'id', 'revision', 'name', 'unit', 'reviewer', 'active', 'title_label', 'body_label', 'instructions', 'attachments' );
		if ( array_keys( $data ) !== $keys ) {
			throw new InvalidArgumentException( 'Tipo armazenado inválido.' );
		}
		return new self( ...$data );
	}
}
