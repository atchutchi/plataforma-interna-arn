<?php
/**
 * Âmbitos explícitos e imutáveis, sem inferência da organização.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

use InvalidArgumentException;

/** Só as fábricas permitem construir âmbitos coerentes. */
final readonly class Scope {

	/**
	 * Guarda um âmbito previamente validado.
	 *
	 * @param ScopeType         $type    Tipo explícito.
	 * @param array<int|string> $targets Alvos validados ou lista vazia para próprio/institucional.
	 */
	private function __construct( public ScopeType $type, public array $targets ) {}

	/**
	 * Âmbito do proprietário da operação.
	 *
	 * @return self
	 * @throws InvalidArgumentException Quando são fornecidos alvos.
	 */
	public static function own(): self {
		if ( func_num_args() > 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Âmbito inválido.' );
		}
		return new self( ScopeType::Own, array() );
	}

	/**
	 * Restringe a unidades explícitas.
	 *
	 * @param array<string> $targets Identificadores sem hierarquia inferida.
	 * @return self
	 */
	public static function units( array $targets ): self {
		return self::fromTargets( ScopeType::Units, $targets );
	}

	/**
	 * Restringe a pessoas explícitas.
	 *
	 * @param array<int> $targets IDs positivos.
	 * @return self
	 */
	public static function people( array $targets ): self {
		return self::fromTargets( ScopeType::People, $targets );
	}

	/**
	 * Restringe a filas explícitas.
	 *
	 * @param array<string> $targets Identificadores exactos.
	 * @return self
	 */
	public static function queues( array $targets ): self {
		return self::fromTargets( ScopeType::Queues, $targets );
	}

	/**
	 * Restringe a audiências explícitas.
	 *
	 * @param array<string> $targets Identificadores exactos.
	 * @return self
	 */
	public static function audiences( array $targets ): self {
		return self::fromTargets( ScopeType::Audiences, $targets );
	}

	/**
	 * Âmbito institucional concedido expressamente.
	 *
	 * @return self
	 * @throws InvalidArgumentException Quando são fornecidos alvos.
	 */
	public static function institutional(): self {
		if ( func_num_args() > 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Âmbito inválido.' );
		}
		return new self( ScopeType::Institutional, array() );
	}

	/**
	 * Exige cobertura completa por este único âmbito.
	 *
	 * @param Scope $required Âmbito do registo.
	 * @param int   $user_id  Conta que recebe a cobertura.
	 * @param int   $owner_id Proprietário conhecido ou zero.
	 * @return bool
	 */
	public function covers( Scope $required, int $user_id, int $owner_id ): bool {
		if ( ScopeType::Own === $required->type && ( $user_id <= 0 || $owner_id !== $user_id ) ) {
			return false;
		}
		if ( ScopeType::Institutional === $this->type ) {
			return true;
		}
		if ( $this->type !== $required->type ) {
			return false;
		}
		if ( ScopeType::Own === $this->type ) {
			return true;
		}
		foreach ( $required->targets as $target ) {
			if ( ! in_array( $target, $this->targets, true ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Valida toda a lista sem converter tipos nem remover duplicados.
	 *
	 * @param ScopeType         $type    Tipo de lista.
	 * @param array<int|string> $targets Alvos recebidos.
	 * @return self
	 * @throws InvalidArgumentException Quando a lista é inválida.
	 */
	private static function fromTargets( ScopeType $type, array $targets ): self {
		if ( array() === $targets ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Âmbito inválido.' );
		}
		$seen = array();
		foreach ( $targets as $target ) {
			$valid = ScopeType::People === $type
				? is_int( $target ) && $target > 0
				: is_string( $target ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{1,64}\z/', $target );
			if ( ! $valid || in_array( $target, $seen, true ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
				throw new InvalidArgumentException( 'Âmbito inválido.' );
			}
			$seen[] = $target;
		}
		return new self( $type, $seen );
	}
}
