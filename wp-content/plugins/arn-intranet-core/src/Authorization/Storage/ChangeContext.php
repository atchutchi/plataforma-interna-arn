<?php
/**
 * Contexto explícito de uma decisão administrativa.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use InvalidArgumentException;

// Mensagens constantes de domínio, escapadas por qualquer interface futura.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Não contém credenciais ou payloads externos. */
final readonly class ChangeContext {

	/**
	 * Valida textos por bytes e não os normaliza silenciosamente.
	 *
	 * @param int    $actor_id     Actor positivo.
	 * @param string $reason       Razão UTF-8, até 500 bytes.
	 * @param string $decision_ref Referência ASCII, até 120 bytes.
	 * @param int    $occurred_at  Instante Unix não negativo.
	 * @throws InvalidArgumentException Quando os valores são inválidos.
	 */
	public function __construct( public int $actor_id, public string $reason, public string $decision_ref, public int $occurred_at ) {
		if ( $actor_id <= 0 || $occurred_at < 0 || strlen( $reason ) < 1 || strlen( $reason ) > 500
			|| 1 !== preg_match( '//u', $reason ) || 1 === preg_match( '/\p{Cc}|\A\s|\s\z/u', $reason )
			|| strlen( $decision_ref ) > 120 || 1 !== preg_match( '/\A[A-Za-z0-9][A-Za-z0-9 ._\/:\-]*\z/', $decision_ref )
			|| trim( $decision_ref ) !== $decision_ref ) {
			throw new InvalidArgumentException( 'Contexto inválido.' );
		}
	}
}
