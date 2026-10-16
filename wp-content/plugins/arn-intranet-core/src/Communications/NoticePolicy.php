<?php
/** Notice permissions, separate from the transition model.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\F112\ActorContext;

/** No author or reviewer bypasses the current recipient audience. */
final class NoticePolicy {

	/** Read only the currently published audience.
	 *
	 * @param Notice       $notice Aggregate.
	 * @param ActorContext $actor Server context.
	 * @param int          $now Clock.
	 * @return bool
	 */
	public static function read( Notice $notice, ActorContext $actor, int $now ): bool {
		$public = $notice->published( $now );
		return null !== $public && $actor->receives( $public->content->audience, $now ) && $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now );
	}

	/** Authorise scope, current state and operation independently.
	 *
	 * @param Notice       $notice Aggregate.
	 * @param string       $operation Server operation.
	 * @param ActorContext $actor Server context.
	 * @param int          $now Clock.
	 * @return bool
	 */
	public static function manage( Notice $notice, string $operation, ActorContext $actor, int $now ): bool {
		if ( ! $actor->valid( $now ) || ! in_array( $operation, array( 'view', 'revise', 'submit', 'publish', 'approve', 'reject', 'return', 'archive' ), true ) ) {
			return false;
		}
		$audience = $notice->latest()->content->audience;
		if ( null !== $audience->unit_id && ! in_array( $audience->unit_id, $actor->active_units, true ) ) {
			return false;
		}
		if ( null !== $notice->unit_id ) {
			return in_array( $operation, array( 'view', 'revise', 'publish', 'archive' ), true )
				&& ( 'view' === $operation || NoticeState::Archived !== $notice->state )
				&& ( 'publish' !== $operation || NoticeState::Draft === $notice->state )
				&& $actor->allows( Action::PublishTeamNotice, Scope::units( array( $notice->unit_id ) ), $notice->author_id, $now );
		}
		$queue    = Scope::queues( array( 'communications-ficticia' ) );
		$reviewer = in_array( $operation, array( 'approve', 'reject', 'return' ), true ) || ( 'view' === $operation && $actor->id() !== $notice->author_id );
		if ( $reviewer ) {
			$action = 'view' === $operation ? Action::ReviewInstitutionalNotice : Action::DecideInstitutionalNotice;
			return NoticeState::Pending === $notice->state && $actor->id() !== $notice->author_id
				&& $actor->allows( $action, $queue, $notice->author_id, $now, $notice->author_id )
				&& $actor->allows( $action, $audience->scope(), $notice->author_id, $now, $notice->author_id );
		}
		return in_array( $operation, array( 'view', 'revise', 'submit', 'archive' ), true )
			&& ( 'view' === $operation || NoticeState::Archived !== $notice->state )
			&& ( 'revise' !== $operation || NoticeState::Pending !== $notice->state )
			&& ( 'submit' !== $operation || ( NoticeState::Draft === $notice->state && $notice->last_submitted_version !== $notice->latest()->number ) )
			&& $actor->allows( Action::ManageInstitutionalNotice, $queue, $notice->author_id, $now )
			&& $actor->allows( Action::ManageInstitutionalNotice, $audience->scope(), $notice->author_id, $now );
	}
}
