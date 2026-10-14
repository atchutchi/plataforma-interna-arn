<?php
/** Ensaios do formulário local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageProbeTest;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use ArnWordPressDoubles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthorizationStorageProbeTest.php';
require_once __DIR__ . '/LocalAccountSessionDefaultsTest.php';

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Fronteiras WordPress do formulário.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Excepções capturadas exclusivamente nos testes.
/** Actor simulado. */
function get_current_user_id(): int {
	return AuthorizationStorageProbeTest::$actor;
}
/** Fronteira do ensaio local.
 *
 * @param string $field Campo.
 * @param string $value Valor.
 */
function get_user_by( string $field, string $value ): mixed {
	return \Arn\Intranet\Authorization\Storage\get_user_by( $field, $value );
}
/** Fronteira do ensaio local.
 *
 * @param string $value Texto.
 */
function esc_html( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' );
}
/** Fronteira do ensaio local.
 *
 * @param string $value Texto.
 */
function esc_attr( string $value ): string {
	return esc_html( $value );
}
/** Fronteira do ensaio local.
 *
 * @param string $value URL.
 */
function esc_url( string $value ): string {
	return esc_html( $value );
}
/** Fronteira do ensaio local.
 *
 * @param string $path Caminho.
 */
function admin_url( string $path ): string {
	return 'https://example.test/wp-admin/' . $path;
}
/** Fronteira do ensaio local.
 *
 * @param string $value Texto.
 */
function wp_unslash( string $value ): string {
	return stripslashes( $value );
}
/** Fronteira do ensaio local.
 *
 * @param string $nonce Nonce.
 * @param string $action Acção.
 */
function wp_verify_nonce( string $nonce, string $action ): bool {
	return 'valid' === $nonce && 'arn-local-account-state' === $action;
}
/** Fronteira do ensaio local.
 *
 * @param string $action Acção.
 */
function wp_nonce_field( string $action ): void {
	echo '<input data-action=' . esc_attr( $action ) . ' name="_wpnonce" value="valid">';
}
/** Fronteira do ensaio local.
 *
 * @param string $text Texto.
 */
function submit_button( string $text ): void {
	echo '<button>' . esc_html( $text ) . '</button>';
}
/** Fronteira do ensaio local.
 *
 * @param string $message Mensagem.
 * @param string $title Título.
 * @param array  $args Argumentos.
 * @throws \RuntimeException Termina o pedido simulado.
 */
function wp_die( string $message, string $title = '', array $args = array() ): never {
	throw new \RuntimeException( $message, $args['response'] ?? 500 );
}
/** Fronteira do ensaio local.
 *
 * @param string $url URL.
 * @param int    $status Código.
 * @throws \RuntimeException Regista o redireccionamento simulado.
 */
function wp_safe_redirect( string $url, int $status ): never {
	throw new \RuntimeException( $url, $status );
}
/** Fronteira do ensaio local.
 *
 * @param string   $title Título.
 * @param string   $menu Menu.
 * @param string   $capability Capacidade.
 * @param string   $slug Slug.
 * @param callable $callback Callback.
 */
function add_management_page( string $title, string $menu, string $capability, string $slug, callable $callback ): void {
	AuthorizationStorageProbeTest::$pages[ $slug ] = $callback;
}

