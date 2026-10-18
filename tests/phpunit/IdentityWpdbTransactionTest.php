<?php
/**
 * Fronteira transaccional da ligação dedicada sem servidor SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

/** A integração real está em tests/integration/transaction-state-v2.php. */
final class IdentityWpdbTransactionTest extends TestCase {

	/** O transporte principal nunca reconecta automaticamente. */
	public function testConnectionNeverReconnectsOrBails(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		self::assertFalse( $database->check_connection( true ) );
		self::assertFalse( $database->check_connection( false ) );
	}

	/** Sem ligação dedicada o estado recusa, em vez de devolver false permissivo. */
	public function testInTransactionRefusesWithoutDedicatedConnection(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Estado transaccional indisponível.' );
		$database->inTransaction();
	}
}
