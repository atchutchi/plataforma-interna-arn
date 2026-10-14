<?php
/** Editorial text and ordered server-selected media.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment

use InvalidArgumentException;

/** Input does not carry an actor, role or editorial queue. */
final readonly class DraftInput {
	public array $gallery;
	public array $attachments;

	public function __construct( public string $title, public string $body, public string $category, public ?MediaInput $featured, array $gallery, array $attachments ) {
		if ( ! self::strict( $gallery ) || ! self::strict( $attachments ) || count( $gallery ) > 8 || count( $attachments ) > 5 ) {
			throw new InvalidArgumentException( 'Entrada editorial inválida.' );
		}
		$gallery_copy = array();
		foreach ( $gallery as $item ) {
			$gallery_copy[] = $item;
		}
		$attachment_copy = array();
		foreach ( $attachments as $item ) {
			$attachment_copy[] = $item;
		}
		$this->gallery     = $gallery_copy;
		$this->attachments = $attachment_copy;
	}

	private static function strict( array $items ): bool {
		if ( array_keys( $items ) !== ( array() === $items ? array() : range( 0, count( $items ) - 1 ) ) ) {
			return false;
		}
		foreach ( $items as $item ) {
			if ( ! $item instanceof MediaInput ) {
				return false;
			}
		}
		return true;
	}
}
