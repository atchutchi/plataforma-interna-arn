<?php
/**
 * Fronteira transaccional da ligação dedicada, sem servidor SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

/**
 * Não substitui o ensaio MySQL/MariaDB real em tests/integration/mysql-transaction-state.php.
 */
final class IdentityWpdbTransactionTest extends TestCase {

	/**
	 * O transporte recusa reconexão automática.
	 */
	public function testConnectionNeverReconnectsOrBails(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		self::assertFalse( $database->check_connection( true ) );
		self::assertFalse( $database->check_connection( false ) );
	}

	/**
	 * Sem handle mysqli o estado transaccional recusa, nunca como ausência segura.
	 */
	public function testInTransactionRefusesWithoutDedicatedConnection(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Estado transaccional indisponível.' );
		$database->inTransaction();
	}
}
