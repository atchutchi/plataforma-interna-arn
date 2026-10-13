<?php
/**
 * Limites puros do ensaio local de sessões federadas.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\FederatedSessionPolicy;
use Arn\Intranet\Identity\LocalIdentityLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Metadados WordPress não substituem vínculo activo e autorizado.
 */
final class FederatedSessionPolicyTest extends TestCase {

	/**
	 * A sessão corrente com vínculo autorizado satisfaz o ensaio.
	 */
	public function testAuthorizedFederatedSessionIsAccepted(): void {
		self::assertTrue( FederatedSessionPolicy::valid( $this->link(), $this->session(), 10000, true ) );
	}

	/**
	 * Uma sessão federada depende da activação explícita do ensaio.
	 */
	public function testDisabledTrialRejectsValidSession(): void {
		self::assertFalse( FederatedSessionPolicy::valid( $this->link(), $this->session(), 10000, false ) );
	}

	/**
	 * Ausência de vínculo nunca resolve uma conta autenticada.
	 */
	public function testAnonymousAccountIsRejected(): void {
		self::assertFalse( FederatedSessionPolicy::valid( null, $this->session(), 10000, true ) );
	}

	/**
	 * Revogação ou desactivação local nega a sessão ainda corrente.
	 */
	public function testInactiveOrUnauthorizedLinksAreRejected(): void {
		foreach ( array( array( false, false ), array( false, true ), array( true, false ) ) as $flags ) {
			$link = new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', $flags[0], $flags[1] );
			self::assertFalse( FederatedSessionPolicy::valid( $link, $this->session(), 10000, true ) );
		}
	}

	/**
	 * Ordenação temporal e limites são verificados sem arredondamento.
	 *
	 * @param int  $issued     Instante de emissão.
	 * @param int  $last_seen  Última actividade.
	 * @param int  $expiration Expiração WordPress.
	 * @param int  $now        Instante actual.
	 * @param bool $expected   Decisão esperada.
	 */
	#[DataProvider( 'timeCases' )]
	public function testTimeBoundaries( int $issued, int $last_seen, int $expiration, int $now, bool $expected ): void {
		$session = array(
			'arn_oidc'           => true,
			'arn_oidc_issued_at' => $issued,
			'arn_oidc_last_seen' => $last_seen,
			'expiration'         => $expiration,
		);
		self::assertSame( $expected, FederatedSessionPolicy::valid( $this->link(), $session, $now, true ) );
	}

	/**
	 * Casos calculados a partir de uma hora e trinta minutos exactos.
	 *
	 * @return array<string, array<int|bool>>
	 */
	public static function timeCases(): array {
		return array(
			'lifetime before boundary' => array( 6401, 9500, 11000, 10000, true ),
			'lifetime at boundary'     => array( 6400, 9500, 11000, 10000, false ),
			'lifetime after boundary'  => array( 6399, 9500, 11000, 10000, false ),
			'idle before boundary'     => array( 8000, 8201, 11000, 10000, true ),
			'idle at boundary'         => array( 8000, 8200, 11000, 10000, false ),
			'idle after boundary'      => array( 8000, 8199, 11000, 10000, false ),
			'expiration at now'        => array( 9000, 9500, 10000, 10000, false ),
			'expiration before now'    => array( 9000, 9500, 9999, 10000, false ),
			'expiration after now'     => array( 9000, 9500, 10001, 10000, true ),
			'issued in future'         => array( 10001, 10001, 11000, 10000, false ),
			'last seen in future'      => array( 9000, 10001, 11000, 10000, false ),
			'last seen before issued'  => array( 9500, 9499, 11000, 10000, false ),
			'same instant allowed'     => array( 10000, 10000, 10001, 10000, true ),
			'zero timestamps allowed'  => array( 0, 0, 1, 0, true ),
			'negative issued'          => array( -1, 0, 1, 0, false ),
			'negative last seen'       => array( -2, -1, 1, 0, false ),
			'negative now'             => array( -3, -2, 1, -1, false ),
		);
	}

	/**
	 * Metadados ausentes ou com tipos coercíveis não concedem acesso.
	 *
	 * @param string $key   Campo substituído.
	 * @param mixed  $value Valor inválido.
	 */
	#[DataProvider( 'invalidMetadata' )]
	public function testMalformedMetadataIsRejected( string $key, mixed $value ): void {
		$session         = $this->session();
		$session[ $key ] = $value;
		self::assertFalse( FederatedSessionPolicy::valid( $this->link(), $session, 10000, true ) );
		unset( $session[ $key ] );
		self::assertFalse( FederatedSessionPolicy::valid( $this->link(), $session, 10000, true ) );
	}

	/**
	 * Os tipos de armazenamento não são convertidos pela política.
	 *
	 * @return array<string, array{string, mixed}>
	 */
	public static function invalidMetadata(): array {
		$cases = array();
		foreach ( array(
			'arn_oidc_issued_at' => 9000,
			'arn_oidc_last_seen' => 9500,
			'expiration'         => 11000,
		) as $key => $valid ) {
			foreach ( array( (string) $valid, (float) $valid, true, false, null, array(), new \stdClass() ) as $index => $value ) {
				$cases[ $key . ' invalid type ' . $index ] = array( $key, $value );
			}
		}
		foreach ( array( false, 1, '1', 'true', null, array(), new \stdClass() ) as $index => $value ) {
			$cases[ 'marker invalid type ' . $index ] = array( 'arn_oidc', $value );
		}
		return $cases;
	}

	/**
	 * Vínculo activo autorizado fictício.
	 */
	private function link(): LocalIdentityLink {
		return new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', true, true );
	}

	/**
	 * Estrutura WordPress completa, com campos habituais adicionais.
	 *
	 * @return array<string, mixed>
	 */
	private function session(): array {
		return array(
			'arn_oidc'           => true,
			'arn_oidc_issued_at' => 9000,
			'arn_oidc_last_seen' => 9500,
			'expiration'         => 11000,
			'ip'                 => '127.0.0.1',
			'ua'                 => 'Fictional browser',
			'login'              => 9000,
		);
	}
}
