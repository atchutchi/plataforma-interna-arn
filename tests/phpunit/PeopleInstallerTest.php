<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Installer boundary tests. */
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\People\LocalPeopleInstaller;
use PHPUnit\Framework\TestCase;

final class PeopleInstallerTest extends TestCase {
	public function test_installer_gate_requires_cli_admin_and_exact_flags(): void {
		self::assertTrue( LocalPeopleInstaller::eligible( 'local', true, true, true, true, true, true ) );
		self::assertFalse( LocalPeopleInstaller::eligible( 'local', true, true, true, true, false, true ) );
		self::assertFalse( LocalPeopleInstaller::eligible( 'local', true, true, true, true, true, false ) );
		self::assertFalse( LocalPeopleInstaller::eligible( 'development', true, true, true, true, true, true ) );
	}

	public function test_exclusive_prefix_shape_is_closed(): void {
		self::assertTrue( LocalPeopleInstaller::validPrefix( 'arn_peo_0123456789_' ) );
		self::assertFalse( LocalPeopleInstaller::validPrefix( 'wp_' ) );
	}
}
