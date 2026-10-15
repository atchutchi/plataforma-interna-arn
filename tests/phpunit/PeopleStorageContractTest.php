<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** People persistence contract. */
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\People\PeopleConfiguration;
use Arn\Intranet\People\LocalPeopleRoster;
use PHPUnit\Framework\TestCase;

final class PeopleStorageContractTest extends TestCase {
	public function test_all_four_flags_must_be_exactly_true(): void {
		self::assertTrue( PeopleConfiguration::enabled( 'local', true, true, true, true ) );
		foreach ( array( false, 1, 'true', null ) as $invalid ) {
			self::assertFalse( PeopleConfiguration::enabled( 'local', $invalid, true, true, true ) );
			self::assertFalse( PeopleConfiguration::enabled( 'local', true, $invalid, true, true ) );
			self::assertFalse( PeopleConfiguration::enabled( 'local', true, true, $invalid, true ) );
			self::assertFalse( PeopleConfiguration::enabled( 'local', true, true, true, $invalid ) );
		}
		self::assertFalse( PeopleConfiguration::enabled( 'production', true, true, true, true ) );
	}

	public function test_roster_is_closed_and_exclusive_names_are_derived(): void {
		self::assertSame( array( 'f107.alfa.teste', 'f107.beta.teste', 'f107.responsavel.a.teste' ), array_values( LocalPeopleRoster::logins() ) );
		self::assertSame( 'arn_peo_0123456789_alfa', LocalPeopleRoster::logins( 'arn_peo_0123456789_' )['alfa'] );
		self::assertFalse( LocalPeopleRoster::validPrefix( 'wp_' ) );
	}
}
