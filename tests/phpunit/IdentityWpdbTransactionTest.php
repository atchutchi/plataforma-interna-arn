<?php
/**
 * Guardas da ligação dedicada sem servidor SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityWpdb;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

/** Verifica o fecho da ligação dedicada sem servidor SQL. */
final class IdentityWpdbTransactionTest extends TestCase {

	/** A ligação dedicada nunca inicia uma reconexão automática. */
	public function testConnectionNeverReconnectsOrBails(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		self::assertFalse( $database->check_connection( true ) );
		self::assertFalse( $database->check_connection( false ) );
	}

	/** Estado sem ligação nunca é apresentado como seguro. */
	public function testStateRefusesWithoutDedicatedConnection(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Estado transaccional indisponível.' );
		$database->inTransaction();
	}

	/** O handle protegido não é exposto pelos métodos mágicos do wpdb. */
	public function testNativeHandleIsNotExposedByWpdbMagicAccess(): void {
		$database = new IdentityWpdb( '', '', '', '' );
		self::assertFalse( isset( $database->dbh ) );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( 'Acesso directo à ligação dedicada recusado.' );
		$database->dbh;
	}
}
