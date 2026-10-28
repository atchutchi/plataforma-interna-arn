<?php
/** Explicit request competencies, without technical administrator bypass.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\Requests\{RequestPolicy, RequestState};
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions
final class LeavePolicy {
	public static function own( LeaveRequest $r, ActorContext $a, int $now ): bool {
		return $r->owner === $a->id() && RequestPolicy::create( $r->unit, $a, $now );}
	public static function team( LeaveRequest $r, ActorContext $a, int $now ): bool {
		return in_array( 'submit', array_column( $r->history, 'operation' ), true ) && RequestPolicy::manageUnit( $r->unit, $a, $now );}
	public static function read( LeaveRequest $r, ActorContext $a, int $now ): bool {
		return self::own( $r, $a, $now ) || self::team( $r, $a, $now );}
	public static function operations( LeaveRequest $r, ActorContext $a, int $now ): array {
		$ops    = array();
		$future = $r->starts > gmdate( 'Y-m-d', $now );
		if ( self::own( $r, $a, $now ) ) {
			if ( $r->state->editable() ) {
				$ops[] = 'edit';
				if ( $future ) {
							$ops[] = 'submit';
				}
			}
			if ( $r->state->editable() || $r->state->pending() ) {
				$ops[] = 'withdraw';
			}
		}
		if ( $a->id() !== $r->owner && self::team( $r, $a, $now ) ) {
			$ops = array_merge(
				$ops,
				match ( $r->state ) {
					RequestState::Submitted=>array( 'start' ),
					RequestState::Analysis=>$future ? array( 'return', 'approve', 'reject' ) : array( 'return', 'reject' ),
					RequestState::Approved=>$future ? array( 'cancel' ) : array(),
					default=>array()}
			);
		}
		return $ops;
	}
}
