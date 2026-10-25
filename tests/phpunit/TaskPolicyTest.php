<?php
/** F1-13 authority and recipient-safe projection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, StoredGrant};
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\Tasks\{Task, TaskContent, TaskPolicy, TaskProjection};
use DomainException;
use PHPUnit\Framework\TestCase;

/** Native administrator privileges never substitute task grants. */
final class TaskPolicyTest extends TestCase {

	/** Own relation and unit management are distinct current proofs. */
	public function testOwnAndTeamAccessAreExplicit(): void {
		$task    = $this->task();
		$reader  = $this->actor( 7, array( Role::Employee ) );
		$manager = $this->actor( 9, array( Role::UnitManager ) );
		self::assertTrue( TaskPolicy::own( $task, $reader, 101 ) );
		self::assertTrue( TaskPolicy::read( $task, $reader, 101 ) );
		self::assertFalse( TaskPolicy::team( $task, $reader, 101 ) );
		self::assertTrue( TaskPolicy::team( $task, $manager, 101 ) );
		self::assertFalse( TaskPolicy::own( $task, $manager, 101 ) );
		self::assertTrue( TaskPolicy::create( 'LAB-A', $manager, 101 ) );
		self::assertFalse( TaskPolicy::create( 'LAB-B', $manager, 101 ) );
		self::assertFalse( TaskPolicy::read( $task, $this->actor( 1, array( Role::TechnicalAdministrator ) ), 101 ) );
		self::assertFalse( TaskPolicy::read( $task, $this->actor( 8, array( Role::Employee ), 'LAB-B' ), 101 ) );
		$next = $task->reassign( 8, 9, 1, 101, 'Mudança.' );
		self::assertFalse( TaskPolicy::read( $next, $reader, 102 ) );
		self::assertTrue( TaskPolicy::read( $next, $manager, 102 ) );
		self::assertFalse( TaskPolicy::read( $next, $this->actor( 9, array( Role::Employee ) ), 102 ), 'Autoria não recupera leitura própria.' );
	}

	/** Session generation, active membership and grant validity remain mandatory. */
	public function testRevocationSuspensionExpiryAndInactiveUnitCloseAccess(): void {
		$good = $this->actor( 7, array( Role::Employee ) );
		foreach ( array(
			$this->actor( 7, array( Role::Employee ), 'LAB-A', AccountState::Suspended ),
			new ActorContext( $good->snapshot, array_merge( $good->session, array( 'arn_authz_revision' => 1 ) ), array( 'LAB-A' ), array( 'LAB-A' ) ),
			new ActorContext( $good->snapshot, array_merge( $good->session, array( 'expiration' => 101 ) ), array( 'LAB-A' ), array( 'LAB-A' ) ),
			new ActorContext( $good->snapshot, $good->session, array( 'LAB-A' ), array( 'LAB-B' ) ),
			$this->actor( 7, array(), 'LAB-A' ),
			new ActorContext( new AuthorizationSnapshot( 7, AccountState::Active, 2, array( new StoredGrant( 1, new Grant( 7, Role::Employee, Scope::own(), 0, null, true ) ) ) ), $good->session, $good->units, $good->active_units ),
			new ActorContext( new AuthorizationSnapshot( 7, AccountState::Active, 2, array( new StoredGrant( 1, new Grant( 7, Role::Employee, Scope::own(), 0, 101 ) ) ) ), $good->session, $good->units, $good->active_units ),
		) as $actor ) {
			self::assertFalse( TaskPolicy::read( $this->task(), $actor, 101 ) );
			self::assertFalse( TaskPolicy::manage( $this->task(), 'start', $actor, 101 ) );
		}
	}

	/** Pending institutional powers use the conservative local preset. */
	public function testManagementDoesNotInventReopeningOrAnotherAuthorsPowers(): void {
		$task    = $this->task();
		$manager = $this->actor( 9, array( Role::UnitManager ) );
		$other   = $this->actor( 10, array( Role::UnitManager ) );
		foreach ( array( 'cancel', 'reassign', 'change_due' ) as $operation ) {
			self::assertTrue( TaskPolicy::manage( $task, $operation, $manager, 101 ) );
			self::assertFalse( TaskPolicy::manage( $task, $operation, $other, 101 ) );
		}
		self::assertTrue( TaskPolicy::manage( $task, 'comment', $other, 101 ) );
		self::assertFalse( TaskPolicy::manage( $task, 'start', $manager, 101 ) );
		self::assertFalse( TaskPolicy::manage( $task, 'reopen', $manager, 101 ) );
		self::assertFalse( TaskPolicy::manage( $task, 'delete', $manager, 101 ) );
		$done = $task->start( 7, 1, 101 )->complete( 7, 2, 102 );
		self::assertFalse( TaskPolicy::manage( $done, 'reassign', $manager, 103 ) );
		self::assertTrue( TaskPolicy::manage( $done, 'comment', $manager, 103 ) );
	}

