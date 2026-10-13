<?php
/**
 * Chaves efémeras e tokens assinados apenas para homologação criptográfica.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Fixture pura sem WordPress e com JSON_THROW_ON_ERROR.

use OpenSSLAsymmetricKey;
use RuntimeException;

/** Não contém material institucional ou persistência de chaves privadas. */
final class OidcRsaFixture {

	/**
	 * Chave privada descartável apenas em memória.
	 *
	 * @var OpenSSLAsymmetricKey
	 */
	private OpenSSLAsymmetricKey $key;

	/**
	 * Gera material descartável em memória.
	 *
	 * @throws RuntimeException Quando OpenSSL não consegue gerar a chave.
	 */
	public function __construct() {
		$key = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		if ( false === $key ) {
			throw new RuntimeException( 'Não foi possível criar a fixture RSA.' );
		}
		$this->key = $key;
	}

	/**
	 * Exporta exclusivamente a chave pública.
	 *
	 * @return array<string, mixed> Chave pública no formato Google JWKS.
	 */
	public function jwks(): array {
		$details = openssl_pkey_get_details( $this->key );
		return array(
			'keys' => array(
				array(
					'kty' => 'RSA',
					'kid' => 'fixture-rsa',
					'use' => 'sig',
					'alg' => 'RS256',
					'n'   => self::encode( $details['rsa']['n'] ),
					'e'   => self::encode( $details['rsa']['e'] ),
				),
			),
		);
	}

	/**
	 * Assina uma identidade de teste com a chave efémera.
	 *
	 * @param array<string, mixed> $claims Claims fictícias.
	 * @param string               $algorithm Algoritmo do ensaio.
	 * @param array<string, mixed> $headers Cabeçalhos adicionais.
	 * @throws RuntimeException Quando a operação criptográfica falha.
	 */
	public function token( array $claims, string $algorithm = 'RS256', array $headers = array() ): string {
		$header  = array_merge(
			array(
				'alg' => $algorithm,
				'kid' => 'fixture-rsa',
				'typ' => 'JWT',
			),
			$headers
		);
		$message = self::encode( json_encode( $header, JSON_THROW_ON_ERROR ) ) . '.' . self::encode( json_encode( $claims, JSON_THROW_ON_ERROR ) );
		if ( ! openssl_sign( $message, $signature, $this->key, 'RS384' === $algorithm ? OPENSSL_ALGO_SHA384 : OPENSSL_ALGO_SHA256 ) ) {
			throw new RuntimeException( 'Não foi possível assinar a fixture.' );
		}
		return $message . '.' . self::encode( $signature );
	}

	/**
	 * Codifica bytes da fixture em base64url.
	 *
	 * @param string $value Bytes a codificar segundo RFC 4648.
	 */
	private static function encode( string $value ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Formato JWT da fixture.
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}
}
