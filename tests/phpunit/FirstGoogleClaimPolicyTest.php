<?php
/**
 * Política de primeira associação sem funções.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Identity\PreRegistration\FirstGoogleClaimPolicy;
use PHPUnit\Framework\TestCase;
/**
 * Testa apenas claims já autenticadas pelo cliente, sem substituir JOSE.
 */
final class FirstGoogleClaimPolicyTest extends TestCase {
	/**
	 * Valores de entrada e resultado.
	 *
	 * @return array Claims fictícias já verificadas.
	 */
	private function claims(): array {
		return array(
			'iss'            => 'https://accounts.google.com',
			'sub'            => 'fixture-sub',
			'hd'             => 'example.invalid',
			'email'          => 'fixture.0@example.invalid',
			'email_verified' => true,
		);
	}
	/**
	 * A igualdade do instante de expiração já recusa.
	 */
	public function testClaimExpiresAtExactBoundary(): void {
		self::assertTrue( FirstGoogleClaimPolicy::allows( $this->claims(), 'fixture.0@example.invalid', 'example.invalid', 2000, 1999 ) );
		self::assertFalse( FirstGoogleClaimPolicy::allows( $this->claims(), 'fixture.0@example.invalid', 'example.invalid', 2000, 2000 ) );
	}
	/**
	 * Não confunde email, alias, domínio e verificação.
	 */
	public function testAdverseClaimsNeverAuthorizeFirstBinding(): void {
		foreach ( array( array( 'email_verified', 'true' ), array( 'email_verified', false ), array( 'hd', 'other.invalid' ), array( 'email', 'alias@example.invalid' ), array( 'email', 'Fixture.0@example.invalid' ), array( 'iss', 'https://attacker.invalid' ), array( 'sub', '' ), array( 'sub', array() ) ) as [ $field, $value ] ) {
			$claims           = $this->claims();
			$claims[ $field ] = $value;
			self::assertFalse( FirstGoogleClaimPolicy::allows( $claims, 'fixture.0@example.invalid', 'example.invalid', 2000, 1999 ) );
		}
		$claims = $this->claims();
		unset( $claims['email_verified'] );
		self::assertFalse( FirstGoogleClaimPolicy::allows( $claims, 'fixture.0@example.invalid', 'example.invalid', 2000, 1999 ) );
	}
}