	/** Offline delivery eligibility never becomes proof of an authenticated route. */
	public function testRecipientEligibilityDoesNotNeedOrCreateASession(): void {
		$reader  = $this->actor( 7, array( Role::Employee ) );
		$offline = new ActorContext( $reader->snapshot, array(), $reader->units, $reader->active_units );
		self::assertTrue( TaskPolicy::recipient( $offline, 'LAB-A', 101 ) );
		self::assertFalse( TaskPolicy::read( $this->task(), $offline, 101 ) );
		self::assertFalse( TaskPolicy::recipient( $offline, 'LAB-B', 101 ) );
		self::assertFalse( TaskPolicy::recipient( $this->actor( 9, array( Role::UnitManager ) ), 'LAB-A', 101 ) );
	}

	/** Reasons and create identities stay out of the recipient DTO. */
	public function testProjectionHidesPrivateReasonsAndRevalidatesAccess(): void {
		$task    = $this->task()->cancel( 9, 1, 101, 'Motivo privado da gestão.' );
		$reader  = TaskProjection::detail( $task, $this->actor( 7, array( Role::Employee ) ), 102 );
		$manager = TaskProjection::detail( $task, $this->actor( 9, array( Role::UnitManager ) ), 102 );
		self::assertArrayNotHasKey( 'reason', $reader['history'][1] );
		self::assertArrayNotHasKey( 'after', $reader['history'][1] );
		self::assertArrayNotHasKey( 'comment_sha256', $reader['history'][1] );
		self::assertArrayNotHasKey( 'create_request_id', $reader );
		self::assertSame( 'Motivo privado da gestão.', $manager['history'][1]['reason'] );
		$this->expectException( DomainException::class );
		TaskProjection::detail( $task, $this->actor( 8, array( Role::Employee ), 'LAB-B' ), 102 );
	}

	/** History exposes earlier business facts without the private raw payload. */
	public function testHistoricalAssigneesAndDeadlinesRemainVisibleToAuthorizedReaders(): void {
		$task = $this->task()->changeDue( '2030-01-15T10:00:00Z', 9, 1, 101, 'Prazo privado.' )->reassign( 9, 9, 2, 102, 'Reatribuição privada.' );
		$view = TaskProjection::detail( $task, $this->actor( 9, array( Role::Employee ) ), 103 );
		self::assertSame( array( 7, 7, 9 ), array_column( $view['history'], 'assignee_id' ) );
		self::assertNull( $view['history'][0]['due_at'] );
		self::assertSame( $task->content->due_at, $view['history'][1]['due_at'] );
		self::assertArrayNotHasKey( 'reason', $view['history'][1] );
		self::assertArrayNotHasKey( 'create_request_id', $view['history'][1] );
	}

	/** Attachments require current writing authority and relationship. */
	public function testAttachmentRequiresWritingCompetenceAndCurrentRelationship(): void {
		$task = $this->task();
		self::assertTrue( TaskPolicy::manage( $task, 'attach', $this->actor( 7, array( Role::Employee ) ), 101 ) );
		self::assertTrue( TaskPolicy::manage( $task, 'attach', $this->actor( 9, array( Role::UnitManager ) ), 101 ) );
		self::assertFalse( TaskPolicy::manage( $task, 'attach', $this->actor( 8, array( Role::UnitManager ) ), 101 ) );
		self::assertFalse( TaskPolicy::manage( $task, 'attach', $this->actor( 7, array(), 'LAB-A' ), 101 ) );
		self::assertFalse( TaskPolicy::manage( $task->cancel( 9, 1, 101, 'Fim' ), 'attach', $this->actor( 9, array( Role::UnitManager ) ), 102 ) );
	}

	/** One fixture with separate author and current assignee. */
	private function task(): Task {
		return Task::create( 1, 9, 7, 'LAB-A', new TaskContent( 'Tarefa fictícia', '', 'normal', null ), 100, str_repeat( 'a', 32 ) );
	}

	/** Current session and explicit grants, without WordPress capability shortcuts.
	 *
	 * @param int          $id Fixture actor.
	 * @param array<Role>  $roles Explicit role packages.
	 * @param string       $unit Personal membership.
	 * @param AccountState $state Current account state.
	 * @return ActorContext
	 */
	private function actor( int $id, array $roles, string $unit = 'LAB-A', AccountState $state = AccountState::Active ): ActorContext {
		$grants = array();
		foreach ( $roles as $index => $role ) {
			$grants[] = new StoredGrant( $index + 1, new Grant( $id, $role, Role::UnitManager === $role ? Scope::units( array( $unit ) ) : Scope::own(), 0, null ) );
		}
		return new ActorContext(
			new AuthorizationSnapshot( $id, $state, 2, $grants ),
			array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => 2,
				'expiration'         => 1000,
			),
			array( $unit ),
			array( 'LAB-A', 'LAB-B' )
		);
	}
}
