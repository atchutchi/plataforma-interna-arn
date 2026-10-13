<?php
/**
 * Integridade do vínculo explícito, sem base de dados ou criação de utilizadores.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\LocalIdentityLink;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Identidade estável e autorização local são requisitos separados.
 */
final class LocalIdentityLinkTest extends TestCase {

	/**
	 * Os dois emissores Google admissíveis partilham a mesma chave canónica.
	 */
	public function testGoogleIssuerIsCanonicalAndSubjectIsPreserved(): void {
		$link = new LocalIdentityLink( 12, 'accounts.google.com', 'subject-ficticio', true, true );
		self::assertSame( 'https://accounts.google.com', $link->issuer );
		self::assertSame( 'subject-ficticio', $link->subject );
		self::assertSame( 12, $link->user_id );
	}

	/**
	 * Vínculos com identificadores inválidos não podem ser utilizados.
	 *
	 * @param int    $user_id ID local.
	 * @param string $issuer  Emissor.
	 * @param string $subject Identificador externo.
	 */
	#[DataProvider( 'invalidLinks' )]
	public function testInvalidIdentifiersAreRejected( int $user_id, string $issuer, string $subject ): void {
		$this->expectException( InvalidArgumentException::class );
		new LocalIdentityLink( $user_id, $issuer, $subject, true, true );
	}

	/**
	 * Dados inválidos sem identidades institucionais reais.
	 *
	 * @return array<string, array{int, string, string}>
	 */
	public static function invalidLinks(): array {
		return array(
			'id zero'       => array( 0, 'https://accounts.google.com', 'abc' ),
			'id negativo'   => array( -1, 'https://accounts.google.com', 'abc' ),
			'emissor falso' => array( 1, 'https://accounts.google.com.example.test', 'abc' ),
			'sub vazio'     => array( 1, 'https://accounts.google.com', '' ),
			'sub espacos'   => array( 1, 'https://accounts.google.com', ' abc ' ),
			'sub longo'     => array( 1, 'https://accounts.google.com', str_repeat( 'a', 256 ) ),
		);
	}
}
