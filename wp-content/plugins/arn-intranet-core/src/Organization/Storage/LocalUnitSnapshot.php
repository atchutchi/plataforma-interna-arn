<?php
/** Snapshot estrito da árvore sintética.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Storage;

use Arn\Intranet\Organization\{Unit, UnitType, ParentStatus, UnitHierarchy};
use RuntimeException;
use Throwable;
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Revisão global e árvore fictícia validadas. */
final readonly class LocalUnitSnapshot {
	/**
	 * Inicializa e valida o contexto local.
	 *
	 * @param int   $revision Argumento da operação.
	 * @param array $units Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function __construct( public int $revision, public array $units ) {
		if ( $revision < 0 ) {
			throw new RuntimeException( 'Revisão local inválida.' ); }
		self::validate( $units );
		if ( ( 0 === $revision ) !== ( array() === $units ) ) {
			throw new RuntimeException( 'Revisão e árvore local incompatíveis.' );
		}
	}
	/**
	 * Valida os invariantes da árvore ou do esquema.
	 *
	 * @param array $units Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function validate( array $units ): void {
		try {
			$ids = array();
			foreach ( $units as $unit ) {
				if ( ! $unit instanceof Unit || ! in_array( $unit->id, array( 'LAB-ROOT', 'LAB-A', 'LAB-B' ), true ) || $unit->acronym !== $unit->id || UnitType::Outro !== $unit->type || ! is_string( $unit->name ) || ! str_starts_with( $unit->name, 'Unidade fictícia ' ) ) {
					throw new RuntimeException(); }
				$ids[] = $unit->id;
			}
			sort( $ids );
			if ( array() !== $ids && array( 'LAB-A', 'LAB-B', 'LAB-ROOT' ) !== $ids ) {
				throw new RuntimeException(); }
			UnitHierarchy::fromList( $units );
		} catch ( Throwable ) {
			throw new RuntimeException( 'Árvore local inválida.' ); }
	}
	/**
	 * Codifica a árvore em JSON estrito.
	 *
	 * @param array $units Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function encode( array $units ): string {
		self::validate( $units );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Exige JSON_THROW_ON_ERROR também nos testes puros sem WordPress.
		return json_encode(
			array_map(
				static fn( Unit $u ): array => array(
					'id'            => $u->id,
					'acronym'       => $u->acronym,
					'name'          => $u->name,
					'type'          => $u->type->value,
					'parent_id'     => $u->parent_id,
					'parent_status' => $u->parent_status->value,
					'active'        => $u->active,
				),
				$units
			),
			JSON_THROW_ON_ERROR
		);
	}
	/**
	 * Descodifica e valida um snapshot JSON.
	 *
	 * @param int    $revision Argumento da operação.
	 * @param string $json Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function decode( int $revision, string $json ): self {
		try {
			$rows = json_decode( $json, true, 32, JSON_THROW_ON_ERROR );
			if ( ! is_array( $rows ) || ! array_is_list( $rows ) ) {
				throw new RuntimeException(); }
			$units = array();
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) || array_keys( $row ) !== array( 'id', 'acronym', 'name', 'type', 'parent_id', 'parent_status', 'active' ) || ! is_bool( $row['active'] ) ) {
					throw new RuntimeException(); }
				$units[] = new Unit( $row['id'], $row['acronym'], $row['name'], UnitType::from( $row['type'] ), $row['parent_id'], ParentStatus::from( $row['parent_status'] ), $row['active'] );
			}
			return new self( $revision, $units );
		} catch ( Throwable ) {
			throw new RuntimeException( 'Snapshot local inválido.' ); }
	}
}
