<?php
/** Server-selected media input for private news.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment

use InvalidArgumentException;

/** A new temporary source or an existing private ID, never both. */
final readonly class MediaInput {
	public function __construct( public ?string $existing_id, public ?string $source, public string $description ) {
		if ( ( null === $existing_id ) === ( null === $source )
			|| ( null !== $existing_id && 1 !== preg_match( '/^[a-f0-9]{32}$/D', $existing_id ) )
			|| ( null !== $source && ( '' === $source || str_contains( $source, '://' ) ) ) ) {
			throw new InvalidArgumentException( 'Entrada de ficheiro inválida.' );
		}
	}
}
