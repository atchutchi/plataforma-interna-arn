<?php
/** One immutable version of a private document.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use InvalidArgumentException;

/** A version always names its own private original. */
final readonly class Revision {
	/** Validate server-supplied identity, actor and creation time.
	 *
	 * @param int            $number     Version number.
	 * @param Metadata       $metadata   Immutable metadata.
	 * @param BlobDescriptor $blob       Private original.
	 * @param int            $actor_id   Creator account.
	 * @param int            $created_at Unix creation time.
	 * @throws InvalidArgumentException Invalid version.
	 */
	public function __construct(
		public int $number,
		public Metadata $metadata,
		public BlobDescriptor $blob,
		public int $actor_id,
		public int $created_at,
	) {
		if ( $number <= 0 || $actor_id <= 0 || $created_at <= 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
			throw new InvalidArgumentException( 'Versão inválida.' );
		}
	}
}
