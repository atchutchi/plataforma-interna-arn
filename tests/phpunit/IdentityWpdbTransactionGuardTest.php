<?php
/**
 * Contrato puro da guarda transaccional da ligação dedicada.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityWpdb;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

/**
 * Verifica o contrato mínimo da ligação dedicada sem base de dados real.
 */
final class IdentityWpdbTransactionGuardTest extends TestCase {

	/**
	 * A ligação dedicada nunca tenta reconectar nem termina o processo.
	 */
	public function testConnectionNeverReconnectsOrBails(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		self::assertFalse( $database->check_connection( true ) );
		self::assertFalse( $database->check_connection( false ) );
	}

	/**
	 * A API transaccional pertence à própria ligação dedicada.
	 */
	public function testTransactionApiBelongsToDedicatedConnection(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		self::assertTrue( method_exists( $database, 'inTransaction' ) );
		self::assertSame( IdentityWpdb::class, ( new ReflectionMethod( $database, 'query' ) )->getDeclaringClass()->getName() );
	}
}
