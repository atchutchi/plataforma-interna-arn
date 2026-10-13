<?php
/**
 * Homologação do adaptador com assinaturas RSA reais e transporte PSR controlado.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fixtures puras sem WordPress.

use Arn\Intranet\Identity\MaintainedGoogleClient;
use Arn\Intranet\Identity\OidcConfiguration;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

require_once __DIR__ . '/doubles/OidcHttpDouble.php';
require_once __DIR__ . '/doubles/OidcRsaFixture.php';

/** Exerce a biblioteca mantida, sem substituir o verificador criptográfico. */
final class MaintainedGoogleClientTest extends TestCase {

	/**
	 * Chave pública anunciada pelo fornecedor fictício.
	 *
	 * @var OidcRsaFixture
	 */
	private static OidcRsaFixture $rsa;
	/**
	 * Chave privada adversa que não pertence ao JWKS.
	 *
	 * @var OidcRsaFixture
	 */
	private static OidcRsaFixture $other_rsa;

	/** Chaves efémeras partilhadas apenas durante a execução da suite. */
	public static function setUpBeforeClass(): void {
		self::$rsa       = new OidcRsaFixture();
		self::$other_rsa = new OidcRsaFixture();
	}

	/** O resultado depende da assinatura, audiência e nonce validados. */
	public function testSignedCodeFlowReturnsOnlyValidatedClaims(): void {
		$claims = $this->claims();
		$http   = $this->transport( self::$rsa->token( $claims ) );
		$result = ( new MaintainedGoogleClient( $http ) )->complete( $this->configuration(), $this->params(), $this->context() );
		self::assertSame( $claims, $result );
		self::assertArrayNotHasKey( 'access_token', $result );
		self::assertArrayNotHasKey( 'refresh_token', $result );
		self::assertCount( 2, $http->requests );
		parse_str( (string) $http->requests[0]->getBody(), $body );
		self::assertSame( 'POST', $http->requests[0]->getMethod() );
		self::assertSame( 'authorization_code', $body['grant_type'] );
		self::assertSame( $this->context()['code_verifier'], $body['code_verifier'] );
		self::assertSame( $this->configuration()->redirect_uri, $body['redirect_uri'] );
	}

	/** Tolera apenas uma pequena diferença entre o relógio Google e o local. */
	public function testSmallIssuedAtClockSkewIsAccepted(): void {
		$claims        = $this->claims();
		$claims['iat'] = time() + 5;
		$http          = $this->transport( self::$rsa->token( $claims ) );
		self::assertSame( $claims, ( new MaintainedGoogleClient( $http ) )->complete( $this->configuration(), $this->params(), $this->context() ) );
	}

	/** Uma diferença de um minuto continua a ser recusada. */
	public function testIssuedAtOutsideClockToleranceIsRefused(): void {
		$claims        = $this->claims();
		$claims['iat'] = time() + 60;
		$http          = $this->transport( self::$rsa->token( $claims ) );
		$this->expectException( RuntimeException::class );
		( new MaintainedGoogleClient( $http ) )->complete( $this->configuration(), $this->params(), $this->context() );
	}

	/** A tolerância não prolonga um ID token que já expirou. */
	public function testRecentlyExpiredTokenRemainsRefused(): void {
		$claims        = $this->claims();
		$claims['exp'] = time() - 1;
		$http          = $this->transport( self::$rsa->token( $claims ) );
		$this->expectException( RuntimeException::class );
		( new MaintainedGoogleClient( $http ) )->complete( $this->configuration(), $this->params(), $this->context() );
	}

