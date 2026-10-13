<?php
/**
 * Associação local após validação criptográfica pelo futuro cliente OIDC.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\GoogleAccountPolicy;
use Arn\Intranet\Identity\LocalIdentityLink;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Não confunde identidade Google válida com autorização local.
 */
final class GoogleAccountPolicyTest extends TestCase {

	/**
	 * Um vínculo prévio e autorizado permite resolver apenas o ID local.
	 */
	public function testExplicitLinkSurvivesAnEmailChange(): void {
		$claims = $this->claims();
		$link   = new LocalIdentityLink( 12, 'https://accounts.google.com', 'subject-ficticio', true, true );
		self::assertSame( 12, GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $link ) ) );
		$claims['email'] = 'email-alterado@example.test';
		$claims['iss']   = 'accounts.google.com';
		self::assertSame( 12, GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $link ) ) );
	}

	/**
	 * A coincidência de email não cria vínculo nem promove outro sub.
	 */
	public function testEmailAloneCannotAuthorizeOrRelink(): void {
		$claims = $this->claims();
		self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array() ) );
		$link = new LocalIdentityLink( 12, 'https://accounts.google.com', 'outro-subject', true, true );
		self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $link ) ) );
	}

	/**
	 * Outros funcionários não tornam ambíguo o vínculo desta identidade.
	 */
	public function testUnrelatedIdentityDoesNotBlockTheAuthorizedLink(): void {
		$valid = new LocalIdentityLink( 12, 'https://accounts.google.com', 'subject-ficticio', true, true );
		$other = new LocalIdentityLink( 13, 'https://accounts.google.com', 'outro-subject', false, false );
		foreach ( array( array( $valid, $other ), array( $other, $valid ) ) as $links ) {
			self::assertSame( 12, GoogleAccountPolicy::localUserId( $this->claims(), 'example.test', $links ) );
		}
	}

	/**
	 * Um repositório que devolva elementos mal tipados não concede acesso.
	 */
	public function testMalformedLinkCollectionFailsClosed(): void {
		$valid = new LocalIdentityLink( 12, 'https://accounts.google.com', 'subject-ficticio', true, true );
		foreach ( array( array( $valid, null ), array( null, $valid ) ) as $links ) {
			self::assertNull( GoogleAccountPolicy::localUserId( $this->claims(), 'example.test', $links ) );
		}
	}

	/**
	 * Identidade institucional não contorna estado ou autorização local.
	 */
	public function testInactiveUnauthorizedAndAmbiguousLinksAreDenied(): void {
		$claims = $this->claims();
		$active = new LocalIdentityLink( 12, 'https://accounts.google.com', 'subject-ficticio', true, true );
		foreach ( array( array( false, true ), array( true, false ) ) as $flags ) {
			$link = new LocalIdentityLink( 12, 'https://accounts.google.com', 'subject-ficticio', $flags[0], $flags[1] );
			self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $link ) ) );
			self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $active, $link ) ) );
		}
		$other = new LocalIdentityLink( 13, 'https://accounts.google.com', 'subject-ficticio', true, true );
		self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $active, $other ) ) );
		self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $active, $active ) ) );
	}

	/**
	 * Claims incompletas ou mal tipadas falham sem recurso ao email.
	 *
	 * @param string $key   Claim alterada.
	 * @param mixed  $value Valor inválido.
	 */
	#[DataProvider( 'invalidClaims' )]
	public function testInvalidClaimsAreDenied( string $key, mixed $value ): void {
		$claims         = $this->claims();
		$claims[ $key ] = $value;
		$link           = new LocalIdentityLink( 12, 'https://accounts.google.com', 'subject-ficticio', true, true );
		self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $link ) ) );
	}

	/**
	 * O domínio autorizado é configuração obrigatória.
	 */
	public function testMissingDomainAndMissingClaimsFailClosed(): void {
		$link = new LocalIdentityLink( 12, 'https://accounts.google.com', 'subject-ficticio', true, true );
		self::assertNull( GoogleAccountPolicy::localUserId( $this->claims(), '', array( $link ) ) );
		foreach ( array( 'iss', 'sub', 'hd', 'email_verified' ) as $key ) {
			$claims = $this->claims();
			unset( $claims[ $key ] );
			self::assertNull( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $link ) ) );
		}
	}

	/**
	 * Claims simuladas. Não representam um token assinado ou uma pessoa real.
	 *
	 * @return array<string, mixed>
	 */
	private function claims(): array {
		return array(
			'iss'            => 'https://accounts.google.com',
			'sub'            => 'subject-ficticio',
			'hd'             => 'example.test',
			'email'          => 'pessoa.ficticia@example.test',
			'email_verified' => true,
		);
	}

	/**
	 * Cenários externos e inputs mal tipados.
	 *
	 * @return array<string, array{string, mixed}>
	 */
	public static function invalidClaims(): array {
		return array(
			'emissor externo'      => array( 'iss', 'https://accounts.google.com.example.test' ),
			'emissor http'         => array( 'iss', 'http://accounts.google.com' ),
			'sub vazio'            => array( 'sub', '' ),
			'sub numerico'         => array( 'sub', 123 ),
			'sub array'            => array( 'sub', array( 'subject-ficticio' ) ),
			'dominio externo'      => array( 'hd', 'externo.test' ),
			'dominio falso sufixo' => array( 'hd', 'example.test.externos.test' ),
			'dominio array'        => array( 'hd', array( 'example.test' ) ),
			'email nao verificado' => array( 'email_verified', false ),
			'verificado texto'     => array( 'email_verified', 'true' ),
			'verificado numero'    => array( 'email_verified', 1 ),
		);
	}
}
