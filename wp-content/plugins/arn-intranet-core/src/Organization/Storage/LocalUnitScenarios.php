<?php
/** Cenários fechados sem dados institucionais.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Storage;

use Arn\Intranet\Organization\{Unit, UnitType, ParentStatus, UnitHierarchy};
use RuntimeException;
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Transformações fechadas da árvore fictícia. */
final class LocalUnitScenarios {
	/**
	 * Devolve os cenários e rótulos permitidos.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function labels(): array {
		return array(
			'initialize'     => 'Criar ROOT, A e B fictícias',
			'rename-a'       => 'Renomear A para Unidade fictícia A revista',
			'move-b-to-a'    => 'Mover B para A',
			'move-b-to-root' => 'Devolver B à raiz',
			'deactivate-b'   => 'Desactivar B conservando o histórico',
		);
	}
	/**
	 * Aplica um cenário sobre uma árvore validada.
	 *
	 * @param string $scenario Argumento da operação.
	 * @param array  $units Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function apply( string $scenario, array $units ): array {
		LocalUnitSnapshot::validate( $units );
		if ( ! isset( self::labels()[ $scenario ] ) ) {
			throw new RuntimeException( 'Cenário local inválido.' ); }
		if ( 'initialize' === $scenario ) {
			if ( array() !== $units ) {
				throw new RuntimeException( 'Árvore local já inicializada.' ); }
			return array( new Unit( 'LAB-ROOT', 'LAB-ROOT', 'Unidade fictícia raiz', UnitType::Outro, null, ParentStatus::Raiz ), new Unit( 'LAB-A', 'LAB-A', 'Unidade fictícia A', UnitType::Outro, 'LAB-ROOT', ParentStatus::Confirmada ), new Unit( 'LAB-B', 'LAB-B', 'Unidade fictícia B', UnitType::Outro, 'LAB-ROOT', ParentStatus::Confirmada ) );
		}
		$tree = UnitHierarchy::fromList( $units );
		match ( $scenario ) {
			'rename-a' => $tree->rename( 'LAB-A', 'Unidade fictícia A revista' ),
			'move-b-to-a' => $tree->reparent( 'LAB-B', 'LAB-A', ParentStatus::Confirmada ),
			'move-b-to-root' => $tree->reparent( 'LAB-B', 'LAB-ROOT', ParentStatus::Confirmada ),
			'deactivate-b' => $tree->deactivate( 'LAB-B' ),
		};
		$result = $tree->all();
		if ( LocalUnitSnapshot::encode( $units ) === LocalUnitSnapshot::encode( $result ) ) {
			throw new RuntimeException( 'Cenário local sem alteração.' ); }
		return $result;
	}
}
