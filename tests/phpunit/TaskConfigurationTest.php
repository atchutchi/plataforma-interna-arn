<?php
/** Task gates and closed fictional roster.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Tasks\{Configuration, LocalRoster};
use Arn\Intranet\Authorization\Role;
use PHPUnit\Framework\TestCase;
use RuntimeException;

// phpcs:disable Squiz.Commenting -- Pure configuration matrix without WordPress sources.
final class TaskConfigurationTest extends TestCase {
	public function testClosedRosterCannotConsultNativeWordPressSources(): void {
		$this->expectException( RuntimeException::class );
		LocalRoster::resolve();
	}
	public function testGateRequiresAnIndependentBooleanAndLocalEnvironment(): void {
		self::assertTrue( Configuration::enabled( 'local', true, true, true, true ) );
		foreach ( array( false, null, 0, 1, 'true', array() ) as $value ) {
			for ( $index = 0; $index < 4; ++$index ) {
				$flags           = array( true, true, true, true );
				$flags[ $index ] = $value;
				self::assertFalse( Configuration::enabled( 'local', ...$flags ) );
			}
		}
		foreach ( array( 'development', 'staging', 'production', '' ) as $environment ) {
			self::assertFalse( Configuration::enabled( $environment, true, true, true, true ) );
		}
	}

	public function testRosterIsClosedAndDoesNotInheritEmployeeFromManager(): void {
		$definitions = LocalRoster::definitions();
		self::assertSame( array( 'a', 'b', 'manager' ), array_keys( $definitions ) );
		self::assertSame( 'f113.alfa.teste', $definitions['a']['login'] );
		self::assertSame( array( 'LAB-B' ), $definitions['b']['units'] );
		$roles = array_column( LocalRoster::grants( 'manager' ), 0 );
		self::assertSame( array( Role::Employee, Role::UnitManager ), $roles );
		self::assertSame( array( Role::Employee ), array_column( LocalRoster::grants( 'a' ), 0 ) );
		self::assertSame( 'arn_tsk_0123456789_a', LocalRoster::definitions( 'arn_tsk_0123456789_' )['a']['login'] );
		self::assertFalse( LocalRoster::validPrefix( 'wp_' ) );
		self::assertFalse( LocalRoster::validPrefix( 'arn_tsk_0123456789_;DROP' ) );
	}

	public function testRosterRejectsArbitraryLoginsUnitsAndPartialMaps(): void {
		$people = array_values( LocalRoster::definitions() );
		$valid  = array(
			9 => $people[2],
			7 => $people[0],
			8 => $people[1],
		);
		self::assertSame( array( 7, 8, 9 ), array_keys( LocalRoster::normalize( $valid, 'wp_' ) ) );
		$foreign                = $valid;
		$foreign[7]['login']    = 'f112.colaborador.a';
		$wrong_unit             = $valid;
		$wrong_unit[7]['units'] = array( 'LAB-B' );
		$duplicate              = $valid;
		$duplicate[8]           = $duplicate[7];
		foreach ( array(
			array_slice( $valid, 0, 2, true ),
			$foreign,
			$wrong_unit,
			$duplicate,
			array(
				'007' => $people[0],
				8     => $people[1],
				9     => $people[2],
			),
		) as $bad ) {
			try {
				LocalRoster::normalize( $bad, 'wp_' );
				self::fail( 'Elenco indevido aceite.' );
			} catch ( RuntimeException ) {
				self::assertTrue( true );
			}
		}
	}
}
