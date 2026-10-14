<?php
/** Ensaios das unidades sintéticas.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Organization\Storage\LocalUnitScenarios;
use Arn\Intranet\Organization\Storage\LocalUnitSnapshot;
use PHPUnit\Framework\TestCase;

/** Ensaios de unidades locais. */
final class LocalUnitScenariosTest extends TestCase {
	/**
	 * Verifica a identidade e a relação após várias operações.
	 */
	public function testRoundtripPreservesIdentityAndHistory(): void {
		$units = LocalUnitScenarios::apply( 'initialize', array() );
		self::assertCount( 3, $units );
		$units    = LocalUnitScenarios::apply( 'rename-a', $units );
		$units    = LocalUnitScenarios::apply( 'move-b-to-a', $units );
		$units    = LocalUnitScenarios::apply( 'deactivate-b', $units );
		$snapshot = LocalUnitSnapshot::decode( 4, LocalUnitSnapshot::encode( $units ) );
		self::assertSame( 4, $snapshot->revision );
		self::assertSame( 'LAB-A', $snapshot->units[2]->parent_id );
		self::assertFalse( $snapshot->units[2]->active );
	}
	/**
	 * Verifica a recusa de uma operação sem alteração.
	 */
	public function testNoChangeIsRejected(): void {
		$this->expectException( RuntimeException::class );
		LocalUnitScenarios::apply( 'move-b-to-root', LocalUnitScenarios::apply( 'initialize', array() ) );
	}
	/**
	 * Verifica a recusa de unidades fora do catálogo fictício.
	 */
	public function testUnknownStoredUnitIsRejected(): void {
		$this->expectException( RuntimeException::class );
		LocalUnitSnapshot::decode( 0, '[{"id":"DCSI"}]' );
	}
	/**
	 * Verifica a recusa de cenários desconhecidos.
	 */
	public function testUnknownScenarioIsRejected(): void {
		$this->expectException( RuntimeException::class );
		LocalUnitScenarios::apply( 'custom', array() );
	}
}
