<?php
/**
 * Fronteira transaccional pura, sem servidor SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\TransactionBoundary;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Verifica a máquina de estados sem WordPress nem servidor SQL. */
final class TransactionBoundaryTest extends TestCase {

	/** START, COMMIT e ROLLBACK usam formas canónicas sem chain. */
	public function testLifecycleUsesCanonicalNonChainedBoundaries(): void {
		$boundary = new TransactionBoundary();
		$this->expectUnavailable( $boundary );

		$boundary->markReady();
		self::assertFalse( $boundary->inTransaction() );
		self::assertSame( 'START TRANSACTION', $boundary->prepare( 'BEGIN WORK' ) );
		$boundary->confirm( true );
		self::assertTrue( $boundary->inTransaction() );
		self::assertSame( 'COMMIT AND NO CHAIN NO RELEASE', $boundary->prepare( 'COMMIT' ) );
		$boundary->confirm( true );
		self::assertFalse( $boundary->inTransaction() );
	}

	/** Comandos inseguros são recusados sem perder a transacção activa. */
	public function testUnsafeBoundariesAreRejectedWithoutChangingActiveState(): void {
		$boundary = $this->activeBoundary();

		foreach (
			array(
				'START TRANSACTION',
				'COMMIT AND CHAIN',
				'SET autocommit=1',
				'CREATE TABLE x (id INT)',
				'CREATE TEMPORARY TABLE x (id INT)',
				'CALL unsafe_procedure()',
				'/*!80000 CREATE TABLE x (id INT) */',
				'SELECT 1 /*!80000; DROP TABLE x */',
			) as $sql
		) {
			$this->expectDenied( static fn() => $boundary->prepare( $sql ) );
			self::assertTrue( $boundary->inTransaction() );
		}
	}

	/** Operações indirectas também são recusadas no estado ocioso. */
	public function testUnknownAndIndirectTransactionCommandsAreRejectedWhileIdle(): void {
		$boundary = new TransactionBoundary();
		$boundary->markReady();

		foreach (
			array(
				'CALL unsafe_procedure()',
				"PREPARE stmt FROM 'START TRANSACTION'",
				'EXECUTE stmt',
				'DEALLOCATE PREPARE stmt',
				'SET LOCAL autocommit := 0',
				'SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE',
			) as $sql
		) {
			$this->expectDenied( static fn() => $boundary->prepare( $sql ) );
			self::assertFalse( $boundary->inTransaction() );
		}
	}

	/** DML e savepoints permanecem na mesma transacção controlada. */
	public function testSavepointsAndOrdinaryDmlRemainInsideTheTransaction(): void {
		$boundary = $this->activeBoundary();

		self::assertSame( 'INSERT INTO x VALUES (1)', $boundary->prepare( 'INSERT INTO x VALUES (1)' ) );
		$boundary->confirm( true );
		self::assertSame( 'SAVEPOINT arn_probe', $boundary->prepare( 'SAVEPOINT arn_probe' ) );
		$boundary->confirm( true );
		self::assertSame( 'ROLLBACK TO SAVEPOINT arn_probe', $boundary->prepare( 'ROLLBACK TO SAVEPOINT arn_probe' ) );
		$boundary->confirm( true );
		self::assertSame( 'RELEASE SAVEPOINT arn_probe', $boundary->prepare( 'RELEASE SAVEPOINT arn_probe' ) );
		$boundary->confirm( true );
		self::assertTrue( $boundary->inTransaction() );
	}

	/** Uma falha de motor exige rollback confirmado. */
	public function testFailureRequiresSuccessfulRollback(): void {
		$boundary = $this->activeBoundary();
		$boundary->prepare( 'UPDATE x SET id = 2' );
		$boundary->confirm( false );

		$this->expectUnavailable( $boundary );
		$this->expectDenied( static fn() => $boundary->prepare( 'COMMIT' ) );
		self::assertSame( 'ROLLBACK AND NO CHAIN NO RELEASE', $boundary->prepare( 'ROLLBACK' ) );
		$boundary->confirm( false );
		$this->expectUnavailable( $boundary );
		$this->expectDenied( static fn() => $boundary->prepare( 'SELECT 1' ) );

		$boundary->markReady();
		self::assertFalse( $boundary->inTransaction() );
	}

	/** Perda de ligação invalida definitivamente o estado observado. */
	public function testConnectionLossCannotBeRecoveredAsAUsableTransaction(): void {
		$boundary = $this->activeBoundary();
		$boundary->prepare( 'DELETE FROM x' );
		$boundary->confirm( false, true );

		$this->expectUnavailable( $boundary );
		$this->expectDenied( static fn() => $boundary->prepare( 'ROLLBACK' ) );
	}

	/** Leituras auxiliares do wpdb não substituem a operação pendente. */
	public function testNestedWpdbPreparationAcceptsOnlyReads(): void {
		$boundary = new TransactionBoundary();
		$boundary->markReady();
		$boundary->prepare( 'INSERT INTO x VALUES (2)' );

		self::assertSame( 'SHOW FULL COLUMNS FROM x', $boundary->prepareNestedRead( 'SHOW FULL COLUMNS FROM x' ) );
		$this->expectDenied( static fn() => $boundary->prepareNestedRead( 'UPDATE x SET id = 4' ) );
		$boundary->confirm( true );
		self::assertFalse( $boundary->inTransaction() );
	}

	/** O parser recusa múltiplas instruções e preserva literais. */
	public function testParserRejectsMultipleStatementsButPreservesQuotedSeparators(): void {
		$boundary = new TransactionBoundary();
		$boundary->markReady();

		$this->expectDenied( static fn() => $boundary->prepare( 'SELECT 1; DROP TABLE x' ) );
		self::assertSame( "SELECT 'a;b'", $boundary->prepare( "SELECT 'a;b'" ) );
		$boundary->confirm( true );
		self::assertSame( 'SELECT 1', $boundary->prepare( "-- leitura\nSELECT 1" ) );
		$boundary->confirm( true );
	}

	/** @return TransactionBoundary Fronteira com uma transacção activa. */
	private function activeBoundary(): TransactionBoundary {
		$boundary = new TransactionBoundary();
		$boundary->markReady();
		$boundary->prepare( 'START TRANSACTION' );
		$boundary->confirm( true );
		return $boundary;
	}

	/** @param TransactionBoundary $boundary Fronteira esperada como insegura. */
	private function expectUnavailable( TransactionBoundary $boundary ): void {
		try {
			$boundary->inTransaction();
			self::fail( 'O estado inseguro foi apresentado como utilizável.' );
		} catch ( RuntimeException ) {
			self::assertTrue( true );
		}
	}

	/** @param callable $operation Operação que deve lançar RuntimeException. */
	private function expectDenied( callable $operation ): void {
		try {
			$operation();
			self::fail( 'A operação insegura não foi recusada.' );
		} catch ( RuntimeException ) {
			self::assertTrue( true );
		}
	}
}