/** Cada teste tem flags isoladas. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class LocalAccountStatePageTest extends TestCase {
	/** Prepara exclusivamente Ana fictícia e actor administrativo. */
	protected function setUp(): void {
		ArnWordPressDoubles::reset();
		AuthorizationStorageProbeTest::$environment                 = 'local';
		AuthorizationStorageProbeTest::$capability                  = true;
		AuthorizationStorageProbeTest::$actor                       = 1;
		AuthorizationStorageProbeTest::$fixture                     = new \WP_User( 2, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		ArnWordPressDoubles::$users[2]                              = AuthorizationStorageProbeTest::$fixture;
		ArnWordPressDoubles::$meta[2][ LocalFixtureUser::META_KEY ] = '1';
		$_SERVER['REQUEST_METHOD']                                  = 'POST';
		$_POST = array(
			'action'   => 'arn-local-account-state',
			'_wpnonce' => 'valid',
			'revision' => '3',
			'state'    => 'Suspended',
			'reason'   => 'Ensaio autorizado',
			'user_id'  => '1',
		);
	}
	/** Activa flags explícitas. */
	private function enable(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
		define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', true );
	}
	/** O alvo, actor e referência são sempre internos e o serviço recebe CAS. */
	public function testValidPostUsesFixedFixtureAndRedirects303(): void {
		$this->enable();
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::once() )->method( 'changeState' )->with( 2, AccountState::Suspended, 3, self::callback( static fn( ChangeContext $context ): bool => 1 === $context->actor_id && 'LAB-ACCOUNT-STATE' === $context->decision_ref && 'Ensaio autorizado' === $context->reason && abs( time() - $context->occurred_at ) < 3 ) );
		$page = new LocalAccountStatePage( static fn() => $repository );
		$this->expectExceptionCode( 303 );
		$this->expectExceptionMessage( 'tools.php?page=arn-local-account-state&message=updated' );
		$page->handlePost();
	}
	/** Verifica entradas e condições efectivas.
	 *
	 * @param string $field Campo.
	 * @param mixed  $value Valor inválido.
	 */
	#[DataProvider( 'invalidInputs' )]
	public function testInvalidInputNeverOpensStorage( string $field, mixed $value ): void {
		$this->enable();
		if ( 'method' === $field ) {
			$_SERVER['REQUEST_METHOD'] = $value;
		} else {
			$_POST[ $field ] = $value;
		}
		$page = new LocalAccountStatePage(
			static function () {
				self::fail( 'Não deve abrir persistência.' );
			}
		);
		$this->expectException( \RuntimeException::class );
		$page->handlePost();
	}
	/** Entradas não canónicas nunca chegam ao serviço. */
	public static function invalidInputs(): array {
		return array( array( 'method', 'GET' ), array( '_wpnonce', 'bad' ), array( '_wpnonce', array() ), array( 'action', 'other' ), array( 'revision', '03' ), array( 'revision', '+3' ), array( 'revision', '0' ), array( 'revision', '3.0' ), array( 'revision', '9223372036854775808' ), array( 'revision', array() ), array( 'state', 'Pending' ), array( 'state', array() ), array( 'reason', '' ), array( 'reason', ' motivo' ), array( 'reason', "a\nb" ), array( 'reason', str_repeat( 'a', 501 ) ), array( 'reason', array() ) );
	}
	/** Verifica entradas e condições efectivas.
	 *
	 * @param string $gate Gate recusado.
	 */
	#[DataProvider( 'deniedGates' )]
	public function testGatesAndFixtureRejectBeforeStorage( string $gate ): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'storage' !== $gate );
		define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'fictional' !== $gate );
		if ( 'environment' === $gate ) {
			AuthorizationStorageProbeTest::$environment = 'development';
		}
		if ( 'capability' === $gate ) {
			AuthorizationStorageProbeTest::$capability = false;
		}
		if ( 'mark' === $gate ) {
			ArnWordPressDoubles::$meta = array();
		}
		if ( 'missing' === $gate ) {
			AuthorizationStorageProbeTest::$fixture = false;
		}
		$page = new LocalAccountStatePage(
			static function () {
				self::fail( 'Não deve abrir persistência.' );
			}
		);
		$this->expectException( \RuntimeException::class );
		$page->handlePost();
	}
	/** Gates reais, sem overrides no formulário. */
	public static function deniedGates(): array {
		return array_map( static fn( $gate ) => array( $gate ), array( 'storage', 'fictional', 'environment', 'capability', 'mark', 'missing' ) );
	}
	/** A revisão recusada não mostra detalhes internos. */
	public function testConflictRequiresReloadWithStaticError(): void {
		$this->enable();
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->method( 'changeState' )->willThrowException( new \RuntimeException( 'SQL private' ) );
		$page = new LocalAccountStatePage( static fn() => $repository );
		$this->expectExceptionCode( 409 );
		$this->expectExceptionMessage( 'Recarrega' );
		$page->handlePost();
	}
	/** GET só lê e o formulário nativo liga rótulos e nonce. */
	public function testRenderReadsOnlyAndEscapesNoArbitraryMessage(): void {
		$this->enable();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['message']           = '<script>bad</script>';
		$repository                = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::never() )->method( 'changeState' );
		$repository->expects( self::once() )->method( 'find' )->with( 2 )->willReturn( new AuthorizationSnapshot( 2, AccountState::Active, 3, array() ) );
		$page = new LocalAccountStatePage( static fn() => $repository );
		ob_start();
		$page->render();
		$html = ob_get_clean();
		self::assertStringContainsString( 'method="post"', $html );
		self::assertStringContainsString( 'name="revision" value="3"', $html );
		self::assertStringContainsString( 'name="_wpnonce"', $html );
		self::assertStringContainsString( 'for="arn-state-reason"', $html );
		self::assertStringContainsString( 'id="arn-state-reason"', $html );
		self::assertStringNotContainsString( 'name="user_id"', $html );
		self::assertStringNotContainsString( '<script>', $html );
		$page->register();
		$page->registerPage();
		self::assertArrayHasKey( 'admin_post_arn-local-account-state', ArnWordPressDoubles::$filters );
		self::assertArrayHasKey( 'arn-local-account-state', AuthorizationStorageProbeTest::$pages );
	}
}
