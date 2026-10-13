<?php
/**
 * Vínculo explícito em memória entre uma identidade Google e uma conta local.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use InvalidArgumentException;

/**
 * Não cria utilizadores, não grava dados nem atribui funções.
 */
final readonly class LocalIdentityLink {

	/**
	 * Emissor canónico da identidade.
	 *
	 * @var string
	 */
	public string $issuer;

	/**
	 * Atribuição prévia fornecida por um futuro repositório autorizado.
	 *
	 * @param int    $user_id    Identificador WordPress positivo.
	 * @param string $issuer     Emissor Google admissível.
	 * @param string $subject    Claim sub opaca, sem alteração ou truncagem.
	 * @param bool   $authorized Acesso local explicitamente autorizado.
	 * @param bool   $active     Conta local activa.
	 *
	 * @throws InvalidArgumentException Em identificadores inválidos.
	 */
	public function __construct(
		public int $user_id,
		string $issuer,
		public string $subject,
		public bool $authorized,
		public bool $active,
	) {
		$canonical = self::canonicalIssuer( $issuer );
		if ( $user_id < 1 || null === $canonical || ! self::validSubject( $subject ) ) {
			throw new InvalidArgumentException( 'Vínculo de identidade inválido.' );
		}
		$this->issuer = $canonical;
	}

	/**
	 * Google documenta estes dois valores de emissor para o mesmo fornecedor.
	 *
	 * @param string $issuer Claim iss.
	 */
	public static function canonicalIssuer( string $issuer ): ?string {
		return in_array( $issuer, array( 'https://accounts.google.com', 'accounts.google.com' ), true )
			? 'https://accounts.google.com'
			: null;
	}

	/**
	 * Mantém o sub exactamente como recebido, sem normalizar a identidade.
	 *
	 * @param string $subject Claim sub.
	 */
	public static function validSubject( string $subject ): bool {
		return 1 === preg_match( '/^[^\s\x00-\x1F\x7F]{1,255}$/D', $subject );
	}
}
