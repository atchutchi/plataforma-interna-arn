<?php
/**
 * Configuração externa, desactivada por defeito e sem divulgação do segredo.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\OidcConfiguration;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Usa apenas credenciais fictícias para validar contratos de configuração.
 */
final class OidcConfigurationTest extends TestCase {

	/**
	 * Ausência ou flag diferente de 1 nunca activa o cliente.
	 */
	public function testDisabledConfigurationDoesNotReadSecrets(): void {
		foreach ( array( false, '', '0', 'true' ) as $flag ) {
			$read = static function ( string $name ) use ( $flag ): string|false {
				self::assertSame( 'ARN_OIDC_ENABLED', $name );
				return $flag;
			};
			self::assertNull( OidcConfiguration::fromEnvironment( $read ) );
		}
	}

	/**
	 * Configuração completa conserva callback exacto e segredos apenas em memória.
	 */
	public function testCompleteConfigurationAndDebugRedaction(): void {
		$values = $this->values();
		$config = OidcConfiguration::fromEnvironment( static fn( string $name ): string|false => $values[ $name ] ?? false );
		self::assertInstanceOf( OidcConfiguration::class, $config );
		self::assertSame( 'cliente-ficticio', $config->client_id );
		self::assertSame( 'example.test', $config->workspace_domain );
		self::assertSame( $values['ARN_OIDC_REDIRECT_URI'], $config->redirect_uri );
		self::assertSame( 'segredo-ficticio-de-teste', $config->clientSecret() );
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r -- Exercita a redacção de debug sem emitir dados.
		self::assertStringNotContainsString( 'segredo-ficticio-de-teste', print_r( $config, true ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Testa o serializador PHP sem WordPress.
		self::assertStringNotContainsString( 'segredo-ficticio-de-teste', (string) json_encode( $config ) );
		self::assertSame( array( 'openid', 'email', 'profile' ), OidcConfiguration::SCOPES );
	}

	/**
	 * O segredo não vai para serialização de cache ou base de dados.
	 */
	public function testConfigurationCannotBeSerialized(): void {
		$values = $this->values();
		$config = OidcConfiguration::fromEnvironment( static fn( string $name ): string|false => $values[ $name ] ?? false );
		$this->expectException( LogicException::class );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Confirma que persistir o segredo lança uma excepção.
		serialize( $config );
	}

	/**
	 * Configuração incompleta ou callback inválido falha sem mostrar os valores.
	 *
	 * @param string       $key   Campo de configuração.
	 * @param string|false $value Valor inválido.
	 */
	#[DataProvider( 'invalidConfiguration' )]
	public function testInvalidConfigurationIsRejected( string $key, string|false $value ): void {
		$values         = $this->values();
		$values[ $key ] = $value;
		try {
			OidcConfiguration::fromEnvironment( static fn( string $name ): string|false => $values[ $name ] ?? false );
			self::fail( 'A configuração inválida foi aceite.' );
		} catch ( InvalidArgumentException $error ) {
			self::assertStringNotContainsString( 'segredo-ficticio-de-teste', $error->getMessage() );
		}
	}

	/**
	 * Dados demonstrativos, sem valores de cliente institucional.
	 *
	 * @return array<string, string>
	 */
	private function values(): array {
		return array(
			'ARN_OIDC_ENABLED'          => '1',
			'ARN_OIDC_CLIENT_ID'        => 'cliente-ficticio',
			'ARN_OIDC_CLIENT_SECRET'    => 'segredo-ficticio-de-teste',
			'ARN_OIDC_REDIRECT_URI'     => 'https://intranet.example.test/wp-admin/admin-ajax.php?action=retorno-ficticio',
			'ARN_OIDC_WORKSPACE_DOMAIN' => 'example.test',
		);
	}

	/**
	 * Valores que não podem configurar uma integração institucional.
	 *
	 * @return array<string, array{string, string|false}>
	 */
	public static function invalidConfiguration(): array {
		return array(
			'cliente ausente'  => array( 'ARN_OIDC_CLIENT_ID', false ),
			'cliente vazio'    => array( 'ARN_OIDC_CLIENT_ID', ' ' ),
			'segredo ausente'  => array( 'ARN_OIDC_CLIENT_SECRET', false ),
			'segredo vazio'    => array( 'ARN_OIDC_CLIENT_SECRET', '' ),
			'callback ausente' => array( 'ARN_OIDC_REDIRECT_URI', false ),
			'callback http'    => array( 'ARN_OIDC_REDIRECT_URI', 'http://intranet.example.test/callback' ),
			'callback ip'      => array( 'ARN_OIDC_REDIRECT_URI', 'https://192.168.17.205/callback' ),
			'callback ipv6'    => array( 'ARN_OIDC_REDIRECT_URI', 'https://[::1]/callback' ),
			'callback local'   => array( 'ARN_OIDC_REDIRECT_URI', 'https://intranet.local/callback' ),
			'callback host'    => array( 'ARN_OIDC_REDIRECT_URI', 'https://localhost/callback' ),
			'callback user'    => array( 'ARN_OIDC_REDIRECT_URI', 'https://user:password@intranet.example.test/callback' ),
			'callback hash'    => array( 'ARN_OIDC_REDIRECT_URI', 'https://intranet.example.test/callback#fragmento' ),
			'callback espaco'  => array( 'ARN_OIDC_REDIRECT_URI', 'https://intranet.example.test/call back' ),
			'dominio ausente'  => array( 'ARN_OIDC_WORKSPACE_DOMAIN', false ),
			'dominio vazio'    => array( 'ARN_OIDC_WORKSPACE_DOMAIN', '' ),
			'dominio email'    => array( 'ARN_OIDC_WORKSPACE_DOMAIN', 'pessoa@example.test' ),
			'dominio url'      => array( 'ARN_OIDC_WORKSPACE_DOMAIN', 'https://example.test' ),
		);
	}
}
