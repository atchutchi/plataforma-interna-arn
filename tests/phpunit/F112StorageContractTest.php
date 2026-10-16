<?php
/** Local storage preconditions without SQL doubles.
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
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\F112\Audience;
use Arn\Intranet\F112\Configuration;
use PHPUnit\Framework\TestCase;

/** Notification eligibility must not manufacture a browser session. */
final class F112StorageContractTest extends TestCase {

	/** A caller cannot mutate the proof or membership after constructing a context. */
	public function test_context_detaches_external_authority_references(): void {
		$snapshot = new AuthorizationSnapshot( 7, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( 7, Role::Employee, Scope::own(), 0, null ) ) ) );
		$local    = true;
		$unit     = 'LAB-A';
		$session  = array(
			'arn_authz_local'    => &$local,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 4,
		);
		$units    = array( &$unit );
		$actor    = new ActorContext( $snapshot, $session, $units, $units );
		$local    = false;
		$unit     = 'LAB-B';
		self::assertTrue( $actor->valid( 11 ) );
		self::assertTrue( $actor->receives( Audience::unit( 'LAB-A' ), 11 ) );
		self::assertFalse( $actor->receives( Audience::unit( 'LAB-B' ), 11 ) );
	}

	/** Native expiry remains exclusive if the clock advances after the SQL read. */
	public function test_context_rechecks_native_expiry_with_its_evaluation_clock(): void {
		$snapshot = new AuthorizationSnapshot( 7, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( 7, Role::Employee, Scope::own(), 0, null ) ) ) );
		$session  = array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 4,
			'expiration'         => 12,
		);
		$actor    = new ActorContext( $snapshot, $session, array( 'LAB-A' ), array( 'LAB-A' ) );
		self::assertTrue( $actor->valid( 11 ) );
		self::assertFalse( $actor->valid( 12 ) );
		self::assertTrue( $actor->canReceive( Audience::all(), 12 ) );
	}

	/** Every flag is a strict boolean, including the independent module flag. */
	public function test_storage_gates_are_strict_and_local_only(): void {
		self::assertTrue( Configuration::enabled( 'local', true, true, true, true ) );
		foreach ( array( false, 0, 1, 'true', null ) as $invalid ) {
			for ( $index = 0; $index < 4; ++$index ) {
				$flags           = array( true, true, true, true );
				$flags[ $index ] = $invalid;
				self::assertFalse( Configuration::enabled( 'local', ...$flags ) );
			}
		}
		foreach ( array( 'production', 'development', 'staging', '' ) as $environment ) {
			self::assertFalse( Configuration::enabled( $environment, true, true, true, true ) );
		}
		self::assertFalse( Configuration::current( 'unknown' ) );
	}

	/** Offline eligible users receive a stored notice without obtaining authenticated access. */
	public function test_offline_recipient_eligibility_is_separate_from_session_access(): void {
		$snapshot = new AuthorizationSnapshot( 7, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( 7, Role::Employee, Scope::own(), 10, 20 ) ) ) );
		$offline  = new ActorContext( $snapshot, array(), array( 'LAB-A' ), array( 'LAB-A', 'LAB-B' ) );
		self::assertFalse( $offline->valid( 11 ) );
		self::assertTrue( $offline->canReceive( Audience::all(), 11 ) );
		self::assertTrue( $offline->canReceive( Audience::unit( 'LAB-A' ), 11 ) );
		self::assertFalse( $offline->canReceive( Audience::unit( 'LAB-B' ), 11 ) );
		self::assertFalse( $offline->canReceive( Audience::all(), 20 ) );
		self::assertFalse( $offline->canReceive( Audience::all(), 9 ) );
		self::assertFalse( ( new ActorContext( new AuthorizationSnapshot( 7, AccountState::Suspended, 5, $snapshot->stored_grants ), array(), array( 'LAB-A' ), array( 'LAB-A' ) ) )->canReceive( Audience::all(), 11 ) );
		self::assertFalse( ( new ActorContext( new AuthorizationSnapshot( 7, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( 7, Role::UnitManager, Scope::institutional(), 0, null ) ) ) ), array(), array( 'LAB-A' ), array( 'LAB-A' ) ) )->canReceive( Audience::all(), 11 ) );
	}

	/** A staff fixture cannot bypass its inactive unit through an all-audience publication. */
	public function test_inactive_membership_closes_session_and_offline_delivery_without_inventing_institutional_membership(): void {
		$snapshot = new AuthorizationSnapshot( 7, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( 7, Role::Employee, Scope::own(), 0, null ) ) ) );
		$session  = array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 4,
		);
		$closed   = new ActorContext( $snapshot, $session, array( 'LAB-A' ), array( 'LAB-B' ) );
		self::assertFalse( $closed->valid( 11 ) );
		self::assertFalse( $closed->canReceive( Audience::all(), 11 ) );
		$institutional = new ActorContext( $snapshot, $session, array(), array( 'LAB-B' ) );
		self::assertTrue( $institutional->valid( 11 ) );
		self::assertTrue( $institutional->canReceive( Audience::all(), 11 ) );
		self::assertFalse( $institutional->canReceive( Audience::unit( 'LAB-B' ), 11 ) );
	}
}
