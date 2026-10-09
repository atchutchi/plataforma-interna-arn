<?php
/**
 * O catálogo documental respeita as regras das secções 4.2 e 4.6.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Organization\DocumentaryCatalog;
use Arn\Intranet\Organization\ParentStatus;
use Arn\Intranet\Organization\Unit;
use PHPUnit\Framework\TestCase;

/**
 * Contagem, DGE distintas, designações pendentes e relações por confirmar.
 */
final class DocumentaryCatalogTest extends TestCase {

	/**
	 * O anexo tem 37 entradas e a hierarquia constrói-se sem ciclos.
	 */
	public function testCatalogHasThirtySevenEntriesAndBuilds(): void {
		$hierarchy = DocumentaryCatalog::hierarchy();

		self::assertCount( 37, DocumentaryCatalog::units() );
		self::assertCount( 37, $hierarchy->all() );
	}

	/**
	 * As duas DGE conservam a sigla e têm identificadores distintos.
	 */
	public function testTwoDgeUnitsStayDistinct(): void {
		$hierarchy = DocumentaryCatalog::hierarchy();
		$dge       = $hierarchy->byAcronym( 'DGE' );

		self::assertCount( 2, $dge );
		self::assertSame( array( 'DRE-DGE', 'DREC-DGE' ), array_map( static fn( Unit $u ): string => $u->id, $dge ) );
		self::assertSame( 'DRE', $hierarchy->get( 'DRE-DGE' )->parent_id );
		self::assertSame( 'DREC', $hierarchy->get( 'DREC-DGE' )->parent_id );
	}

	/**
	 * DSU, DSGI e DSIC não recebem designação inventada.
	 */
	public function testUnnamedUnitsStayPending(): void {
		$hierarchy = DocumentaryCatalog::hierarchy();

		foreach ( array( 'DSU', 'DSGI', 'DSIC', 'ARQUIVO', 'RECEPCAO' ) as $id ) {
			self::assertTrue( $hierarchy->get( $id )->nameIsPending(), $id );
		}
	}

	/**
	 * As unidades sem superior expresso ficam pendentes, incluindo as que seguem DRH e NIC na lista.
	 */
	public function testUnitsWithoutExplicitParentArePending(): void {
		$hierarchy = DocumentaryCatalog::hierarchy();

		foreach ( array( 'SOS-DNS', 'SGND', 'ARQUIVO', 'RECEPCAO', 'FAU', 'CA', 'SEC-CA', 'CONS-CA', 'NIC' ) as $id ) {
			$unit = $hierarchy->get( $id );
			self::assertNull( $unit->parent_id, $id );
			self::assertSame( ParentStatus::Pendente, $unit->parent_status, $id );
		}
	}

	/**
	 * Nenhuma relação do catálogo é apresentada como confirmada.
	 */
	public function testNoRelationIsConfirmedYet(): void {
		foreach ( DocumentaryCatalog::units() as $unit ) {
			self::assertNotSame( ParentStatus::Confirmada, $unit->parent_status, $unit->id );
			self::assertNotSame( ParentStatus::Raiz, $unit->parent_status, $unit->id );
		}
	}

	/**
	 * O agrupamento da secção 4.6 está refletido como relação documental.
	 */
	public function testSectionFourSixGroupingIsDocumentary(): void {
		$hierarchy = DocumentaryCatalog::hierarchy();
		$expected  = array(
			'DCSI'   => array( 'DSU', 'DSGI', 'DSIC' ),
			'DF'     => array( 'DFC', 'DPL' ),
			'DRE'    => array( 'DRS', 'DRE-DGE', 'DFV' ),
			'DCT-QS' => array( 'DQS', 'DCT' ),
			'DMAO'   => array( 'DSAU', 'DMTC', 'DEEP' ),
			'DRH'    => array( 'DRB', 'DARHF' ),
			'DRAJDC' => array( 'DLR', 'DAJDC' ),
			'DREC'   => array( 'DREC-DGE', 'DCRP', 'DRIC' ),
		);

		foreach ( $expected as $parent => $children ) {
			$actual = array_map( static fn( Unit $u ): string => $u->id, $hierarchy->children( $parent ) );
			self::assertSame( $children, $actual, $parent );

			foreach ( $children as $child ) {
				self::assertSame( ParentStatus::Documental, $hierarchy->get( $child )->parent_status, $child );
			}
		}
	}
}
