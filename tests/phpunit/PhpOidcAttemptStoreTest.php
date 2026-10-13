<?php
/**
 * Exercita sessões nativas dedicadas sem WordPress nem fornecedor OAuth.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Exercita ficheiros e processos PHP reais sem carregar o WordPress.
// phpcs:disable WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Codifica exclusivamente fixtures fictícias para processos de teste.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Isola pedidos PHP reais sem mocks do motor de sessões.

use Arn\Intranet\Identity\PhpOidcAttemptStore;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Cada processo dispõe de cookies e motor PHP reais, sem cabeçalhos prévios. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class PhpOidcAttemptStoreTest extends TestCase {

	/**
	 * Pasta exclusiva deste teste.
	 *
	 * @var string
	 */
	private string $directory;
	/**
	 * Relógio controlado, em segundos Unix.
	 *
	 * @var int
	 */
	private int $now = 1000;

	/** Prepara apenas uma pasta exclusiva de cada teste. */
	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/arn-oidc-attempt-test-' . bin2hex( random_bytes( 12 ) );
		mkdir( $this->directory, 0700 );
	}

	/** Remove apenas os ficheiros de sessão criados na pasta exclusiva. */
	protected function tearDown(): void {
		if ( PHP_SESSION_ACTIVE === session_status() ) {
			session_write_close();
		}
		$files = glob( $this->directory . '/sess_*' );
		foreach ( false === $files ? array() : $files as $file ) {
			unlink( $file );
		}
		rmdir( $this->directory );
	}

	/** A ausência do armazenamento é detectada antes de tentar construir a classe. */
	public function testContextIsConsumedOnceAndSessionIsClosed(): void {
		self::assertTrue( class_exists( PhpOidcAttemptStore::class ) );
		$store = $this->store();
		$store->put( $this->context() );
		self::assertSame( PHP_SESSION_NONE, session_status() );
		self::assertSame( $this->context(), $store->consume() );
		self::assertSame( PHP_SESSION_NONE, session_status() );
		$this->expectException( RuntimeException::class );
		$store->consume();
	}

	/**
	 * A cookie limita a sessão à rota de autenticação.
	 *
	 * @param bool $secure Se a origem usa HTTPS.
	 */
	#[DataProvider( 'cookieSecurity' )]
	public function testSessionHasDedicatedRestrictedCookie( bool $secure ): void {
		$store = $this->store( $secure );
		$store->put( $this->context() );
		self::assertSame( 'ARN_OIDC_FLOW', session_name() );
		self::assertSame( '1', ini_get( 'session.use_strict_mode' ) );
		self::assertSame( '1', ini_get( 'session.use_only_cookies' ) );
		self::assertSame( '0', ini_get( 'session.use_trans_sid' ) );
		self::assertSame(
			array(
				'lifetime' => 0,
				'path'     => '/wp-login.php',
				'domain'   => '',
				'secure'   => $secure,
				'httponly' => true,
				'samesite' => 'Lax',
			),
			session_get_cookie_params()
		);
	}

	/**
	 * Fornece a segurança esperada para cada origem.
	 *
	 * @return array<string,array{bool}> HTTP local e HTTPS.
	 */
	public static function cookieSecurity(): array {
		return array(
			'https'      => array( true ),
			'http local' => array( false ),
		);
	}

	/** Uma sessão escolhida anteriormente não fixa a nova tentativa. */
	public function testPutRotatesAnExistingSessionIdentifier(): void {
		session_save_path( $this->directory );
		session_name( 'ARN_OIDC_FLOW' );
		session_id( 'attackerchosenid12345' );
		session_start();
		$_SESSION['unrelated_secret'] = 'must-not-survive';
		session_write_close();
		$this->store()->put( $this->context() );
		self::assertNotSame( 'attackerchosenid12345', session_id() );
		self::assertFileDoesNotExist( $this->directory . '/sess_attackerchosenid12345' );
		self::assertStringNotContainsString( 'must-not-survive', file_get_contents( $this->directory . '/sess_' . session_id() ) );
		self::assertSame( $this->context(), $this->store()->consume() );
	}

	/** Uma cookie sem ficheiro de sessão não é aceite como identificador fiável. */
	public function testUnknownSessionIdentifierIsRejectedByStrictMode(): void {
		session_id( 'nonexistingchosenid12345' );
		$this->store()->put( $this->context() );
		self::assertNotSame( 'nonexistingchosenid12345', session_id() );
	}

	/** As duas instâncias lêem os mesmos ficheiros e não reproduzem a tentativa. */
	public function testSeparateStoreInstancesCannotReplayConsumedContext(): void {
		$this->store()->put( $this->context() );
		self::assertSame( $this->context(), $this->store()->consume() );
		$this->expectException( RuntimeException::class );
		$this->store()->consume();
	}

	/** A cookie recupera a tentativa noutro pedido e a repetição falha. */
	public function testCookieBoundContextIsConsumedAcrossRealPhpProcesses(): void {
		$id = $this->phpRequest( '$store->put(' . var_export( $this->context(), true ) . '); echo session_id();' );
		self::assertMatchesRegularExpression( '/^[a-zA-Z0-9,-]+$/D', $id );
		$cookie = '$_COOKIE["ARN_OIDC_FLOW"] = ' . var_export( $id, true ) . '; ';
		self::assertSame( 'login', $this->phpRequest( $cookie . 'echo $store->consume()["purpose"];' ) );
		self::assertSame( 'rejected', $this->phpRequest( $cookie . 'try { $store->consume(); } catch (RuntimeException) { echo "rejected"; }' ) );
	}

	/** Não tenta enviar cookies quando o pedido já produziu saída. */
	public function testHeadersAlreadySentFailClosed(): void {
		self::assertSame( 'output-rejected', $this->phpRequest( 'echo "output-"; try { $store->put(' . var_export( $this->context(), true ) . '); } catch (RuntimeException) { echo "rejected"; }' ) );
		self::assertSame( array(), glob( $this->directory . '/sess_*' ) );
	}

	/** O prazo termina aos 300 segundos e uma tentativa expirada fica apagada. */
	public function testExpiredAttemptIsRemovedBeforeFailure(): void {
		$store = $this->store();
		$store->put( $this->context() );
		$this->now = 1301;
		try {
			$store->consume();
			self::fail( 'A tentativa expirada foi aceite.' );
		} catch ( RuntimeException ) {
			self::assertSame( PHP_SESSION_NONE, session_status() );
		}
		$this->now = 1000;
		$this->expectException( RuntimeException::class );
		$store->consume();
	}

	/** O segundo final autorizado ainda permite consumir o contexto. */
	public function testAttemptAtTtlBoundaryIsAccepted(): void {
		$store = $this->store();
		$store->put( $this->context() );
		$this->now = 1300;
		self::assertSame( $this->context(), $store->consume() );
	}

	/** Corrupção do ficheiro não preserva a tentativa para uma segunda chamada. */
	public function testMalformedPersistedAttemptIsDeleted(): void {
		$store = $this->store();
		$store->put( $this->context() );
		session_start();
		$_SESSION['arn_oidc_attempt']['issued_at'] = '1000';
		session_write_close();
		try {
			$store->consume();
			self::fail( 'O contexto corrompido foi aceite.' );
		} catch ( RuntimeException ) {
			self::assertSame( PHP_SESSION_NONE, session_status() );
		}
		$this->expectException( RuntimeException::class );
		$store->consume();
	}

	/**
	 * Recusa valores incompatíveis com o contrato antes de persistir.
	 *
	 * @param string $key   Campo a substituir.
	 * @param mixed  $value Valor recusado.
	 */
	#[DataProvider( 'invalidContexts' )]
	public function testPutRejectsInvalidContextWithoutPersistingSecrets( string $key, mixed $value ): void {
		$context         = $this->context();
		$context[ $key ] = $value;
		try {
			$this->store()->put( $context );
			self::fail( 'A tentativa inválida foi guardada.' );
		} catch ( RuntimeException $error ) {
			self::assertStringNotContainsString( 'private-test-secret', $error->getMessage() );
			self::assertSame( array(), glob( $this->directory . '/sess_*' ) );
		}
	}

	/**
	 * Fornece violações reais do contrato de armazenamento.
	 *
	 * @return array<string,array{string,mixed}> Tipos, dados futuros e valores secretos.
	 */
	public static function invalidContexts(): array {
		return array(
			'estado'           => array( 'state', '' ),
			'nonce'            => array( 'nonce', false ),
			'pkce'             => array( 'code_verifier', 'short' ),
			'timestamp string' => array( 'issued_at', '1000' ),
			'futuro'           => array( 'issued_at', 1001 ),
			'expirado'         => array( 'issued_at', 699 ),
			'finalidade'       => array( 'purpose', 'other' ),
			'conta negativa'   => array( 'target_user_id', -1 ),
			'actor string'     => array( 'actor_id', '0' ),
			'jwt'              => array( 'id_token', 'private-test-secret' ),
			'access token'     => array( 'access_token', 'private-test-secret' ),
			'refresh token'    => array( 'refresh_token', 'private-test-secret' ),
			'client secret'    => array( 'client_secret', 'private-test-secret' ),
		);
	}

	/** A associação conserva a autorização específica e hash de sessão do actor. */
	public function testLinkContextRequiresActorAndSessionHash(): void {
		$context                     = $this->context();
		$context['purpose']          = 'link';
		$context['target_user_id']   = 12;
		$context['actor_id']         = 7;
		$context['actor_token_hash'] = str_repeat( 'c', 64 );
		$this->store()->put( $context );
		self::assertSame( $context, $this->store()->consume() );
		unset( $context['actor_token_hash'] );
		$this->expectException( RuntimeException::class );
		$this->store()->put( $context );
	}

	/** Não usa nem encerra uma sessão iniciada por outra integração. */
	public function testActiveSessionFailsClosedWithoutChangingIt(): void {
		session_save_path( $this->directory );
		session_name( 'OTHER_PLUGIN' );
		session_start();
		$_SESSION['other'] = 'preserve';
		try {
			$this->store()->put( $this->context() );
			self::fail( 'A sessão de outra integração foi usada.' );
		} catch ( RuntimeException ) {
			self::assertSame( 'OTHER_PLUGIN', session_name() );
			self::assertSame( array( 'other' => 'preserve' ), $_SESSION );
			self::assertSame( PHP_SESSION_ACTIVE, session_status() );
		}
	}

	/** Uma pasta impossível não recai no armazenamento público por defeito. */
	public function testInvalidDirectoryFailsClosed(): void {
		$this->expectException( RuntimeException::class );
		new PhpOidcAttemptStore( $this->directory . '/missing/child', true );
	}

	/**
	 * Constrói o armazenamento com o relógio controlado.
	 *
	 * @param bool $secure Se a cookie exige HTTPS.
	 */
	private function store( bool $secure = true ): PhpOidcAttemptStore {
		return new PhpOidcAttemptStore( $this->directory, $secure, fn(): int => $this->now );
	}

	/**
	 * Prepara uma tentativa fictícia completa.
	 *
	 * @return array<string,mixed> Tentativa fictícia ligada ao browser.
	 */
	private function context(): array {
		return array(
			'state'          => str_repeat( 'a', 64 ),
			'nonce'          => str_repeat( 'b', 64 ),
			'code_verifier'  => 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
			'issued_at'      => 1000,
			'purpose'        => 'login',
			'target_user_id' => 0,
			'actor_id'       => 0,
		);
	}

	/**
	 * Executa outro pedido real com a mesma pasta e sem estado do PHPUnit.
	 *
	 * @param string $code Operação a executar no processo isolado.
	 * @return string Saída do pedido simulado.
	 */
	private function phpRequest( string $code ): string {
		$bootstrap = var_export( __DIR__ . '/bootstrap.php', true );
		$directory = var_export( $this->directory, true );
		$script    = 'require ' . $bootstrap . '; $store = new \\Arn\\Intranet\\Identity\\PhpOidcAttemptStore(' . $directory . ', true, static fn(): int => 1000); ' . $code;
		$process   = proc_open(
			array( PHP_BINARY, '-r', $script ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		self::assertIsResource( $process );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$errors = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		self::assertSame( '', $errors );
		self::assertSame( 0, proc_close( $process ) );
		return $output;
	}
}
