<?php
/**
 * Identidade persistente de uma concessão imutável.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Authorization\Grant;
use InvalidArgumentException;

/** Mantém a identidade sem alterar a concessão histórica. */
final readonly class StoredGrant {

	/**
	 * Exige identificador persistente positivo.
	 *
	 * @param int   $id    Identificador.
	 * @param Grant $grant Concessão validada.
	 * @throws InvalidArgumentException Quando os valores são inválidos.
	 */
	public function __construct( public int $id, public Grant $grant ) {
		if ( $id <= 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Concessão persistente inválida.' );
		}
	}
}
