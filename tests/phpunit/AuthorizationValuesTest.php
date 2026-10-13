<?php
/**
 * Invariantes e fronteiras dos valores de autorização.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\Account;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\Delegation;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Impede valores ambíguos, períodos inválidos e alargamentos de âmbito.
 */
final class AuthorizationValuesTest extends TestCase {

	/**
	 * Rejeita listas vazias, duplicadas, heterogéneas e identificadores inválidos.
	 *
	 * @param string $factory Fábrica do âmbito.
	 * @param array  $values  Valores rejeitados.
	 */
	#[DataProvider( 'invalidScopes' )]
	public function testScopeRejectsInvalidTargets( string $factory, array $values ): void {
		$this->expectException( InvalidArgumentException::class );
		Scope::$factory( $values );
	}

	/**
	 * Casos inválidos sem dependência das classes em teste.
	 *
	 * @return array
	 */
	public static function invalidScopes(): array {
		$cases = array();
		foreach ( array( 'units', 'queues', 'audiences' ) as $factory ) {
			foreach ( array( array(), array( '' ), array( 'a b' ), array( 'á' ), array( 'a/b' ), array( str_repeat( 'a', 65 ) ), array( 'A', 'A' ), array( 1 ), array( 'A', 2 ), array( null ), array( true ) ) as $index => $values ) {
				$cases[ $factory . $index ] = array( $factory, $values );
			}
		}
		foreach ( array( array(), array( 0 ), array( -1 ), array( '1' ), array( 1.0 ), array( true ), array( 1, 1 ), array( 1, '2' ), array( null ) ) as $index => $values ) {
			$cases[ 'people' . $index ] = array( 'people', $values );
		}
		return $cases;
	}

	/** Não combina âmbitos e compara identificadores de forma estrita. */
	public function testScopeCoverageRequiresTheWholeExactScope(): void {
		$scope = Scope::units( array( 'U-1', 'U_2', str_repeat( 'a', 64 ) ) );
		self::assertTrue( $scope->covers( Scope::units( array( 'U_2', 'U-1' ) ), 1, 0 ) );
		self::assertFalse( $scope->covers( Scope::units( array( 'U-1', 'U-3' ) ), 1, 0 ) );
		self::assertFalse( $scope->covers( Scope::units( array( 'u-1' ) ), 1, 0 ) );
		self::assertFalse( $scope->covers( Scope::queues( array( 'U-1' ) ), 1, 0 ) );
		self::assertFalse( $scope->covers( Scope::institutional(), 1, 1 ) );
		self::assertTrue( Scope::people( array( 1, 2 ) )->covers( Scope::people( array( 2 ) ), 1, 0 ) );
		self::assertTrue( Scope::queues( array( 'Q' ) )->covers( Scope::queues( array( 'Q' ) ), 1, 0 ) );
		self::assertTrue( Scope::audiences( array( 'A' ) )->covers( Scope::audiences( array( 'A' ) ), 1, 0 ) );
		self::assertTrue( Scope::institutional()->covers( Scope::institutional(), 1, 0 ) );
		self::assertTrue( Scope::institutional()->covers( $scope, 1, 0 ) );
	}

	/** Próprio exige um proprietário conhecido mesmo num âmbito institucional. */
	public function testOwnScopeRequiresKnownOwnership(): void {
		foreach ( array( Scope::own(), Scope::institutional() ) as $scope ) {
			self::assertTrue( $scope->covers( Scope::own(), 1, 1 ) );
			self::assertFalse( $scope->covers( Scope::own(), 1, 2 ) );
			self::assertFalse( $scope->covers( Scope::own(), 1, 0 ) );
			self::assertFalse( $scope->covers( Scope::own(), 0, 0 ) );
		}
		self::assertFalse( Scope::own()->covers( Scope::people( array( 1 ) ), 1, 1 ) );
	}

	/** Nenhum chamador pode alterar alvos ou pacotes através de referências externas. */
	public function testExternalArrayReferencesCannotMutateAuthorizationInputs(): void {
		$unit       = 'U1';
		$user_id    = 2;
		$role       = Role::Employee;
		$scope      = Scope::units( array( &$unit ) );
		$delegation = new Delegation( 1, array( &$user_id ), array( &$role ), Scope::own(), 0, null );
		$unit       = 'U2';
		$user_id    = 3;
		$role       = Role::Editorial;
		self::assertTrue( $scope->covers( Scope::units( array( 'U1' ) ), 1, 0 ) );
		self::assertFalse( $scope->covers( Scope::units( array( 'U2' ) ), 1, 0 ) );
		self::assertSame( array( 2 ), $delegation->manageable_user_ids );
		self::assertSame( array( Role::Employee ), $delegation->allowed_roles );
	}

