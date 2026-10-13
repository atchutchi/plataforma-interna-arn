<?php
/**
 * Testes da configuração OIDC fora das raízes públicas e Git.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Identity\ProtectedOidcConfiguration;
use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Testes puros sem WordPress usam ficheiros temporários locais e JSON nativo.

/** Verifica isolamento, leitura e recusa segura da configuração privada. */
final class ProtectedOidcConfigurationTest extends TestCase {

	/**
	 * Directório temporário exclusivo do teste.
	 *
	 * @var string
	 */
	private string $directory;

	/**
	 * Ficheiro JSON privado da fixture.
	 *
	 * @var string
	 */
	private string $file;

	/** Prepara configuração fictícia fora das duas raízes. */
	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/arn_config_' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->directory, 0700 );
		mkdir( $this->directory . '/public', 0700 );
		mkdir( $this->directory . '/repo', 0700 );
		$this->file = $this->directory . '/oidc.json';
		file_put_contents(
			$this->file,
			json_encode(
				array(
					'ARN_OIDC_ENABLED'          => '1',
					'ARN_OIDC_CLIENT_ID'        => 'fixture-client',
					'ARN_OIDC_CLIENT_SECRET'    => 'fixture-secret',
					'ARN_OIDC_REDIRECT_URI'     => 'https://portal.example.test/wp-login.php?action=arn_google_callback',
					'ARN_OIDC_WORKSPACE_DOMAIN' => 'example.test',
				)
			)
		);
		chmod( $this->file, 0600 );
	}

	/** Remove apenas os ficheiros e directórios temporários do teste. */
	protected function tearDown(): void {
		foreach ( array( '/oidc.json', '/public/oidc.json', '/repo/oidc.json' ) as $name ) {
			if ( file_exists( $this->directory . $name ) ) {
				unlink( $this->directory . $name );
			}
		}
		rmdir( $this->directory . '/public' );
		rmdir( $this->directory . '/repo' );
		rmdir( $this->directory );
	}

	/** Carrega uma configuração completa fora das duas raízes. */
	public function testLoadsCompleteConfigurationFromOutsideBothRoots(): void {
		$config = ProtectedOidcConfiguration::load( $this->file, $this->directory . '/public', $this->directory . '/repo', 'production' );
		$this->assertSame( 'fixture-client', $config->client_id );
		$this->assertSame( 'fixture-secret', $config->clientSecret() );
	}

	/** Recusa configuração dentro da raiz pública. */
	public function testRefusesFileInsidePublicRoot(): void {
		copy( $this->file, $this->directory . '/public/oidc.json' );
		$this->expectException( RuntimeException::class );
		ProtectedOidcConfiguration::load( $this->directory . '/public/oidc.json', $this->directory . '/public', $this->directory . '/repo', 'local' );
	}

	/** Recusa configuração guardada no repositório. */
	public function testRefusesFileInsideRepository(): void {
		copy( $this->file, $this->directory . '/repo/oidc.json' );
		$this->expectException( RuntimeException::class );
		ProtectedOidcConfiguration::load( $this->directory . '/repo/oidc.json', $this->directory . '/public', $this->directory . '/repo', 'local' );
	}

	/** Oculta detalhes internos quando o ficheiro não existe. */
	public function testMissingFileFailsWithGenericMessage(): void {
		$this->expectExceptionMessage( 'Configuração protegida OIDC indisponível.' );
		ProtectedOidcConfiguration::load( $this->directory . '/missing.json', $this->directory . '/public', $this->directory . '/repo', 'local' );
	}

	/** Não divulga valores privados ao recusar JSON inválido. */
	public function testMalformedJsonFailsWithoutLeakingSecret(): void {
		file_put_contents( $this->file, 'fixture-secret' );
		$this->expectExceptionMessage( 'Configuração protegida OIDC indisponível.' );
		ProtectedOidcConfiguration::load( $this->file, $this->directory . '/public', $this->directory . '/repo', 'local' );
	}

	/** Recusa tipos de dados inesperados na configuração. */
	public function testTypedArrayInsteadOfClientIdFails(): void {
		$data                       = json_decode( file_get_contents( $this->file ), true );
		$data['ARN_OIDC_CLIENT_ID'] = array( 'fixture' );
		file_put_contents( $this->file, json_encode( $data ) );
		$this->expectException( RuntimeException::class );
		ProtectedOidcConfiguration::load( $this->file, $this->directory . '/public', $this->directory . '/repo', 'local' );
	}

	/** Permite desactivar explicitamente o cliente. */
	public function testDisabledFileReturnsNull(): void {
		file_put_contents( $this->file, '{"ARN_OIDC_ENABLED":"0"}' );
		$this->assertNull( ProtectedOidcConfiguration::load( $this->file, $this->directory . '/public', $this->directory . '/repo', 'local' ) );
	}

	/** Recusa ficheiros que excedem o tamanho permitido. */
	public function testRefusesFileLargerThanLimit(): void {
		file_put_contents( $this->file, str_repeat( 'x', 65537 ) );
		$this->expectException( RuntimeException::class );
		ProtectedOidcConfiguration::load( $this->file, $this->directory . '/public', $this->directory . '/repo', 'local' );
	}
}

// phpcs:enable WordPress.WP.AlternativeFunctions
