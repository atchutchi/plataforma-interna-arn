<?php
/**
 * Erros de integridade da hierarquia.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Organization;

use RuntimeException;

/**
 * Identificador duplicado, superior inexistente ou ciclo.
 */
final class HierarchyException extends RuntimeException {

	/**
	 * Identificador repetido.
	 *
	 * @param string $id Identificador.
	 */
	public static function duplicateId( string $id ): self {
		return new self( sprintf( 'Já existe uma unidade com o identificador %s.', $id ) );
	}

	/**
	 * Superior não registado.
	 *
	 * @param string $id        Unidade.
	 * @param string $parent_id Superior em falta.
	 */
	public static function unknownParent( string $id, string $parent_id ): self {
		return new self( sprintf( 'A unidade %s indica o superior %s, que não existe.', $id, $parent_id ) );
	}

	/**
	 * Uma unidade ativa não pode depender de um superior desativado.
	 *
	 * @param string $id        Unidade ativa.
	 * @param string $parent_id Superior desativado.
	 */
	public static function inactiveParent( string $id, string $parent_id ): self {
		return new self( sprintf( 'A unidade ativa %s não pode depender do superior desativado %s.', $id, $parent_id ) );
	}

	/**
	 * A relação criaria um ciclo.
	 *
	 * @param string[] $path Caminho do ciclo.
	 */
	public static function cycle( array $path ): self {
		return new self( sprintf( 'A relação criaria um ciclo: %s.', implode( ' -> ', $path ) ) );
	}

	/**
	 * Unidade não registada.
	 *
	 * @param string $id Identificador.
	 */
	public static function unknownUnit( string $id ): self {
		return new self( sprintf( 'A unidade %s não existe.', $id ) );
	}

	/**
	 * Não se pode desativar uma unidade que ainda tem subordinadas ativas.
	 *
	 * @param string   $id       Unidade.
	 * @param string[] $children Subordinadas ativas.
	 */
	public static function activeChildren( string $id, array $children ): self {
		return new self( sprintf( 'A unidade %s tem subordinadas ativas: %s.', $id, implode( ', ', $children ) ) );
	}
}
