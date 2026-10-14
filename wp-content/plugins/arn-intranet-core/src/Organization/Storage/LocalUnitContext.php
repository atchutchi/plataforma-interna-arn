<?php
/** Contexto exclusivamente obtido no servidor.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Storage;

use RuntimeException;
/** Actor, motivo e instante validados no servidor. */
final readonly class LocalUnitContext {
	/**
	 * Inicializa e valida o contexto local.
	 *
	 * @param int    $actor_id Argumento da operação.
	 * @param string $reason Argumento da operação.
	 * @param int    $occurred_at Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function __construct( public int $actor_id, public string $reason, public int $occurred_at ) {
		if ( $actor_id < 1 || $occurred_at < 1 || '' === $reason || trim( $reason ) !== $reason || preg_match( '/[\x00-\x1f\x7f]/u', $reason ) || false === preg_match( '//u', $reason ) || mb_strlen( $reason ) > 500 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new RuntimeException( 'Contexto local inválido.' );
		}
	}
}
