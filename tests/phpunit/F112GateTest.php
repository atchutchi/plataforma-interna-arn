<?php
/**
 * Independent local switches.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Closed switch matrix.
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\F112\Configuration;
use PHPUnit\Framework\TestCase;

final class F112GateTest extends TestCase {
	public function test_each_module_has_its_own_explicit_boolean_before_any_source(): void {
		self::assertTrue( Configuration::enabled( 'local', true, true, true, true ) );
		foreach ( array( false, null, 'true', 1, 0, array() ) as $value ) {
			for ( $slot = 0; $slot < 4; ++$slot ) {
				$flags          = array( true, true, true, true );
				$flags[ $slot ] = $value;
				self::assertFalse( Configuration::enabled( 'local', ...$flags ) );
			}
		}
		foreach ( array( 'development', 'staging', 'production', '' ) as $environment ) {
			self::assertFalse( Configuration::enabled( $environment, true, true, true, true ) );
		}
		self::assertFalse( Configuration::current( 'unknown' ) );
	}
}
