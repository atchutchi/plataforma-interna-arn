<?php
/** Task authority uses existing actions and current server evidence.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\Authorization\{Action, AuthorizationPolicy, Request, Scope};
use Arn\Intranet\F112\{ActorContext, Audience};

// phpcs:disable Squiz.Commenting -- The closed local policy is documented in the F1-13 plan.
/** No native administrator, authorship or membership grants a missing action. */
final class TaskPolicy {

	public static function read( Task $task, ActorContext $actor, int $now ): bool {
		return self::own( $task, $actor, $now ) || self::team( $task, $actor, $now );
	}

	public static function own( Task $task, ActorContext $actor, int $now ): bool {
		return self::member( $actor, $task->unit_id, $now ) && $actor->id() === $task->assignee_id
			&& $actor->allows( Action::ReadOwnTask, Scope::own(), $task->assignee_id, $now );
	}

	public static function team( Task $task, ActorContext $actor, int $now ): bool {
		return self::member( $actor, $task->unit_id, $now )
			&& $actor->allows( Action::ReadUnitTask, Scope::units( array( $task->unit_id ) ), $task->assignee_id, $now );
	}

	public static function create( string $unit, ActorContext $actor, int $now ): bool {
		return self::member( $actor, $unit, $now )
			&& $actor->allows( Action::ReadUnitTask, Scope::units( array( $unit ) ), $actor->id(), $now )
			&& $actor->allows( Action::AssignUnitTask, Scope::units( array( $unit ) ), $actor->id(), $now );
	}

	public static function manage( Task $task, string $operation, ActorContext $actor, int $now ): bool {
		return match ( $operation ) {
			'start' => TaskState::Open === $task->state && self::ownUpdate( $task, $actor, $now ),
			'complete' => TaskState::InProgress === $task->state && self::ownUpdate( $task, $actor, $now ),
			'cancel', 'reassign', 'change_due' => ! $task->state->terminal() && $actor->id() === $task->author_id && self::team( $task, $actor, $now )
				&& $actor->allows( Action::AssignUnitTask, Scope::units( array( $task->unit_id ) ), $task->assignee_id, $now ),
			'comment' => self::read( $task, $actor, $now ),
			default => false,
		};
	}

	/** Offline notice/assignment eligibility is never a route session proof. */
	public static function recipient( ActorContext $person, string $unit, int $now ): bool {
		if ( ! in_array( $unit, array( 'LAB-A', 'LAB-B' ), true ) || ! $person->canReceive( Audience::unit( $unit ), $now ) ) {
			return false;
		}
		return ( new AuthorizationPolicy() )->allows(
			$person->snapshot->toAccount( true ),
			new Request( Action::ReadOwnTask, Scope::own(), $person->id(), 0, true, true, true, true ),
			$person->snapshot->policyGrants(),
			$now
		);
	}

	private static function member( ActorContext $actor, string $unit, int $now ): bool {
		return in_array( $unit, array( 'LAB-A', 'LAB-B' ), true ) && $actor->valid( $now )
			&& in_array( $unit, $actor->units, true ) && in_array( $unit, $actor->active_units, true );
	}

	private static function ownUpdate( Task $task, ActorContext $actor, int $now ): bool {
		return self::own( $task, $actor, $now ) && $actor->allows( Action::UpdateOwnTask, Scope::own(), $task->assignee_id, $now );
	}
}
