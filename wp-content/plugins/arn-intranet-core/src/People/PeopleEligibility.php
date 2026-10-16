<?php
/** Target eligibility independent of reader permission.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** A current target account and its unit must both be active. */
final class PeopleEligibility {
	/** The caller separately checks the actor's read permission.
	 *
	 * @param Profile               $profile Target profile.
	 * @param AuthorizationSnapshot $target Target account snapshot.
	 * @param array<string>         $active_unit_ids Active units.
	 */
	public static function active( Profile $profile, AuthorizationSnapshot $target, array $active_unit_ids ): bool {
		return $profile->user_id === $target->user_id
			&& AccountState::Active === $target->state
			&& in_array( $profile->professional->unit_id, $active_unit_ids, true );
	}
}
