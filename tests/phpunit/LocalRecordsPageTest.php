<?php
/** Fronteiras de leitura WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Records;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\LocalGrantPresets;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use ArnWordPressDoubles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Doubles de fronteira junto dos ensaios exclusivos.

/** Ambiente efectivo. */
function wp_get_environment_type(): string {
	return LocalRecordsPageTest::$environment;
}
/** Actor corrente. */
function get_current_user_id(): int {
	return LocalRecordsPageTest::$actor;
}
/** Capacidade nativa.
 *
 * @param string $capability Capacidade.
 */
function current_user_can( string $capability ): bool {
	return 'read' === $capability && LocalRecordsPageTest::$read;
}
/** Token corrente. */
function wp_get_session_token(): string {
	return 'current';
}
/** Contabiliza cabeçalhos privados. */
function nocache_headers(): void {
	++LocalRecordsPageTest::$nocache;
}
/** Falha pública.
 *
 * @param string $message Mensagem.
 * @param string $title Título.
 * @param array  $args Opções.
 * @throws \RuntimeException Simula wp_die sem terminar PHPUnit.
 */
function wp_die( string $message, string $title = '', array $args = array() ): never {
	// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Excepção interna do double, sem saída HTML.
	throw new \RuntimeException( $message, $args['response'] ?? 0 );
}
/** Hook local.
 *
 * @param string   $hook Hook.
 * @param callable $callback Callback.
 */
function add_action( string $hook, callable $callback ): void {
	LocalRecordsPageTest::$hooks[ $hook ] = $callback;
}
/** Página nativa.
 *
 * @param string   $title Título.
 * @param string   $menu Menu.
 * @param string   $capability Capacidade.
 * @param string   $slug Slug.
 * @param callable $callback Callback.
 */
function add_management_page( string $title, string $menu, string $capability, string $slug, callable $callback ): string {
	LocalRecordsPageTest::$pages[ $slug ] = $capability;
	LocalRecordsPageTest::$hooks[ $slug ] = $callback;
	return 'tools_page_' . $slug;
}
/** URL administrativa.
 *
 * @param string $path Caminho.
 */
function admin_url( string $path ): string {
	return $path;
}
/** Escape textual.
 *
 * @param string $text Texto.
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
/** Escape de URL local.
 *
 * @param string $url URL.
 */
function esc_url( string $url ): string {
	return htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' );
}