	/** Âmbitos sem alvos rejeitam argumentos adicionais em vez de os ignorar. */
	public function testOwnScopeRejectsTargets(): void {
		$this->expectException( InvalidArgumentException::class );
		Scope::own( array( 2 ) );
	}

	/** Institucional não recebe uma lista de alvos disfarçada. */
	public function testInstitutionalScopeRejectsTargets(): void {
		$this->expectException( InvalidArgumentException::class );
		Scope::institutional( array( 'U1' ) );
	}

	/** O início é inclusivo e o fim é exclusivo nas duas vigências. */
	public function testValidityBoundariesAndRevocation(): void {
		foreach ( array( false, true ) as $revoked ) {
			$grant      = new Grant( 1, Role::Employee, Scope::own(), 10, 20, $revoked );
			$delegation = new Delegation( 1, array( 2 ), array( Role::Employee ), Scope::own(), 10, 20, $revoked );
			foreach ( array(
				-1 => false,
				9  => false,
				10 => ! $revoked,
				19 => ! $revoked,
				20 => false,
				21 => false,
			) as $now => $expected ) {
				self::assertSame( $expected, $grant->validAt( $now ) );
				self::assertSame( $expected, $delegation->validAt( $now ) );
			}
		}
		self::assertTrue( ( new Grant( 1, Role::Employee, Scope::own(), 0, null ) )->validAt( PHP_INT_MAX ) );
		self::assertTrue( ( new Delegation( 1, array( 2 ), array( Role::Employee ), Scope::own(), 0, null ) )->validAt( PHP_INT_MAX ) );
	}

	/**
	 * Rejeita IDs inválidos, relógios incoerentes e listas de delegação mal tipadas.
	 *
	 * @param string $scenario Invariante a violar.
	 */
	#[DataProvider( 'invalidValues' )]
	public function testValuesRejectInvalidConstruction( string $scenario ): void {
		$this->expectException( InvalidArgumentException::class );
		switch ( $scenario ) {
			case 'account':
				new Account( 0, AccountState::Active, true );
				break;
			case 'account-negative':
				new Account( -1, AccountState::Active, true );
				break;
			case 'grant-user':
				new Grant( 0, Role::Employee, Scope::own(), 0, null );
				break;
			case 'grant-start':
				new Grant( 1, Role::Employee, Scope::own(), -1, null );
				break;
			case 'grant-end':
				new Grant( 1, Role::Employee, Scope::own(), 10, 10 );
				break;
			case 'grant-end-before':
				new Grant( 1, Role::Employee, Scope::own(), 10, 9 );
				break;
			case 'request-owner':
				new Request( Action::ReadContent, Scope::own(), -1, 0, true, true, true, true );
				break;
			case 'request-author':
				new Request( Action::ReadContent, Scope::own(), 0, -1, true, true, true, true );
				break;
			default:
				$arguments               = array( 1, array( 2 ), array( Role::Employee ), Scope::own(), 0, null );
				$changes                 = array(
					'delegation-actor'            => array( 0, 0 ),
					'delegation-target-empty'     => array( 1, array() ),
					'delegation-target-zero'      => array( 1, array( 0 ) ),
					'delegation-target-string'    => array( 1, array( '2' ) ),
					'delegation-target-duplicate' => array( 1, array( 2, 2 ) ),
					'delegation-target-mixed'     => array( 1, array( 2, '3' ) ),
					'delegation-role-empty'       => array( 2, array() ),
					'delegation-role-string'      => array( 2, array( 'Employee' ) ),
					'delegation-role-duplicate'   => array( 2, array( Role::Employee, Role::Employee ) ),
					'delegation-role-mixed'       => array( 2, array( Role::Employee, null ) ),
					'delegation-start'            => array( 4, -1 ),
					'delegation-end'              => array( 5, 0 ),
					'delegation-end-before'       => array( 5, -1 ),
				);
				$change                  = $changes[ $scenario ];
				$arguments[ $change[0] ] = $change[1];
				new Delegation( ...$arguments );
		}
	}

	/**
	 * Identificadores dos casos inválidos.
	 *
	 * @return array
	 */
	public static function invalidValues(): array {
		return array_map(
			static fn ( string $scenario ): array => array( $scenario ),
			array( 'account', 'account-negative', 'grant-user', 'grant-start', 'grant-end', 'grant-end-before', 'request-owner', 'request-author', 'delegation-actor', 'delegation-target-empty', 'delegation-target-zero', 'delegation-target-string', 'delegation-target-duplicate', 'delegation-target-mixed', 'delegation-role-empty', 'delegation-role-string', 'delegation-role-duplicate', 'delegation-role-mixed', 'delegation-start', 'delegation-end', 'delegation-end-before' )
		);
	}
}
