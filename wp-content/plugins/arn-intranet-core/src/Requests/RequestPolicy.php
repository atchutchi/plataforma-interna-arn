<?php
/** Current request competence, independent of native administration.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

use Arn\Intranet\Authorization\{Action, AuthorizationPolicy, Request, Scope};
use Arn\Intranet\F112\ActorContext;

// phpcs:disable Squiz.Commenting -- Closed authorization rules, also checked within the SQL transaction.
final class RequestPolicy {
	public static function own( InternalRequest $request, ActorContext $actor, int $now ): bool {
		return $request->owner === $actor->id() && self::create( $request->type->unit, $actor, $now );
	}
	public static function create( string $unit, ActorContext $actor, int $now ): bool {
		return in_array( $unit, $actor->units, true ) && in_array( $unit, $actor->active_units, true ) && $actor->allows( Action::ManageOwnRequest, Scope::own(), $actor->id(), $now );
	}
	public static function manageUnit( string $unit, ActorContext $actor, int $now ): bool {
		return in_array( $unit, $actor->active_units, true ) && $actor->allows( Action::ManageInternalRequest, Scope::units( array( $unit ) ), $actor->id(), $now );
	}
	public static function configure( ActorContext $actor, int $now ): bool {
		return $actor->allows( Action::ManageInternalRequest, Scope::institutional(), $actor->id(), $now );
	}
	public static function team( InternalRequest $request, ActorContext $actor, int $now ): bool {
		return in_array( 'submit', array_column( $request->history, 'operation' ), true ) && self::manageUnit( $request->type->unit, $actor, $now );
	}
	public static function read( InternalRequest $request, ActorContext $actor, int $now ): bool {
		return self::own( $request, $actor, $now ) || self::team( $request, $actor, $now );
	}
	public static function operations( InternalRequest $r, ActorContext $actor, int $now ): array {
		$result = array();
		if ( self::own( $r, $actor, $now ) ) {
			if ( $r->state->editable() ) {
				$result = array( 'edit', 'submit' );
				if ( $r->type->attachments && count( $r->attachments ) < 8 ) {
					$result[] = 'attach';
				}
			}
			if ( $r->state->editable() || $r->state->pending() ) {
				$result[] = 'withdraw';
			}
		}
		if ( self::team( $r, $actor, $now ) && $r->reviewer( $actor->id(), $now ) ) {
			$result = array_merge(
				$result,
				match ( $r->state ) {
				RequestState::Submitted => array( 'start' ),
				RequestState::Analysis => array( 'return', 'approve', 'reject' ),
				RequestState::Approved => array( 'cancel' ),
				default => array() }
			);
			if ( $r->state->pending() && $actor->id() === $r->type->reviewer ) {
				$result[] = 'substitute';
				if ( array() !== $r->substitute ) {
					$result[] = 'revoke_substitute';
				}
			}
		}
		return $result;
	}
	/** Offline recipient/reviewer eligibility cannot serve as a route session. */
	public static function eligible( ActorContext $person, string $unit, int $now, bool $manager ): bool {
		return in_array( $unit, $person->active_units, true ) && ( $manager || in_array( $unit, $person->units, true ) ) && ( new AuthorizationPolicy() )->allows(
			$person->snapshot->toAccount( true ),
			new Request( $manager ? Action::ManageInternalRequest : Action::ManageOwnRequest, $manager ? Scope::units( array( $unit ) ) : Scope::own(), $person->id(), 0, true, true, true, true ),
			$person->snapshot->policyGrants(),
			$now
		);
	}
}
