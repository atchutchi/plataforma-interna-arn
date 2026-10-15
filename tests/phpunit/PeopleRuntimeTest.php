<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Runtime keeps disabled People sources closed. */
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\People\LocalPeopleDirectory;
use Arn\Intranet\People\PeopleQuery;
use Closure;
use PHPUnit\Framework\TestCase;

final class PeopleRuntimeTest extends TestCase {
	public function test_disabled_gate_never_opens_profile_factory(): void {
		$opened  = 0;
		$factory = static function () use ( &$opened ): never {
			++$opened;
			throw new \RuntimeException( 'Fonte aberta.' );
		};
		$runtime = new LocalPeopleDirectory( $factory, $factory, $factory );
		$query   = PeopleQuery::parse( array() );
		self::assertNotNull( $query );
		self::assertNull( $runtime->contactList( $query ) );
		self::assertNull( $runtime->own() );
		self::assertSame( 0, $opened );
	}
}
