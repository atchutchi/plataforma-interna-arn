<?php
/**
 * Regressão de isolamento de credenciais da dependência HTTP, sem rede.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\ScopingHttpClient;

/** Exerce o cliente real com transporte em memória e credencial fictícia. */
final class HttpClientSecurityRegressionTest extends TestCase {

	/**
	 * Casos relativos e absolutos, incluindo GHSA-gq53-v8w2-gjhj.
	 *
	 * @return array
	 */
	public static function urls(): array {
		return array(
			'origem autorizada' => array( 'owned', 'https://issuer.example.invalid/api/owned', true ),
			'origem absoluta'   => array( 'https://other.example.invalid/request', 'https://other.example.invalid/request', false ),
			'referencia rede'   => array( '//other.example.invalid/request', 'https://other.example.invalid/request', false ),
		);
	}

	/**
	 * A mesma credencial nunca acompanha uma mudança de origem.
	 *
	 * @param string $input URL recebida pelo cliente.
	 * @param string $expected_url Destino esperado no transporte.
	 * @param bool   $credential_expected Só a origem autorizada recebe o token fictício.
	 */
	#[DataProvider( 'urls' )]
	public function testCredentialsStayInTheirScope( string $input, string $expected_url, bool $credential_expected ): void {
		$calls     = array();
		$transport = new MockHttpClient(
			static function ( string $method, string $url, array $options ) use ( &$calls ): MockResponse {
				$calls[] = array( $method, $url, $options['normalized_headers']['authorization'] ?? array() );
				return new MockResponse( 'synthetic' );
			}
		);
		$client    = ScopingHttpClient::forBaseUri( $transport, 'https://issuer.example.invalid/api/', array( 'auth_bearer' => 'synthetic-token' ) );
		self::assertSame( 'synthetic', $client->request( 'GET', $input )->getContent() );
		self::assertCount( 1, $calls );
		self::assertSame( 'GET', $calls[0][0] );
		self::assertSame( $expected_url, $calls[0][1] );
		self::assertSame( $credential_expected ? array( 'Authorization: Bearer synthetic-token' ) : array(), $calls[0][2] );
	}
}
