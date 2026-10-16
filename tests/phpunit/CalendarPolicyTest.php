<?php
/** Explicit event management permissions.
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
use Arn\Intranet\Calendar\Event;
use Arn\Intranet\Calendar\EventContent;
use Arn\Intranet\Calendar\EventPolicy;
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\F112\Audience;
use PHPUnit\Framework\TestCase;

/** Institutional and unit managers have independent capabilities. */
final class CalendarPolicyTest extends TestCase {

	/** Native privilege, organiser identity and notice grants do not manage events. */
	public function test_management_and_recipient_boundaries(): void {
		$content = new EventContent( 'A', 'B', Audience::unit( 'LAB-A' ), 'online', '', 'https://example.invalid/reuniao-f112', false, '2030-01-15T10:00:00Z', '2030-01-15T11:00:00Z', null, null );
		$unit    = Event::draft( 1, 7, 'LAB-A', $content, 10 )->publish( 7, 1, 11, 'Publicar.' );
		$global  = Event::draft( 2, 7, null, $content, 10 )->publish( 7, 1, 11, 'Publicar institucional.' );
		$manager = $this->actor( Role::UnitEventManager, Scope::units( array( 'LAB-A' ) ) );
		self::assertTrue( EventPolicy::manage( $unit, 'revise', $manager, 12 ) );
		self::assertFalse( EventPolicy::read( $unit, $manager, 12 ) );
		self::assertFalse( EventPolicy::manage( $global, 'revise', $manager, 12 ) );
		self::assertFalse( EventPolicy::manage( $global, 'publish', $this->actor( Role::InstitutionalEventManager, Scope::institutional() ), 12 ) );
		self::assertTrue( EventPolicy::manage( $global, 'cancel', $this->actor( Role::InstitutionalEventManager, Scope::institutional() ), 12 ) );
		foreach ( array( Role::UnitManager, Role::Employee, Role::Editorial, Role::NoticeReviewer, Role::TechnicalAdministrator ) as $role ) {
			self::assertFalse( EventPolicy::manage( $unit, 'cancel', $this->actor( $role, Scope::institutional() ), 12 ) );
		}
		self::assertFalse( EventPolicy::manage( $unit, 'cancel', $this->actor( Role::UnitEventManager, Scope::units( array( 'LAB-B' ) ) ), 12 ) );
		self::assertFalse( EventPolicy::manage( $unit, 'cancel', $this->actor( Role::InstitutionalEventManager, Scope::institutional() ), 12 ) );
		$reader = $this->actor( Role::Employee, Scope::own() );
		self::assertTrue( EventPolicy::read( $unit, $reader, 12 ) );
		self::assertFalse( EventPolicy::read( $unit, $reader, 10 ) );
		$cancelled = $unit->cancel( 7, 2, 12, 'Privado.' );
		self::assertTrue( EventPolicy::read( $cancelled, $reader, 13 ) );
		self::assertFalse( EventPolicy::read( $cancelled->archive( 7, 3, 13, 'Arquivo.' ), $reader, 14 ) );
		self::assertFalse( EventPolicy::read( $unit, $this->actor( Role::Employee, Scope::own(), array( 'LAB-B' ) ), 12 ) );
		self::assertFalse( EventPolicy::read( $unit, $this->actor( Role::Employee, Scope::own(), array( 'LAB-A' ), AccountState::Suspended ), 12 ) );
	}

	/** Real policy inputs without native role mocks.
	 *
	 * @param Role         $role Granted package.
	 * @param Scope        $scope Granted scope.
	 * @param array        $units Membership.
	 * @param AccountState $state Current state.
	 * @return ActorContext
	 */
	private function actor( Role $role, Scope $scope, array $units = array( 'LAB-A' ), AccountState $state = AccountState::Active ): ActorContext {
		return new ActorContext(
			new AuthorizationSnapshot( 8, $state, 4, array( new StoredGrant( 1, new Grant( 8, $role, $scope, 0, null ) ) ) ),
			array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => 8,
				'arn_authz_revision' => 4,
			),
			$units,
			array( 'LAB-A', 'LAB-B' )
		);
	}
}
