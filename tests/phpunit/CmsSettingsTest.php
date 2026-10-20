<?php
/** Closed CMS upload policies, revisions and explicit authority.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, StoredGrant};
use Arn\Intranet\Cms\{Configuration, SettingsActor, SettingsState, UploadPolicy, UploadAdmission};
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment -- Behaviour names describe the cases and fixture helpers.

/** These policies apply to new bytes, not to existing file delivery. */
final class CmsSettingsTest extends TestCase {

	public function testRetainedBytesSurviveReducedSizeAndRemovedMime(): void {
		$values             = UploadPolicy::defaults()->toArray();
		$values['document'] = array(
			'max_bytes' => 1,
			'mimes'     => array( 'image/png' ),
		);
		$policy             = UploadPolicy::fromArray( $values );
		$previous           = new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'c', 64 ), 100, 'application/pdf' );
		$copy               = new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'c', 64 ), 100, 'application/pdf' );
		UploadAdmission::assertAllowed( $policy, 'document', $copy, $previous );
		self::assertFalse( $policy->allows( 'document', $copy ) );
		$this->expectException( DomainException::class );
		UploadAdmission::assertAllowed( $policy, 'document', $copy );
	}

	public function testRetainedDescriptorsCannotChangeBytesOrWidenDestinationTypes(): void {
		$previous = new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'c', 64 ), 100, 'application/pdf' );
		foreach ( array(
			array( 'document', $previous ),
			array( 'document', new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'd', 64 ), 100, 'application/pdf' ) ),
			array( 'document', new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'c', 64 ), 101, 'application/pdf' ) ),
			array( 'document', new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'c', 64 ), 100, 'image/png' ) ),
			array( 'news_image', new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'c', 64 ), 100, 'application/pdf' ) ),
			array( 'unknown', new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'c', 64 ), 100, 'application/pdf' ) ),
		) as [$destination, $copy] ) {
			try {
				UploadAdmission::assertAllowed( UploadPolicy::defaults(), $destination, $copy, $previous );
				self::fail( 'Invalid retained descriptor accepted.' );
			} catch ( DomainException ) {
				self::assertTrue( true );
			}
		}
	}

	public function testConfigurationRequiresEveryExplicitLocalBoolean(): void {
		self::assertTrue( Configuration::enabled( 'local', true, true, true ) );
		foreach ( array( 'production', 'staging', '', 'LOCAL' ) as $environment ) {
			self::assertFalse( Configuration::enabled( $environment, true, true, true ) );
		}
		foreach ( array( false, 1, '1', 'true', null ) as $value ) {
			self::assertFalse( Configuration::enabled( 'local', $value, true, true ) );
			self::assertFalse( Configuration::enabled( 'local', true, $value, true ) );
			self::assertFalse( Configuration::enabled( 'local', true, true, $value ) );
		}
	}

	public function testStoredStateRejectsCorruptInitialMetadataAndLooseTypes(): void {
		$valid = SettingsState::initial()->toArray();
		foreach ( array( array( 'revision', '1' ), array( 'revision', 0 ), array( 'actor_id', 7 ), array( 'occurred_at', 100 ), array( 'reason', 'Invented initial change.' ), array( 'policy', false ), array( 'extra', true ) ) as [$key, $value] ) {
			$bad         = $valid;
			$bad[ $key ] = $value;
			try {
				SettingsState::fromArray( $bad );
				self::fail( 'Corrupt state accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	public function testDefaultsMatchCurrentDestinationsAndRestrictNewBytes(): void {
		$policy = UploadPolicy::defaults();
		$pdf    = new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1048576, 'application/pdf' );
		self::assertTrue( $policy->allows( 'document', $pdf ) );
		self::assertTrue( $policy->allows( 'news_attachment', $pdf ) );
		self::assertFalse( $policy->allows( 'news_image', $pdf ) );
		self::assertFalse( $policy->allows( 'unknown', $pdf ) );
		$values                          = $policy->toArray();
		$values['document']['max_bytes'] = 1024;
		$reduced                         = UploadPolicy::fromArray( $values );
		self::assertFalse( $reduced->allows( 'document', $pdf ) );
		self::assertTrue( $policy->allows( 'document', $pdf ), 'An earlier immutable policy remains unchanged.' );
		self::assertTrue( $reduced->allows( 'news_attachment', $pdf ) );
	}

	public function testMalformedPoliciesCannotWidenServerLimits(): void {
		$defaults = UploadPolicy::defaults()->toArray();
		$invalid  = array();
		foreach ( array( 0, -1, 1048577, PHP_INT_MAX, '1024', 1.0, true, null, array() ) as $value ) {
			$case                          = $defaults;
			$case['document']['max_bytes'] = $value;
			$invalid[]                     = $case;
		}
		foreach ( array( array(), array( 'application/pdf', 'application/pdf' ), array( 'text/html' ), array( 'IMAGE/PNG' ), array( 1 ), array( 'x' => 'image/png' ), 'image/png' ) as $value ) {
			$case                      = $defaults;
			$case['document']['mimes'] = $value;
			$invalid[]                 = $case;
		}
		$case                        = $defaults;
		$case['news_image']['mimes'] = array( 'application/pdf' );
		$invalid[]                   = $case;
		$invalid[]                   = $defaults + array( 'oidc_secret' => 'fictional' );
		$invalid[]                   = array_slice( $defaults, 1, null, true );
		$case                        = $defaults;
		$case['document']['extra']   = true;
		$invalid[]                   = $case;
		$case                        = $defaults;
		$case['document']            = false;
		$invalid[]                   = $case;
		foreach ( $invalid as $case ) {
			try {
				UploadPolicy::fromArray( $case );
				self::fail( 'Malformed settings accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	public function testMimeAndDestinationOrderDoNotProduceFalseChanges(): void {
		$defaults                    = UploadPolicy::defaults();
		$values                      = array_reverse( $defaults->toArray(), true );
		$values['document']['mimes'] = array_reverse( $values['document']['mimes'] );
		self::assertSame( $defaults->toArray(), UploadPolicy::fromArray( $values )->toArray() );
		$state = SettingsState::initial();
		self::assertSame( $state, $state->change( UploadPolicy::fromArray( $values ), 1, 7, 100, 'Confirmação dos limites.' ) );
	}

	public function testRevisionsRejectStaleWritesAndPreserveEarlierState(): void {
		$first                           = SettingsState::initial();
		$values                          = $first->policy->toArray();
		$values['document']['max_bytes'] = 1024;
		$second                          = $first->change( UploadPolicy::fromArray( $values ), 1, 7, 100, 'Redução para o ensaio.' );
		self::assertSame( 2, $second->revision );
		self::assertSame( 7, $second->actor_id );
		self::assertSame( 100, $second->occurred_at );
		self::assertSame( 'Redução para o ensaio.', $second->reason );
		self::assertSame( 1048576, $first->policy->toArray()['document']['max_bytes'] );
		self::assertSame( $second->toArray(), SettingsState::fromArray( $second->toArray() )->toArray() );
		$this->expectException( DomainException::class );
		$second->change( $second->policy, 1, 7, 101, 'Revisão antiga, mesmo com valores iguais.' );
	}

	public function testChangeRejectsMissingReasonInvalidActorAndBackdatedClock(): void {
		$first                           = SettingsState::initial();
		$values                          = $first->policy->toArray();
		$values['document']['max_bytes'] = 1024;
		$state                           = $first->change( UploadPolicy::fromArray( $values ), 1, 7, 100, 'Ensaio.' );
		foreach ( array( array( 0, 101, 'Ensaio.' ), array( 7, 99, 'Ensaio.' ), array( 7, 101, ' ' ), array( 7, 101, str_repeat( 'x', 1001 ) ), array( 7, 101, "invalido\x00" ), array( 7, 101, "\xff" ) ) as [$actor, $time, $reason] ) {
			try {
				$state->change( UploadPolicy::defaults(), 2, $actor, $time, $reason );
				self::fail( 'Invalid change accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	public function testManagementRequiresDedicatedInstitutionalGrantAndCurrentSession(): void {
		$good = $this->actor( Role::CmsAdministrator, Scope::institutional() );
		self::assertTrue( $good->allows( 100 ) );
		foreach ( Role::cases() as $role ) {
			if ( Role::CmsAdministrator !== $role ) {
				self::assertFalse( $this->actor( $role, Scope::institutional() )->allows( 100 ), $role->name );
			}
		}
		self::assertFalse( $this->actor( Role::CmsAdministrator, Scope::own() )->allows( 100 ) );
		self::assertFalse( $this->actor( Role::CmsAdministrator, Scope::units( array( 'LAB-A' ) ) )->allows( 100 ) );
		self::assertFalse( $this->actor( Role::CmsAdministrator, Scope::institutional(), AccountState::Suspended )->allows( 100 ) );
		self::assertFalse( $this->actor( Role::CmsAdministrator, Scope::institutional(), AccountState::Active, 100 )->allows( 100 ) );
		self::assertFalse( $this->actor( Role::CmsAdministrator, Scope::institutional(), AccountState::Active, 999, true )->allows( 100 ) );
		foreach ( array( array(), array( 'arn_authz_revision' => 1 ), array( 'arn_authz_user_id' => 8 ), array( 'expiration' => 100 ), array( 'expiration' => '999' ) ) as $invalid ) {
			$session = array() === $invalid ? array() : array_merge( $this->session(), $invalid );
			self::assertFalse( ( new SettingsActor( $good->snapshot, $session ) )->allows( 100 ) );
		}
	}

	private function actor( Role $role, Scope $scope, AccountState $state = AccountState::Active, int $until = 999, bool $revoked = false ): SettingsActor {
		return new SettingsActor( new AuthorizationSnapshot( 7, $state, 2, array( new StoredGrant( 1, new Grant( 7, $role, $scope, 0, $until, $revoked ) ) ) ), $this->session() );
	}

	private function session(): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 2,
			'expiration'         => 999,
		);
	}
}
