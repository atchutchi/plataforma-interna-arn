<?php
/** Task authority refuses before native or database sources when disabled.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Tasks\{CurrentActor, LocalRoster};
use LogicException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

/** Negative-only double. It cannot prove locking or SQL authority. */
final class TaskActorGateTest extends TestCase {

	/** No database query or identity resolver is needed for a closed module.
	 *
	 * @throws LogicException If the protected identity source is reached.
	 */
	public function testClosedGateDoesNotResolveIdentityOrQueryTheDatabase(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		$rows     = array_values( LocalRoster::definitions() );
		$actors   = new CurrentActor(
			$database,
			array(
				7 => $rows[0],
				8 => $rows[1],
				9 => $rows[2],
			),
			static fn(): int => 100,
			static fn() => throw new LogicException( 'A fonte não deve ser chamada.' )
		);
		self::assertNull( $actors->read() );
		self::assertNull( $actors->currentId() );
		self::assertSame( array(), $database->queries );
		self::assertSame( $database, $actors->connection() );
	}

	/** Constructor validation cannot quietly accept an unrelated source map. */
	public function testIncompleteRosterIsRefusedBeforeSources(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		$this->expectException( RuntimeException::class );
		new CurrentActor( $database, array(), static fn(): int => 100 );
	}
}
