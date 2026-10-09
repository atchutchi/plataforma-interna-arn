<?php
/**
 * Integridade da hierarquia de unidades, com dados fictícios.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Organization\HierarchyException;
use Arn\Intranet\Organization\ParentStatus;
use Arn\Intranet\Organization\Unit;
use Arn\Intranet\Organization\UnitHierarchy;
use Arn\Intranet\Organization\UnitType;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Identificadores estáveis, siglas repetidas, ciclos e desativação.
 */
final class UnitHierarchyTest extends TestCase {

	/**
	 * Hierarquia fictícia: uma direção com dois departamentos e uma unidade pendente.
	 */
	private function fixture(): UnitHierarchy {
		return UnitHierarchy::fromList(
			array(
				new Unit( 'DIR-ALFA', 'DA', 'Direção Alfa', UnitType::Direcao, null, ParentStatus::Raiz ),
				new Unit( 'DIR-ALFA-DEP1', 'DEP', 'Departamento Um', UnitType::Departamento, 'DIR-ALFA', ParentStatus::Confirmada ),
				new Unit( 'DIR-ALFA-DEP2', 'DEP', 'Departamento Dois', UnitType::Departamento, 'DIR-ALFA', ParentStatus::Documental ),
				new Unit( 'SERV-SOLTO', 'SS', null, UnitType::Servico, null, ParentStatus::Pendente ),
			)
		);
	}

	/**
	 * A sigla repete-se e os identificadores continuam distintos.
	 */
	public function testAcronymIsNotAUniqueKey(): void {
		$hierarchy = $this->fixture();
		$same      = $hierarchy->byAcronym( 'DEP' );

		self::assertCount( 2, $same );
		self::assertSame( array( 'DIR-ALFA-DEP1', 'DIR-ALFA-DEP2' ), array_map( static fn( Unit $u ): string => $u->id, $same ) );
	}

	/**
	 * Um identificador repetido é recusado.
	 */
	public function testDuplicateIdIsRejected(): void {
		$hierarchy = $this->fixture();

		$this->expectException( HierarchyException::class );
		$hierarchy->add( new Unit( 'DIR-ALFA', 'OUTRA', 'Outra', UnitType::Direcao, null, ParentStatus::Raiz ) );
	}

	/**
	 * Um superior inexistente é recusado, na construção e na adição.
	 */
	public function testUnknownParentIsRejected(): void {
		$hierarchy = $this->fixture();

		$this->expectException( HierarchyException::class );
		$hierarchy->add( new Unit( 'NOVA', 'N', 'Nova', UnitType::Departamento, 'NAO-EXISTE', ParentStatus::Documental ) );
	}

	/**
	 * Mover a direção para debaixo do próprio departamento cria ciclo.
	 */
	public function testReparentingThatCreatesCycleIsRejected(): void {
		$hierarchy = $this->fixture();

		$this->expectException( HierarchyException::class );
		$this->expectExceptionMessage( 'ciclo' );
		$hierarchy->reparent( 'DIR-ALFA', 'DIR-ALFA-DEP1', ParentStatus::Confirmada );
	}

	/**
	 * Um ciclo presente na lista inicial é detetado independentemente da ordem.
	 */
	public function testCycleInInitialListIsRejected(): void {
		$this->expectException( HierarchyException::class );

		UnitHierarchy::fromList(
			array(
				new Unit( 'A', 'A', 'A', UnitType::Outro, 'C', ParentStatus::Documental ),
				new Unit( 'B', 'B', 'B', UnitType::Outro, 'A', ParentStatus::Documental ),
				new Unit( 'C', 'C', 'C', UnitType::Outro, 'B', ParentStatus::Documental ),
			)
		);
	}

	/**
	 * Uma unidade não pode ser superior de si própria.
	 */
	public function testSelfParentIsRejected(): void {
		$this->expectException( InvalidArgumentException::class );
		new Unit( 'X', 'X', 'X', UnitType::Outro, 'X', ParentStatus::Documental );
	}

	/**
	 * Renomear não altera o identificador nem as relações.
	 */
	public function testRenameKeepsIdAndRelations(): void {
		$hierarchy = $this->fixture();
		$hierarchy->rename( 'DIR-ALFA-DEP1', 'Departamento Um Renomeado' );

		$unit = $hierarchy->get( 'DIR-ALFA-DEP1' );

		self::assertSame( 'Departamento Um Renomeado', $unit->name );
		self::assertSame( 'DIR-ALFA', $unit->parent_id );
		self::assertCount( 2, $hierarchy->children( 'DIR-ALFA' ) );
	}

	/**
	 * Relações documentais e pendentes não são posições definitivas.
	 */
	public function testPendingPlacementsAreReported(): void {
		$hierarchy = $this->fixture();
		$pending   = array_map( static fn( Unit $u ): string => $u->id, $hierarchy->pendingPlacements() );

		self::assertSame( array( 'DIR-ALFA-DEP2', 'SERV-SOLTO' ), $pending );
	}

	/**
	 * Os superiores devolvem-se do mais próximo para a raiz.
	 */
	public function testAncestors(): void {
		$hierarchy = $this->fixture();
		$hierarchy->add( new Unit( 'SUB', 'SUB', 'Subunidade', UnitType::Servico, 'DIR-ALFA-DEP1', ParentStatus::Confirmada ) );

		$ids = array_map( static fn( Unit $u ): string => $u->id, $hierarchy->ancestors( 'SUB' ) );

		self::assertSame( array( 'DIR-ALFA-DEP1', 'DIR-ALFA' ), $ids );
	}

	/**
	 * Desativar conserva o registo e exige subordinadas resolvidas.
	 */
	public function testDeactivationKeepsHistoryAndRequiresResolvedChildren(): void {
		$hierarchy = $this->fixture();

		try {
			$hierarchy->deactivate( 'DIR-ALFA' );
			self::fail( 'A direção tem subordinadas ativas.' );
		} catch ( HierarchyException $exception ) {
			self::assertStringContainsString( 'DIR-ALFA-DEP1', $exception->getMessage() );
		}

		$hierarchy->deactivate( 'DIR-ALFA-DEP1' );
		$hierarchy->deactivate( 'DIR-ALFA-DEP2' );
		$hierarchy->deactivate( 'DIR-ALFA' );

		self::assertFalse( $hierarchy->get( 'DIR-ALFA' )->active );
		self::assertTrue( $hierarchy->has( 'DIR-ALFA' ) );
		self::assertCount( 2, $hierarchy->children( 'DIR-ALFA' ) );
	}

	/**
	 * Estado e presença de superior têm de ser coerentes.
	 */
	public function testParentStatusMustMatchParentPresence(): void {
		$this->expectException( InvalidArgumentException::class );
		new Unit( 'Y', 'Y', 'Y', UnitType::Outro, null, ParentStatus::Confirmada );
	}
}
