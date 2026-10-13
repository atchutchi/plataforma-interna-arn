<?php
/**
 * Delegação explícita limitada ao destinatário e ao pacote.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\Account;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Delegation;
use Arn\Intranet\Authorization\DelegationPolicy;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use PHPUnit\Framework\TestCase;

/** Impede promoção própria e delegações que ultrapassem qualquer limite. */
final class DelegationPolicyTest extends TestCase {

	/** Próprio diz respeito ao destinatário e não à conta administrativa. */
	public function testOwnDelegationIsRelativeToTheRecipient(): void {
		$delegation = $this->delegation( Scope::own() );
		self::assertTrue( $this->canGrant( array( $delegation ), Scope::own() ) );
		self::assertFalse( $this->canGrant( array( $delegation ), Scope::people( array( 1 ) ) ) );
		self::assertFalse( $this->canGrant( array( $delegation ), Scope::institutional() ) );
	}

	/** Uma só delegação tem de abranger o pacote e todos os alvos. */
	public function testDelegationRequiresWholeScopeAndAllowedRole(): void {
		$delegation = $this->delegation( Scope::units( array( 'U1', 'U2' ) ) );
		self::assertTrue( $this->canGrant( array( $delegation ), Scope::units( array( 'U1' ) ) ) );
		self::assertFalse( $this->canGrant( array( $delegation ), Scope::units( array( 'U1', 'U3' ) ) ) );
		self::assertFalse( $this->canGrant( array( $this->delegation( Scope::units( array( 'U1' ) ) ), $this->delegation( Scope::units( array( 'U2' ) ) ) ), Scope::units( array( 'U1', 'U2' ) ) ) );
		self::assertFalse( $this->canGrant( array( $delegation ), Scope::units( array( 'U1' ) ), 2, Role::HumanResources ) );
		self::assertTrue( $this->canGrant( array( $this->delegation( Scope::institutional() ) ), Scope::units( array( 'U1' ) ) ) );
	}

	/** Alvo, actor e pacotes administrativos têm procedimentos separados. */
	public function testUnknownTargetSelfPromotionAndAdministrativeRolesAreRejected(): void {
		$delegation = new Delegation( 1, array( 1, 2 ), array( Role::Employee, Role::AccessAdministrator, Role::TechnicalAdministrator ), Scope::institutional(), 0, null );
		foreach ( array( -1, 0, 1, 3 ) as $target ) {
			self::assertFalse( $this->canGrant( array( $delegation ), Scope::own(), $target ) );
		}
		foreach ( array( Role::AccessAdministrator, Role::TechnicalAdministrator ) as $role ) {
			self::assertFalse( $this->canGrant( array( $delegation ), Scope::own(), 2, $role ) );
		}
	}

	/** Toda a colecção é validada antes de conceder uma delegação. */
	public function testMalformedOrForeignDelegationsDenyTheWholeCollection(): void {
		$valid = $this->delegation( Scope::own() );
		foreach ( array( null, 'delegation', array(), new Delegation( 3, array( 2 ), array( Role::Employee ), Scope::own(), 0, null ) ) as $invalid ) {
			self::assertFalse( $this->canGrant( array( $valid, $invalid ), Scope::own() ) );
			self::assertFalse( $this->canGrant( array( $invalid, $valid ), Scope::own() ) );
		}
		self::assertFalse( $this->canGrant( array(), Scope::own() ) );
	}

	/** A delegação não substitui ManageGrants ou os gates da conta. */
	public function testDelegationRequiresAuthorizationToManageTheTarget(): void {
		$policy      = new DelegationPolicy();
		$delegations = array( $this->delegation( Scope::own() ) );
		$active      = new Account( 1, AccountState::Active, true );
		foreach ( array( array(), array( new Grant( 1, Role::Employee, Scope::institutional(), 0, null ) ), array( new Grant( 1, Role::AccessAdministrator, Scope::people( array( 3 ) ), 0, null ) ), array( new Grant( 1, Role::AccessAdministrator, Scope::institutional(), 0, null, true ) ), array( $this->grant(), null ), array( $this->grant(), new Grant( 3, Role::AccessAdministrator, Scope::institutional(), 0, null ) ) ) as $grants ) {
			self::assertFalse( $policy->canGrant( $active, $grants, $delegations, 2, Role::Employee, Scope::own(), 10, true ) );
		}
		foreach ( array( new Account( 1, AccountState::Pending, true ), new Account( 1, AccountState::Suspended, true ), new Account( 1, AccountState::Active, false ) ) as $actor ) {
			self::assertFalse( $policy->canGrant( $actor, array( $this->grant() ), $delegations, 2, Role::Employee, Scope::own(), 10, true ) );
		}
		self::assertFalse( $policy->canGrant( $active, array( $this->grant() ), $delegations, 2, Role::Employee, Scope::own(), 10, false ) );
		self::assertFalse( $policy->canGrant( $active, array( $this->grant() ), $delegations, 2, Role::Employee, Scope::own(), -1, true ) );
		self::assertTrue( $policy->canGrant( $active, array( new Grant( 1, Role::AccessAdministrator, Scope::people( array( 2 ) ), 0, null ) ), $delegations, 2, Role::Employee, Scope::own(), 10, true ) );
	}

	/** Os limites temporais também são obrigatórios para a delegação. */
	public function testDelegationLifetimeAndRevocation(): void {
		$policy = new DelegationPolicy();
		foreach ( array( false, true ) as $revoked ) {
			$delegation = new Delegation( 1, array( 2 ), array( Role::Employee ), Scope::own(), 10, 20, $revoked );
			foreach ( array(
				9  => false,
				10 => ! $revoked,
				19 => ! $revoked,
				20 => false,
			) as $now => $expected ) {
				self::assertSame( $expected, $policy->canGrant( new Account( 1, AccountState::Active, true ), array( $this->grant() ), array( $delegation ), 2, Role::Employee, Scope::own(), $now, true ) );
			}
		}
	}

	/**
	 * Avalia fixtures constantes de conta e concessão.
	 *
	 * @param array $delegations Delegações explícitas.
	 * @param Scope $scope      Âmbito pedido.
	 * @param int   $target     Conta destinatária.
	 * @param Role  $role       Pacote pedido.
	 * @return bool
	 */
	private function canGrant( array $delegations, Scope $scope, int $target = 2, Role $role = Role::Employee ): bool {
		return ( new DelegationPolicy() )->canGrant( new Account( 1, AccountState::Active, true ), array( $this->grant() ), $delegations, $target, $role, $scope, 10, true );
	}

	/**
	 * Constrói uma delegação coerente.
	 *
	 * @param Scope $scope Limite autorizado.
	 * @return Delegation
	 */
	private function delegation( Scope $scope ): Delegation {
		return new Delegation( 1, array( 2 ), array( Role::Employee ), $scope, 0, null );
	}

	/**
	 * Concessão explícita de gestão.
	 *
	 * @return Grant
	 */
	private function grant(): Grant {
		return new Grant( 1, Role::AccessAdministrator, Scope::institutional(), 0, null );
	}
}
