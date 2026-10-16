<?php
/** Pure local directory authorization.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** Uses the current session generation and existing closed action matrix. */
final class PeoplePolicy {
	/** Any Employee/Own may read the shared contact directory.
	 *
	 * @param AuthorizationSnapshot $snapshot Current actor snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Current Unix timestamp.
	 */
	public static function canReadDirectory( AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		return self::allows( Action::ReadDirectory, Scope::own(), $snapshot->user_id, $snapshot, $session, $now, $snapshot->policyGrants() );
	}

	/** An actor may update only the real owner of the profile.
	 *
	 * @param int                   $owner_id Profile owner.
	 * @param AuthorizationSnapshot $snapshot Current actor snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Current Unix timestamp.
	 */
	public static function canUpdateOwn( int $owner_id, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		return $owner_id > 0 && $owner_id === $snapshot->user_id
			&& self::allows( Action::UpdateOwnProfile, Scope::own(), $owner_id, $snapshot, $session, $now, $snapshot->policyGrants() );
	}

	/** A single UnitManager grant must cover the requested unit.
	 *
	 * @param string                $unit_id Requested unit.
	 * @param AuthorizationSnapshot $snapshot Current actor snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Current Unix timestamp.
	 */
	public static function canReadTeam( string $unit_id, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_-]{1,64}\z/D', $unit_id ) ) {
			return false;
		}
		$manager_grants = array();
		foreach ( $snapshot->policyGrants() as $grant ) {
			if ( Role::UnitManager === $grant->role ) {
				$manager_grants[] = $grant;
			}
		}
		return self::allows( Action::ReadDirectory, Scope::units( array( $unit_id ) ), 0, $snapshot, $session, $now, $manager_grants );
	}

	/** Delegate grant, state and scope decisions to the existing engine.
	 *
	 * @param Action                $action Existing business action.
	 * @param Scope                 $scope Required scope.
	 * @param int                   $owner_id Record owner.
	 * @param AuthorizationSnapshot $snapshot Current actor snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Current Unix timestamp.
	 * @param array                 $grants Candidate grants.
	 */
	private static function allows( Action $action, Scope $scope, int $owner_id, AuthorizationSnapshot $snapshot, array $session, int $now, array $grants ): bool {
		if ( ! LocalAccountSessionPolicy::valid( $snapshot, $snapshot->user_id, $session, true ) ) {
			return false;
		}
		$request = new Request( $action, $scope, $owner_id, 0, true, true, true, true );
		return ( new AuthorizationPolicy() )->allows( $snapshot->toAccount( true ), $request, $grants, $now );
	}
}
