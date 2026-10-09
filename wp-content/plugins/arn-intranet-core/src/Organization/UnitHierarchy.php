<?php
/**
 * Hierarquia de unidades em memória.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Organization;

/**
 * Garante identificadores únicos, superiores existentes e ausência de ciclos.
 */
final class UnitHierarchy {

	/**
	 * Unidades por identificador.
	 *
	 * @var array<string, Unit>
	 */
	private array $units = array();

	/**
	 * Constrói a hierarquia a partir de uma lista, independentemente da ordem.
	 *
	 * @param Unit[] $units Unidades.
	 *
	 * @throws HierarchyException Em identificador repetido, superior inválido ou ciclo.
	 */
	public static function fromList( array $units ): self {
		$hierarchy = new self();

		foreach ( $units as $unit ) {
			if ( isset( $hierarchy->units[ $unit->id ] ) ) {
				throw HierarchyException::duplicateId( $unit->id );
			}

			$hierarchy->units[ $unit->id ] = $unit;
		}

		foreach ( $hierarchy->units as $unit ) {
			$hierarchy->assertParentIsValid( $unit );
			$hierarchy->assertNoCycle( $unit->id, $unit->parent_id );
		}

		return $hierarchy;
	}

	/**
	 * Acrescenta uma unidade.
	 *
	 * @param Unit $unit Unidade.
	 *
	 * @throws HierarchyException Em identificador repetido ou superior inválido.
	 */
	public function add( Unit $unit ): void {
		if ( isset( $this->units[ $unit->id ] ) ) {
			throw HierarchyException::duplicateId( $unit->id );
		}

		$this->assertParentIsValid( $unit );
		$this->units[ $unit->id ] = $unit;
	}

	/**
	 * Altera o superior de uma unidade mantendo o identificador.
	 *
	 * @param string       $id        Unidade.
	 * @param string|null  $parent_id Novo superior.
	 * @param ParentStatus $status    Estado da relação.
	 *
	 * @throws HierarchyException Se a unidade não existir, o superior for inválido ou se criar ciclo.
	 */
	public function reparent( string $id, ?string $parent_id, ParentStatus $status ): void {
		$unit = $this->get( $id );

		$this->assertNoCycle( $id, $parent_id );
		$updated = $unit->withParent( $parent_id, $status );
		$this->assertParentIsValid( $updated );
		$this->units[ $id ] = $updated;
	}

	/**
	 * Altera a designação sem alterar o identificador nem as relações.
	 *
	 * @param string $id   Unidade.
	 * @param string $name Nova designação.
	 */
	public function rename( string $id, string $name ): void {
		$unit = $this->get( $id );

		$this->units[ $id ] = new Unit( $unit->id, $unit->acronym, $name, $unit->type, $unit->parent_id, $unit->parent_status, $unit->active );
	}

	/**
	 * Desativa uma unidade conservando o registo.
	 *
	 * @param string $id Unidade.
	 *
	 * @throws HierarchyException Se existirem subordinadas ativas.
	 */
	public function deactivate( string $id ): void {
		$unit = $this->get( $id );

		$active_children = array_map(
			static fn( Unit $child ): string => $child->id,
			array_filter( $this->children( $id ), static fn( Unit $child ): bool => $child->active )
		);

		if ( array() !== $active_children ) {
			throw HierarchyException::activeChildren( $id, array_values( $active_children ) );
		}

		$this->units[ $id ] = $unit->deactivated();
	}

	/**
	 * Devolve a unidade.
	 *
	 * @param string $id Identificador.
	 *
	 * @throws HierarchyException Se não existir.
	 */
	public function get( string $id ): Unit {
		if ( ! isset( $this->units[ $id ] ) ) {
			throw HierarchyException::unknownUnit( $id );
		}

		return $this->units[ $id ];
	}

	/**
	 * Existe uma unidade com este identificador.
	 *
	 * @param string $id Identificador.
	 */
	public function has( string $id ): bool {
		return isset( $this->units[ $id ] );
	}

	/**
	 * Todas as unidades, por ordem de registo.
	 *
	 * @return Unit[]
	 */
	public function all(): array {
		return array_values( $this->units );
	}

	/**
	 * Subordinadas diretas.
	 *
	 * @param string $id Identificador.
	 * @return Unit[]
	 */
	public function children( string $id ): array {
		return array_values(
			array_filter( $this->units, static fn( Unit $unit ): bool => $unit->parent_id === $id )
		);
	}

	/**
	 * Superiores, do mais próximo para a raiz.
	 *
	 * @param string $id Identificador.
	 * @return Unit[]
	 */
	public function ancestors( string $id ): array {
		$ancestors = array();
		$current   = $this->get( $id );

		while ( null !== $current->parent_id ) {
			$current     = $this->get( $current->parent_id );
			$ancestors[] = $current;
		}

		return $ancestors;
	}

	/**
	 * Unidades que partilham a mesma sigla.
	 *
	 * @param string $acronym Sigla.
	 * @return Unit[]
	 */
	public function byAcronym( string $acronym ): array {
		return array_values(
			array_filter( $this->units, static fn( Unit $unit ): bool => $unit->acronym === $acronym )
		);
	}

	/**
	 * Unidades cuja posição não pode ser apresentada como definitiva.
	 *
	 * @return Unit[]
	 */
	public function pendingPlacements(): array {
		return array_values(
			array_filter( $this->units, static fn( Unit $unit ): bool => $unit->placementIsPending() )
		);
	}

	/**
	 * O superior tem de existir e permanecer ativo enquanto a subordinada está ativa.
	 *
	 * @param Unit $unit Unidade.
	 *
	 * @throws HierarchyException Se o superior for desconhecido ou estiver inativo para uma unidade ativa.
	 */
	private function assertParentIsValid( Unit $unit ): void {
		if ( null === $unit->parent_id ) {
			return;
		}

		if ( ! isset( $this->units[ $unit->parent_id ] ) ) {
			throw HierarchyException::unknownParent( $unit->id, $unit->parent_id );
		}

		if ( $unit->active && ! $this->units[ $unit->parent_id ]->active ) {
			throw HierarchyException::inactiveParent( $unit->id, $unit->parent_id );
		}
	}

	/**
	 * Seguir os superiores a partir do novo pai não pode voltar à unidade.
	 *
	 * @param string      $id        Unidade.
	 * @param string|null $parent_id Superior proposto.
	 *
	 * @throws HierarchyException Se formar ciclo.
	 */
	private function assertNoCycle( string $id, ?string $parent_id ): void {
		$path    = array( $id );
		$visited = array( $id => true );
		$current = $parent_id;

		while ( null !== $current ) {
			$path[] = $current;

			if ( isset( $visited[ $current ] ) ) {
				throw HierarchyException::cycle( $path );
			}

			$visited[ $current ] = true;

			if ( ! isset( $this->units[ $current ] ) ) {
				break;
			}

			$current = $this->units[ $current ]->parent_id;
		}
	}
}
