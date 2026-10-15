<?php
/**
 * Immutable editorial audit event.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use InvalidArgumentException;

/** One verified event in the article history. */
final readonly class EditorialTransition {
	/** Validate event fields and required decision reasons.
	 *
	 * @param int    $revision Sequential article revision.
	 * @param string $event Closed event name.
	 * @param int    $version Affected version.
	 * @param int    $actor_id Acting user.
	 * @param int    $occurred_at Unix UTC timestamp.
	 * @param string $reason Editorial justification.
	 * @throws InvalidArgumentException For invalid event data.
	 */
	public function __construct( public int $revision, public string $event, public int $version, public int $actor_id, public int $occurred_at, public string $reason ) {
		if ( $revision <= 0 || $version <= 0 || $actor_id <= 0 || $occurred_at < 0
			|| ! in_array( $event, array( 'created', 'revised', 'submitted', 'approved', 'rejected', 'returned', 'archived' ), true )
			|| ! Content::plainText( $reason, 0, 2000 )
			|| ( in_array( $event, array( 'rejected', 'returned', 'archived' ), true ) && ! Content::plainText( $reason, 1, 2000 ) ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
			throw new InvalidArgumentException( 'Transição editorial inválida.' );
		}
	}
}
