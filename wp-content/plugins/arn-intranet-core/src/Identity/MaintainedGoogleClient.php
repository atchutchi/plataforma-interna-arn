<?php
/**
 * Adaptador Google com protocolo e verificação JOSE da biblioteca homologada.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante sem dados externos, tratada pelo chamador.

use Facile\JoseVerifier\JWK\JwksProviderBuilder;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface as OpenIdClient;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\Issuer;
use Facile\OpenIDClient\Issuer\Metadata\IssuerMetadata;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Session\AuthSession;
use Facile\OpenIDClient\Token\IdTokenVerifierBuilder;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use RuntimeException;
use Throwable;

/** O armazenamento e o consumo único do contexto pertencem à integração. */
final class MaintainedGoogleClient {

	private const FAILURE = 'Não foi possível validar a autenticação Google.';
	private const ISSUER  = 'https://accounts.google.com';
	private const JWKS    = 'https://www.googleapis.com/oauth2/v3/certs';

	/**
	 * Transporte PSR-18 usado tanto na troca como na obtenção de JWKS.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $http_client;

	/**
	 * Configura o transporte sem acesso à rede no construtor.
	 *
	 * @param ?ClientInterface $http_client Transporte PSR-18 injectável em testes.
	 */
	public function __construct( ?ClientInterface $http_client = null ) {
		$this->http_client = $http_client ?? new Client(
			array(
				'timeout'         => 10,
				'connect_timeout' => 5,
				'allow_redirects' => false,
			)
		);
	}

	/**
	 * Gera code flow com state, nonce obrigatório e PKCE S256.
	 *
	 * @param OidcConfiguration   $config Configuração externa validada.
	 * @param array<string,mixed> $context Contexto previamente ligado ao browser.
	 * @throws RuntimeException Em contexto inválido ou falha da biblioteca.
	 */
	public function authorizationUrl( OidcConfiguration $config, array $context ): string {
		try {
			$this->validateContext( $context );
			return $this->service()->getAuthorizationUri(
				$this->client( $config ),
				array(
					'response_type'         => 'code',
					'scope'                 => 'openid email',
					'state'                 => $context['state'],
					'nonce'                 => $context['nonce'],
					// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE S256 usa base64url segundo RFC 7636.
					'code_challenge'        => rtrim( strtr( base64_encode( hash( 'sha256', $context['code_verifier'], true ) ), '+/', '-_' ), '=' ),
					'code_challenge_method' => 'S256',
					'hd'                    => $config->workspace_domain,
				)
			);
		} catch ( Throwable ) {
			throw new RuntimeException( self::FAILURE );
		}
	}

	/**
	 * O chamador deve consumir o contexto uma única vez antes de invocar este método.
	 * A biblioteca troca o código e verifica assinatura, algoritmo e claims OIDC.
	 *
	 * @param OidcConfiguration   $config Configuração externa validada.
	 * @param array<string,mixed> $params Parâmetros do callback.
	 * @param array<string,mixed> $context Contexto consumido e ligado ao browser.
	 * @return array<string,mixed> Apenas claims verificadas, sem tokens OAuth.
	 * @throws RuntimeException Em qualquer recusa ou indisponibilidade.
	 */
	public function complete( OidcConfiguration $config, array $params, array $context ): array {
		try {
			$this->validateContext( $context );
			if ( ! is_string( $params['state'] ?? null ) || ! hash_equals( $context['state'], $params['state'] ) ||
				! is_string( $params['code'] ?? null ) || '' === $params['code'] ) {
				throw new RuntimeException( self::FAILURE );
			}
			foreach ( array( 'error', 'id_token', 'access_token', 'refresh_token', 'response' ) as $forbidden ) {
				if ( array_key_exists( $forbidden, $params ) ) {
					throw new RuntimeException( self::FAILURE );
				}
			}
			$tokens = $this->service()->callback(
				$this->client( $config ),
				array(
					'code'  => $params['code'],
					'state' => $params['state'],
				),
				$config->redirect_uri,
				AuthSession::fromArray( $context )
			);
			if ( null === $tokens->getIdToken() || '' === $tokens->getIdToken() ) {
				throw new RuntimeException( self::FAILURE );
			}
			$claims = $tokens->claims();
			if ( ! is_int( $claims['exp'] ?? null ) || $claims['exp'] <= time() ) {
				throw new RuntimeException( self::FAILURE );
			}
			// A biblioteca compara nonce quando presente, mas não exige a sua presença.
			if ( ! is_string( $claims['nonce'] ?? null ) || ! hash_equals( $context['nonce'], $claims['nonce'] ) ) {
				throw new RuntimeException( self::FAILURE );
			}
			return $claims;
		} catch ( Throwable ) {
			// Não encadear excepções do fornecedor: podem conter tokens ou segredos.
			throw new RuntimeException( self::FAILURE );
		}
	}

	/**
	 * Exige os valores aleatórios tipados antes de construir ou concluir o pedido.
	 *
	 * @param array<string,mixed> $context Contexto obtido pelo armazenamento seguro.
	 * @throws RuntimeException Quando falta material de ligação ao pedido.
	 */
	private function validateContext( array $context ): void {
		foreach ( array( 'state', 'nonce' ) as $field ) {
			if ( ! is_string( $context[ $field ] ?? null ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $context[ $field ] ) ) {
				throw new RuntimeException( self::FAILURE );
			}
		}
		if ( ! is_string( $context['code_verifier'] ?? null ) || 1 !== preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $context['code_verifier'] ) ) {
			throw new RuntimeException( self::FAILURE );
		}
	}

	/**
	 * Fixa o fornecedor e o algoritmo no cliente mantido.
	 *
	 * @param OidcConfiguration $config Configuração externa validada.
	 */
	private function client( OidcConfiguration $config ): OpenIdClient {
		$issuer = new Issuer(
			IssuerMetadata::fromArray(
				array(
					'issuer'                   => self::ISSUER,
					'authorization_endpoint'   => self::ISSUER . '/o/oauth2/v2/auth',
					'token_endpoint'           => 'https://oauth2.googleapis.com/token',
					'jwks_uri'                 => self::JWKS,
					'response_types_supported' => array( 'code' ),
					'subject_types_supported'  => array( 'public' ),
					'id_token_signing_alg_values_supported' => array( 'RS256' ),
				)
			),
			( new JwksProviderBuilder() )->withJwksUri( self::JWKS )->withHttpClient( $this->http_client )->withRequestFactory( new HttpFactory() )->build()
		);
		return ( new ClientBuilder() )->setIssuer( $issuer )->setHttpClient( $this->http_client )->setClientMetadata(
			ClientMetadata::fromArray(
				array(
					'client_id'                    => $config->client_id,
					'client_secret'                => $config->clientSecret(),
					'redirect_uris'                => array( $config->redirect_uri ),
					'response_types'               => array( 'code' ),
					'grant_types'                  => array( 'authorization_code' ),
					'token_endpoint_auth_method'   => 'client_secret_post',
					'id_token_signed_response_alg' => 'RS256',
				)
			)
		)->build();
	}

	/** Serviço mantido para autorização, troca de código e verificação JOSE. */
	private function service(): AuthorizationService {
		return ( new AuthorizationServiceBuilder() )
			->setIdTokenVerifierBuilder( ( new IdTokenVerifierBuilder() )->setClockTolerance( 30 ) )
			->setHttpClient( $this->http_client )->setRequestFactory( new HttpFactory() )->build();
	}
}
