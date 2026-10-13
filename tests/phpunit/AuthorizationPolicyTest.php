<?php
/**
 * Decisões fechadas de autorização sem WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\Account;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use PHPUnit\Framework\TestCase;

/** Testa recusas de dados incompletos e de privilégios não concedidos. */
final class AuthorizationPolicyTest extends TestCase {

	/** Testa todos os pares função/acção contra o catálogo explícito. */
	public function testClosedRoleCatalogueHasNoImplicitInheritance(): void {
		$catalogue = array(
			'Employee'               => array( 'ReadContent', 'ReadDirectory', 'UpdateOwnProfile', 'ReadOwnTask', 'UpdateOwnTask', 'CreateNewsDraft', 'SubmitNews' ),
			'UnitManager'            => array( 'ReadDirectory', 'AssignUnitTask', 'ReadUnitTask', 'PublishTeamNotice' ),
			'HumanResources'         => array( 'ReadHrRecord', 'WriteHrRecord' ),
			'Editorial'              => array( 'ReviewNews', 'ApproveNews', 'PublishNews' ),
			'AccessAdministrator'    => array( 'ManageAccount', 'ManageGrants' ),
			'TechnicalAdministrator' => array(),
			'Leadership'             => array(),
			'Support'                => array(),
		);
		foreach ( Role::cases() as $role ) {
			foreach ( Action::cases() as $action ) {
				$owner = in_array( $action->name, array( 'ManageAccount', 'ManageGrants' ), true ) ? 2 : 1;
				self::assertSame(
					in_array( $action->name, $catalogue[ $role->name ], true ),
					( new AuthorizationPolicy() )->allows( new Account( 1, AccountState::Active, true ), $this->request( $action, Scope::institutional(), $owner ), array( $this->grant( $role, Scope::institutional() ) ), 10 ),
					$role->name . '/' . $action->name
				);
			}
		}
	}

	/** Estado, sessão e provas de servidor são gates obrigatórios. */
	public function testAccountAndRequestGatesDenyAccess(): void {
		$policy  = new AuthorizationPolicy();
		$request = $this->request( Action::ReadDirectory, Scope::institutional() );
		$grants  = array( $this->grant( Role::Employee, Scope::institutional() ) );
		foreach ( array( AccountState::Pending, AccountState::Suspended ) as $state ) {
			self::assertFalse( $policy->allows( new Account( 1, $state, true ), $request, $grants, 10 ) );
		}
		self::assertFalse( $policy->allows( new Account( 1, AccountState::Active, false ), $request, $grants, 10 ) );
		self::assertFalse( $policy->allows( new Account( 1, AccountState::Active, true ), $request, $grants, -1 ) );
		foreach ( array( array( false, true, true ), array( true, false, true ), array( true, true, false ) ) as $proofs ) {
			self::assertFalse( $policy->allows( new Account( 1, AccountState::Active, true ), new Request( Action::ReadDirectory, Scope::institutional(), 1, 2, $proofs[0], $proofs[1], $proofs[2], true ), $grants, 10 ) );
		}
		self::assertFalse( $policy->allows( new Account( 1, AccountState::Active, true ), $request, array(), 10 ) );
	}

	/** Um elemento inválido depois da concessão suficiente recusa a colecção. */
	public function testEntireGrantCollectionMustBelongToTheAccount(): void {
		$valid = $this->grant( Role::Employee, Scope::institutional() );
		foreach ( array( null, 'grant', array(), new Grant( 2, Role::Employee, Scope::institutional(), 0, null ) ) as $invalid ) {
			foreach ( array( array( $valid, $invalid ), array( $invalid, $valid ) ) as $grants ) {
				self::assertFalse( ( new AuthorizationPolicy() )->allows( new Account( 1, AccountState::Active, true ), $this->request( Action::ReadContent, Scope::institutional() ), $grants, 10 ) );
			}
		}
	}

	/** Uma operação multi-alvo precisa de uma só concessão suficiente. */
	public function testPartialGrantsCannotBeCombined(): void {
		$policy  = new AuthorizationPolicy();
		$account = new Account( 1, AccountState::Active, true );
		$request = $this->request( Action::ReadUnitTask, Scope::units( array( 'U1', 'U2' ) ) );
		self::assertFalse( $policy->allows( $account, $request, array( $this->grant( Role::UnitManager, Scope::units( array( 'U1' ) ) ), $this->grant( Role::UnitManager, Scope::units( array( 'U2' ) ) ) ), 10 ) );
		self::assertTrue( $policy->allows( $account, $request, array( $this->grant( Role::UnitManager, Scope::units( array( 'U1', 'U2' ) ) ) ), 10 ) );
		self::assertFalse( $policy->allows( $account, $this->request( Action::ReadHrRecord, Scope::people( array( 3 ) ) ), array( $this->grant( Role::HumanResources, Scope::people( array( 2 ) ) ) ), 10 ) );
	}

