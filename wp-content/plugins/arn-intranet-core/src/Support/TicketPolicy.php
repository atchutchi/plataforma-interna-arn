<?php
/** Current support authority, without native administrator inheritance.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use Arn\Intranet\Authorization\{Action, AuthorizationPolicy, Request, Scope};
use Arn\Intranet\F112\ActorContext;
// phpcs:disable Squiz.Commenting
final class TicketPolicy {
	public static function create( string $unit, ActorContext $actor, int $now ): bool {
		return in_array( $unit, $actor->units, true ) && in_array( $unit, $actor->active_units, true ) && $actor->allows( Action::ManageOwnTicket, Scope::own(), $actor->id(), $now ); }
	public static function manageUnit( string $unit, ActorContext $actor, int $now ): bool {
		return in_array( $unit, $actor->active_units, true ) && $actor->allows( Action::ManageSupportTicket, Scope::units( array( $unit ) ), 0, $now ); }
	public static function own( Ticket $t, ActorContext $a, int $now ): bool {
		return $t->owner === $a->id() && self::create( $t->unit, $a, $now ); }
	public static function team( Ticket $t, ActorContext $a, int $now ): bool {
		return self::manageUnit( $t->unit, $a, $now ); }
	public static function read( Ticket $t, ActorContext $a, int $now ): bool {
		return self::own( $t, $a, $now ) || self::team( $t, $a, $now ); }
	public static function operations( Ticket $t, ActorContext $a, int $now ): array {
		if ( TicketState::Resolved === $t->state ) {
			return array(); }
		$own      = self::own( $t, $a, $now );
		$assigned = $t->assigned === $a->id() && $t->owner !== $a->id() && self::team( $t, $a, $now );
		$ops      = array();
		if ( TicketState::New === $t->state && 0 === $t->assigned && $t->owner !== $a->id() && self::team( $t, $a, $now ) ) {
			$ops[] = 'claim'; }
		if ( $own || $assigned ) {
			if ( count( $t->messages ) < 49 ) {
				$ops[] = 'reply'; }
			if ( count( $t->attachments ) < 8 ) {
				$ops[] = 'attach'; }
		}
		if ( $assigned && TicketState::InProgress === $t->state ) {
			$ops[] = 'resolve'; }
		return $ops;
	}
	public static function eligible( ActorContext $a, string $unit, int $now, bool $support ): bool {
		return in_array( $unit, $a->active_units, true ) && ( $support || in_array( $unit, $a->units, true ) ) && ( new AuthorizationPolicy() )->allows( $a->snapshot->toAccount( true ), new Request( $support ? Action::ManageSupportTicket : Action::ManageOwnTicket, $support ? Scope::units( array( $unit ) ) : Scope::own(), $a->id(), 0, true, true, true, true ), $a->snapshot->policyGrants(), $now );
	}
}
