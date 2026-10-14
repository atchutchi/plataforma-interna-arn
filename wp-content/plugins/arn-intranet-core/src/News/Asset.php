<?php
/** Private asset metadata for a news version.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\News;

use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use InvalidArgumentException;

/** An image has alternative text, an attachment has a designation. */
final readonly class Asset {
	/** Validate media kind, verified MIME and user-facing description.
	 *
	 * @param string         $kind Kind of asset.
	 * @param BlobDescriptor $blob Private blob descriptor.
	 * @param string         $description Alternative text or designation.
	 * @throws InvalidArgumentException For an invalid asset.
	 */
	public function __construct( public string $kind, public BlobDescriptor $blob, public string $description ) {
		$max = 'attachment' === $kind ? 160 : 300;
		if ( ! in_array( $kind, array( 'featured', 'gallery', 'attachment' ), true )
			|| ( 'attachment' !== $kind && ! in_array( $blob->mime, array( 'image/png', 'image/jpeg' ), true ) )
			|| ! Content::plainText( $description, 1, $max ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
			throw new InvalidArgumentException( 'Recurso de notícia inválido.' );
		}
	}
}
