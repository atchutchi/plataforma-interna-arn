<?php
/** Pure People permission tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use Arn\Intranet\People\PeopleEligibility;
use Arn\Intranet\People\PeoplePolicy;
use Arn\Intranet\People\ProfessionalDetails;
use Arn\Intranet\People\Profile;
use Arn\Intranet\People\SelfService;
use PHPUnit\Framework\TestCase;

/** Existing grants and session generation govern the module. */
final class PeoplePolicyTest extends TestCase {
	/** Employee/Own shares contacts but only its owner edits. */
	public function test_employee_own_reads_directory_and_edits_only_self(): void {
		$actor   = $this->snapshot( array( $this->grant( Role::Employee, Scope::own() ) ) );
		$session = $this->session();
		self::assertTrue( PeoplePolicy::canReadDirectory( $actor, $session, 10 ) );
		self::assertTrue( PeoplePolicy::canUpdateOwn( 7, $actor, $session, 10 ) );
		self::assertFalse( PeoplePolicy::canUpdateOwn( 8, $actor, $session, 10 ) );
		self::assertFalse( PeoplePolicy::canReadTeam( 'LAB-A', $actor, $session, 10 ) );
	}

	/** Team needs one current UnitManager grant covering the requested unit. */
	public function test_team_uses_one_manager_grant_without_partial_aggregation(): void {
		$session = $this->session();
		$actor   = $this->snapshot( array( $this->grant( Role::Employee, Scope::own() ), $this->grant( Role::UnitManager, Scope::units( array( 'LAB-A' ) ) ) ) );
		self::assertTrue( PeoplePolicy::canReadTeam( 'LAB-A', $actor, $session, 10 ) );
		self::assertFalse( PeoplePolicy::canReadTeam( 'LAB-B', $actor, $session, 10 ) );
		$hr        = $this->snapshot( array( $this->grant( Role::Employee, Scope::own() ), $this->grant( Role::HumanResources, Scope::units( array( 'LAB-A' ) ) ) ) );
		$outside   = $this->snapshot( array( $this->grant( Role::UnitManager, Scope::units( array( 'LAB-B' ) ) ), $this->grant( Role::Employee, Scope::own() ) ) );
		$technical = $this->snapshot( array( $this->grant( Role::TechnicalAdministrator, Scope::institutional() ) ) );
		$editorial = $this->snapshot( array( $this->grant( Role::Editorial, Scope::institutional() ) ) );
		foreach ( array( $hr, $outside, $technical, $editorial ) as $denied ) {
			self::assertFalse( PeoplePolicy::canReadTeam( 'LAB-A', $denied, $session, 10 ) );
		}
	}

	/** Revocation, expiry, account state and old tokens close all permissions. */
	public function test_stale_and_inactive_authority_denies_access(): void {
		$session = $this->session();
		foreach ( array(
			$this->snapshot( array( $this->grant( Role::Employee, Scope::own(), true ) ) ),
			$this->snapshot( array( $this->grant( Role::Employee, Scope::own(), false, 10 ) ) ),
			$this->snapshot( array( $this->grant( Role::Employee, Scope::own() ) ), AccountState::Suspended ),
		) as $actor ) {
			self::assertFalse( PeoplePolicy::canReadDirectory( $actor, $session, 10 ) );
			self::assertFalse( PeoplePolicy::canUpdateOwn( 7, $actor, $session, 10 ) );
		}
		$actor = $this->snapshot( array( $this->grant( Role::Employee, Scope::own() ) ) );
		self::assertFalse(
			PeoplePolicy::canReadDirectory(
				$actor,
				array(
					'arn_authz_local'    => true,
					'arn_authz_user_id'  => 7,
					'arn_authz_revision' => 3,
				),
				10
			)
		);
		self::assertFalse(
			PeoplePolicy::canReadDirectory(
				$actor,
				array(
					'arn_authz_local'    => 1,
					'arn_authz_user_id'  => 7,
					'arn_authz_revision' => 4,
				),
				10
			)
		);
	}

	/** Eligibility is target state plus active unit, never an access grant. */
	public function test_target_eligibility_requires_active_target_and_unit(): void {
		$profile = new Profile( 8, new ProfessionalDetails( 'B', 'Beta', 'b@example.invalid', 'Cargo', 'LAB-B' ), new SelfService( '', '', '' ), 1, 0 );
		$target  = $this->snapshot( array(), AccountState::Active, 8 );
		self::assertTrue( PeopleEligibility::active( $profile, $target, array( 'LAB-B' ) ) );
		self::assertFalse( PeopleEligibility::active( $profile, $target, array( 'LAB-A' ) ) );
		self::assertFalse( PeopleEligibility::active( $profile, $this->snapshot( array(), AccountState::Suspended, 8 ), array( 'LAB-B' ) ) );
		self::assertFalse( PeopleEligibility::active( $profile, $this->snapshot( array(), AccountState::Active, 7 ), array( 'LAB-B' ) ) );
	}

	/** Build a complete stored authority snapshot.
	 *
	 * @param array<Grant> $grants Current grants.
	 * @param AccountState $state Current state.
	 * @param int          $user_id Target user.
	 */
	private function snapshot( array $grants, AccountState $state = AccountState::Active, int $user_id = 7 ): AuthorizationSnapshot {
		$stored = array();
		foreach ( $grants as $index => $grant ) {
			$stored[] = new StoredGrant( $index + 1, $grant );
		}
		return new AuthorizationSnapshot( $user_id, $state, 4, $stored );
	}

	/** Create one grant for the actor.
	 *
	 * @param Role     $role Explicit role.
	 * @param Scope    $scope Explicit scope.
	 * @param bool     $revoked Revocation marker.
	 * @param int|null $expires_at Exclusive end.
	 */
	private function grant( Role $role, Scope $scope, bool $revoked = false, ?int $expires_at = null ): Grant {
		return new Grant( 7, $role, $scope, 0, $expires_at, $revoked );
	}

	/** Current server session. */
	private function session(): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 4,
		);
	}
}
