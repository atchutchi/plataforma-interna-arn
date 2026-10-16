<?php
/**
 * Fornecedor OIDC fictício para um WordPress separado de testes.
 * Nunca é incluído pelo plugin normal. Não oferece endpoints de controlo HTTP.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\{GoogleLoginController, MaintainedGoogleClient};
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Transporte fictício sem rede, controlo apenas por ficheiro privado e flag exclusiva de ensaio.
if ( ! defined( 'ABSPATH' ) || ! defined( 'ARN_WORKSPACE_BROWSER_FIXTURE' ) || true !== ARN_WORKSPACE_BROWSER_FIXTURE || 'local' !== wp_get_environment_type() || ! defined( 'ARN_WORKSPACE_PRIVATE_DIR' ) || ! defined( 'WP_HOME' ) || 'http://localhost:8889' !== WP_HOME ) {
	throw new RuntimeException( 'Fornecedor de browser recusado.' );}
global $table_prefix;
if ( ! is_string( $table_prefix ) || 1 !== preg_match( '/\Aarn_wsb_[a-f0-9]{12}_\z/D', $table_prefix ) ) {
	throw new RuntimeException( 'Instalação de browser recusada.' );}
require_once dirname( __DIR__ ) . '/phpunit/doubles/OidcHttpDouble.php';
require_once dirname( __DIR__ ) . '/phpunit/doubles/OidcRsaFixture.php';

add_action(
	'plugins_loaded',
	static function (): void {
		global $wp_filter;
		foreach ( array( 'login_form_arn_google', 'login_form_arn_google_callback', 'login_form', 'admin_menu', 'admin_post_arn_google_link', 'admin_post_arn_google_revoke' )as$hook ) {
			foreach ( $wp_filter[ $hook ]->callbacks ?? array()as$priority => $callbacks ) {
				foreach ( $callbacks as$callback ) {
					$function = $callback['function'] ?? null;
					if ( is_array( $function ) && ( $function[0] ?? null ) instanceof GoogleLoginController ) {
						remove_action( $hook, $function, $priority );}
				}
			}
		}
		$rsa       = null;
		$transport = new OidcHttpDouble(
			static function ( RequestInterface $request ) use ( &$rsa ): Response {
				$uri = (string) $request->getUri();
				if ( 'https://oauth2.googleapis.com/token' === $uri ) {
					parse_str( (string) $request->getBody(), $body );
					$code = $body['code'] ?? null;
					if ( ! is_string( $code ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $code ) || ! is_string( $body['code_verifier'] ?? null ) ) {
						throw new RuntimeException( 'Código fictício recusado.' );}
					$path  = ARN_WORKSPACE_PRIVATE_DIR . '/code-' . $code . '.json';
					$proof = json_decode( file_get_contents( $path ), true, 16, JSON_THROW_ON_ERROR );
					unlink( $path );
					$challenge = rtrim( strtr( base64_encode( hash( 'sha256', $body['code_verifier'], true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- S256 do ensaio.
					if ( ! hash_equals( $proof['challenge'], $challenge ) || 'authorization_code' !== ( $body['grant_type'] ?? null ) || 'http://localhost:8889/wp-login.php?action=arn_google_callback' !== ( $body['redirect_uri'] ?? null ) ) {
						throw new RuntimeException( 'PKCE fictício recusado.' );}
					$scenario = json_decode( file_get_contents( ARN_WORKSPACE_PRIVATE_DIR . '/scenario.json' ), true, 16, JSON_THROW_ON_ERROR );
					if ( ! is_string( $scenario['email'] ?? null ) || ! str_ends_with( $scenario['email'], '@example.invalid' ) || ! is_string( $scenario['sub'] ?? null ) || ! str_starts_with( $scenario['sub'], 'synthetic-browser-' ) ) {
						throw new RuntimeException( 'Identidade não fictícia recusada.' );}
					$claims = array(
						'iss'            => 'https://accounts.google.com',
						'sub'            => $scenario['sub'],
						'aud'            => 'browser-fixture',
						'exp'            => time() + 300,
						'iat'            => time() - 1,
						'nonce'          => $proof['nonce'],
						'email'          => $scenario['email'],
						'email_verified' => $scenario['email_verified'] ?? true,
						'hd'             => $scenario['hd'] ?? 'example.invalid',
					);
					if ( 'bad_nonce' === ( $scenario['mutation'] ?? null ) ) {
						$claims['nonce'] = str_repeat( '0', 64 );}
					if ( 'expired_token' === ( $scenario['mutation'] ?? null ) ) {
						$claims['exp'] = time() - 300;
					}
					$rsa   = new OidcRsaFixture();
					$token = $rsa->token( $claims );
					if ( 'bad_signature' === ( $scenario['mutation'] ?? null ) ) {
						$token = ( new OidcRsaFixture() )->token( $claims );
					}
					$data = array(
						'id_token'     => $token,
						'access_token' => 'synthetic-access',
						'token_type'   => 'Bearer',
						'expires_in'   => 300,
					);
					file_put_contents(
						ARN_WORKSPACE_PRIVATE_DIR . '/provider-proof.jsonl',
						json_encode(
							array(
								'event' => 'signed_token_with_pkce',
								'at'    => time(),
							),
							JSON_THROW_ON_ERROR
						) . "\n",
						FILE_APPEND | LOCK_EX
					);
				} elseif ( 'https://www.googleapis.com/oauth2/v3/certs' === $uri && $rsa instanceof OidcRsaFixture ) {
					$data = $rsa->jwks();
				} else {
					throw new RuntimeException( 'Nenhum pedido de rede é permitido.' );}
				return new Response( 200, array( 'Content-Type' => 'application/json' ), json_encode( $data, JSON_THROW_ON_ERROR ) );
			}
		);
		$transform = static function ( string $url ): string {
			parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $query );
			if ( 'code' !== ( $query['response_type'] ?? null ) || 'openid email' !== ( $query['scope'] ?? null ) || 'S256' !== ( $query['code_challenge_method'] ?? null ) || ! is_string( $query['nonce'] ?? null ) || ! is_string( $query['state'] ?? null ) || ! is_string( $query['code_challenge'] ?? null ) ) {
				throw new RuntimeException( 'Autorização fictícia recusada.' );}
			$code   = bin2hex( random_bytes( 16 ) );
			$handle = fopen( ARN_WORKSPACE_PRIVATE_DIR . '/code-' . $code . '.json', 'x' );
			if ( false === $handle ) {
				throw new RuntimeException();
			}fwrite(
				$handle,
				json_encode(
					array(
						'nonce'     => $query['nonce'],
						'challenge' => $query['code_challenge'],
					),
					JSON_THROW_ON_ERROR
				)
			);
			fclose( $handle );
			$scenario = json_decode( file_get_contents( ARN_WORKSPACE_PRIVATE_DIR . '/scenario.json' ), true, 16, JSON_THROW_ON_ERROR );
			if ( 'bad_state' === ( $scenario['mutation'] ?? null ) ) {
				$query['state'] = str_repeat( '0', 64 );
			}
			return 'http://localhost:8889/wp-login.php?action=arn_google_callback&' . http_build_query(
				array(
					'code'  => $code,
					'state' => $query['state'],
				),
				'',
				'&',
				PHP_QUERY_RFC3986
			);
		};
		( new GoogleLoginController( new MaintainedGoogleClient( $transport ), $transform ) )->registerHooks();
	},
	-1000
);
