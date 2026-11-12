<?php
/**
 * Identidade corrente sem concessões de negócio.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\LocalIdentityLink;
use InvalidArgumentException;

/** Valor imutável. A sua construção não autentica nem atribui competências. */
final readonly class Principal {

	/**
	 * Exige valores canónicos previamente revalidados pela fonte.
	 *
	 * @param int    $user_id                Titular WordPress.
	 * @param string $issuer                 Emissor canónico.
	 * @param string $subject                Identificador opaco.
	 * @param int    $link_generation        Geração persistida do vínculo.
	 * @param int    $authorization_revision Revisão persistida da autorização.
	 * @throws InvalidArgumentException Quando a identidade não é válida.
	 */
	public function __construct(
		public int $user_id,
		public string $issuer,
		public string $subject,
		public int $link_generation,
		public int $authorization_revision,
	) {
		if ( $user_id < 1 || $link_generation < 1 || $authorization_revision < 1 ||
			LocalIdentityLink::canonicalIssuer( $issuer ) !== $issuer || ! LocalIdentityLink::validSubject( $subject ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante, sem claims.
			throw new InvalidArgumentException( 'Principal institucional inválido.' );
		}
	}
}