	/** Concessões revogadas ou fora da vigência não concedem acesso. */
	public function testGrantPeriodIsEnforcedByPolicy(): void {
		$policy  = new AuthorizationPolicy();
		$account = new Account( 1, AccountState::Active, true );
		$request = $this->request( Action::ReadContent, Scope::institutional() );
		$grant   = new Grant( 1, Role::Employee, Scope::institutional(), 10, 20 );
		foreach ( array(
			9  => false,
			10 => true,
			19 => true,
			20 => false,
		) as $now => $expected ) {
			self::assertSame( $expected, $policy->allows( $account, $request, array( $grant ), $now ) );
		}
		self::assertFalse( $policy->allows( $account, $request, array( new Grant( 1, Role::Employee, Scope::institutional(), 0, null, true ) ), 10 ) );
	}

	/** Institucional não autoriza acções próprias de um colega. */
	public function testOwnActionsAlwaysRequireTheActorAsOwner(): void {
		foreach ( array( Action::UpdateOwnProfile, Action::ReadOwnTask, Action::UpdateOwnTask, Action::CreateNewsDraft, Action::SubmitNews ) as $action ) {
			foreach ( array( Scope::own(), Scope::institutional() ) as $scope ) {
				foreach ( array(
					0 => false,
					1 => true,
					2 => false,
				) as $owner => $expected ) {
					self::assertSame( $expected, ( new AuthorizationPolicy() )->allows( new Account( 1, AccountState::Active, true ), $this->request( $action, $scope, $owner ), array( $this->grant( Role::Employee, Scope::institutional() ) ), 10 ) );
				}
			}
		}
	}

	/** Autor desconhecido, publicação própria e versão alterada são recusados. */
	public function testEditorialApprovalNeedsAnotherKnownAuthorAndCurrentVersion(): void {
		foreach ( array( Action::ApproveNews, Action::PublishNews ) as $action ) {
			foreach ( array( array( 0, true, false ), array( 1, true, false ), array( 2, false, false ), array( 2, true, true ) ) as $case ) {
				$request = new Request( $action, Scope::institutional(), 0, $case[0], true, true, true, $case[1] );
				self::assertSame( $case[2], ( new AuthorizationPolicy() )->allows( new Account( 1, AccountState::Active, true ), $request, array( $this->grant( Role::Editorial, Scope::institutional() ) ), 10 ) );
			}
		}
	}

	/** A gestão precisa de alvo conhecido diferente do actor. */
	public function testManagementRejectsUnknownTargetAndSelfManagement(): void {
		foreach ( array( Action::ManageAccount, Action::ManageGrants ) as $action ) {
			foreach ( array(
				0 => false,
				1 => false,
				2 => true,
			) as $owner => $expected ) {
				self::assertSame( $expected, ( new AuthorizationPolicy() )->allows( new Account( 1, AccountState::Active, true ), $this->request( $action, Scope::institutional(), $owner ), array( $this->grant( Role::AccessAdministrator, Scope::institutional() ) ), 10 ) );
			}
		}
	}

	/** As duas acções de leitura pública exigem audiência permitida. */
	public function testReadContentRequiresAudienceProof(): void {
		self::assertFalse( ( new AuthorizationPolicy() )->allows( new Account( 1, AccountState::Active, true ), new Request( Action::ReadContent, Scope::institutional(), 0, 0, true, true, false, true ), array( $this->grant( Role::Employee, Scope::institutional() ) ), 10 ) );
	}

	/**
	 * Pedido coerente reutilizado pelos cenários.
	 *
	 * @param Action $action Acção pedida.
	 * @param Scope  $scope  Âmbito requerido.
	 * @param int    $owner  Proprietário ou alvo.
	 * @return Request
	 */
	private function request( Action $action, Scope $scope, int $owner = 1 ): Request {
		return new Request( $action, $scope, $owner, 2, true, true, true, true );
	}

	/**
	 * Concessão da conta fictícia.
	 *
	 * @param Role  $role  Função explícita.
	 * @param Scope $scope Âmbito concedido.
	 * @return Grant
	 */
	private function grant( Role $role, Scope $scope ): Grant {
		return new Grant( 1, $role, $scope, 0, null );
	}
}
