<?php
/** Exclusão e manutenção persistente em directório privado descartável.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\Institutional\MaintenanceGate;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
final class InstitutionalMaintenanceTest extends TestCase {
	public function testReceiptsPreserveHistoryAndRefuseCorruptOrForeignRecords(): void {
		$dir = sys_get_temp_dir() . '/arn-receipt-' . bin2hex( random_bytes( 8 ) );
		mkdir( $dir, 0700 );
		$gate = new MaintenanceGate( $dir, __DIR__, true );
		try {
			$gate->exclusive();
			$receipts = new \Arn\Intranet\Identity\Institutional\MigrationReceipts( $gate, hash( 'sha256', 'synthetic' ) );
			$receipts->append( 'started', 1, 'synthetic-backup' );
			$first      = file_get_contents( $dir . '/receipt-00000001.json' );
			$incomplete = json_decode( $first, true );
			unset( $incomplete['backup_ref'] );
			file_put_contents( $dir . '/receipt-00000001.json', json_encode( $incomplete ) );
			$refused = false;
			try {
				$receipts->all();
			} catch ( \RuntimeException ) {
				$refused = true;
			}
			self::assertTrue( $refused, 'Recibo incompleto deve ser recusado.' );
			file_put_contents( $dir . '/receipt-00000001.json', $first );
			file_put_contents( $dir . '/pending-interrupted', '{' );
			$receipts->append( 'complete', 1, 'synthetic-backup' );
			self::assertCount( 2, $receipts->all() );
			self::assertSame( $first, file_get_contents( $dir . '/receipt-00000001.json' ) );
			file_put_contents( $dir . '/receipt-00000002.json', '{' );
			$this->expectException( \JsonException::class );
			$receipts->all();
		} finally {
			$gate->close();
			foreach ( glob( $dir . '/*' ) as $file ) {
				unlink( $file ); }
			rmdir( $dir );
		}
	}
	public function testReadersDrainAndInterruptedMaintenanceRemainsClosed(): void {
		$dir = sys_get_temp_dir() . '/arn-maint-' . bin2hex( random_bytes( 8 ) );
		mkdir( $dir, 0700 );
		$reader   = null;
		$operator = null;
		$resume   = null;
		try {
			$reader   = new MaintenanceGate( $dir, __DIR__, false );
			$operator = new MaintenanceGate( $dir, __DIR__, true );
			$refused  = false;
			try {
				$operator->exclusive();
			} catch ( \RuntimeException ) {
				$refused = true; }
			self::assertTrue( $refused, 'Leitor activo deve impedir DDL.' );
			self::assertFileExists( $dir . '/maintenance' );
			$reader->close();
			$operator->close();
			$refused = false;
			try {
				new MaintenanceGate( $dir, __DIR__, false );
			} catch ( \RuntimeException ) {
				$refused = true; }
			self::assertTrue( $refused, 'Manutenção deve persistir.' );
			$resume = new MaintenanceGate( $dir, __DIR__, true );
			$resume->exclusive();
			$resume->assertExclusive();
			$resume->releaseMaintenance();
			self::assertFileDoesNotExist( $dir . '/maintenance' );
		} finally {
			$reader?->close();
			$operator?->close();
			$resume?->close();
			foreach ( glob( $dir . '/*' ) as $file ) {
				unlink( $file ); }
			rmdir( $dir );
		}
	}
}
