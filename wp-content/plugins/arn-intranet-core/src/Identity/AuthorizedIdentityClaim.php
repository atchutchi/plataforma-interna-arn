<?php
/**
 * Decisão interna previamente autorizada para um primeiro vínculo atómico.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity;

use RuntimeException;

/** O actor vem do pré-cadastro auditado, nunca do callback HTTP. */
final readonly class AuthorizedIdentityClaim {
	/**
	 * Preserva a decisão antes de inserir vínculo e auditorias.
	 *
	 * @param LocalIdentityLink $link Vínculo autorizado.
	 * @param int               $authorizing_actor_id Actor da criação CLI.
	 * @throws RuntimeException Quando o vínculo ou o actor são inválidos.
	 */
	public function __construct( public LocalIdentityLink $link, public int $authorizing_actor_id ) {
		if ( ! $link->authorized || ! $link->active || $authorizing_actor_id < 1 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
			throw new RuntimeException( 'Decisão de identidade inválida.' );
		}
	}
}
