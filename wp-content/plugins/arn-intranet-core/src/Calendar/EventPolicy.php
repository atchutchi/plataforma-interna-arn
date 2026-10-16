<?php
/** Explicit event scope and state decisions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Calendar;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\F112\ActorContext;

/** Neither organiser identity nor native administration bypasses grants. */
final class EventPolicy {

	/** Current public/cancelled content for an eligible recipient.
	 *
	 * @param Event        $event Aggregate.
	 * @param ActorContext $actor Current authority.
	 * @param int          $now Clock.
	 * @return bool
	 */
	public static function read( Event $event, ActorContext $actor, int $now ): bool {
		$public = $event->published();
		return null !== $public && $now >= $event->published_at && $actor->receives( $public->content->audience, $now ) && $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now );
	}

	/** Only an explicit current manager can act in this immutable owner scope.
	 *
	 * @param Event        $event Aggregate.
	 * @param string       $operation Server operation.
	 * @param ActorContext $actor Authority context.
	 * @param int          $now Clock.
	 * @return bool
	 */
	public static function manage( Event $event, string $operation, ActorContext $actor, int $now ): bool {
		if ( ! $actor->valid( $now ) || ! in_array( $operation, array( 'view', 'revise', 'publish', 'cancel', 'archive' ), true ) || ( 'view' !== $operation && EventState::Archived === $event->state ) || ( EventState::Cancelled === $event->state && ! in_array( $operation, array( 'view', 'archive' ), true ) ) || ( 'publish' === $operation && EventState::Draft !== $event->state ) || ( 'cancel' === $operation && null === $event->published_version ) ) {
			return false;
		}
		$audience = $event->latest()->content->audience;
		if ( null !== $audience->unit_id && ! in_array( $audience->unit_id, $actor->active_units, true ) ) {
			return false;
		}
		if ( null !== $event->unit_id ) {
			return $actor->allows( Action::ManageUnitEvent, Scope::units( array( $event->unit_id ) ), $event->author_id, $now );
		}
		return $actor->allows( Action::ManageInstitutionalEvent, Scope::institutional(), $event->author_id, $now )
			&& $actor->allows( Action::ManageInstitutionalEvent, $audience->scope(), $event->author_id, $now );
	}
}