/** Flags isoladas por processo. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class LocalRecordsPageTest extends TestCase {
	/** Ambiente.
	 *
	 * @var string
	 */
	public static string $environment = 'local';
	/** Actor.
	 *
	 * @var int
	 */
	public static int $actor = 7;
	/** Capacidade.
	 *
	 * @var bool
	 */
	public static bool $read = true;
	/** Cabeçalhos.
	 *
	 * @var int
	 */
	public static int $nocache = 0;
	/** Hooks.
	 *
	 * @var array
	 */
	public static array $hooks = array();
	/** Páginas.
	 *
	 * @var array
	 */
	public static array $pages = array();
	/** Prepara Ana exclusivamente em memória. */
	protected function setUp(): void {
		ArnWordPressDoubles::reset();
		ArnWordPressDoubles::$users[7]                              = new \WP_User( 7, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		ArnWordPressDoubles::$meta[7][ LocalFixtureUser::META_KEY ] = '1';
		ArnWordPressDoubles::$sessions[7]['current']                = array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 3,
		);
		$_GET = array();
	}
	/** Activa fronteiras reais. */
	private function enable(): void {
		define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', true );
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
	}
	/** Repositório apenas de leitura. */
	private function repository(): AuthorizationRepository {
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->method( 'find' )->with( 7 )->willReturn( new AuthorizationSnapshot( 7, AccountState::Active, 3, array( new StoredGrant( 1, LocalGrantPresets::grant( 'employee-own', 7, 0 ) ) ) ) );
		$repository->expects( self::never() )->method( 'installSchema' );
		$repository->expects( self::never() )->method( 'createGrant' );
		return $repository;
	}
	/** A lista não revela o inventário recusado ou inputs de autoridade. */
	public function testListUsesNativeSessionAndIgnoresAuthorityInputs(): void {
		$this->enable();
		$_GET       = array(
			'actor'         => 1,
			'owner'         => 1,
			'scope'         => 'Institutional',
			'session_valid' => true,
			'revision'      => 99,
		);
		$repository = $this->repository();
		$page       = new LocalRecordsPage( new LocalRecordAccess( static fn() => $repository ) );
		$page->register();
		( self::$hooks['admin_menu'] )();
		self::assertSame( 'read', self::$pages['arn-local-records'] );
		ob_start();
		$page->render();
		$html = ob_get_clean();
		self::assertStringContainsString( 'Registos disponíveis: 1', $html );
		self::assertStringContainsString( 'record=proprio', $html );
		self::assertStringNotContainsString( 'unidade', $html );
		self::assertSame( 1, self::$nocache );
		$_GET['record'] = 'proprio';
		ob_start();
		$page->render();
		self::assertStringContainsString( 'Voltar à lista', ob_get_clean() );
	}
	/** Indistinguibilidade da recusa e ausência.
	 *
	 * @param mixed $key Chave do browser.
	 */
	#[DataProvider( 'deniedKeys' )]
	public function testDeniedDetailHasSame404WithoutTargetData( mixed $key ): void {
		$this->enable();
		$_GET['record'] = $key;
		$repository     = $this->repository();
		$this->expectExceptionCode( 404 );
		$this->expectExceptionMessage( 'Registo não encontrado.' );
		( new LocalRecordsPage( new LocalRecordAccess( static fn() => $repository ) ) )->render();
	}
	/** Entradas inexistentes, recusadas e malformadas. */
	public static function deniedKeys(): array {
		return array( array( 'unidade-a' ), array( 'unidade-b' ), array( 'unknown' ), array( array( 'proprio' ) ), array( null ), array( '' ) );
	}
	/** Revalidação antes de abrir SQL.
	 *
	 * @param string $gate Condição removida após registo.
	 */
	#[DataProvider( 'gates' )]
	public function testGatesRecheckedBeforeReading( string $gate ): void {
		define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'fictional' === $gate ? 'true' : true );
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'storage' !== $gate );
		$access = new LocalRecordAccess(
			static function () {
				self::fail( 'Não deve abrir SQL.' );
			}
		);
		$page   = new LocalRecordsPage( $access );
		$page->register();
		if ( 'environment' === $gate ) {
			self::$environment = 'development';
		}
		if ( 'actor' === $gate ) {
			self::$actor = 1;
		}
		if ( 'login' === $gate ) {
			ArnWordPressDoubles::$users[7]->user_login = 'lab_admin';
		}
		if ( 'mark' === $gate ) {
			ArnWordPressDoubles::$meta[7][ LocalFixtureUser::META_KEY ] = 'yes';
		}
		if ( 'read' === $gate ) {
			self::$read = false;
		}
		$page->registerPage();
		self::assertSame( array(), self::$pages );
		self::assertNull( $access->read() );
		$this->expectExceptionCode( 404 );
		$page->render();
	}
	/** Fronteiras reais. */
	public static function gates(): array {
		return array_map( static fn( $gate ) => array( $gate ), array( 'fictional', 'storage', 'environment', 'actor', 'login', 'mark', 'read' ) );
	}
	/** A leitura falha fechada e nunca reutiliza decisões anteriores. */
	public function testCurrentSnapshotSessionAndSqlFailureAreNeverCached(): void {
		$this->enable();
		$repository = $this->repository();
		$access     = new LocalRecordAccess( static fn() => $repository );
		self::assertCount( 1, $access->read() );
		ArnWordPressDoubles::$sessions[7]['current']['arn_authz_revision'] = 2;
		self::assertNull( $access->read() );
		ArnWordPressDoubles::$sessions[7] = array();
		self::assertNull( $access->read() );
		self::assertNull(
			( new LocalRecordAccess(
				static function () {
					throw new \RuntimeException( 'SQL secret' );
				}
			) )->read()
		);
		$missing = $this->createMock( AuthorizationRepository::class );
		$missing->method( 'find' )->willReturn( null );
		self::assertNull( ( new LocalRecordAccess( static fn() => $missing ) )->read() );
	}
}