	/** A autorização envia apenas scopes autorizados e PKCE S256. */
	public function testAuthorizationHasCodeFlowPkceStateNonceAndMinimalScopes(): void {
		$http = $this->transport( '' );
		$url  = ( new MaintainedGoogleClient( $http ) )->authorizationUrl( $this->configuration(), $this->context() );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Inspecção de URL emitido pela classe pura.
		$parts = parse_url( $url );
		parse_str( $parts['query'], $query );
		self::assertSame( 'https', $parts['scheme'] );
		self::assertSame( 'accounts.google.com', $parts['host'] );
		self::assertSame( '/o/oauth2/v2/auth', $parts['path'] );
		self::assertSame( 'code', $query['response_type'] );
		self::assertSame( 'openid email', $query['scope'] );
		self::assertSame( $this->context()['nonce'], $query['nonce'] );
		self::assertSame( $this->context()['state'], $query['state'] );
		self::assertSame( 'S256', $query['code_challenge_method'] );
		self::assertSame( 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', $query['code_challenge'] );
		self::assertSame( 'example.test', $query['hd'] );
		self::assertArrayNotHasKey( 'client_secret', $query );
		self::assertArrayNotHasKey( 'code_verifier', $query );
		self::assertCount( 0, $http->requests );
	}

	/**
	 * Recusa identidades assinadas que violam os critérios OIDC.
	 *
	 * @param string $scenario Mutação adversa do token assinado.
	 */
	#[DataProvider( 'invalidTokens' )]
	public function testInvalidTokenIsRefused( string $scenario ): void {
		$claims    = $this->claims();
		$algorithm = 'RS256';
		$key       = self::$rsa;
		$headers   = array();
		switch ( $scenario ) {
			case 'signature':
				$key = self::$other_rsa;
				break;
			case 'embedded-key':
				$key            = self::$other_rsa;
				$headers['jwk'] = self::$other_rsa->jwks()['keys'][0];
				break;
			case 'algorithm':
				$algorithm = 'RS384';
				break;
			case 'issuer':
				$claims['iss'] = 'https://attacker.example.test';
				break;
			case 'audience':
				$claims['aud'] = 'outro-cliente';
				break;
			case 'expired':
				$claims['exp'] = time() - 100;
				break;
			case 'future-iat':
				$claims['iat'] = time() + 300;
				break;
			case 'nonce':
				$claims['nonce'] = str_repeat( 'c', 64 );
				break;
			case 'missing-nonce':
				unset( $claims['nonce'] );
				break;
			case 'typed-nonce':
				$claims['nonce'] = 12;
				break;
			case 'missing-exp':
				unset( $claims['exp'] );
				break;
			case 'missing-iat':
				unset( $claims['iat'] );
				break;
			case 'missing-sub':
				unset( $claims['sub'] );
				break;
		}
		$http = $this->transport( $key->token( $claims, $algorithm, $headers ) );
		$this->assertRefused( new MaintainedGoogleClient( $http ), $this->params(), $this->context() );
	}

	/**
	 * Identifica mutações que quebrariam a autenticação.
	 *
	 * @return array<string,array{string}> Casos adversos.
	 */
	public static function invalidTokens(): array {
		$cases = array( 'signature', 'embedded-key', 'algorithm', 'issuer', 'audience', 'expired', 'future-iat', 'nonce', 'missing-nonce', 'typed-nonce', 'missing-exp', 'missing-iat', 'missing-sub' );
		return array_combine( $cases, array_map( static fn( string $scenario ): array => array( $scenario ), $cases ) );
	}

	/**
	 * Recusa o callback inválido antes de divulgar o código à rede.
	 *
	 * @param string $scenario Parâmetro frontal ou contexto inválido.
	 */
	#[DataProvider( 'invalidCallbacks' )]
	public function testInvalidCallbackIsRefusedBeforeNetwork( string $scenario ): void {
		$params  = $this->params();
		$context = $this->context();
		switch ( $scenario ) {
			case 'wrong-state':
				$params['state'] = str_repeat( 'c', 64 );
				break;
			case 'missing-state':
				unset( $params['state'] );
				break;
			case 'typed-state':
				$params['state'] = array( 'attack' );
				break;
			case 'missing-code':
				unset( $params['code'] );
				break;
			case 'empty-code':
				$params['code'] = '';
				break;
			case 'typed-code':
				$params['code'] = array( 'attack' );
				break;
			case 'front-token':
				$params['id_token'] = self::$rsa->token( $this->claims() );
				break;
			case 'front-access':
				$params['access_token'] = 'front-token';
				break;
			case 'error':
				$params['error'] = 'access_denied';
				break;
			case 'consumed-context':
				$context = array();
				break;
			case 'context-nonce':
				unset( $context['nonce'] );
				break;
			case 'short-verifier':
				$context['code_verifier'] = 'short';
				break;
		}
		$http = $this->transport( self::$rsa->token( $this->claims() ) );
		$this->assertRefused( new MaintainedGoogleClient( $http ), $params, $context );
		self::assertCount( 0, $http->requests );
	}

	/**
	 * Identifica as recusas que dispensam contacto Google.
	 *
	 * @return array<string,array{string}> Casos adversos.
	 */
	public static function invalidCallbacks(): array {
		$cases = array( 'wrong-state', 'missing-state', 'typed-state', 'missing-code', 'empty-code', 'typed-code', 'front-token', 'front-access', 'error', 'consumed-context', 'context-nonce', 'short-verifier' );
		return array_combine( $cases, array_map( static fn( string $scenario ): array => array( $scenario ), $cases ) );
	}

	/** Resposta de token sem identidade nunca produz claims. */
	public function testMissingIdTokenIsRefused(): void {
		$this->assertRefused( new MaintainedGoogleClient( $this->transport( null ) ), $this->params(), $this->context() );
	}

	/** Falha na obtenção do JWKS não permite devolver a identidade descodificada. */
	public function testJwksFailureIsRefused(): void {
		$normal = $this->transport( self::$rsa->token( $this->claims() ) );
		$http   = new OidcHttpDouble(
			static function ( RequestInterface $request ) use ( $normal ): Response {
				if ( 'https://www.googleapis.com/oauth2/v3/certs' === (string) $request->getUri() ) {
					return new Response( 503, array(), 'indisponivel' );
				}
				return $normal->sendRequest( $request );
			}
		);
		$this->assertRefused( new MaintainedGoogleClient( $http ), $this->params(), $this->context() );
	}

	/** Uma resposta inválida da troca nunca expõe o corpo ou os tokens no erro. */
	public function testMalformedTokenResponseIsRefused(): void {
		$http = new OidcHttpDouble(
			static function ( RequestInterface $request ): Response {
				self::assertSame( 'https://oauth2.googleapis.com/token', (string) $request->getUri() );
				return new Response( 200, array( 'Content-Type' => 'application/json' ), '{segredo-ficticio' );
			}
		);
		$this->assertRefused( new MaintainedGoogleClient( $http ), $this->params(), $this->context() );
	}

	/** A ausência do nonce local também impede construir a autorização. */
	public function testAuthorizationRejectsIncompleteContext(): void {
		$http    = $this->transport( '' );
		$context = $this->context();
		unset( $context['nonce'] );
		try {
			( new MaintainedGoogleClient( $http ) )->authorizationUrl( $this->configuration(), $context );
			self::fail( 'A autorização sem nonce foi aceite.' );
		} catch ( RuntimeException $error ) {
			self::assertNull( $error->getPrevious() );
			self::assertCount( 0, $http->requests );
		}
	}

	/** Os detalhes da excepção de rede não são encadeados no erro público. */
	public function testNetworkFailureIsGeneric(): void {
		$http = new OidcHttpDouble(
			static function ( RequestInterface $request ): Response {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Simula PSR-18, sem emitir dados.
				throw new ConnectException( 'segredo-ficticio token-ficticio', $request );
			}
		);
		$this->assertRefused( new MaintainedGoogleClient( $http ), $this->params(), $this->context() );
	}

	/**
	 * Confirma a recusa pública sem divulgação da causa interna.
	 *
	 * @param MaintainedGoogleClient $client Adaptador.
	 * @param array                  $params Callback.
	 * @param array                  $context Contexto.
	 */
	private function assertRefused( MaintainedGoogleClient $client, array $params, array $context ): void {
		try {
			$client->complete( $this->configuration(), $params, $context );
			self::fail( 'O callback inválido foi aceite.' );
		} catch ( RuntimeException $error ) {
			self::assertSame( 'Não foi possível validar a autenticação Google.', $error->getMessage() );
			self::assertNull( $error->getPrevious() );
		}
	}

	/**
	 * Responde apenas aos endpoints oficiais fixados no adaptador.
	 *
	 * @param ?string $token Token assinado ou resposta sem token.
	 */
	private function transport( ?string $token ): OidcHttpDouble {
		return new OidcHttpDouble(
			static function ( RequestInterface $request ) use ( $token ): Response {
				$uri = (string) $request->getUri();
				if ( 'https://oauth2.googleapis.com/token' === $uri ) {
					$data = array(
						'access_token'  => 'access-fixture',
						'token_type'    => 'Bearer',
						'expires_in'    => 3600,
						'refresh_token' => 'refresh-fixture',
					);
					if ( null !== $token ) {
						$data['id_token'] = $token;
					}
				} elseif ( 'https://www.googleapis.com/oauth2/v3/certs' === $uri ) {
					$data = self::$rsa->jwks();
				} else {
					throw new RuntimeException( 'Endpoint inesperado no ensaio.' );
				}
				return new Response( 200, array( 'Content-Type' => 'application/json' ), json_encode( $data, JSON_THROW_ON_ERROR ) );
			}
		);
	}

	/**
	 * Cria uma identidade válida antes de cada mutação adversa.
	 *
	 * @return array<string,mixed> Identidade fictícia.
	 */
	private function claims(): array {
		return array(
			'iss'            => 'https://accounts.google.com',
			'sub'            => 'fixture-sub-123',
			'aud'            => 'cliente-ficticio',
			'exp'            => time() + 600,
			'iat'            => time() - 1,
			'nonce'          => str_repeat( 'b', 64 ),
			'email'          => 'fixture@example.test',
			'email_verified' => true,
			'hd'             => 'example.test',
		);
	}

	/**
	 * Usa o vector PKCE conhecido de RFC 7636.
	 *
	 * @return array<string,mixed> Contexto gerado anteriormente no browser.
	 */
	private function context(): array {
		return array(
			'state'         => str_repeat( 'a', 64 ),
			'nonce'         => str_repeat( 'b', 64 ),
			'code_verifier' => 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
			'issued_at'     => time(),
			'purpose'       => 'login',
			'user_id'       => 0,
		);
	}

	/**
	 * Representa a resposta frontal permitida.
	 *
	 * @return array<string,string> Resposta code flow.
	 */
	private function params(): array {
		return array(
			'state' => str_repeat( 'a', 64 ),
			'code'  => 'codigo-fixture',
		);
	}

	/** Configuração sem credenciais institucionais. */
	private function configuration(): OidcConfiguration {
		$values = array(
			'ARN_OIDC_ENABLED'          => '1',
			'ARN_OIDC_CLIENT_ID'        => 'cliente-ficticio',
			'ARN_OIDC_CLIENT_SECRET'    => 'segredo-ficticio',
			'ARN_OIDC_REDIRECT_URI'     => 'https://intranet.example.test/callback',
			'ARN_OIDC_WORKSPACE_DOMAIN' => 'example.test',
		);
		return OidcConfiguration::fromEnvironment( static fn( string $name ): string|false => $values[ $name ] ?? false );
	}
}
