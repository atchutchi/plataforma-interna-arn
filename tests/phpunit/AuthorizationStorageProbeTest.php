<?php
/** Gates da persistência local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Access\LocalFixtureUser;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Doubles isolados dos gates WordPress.
/**
 * Simula wp_get_environment_type.
 */
function wp_get_environment_type(): string {
	return AuthorizationStorageProbeTest::$environment;
}
/**
 * Simula current_user_can.
 *
 * @param string $capability Valor simulado.
 */
function current_user_can( string $capability ): bool {
	return 'manage_options' === $capability && AuthorizationStorageProbeTest::$capability;
}
/**
 * Simula get_current_user_id.
 */
function get_current_user_id(): int {
	return AuthorizationStorageProbeTest::$actor;
}
/**
 * Simula get_user_by.
 *
 * @param string $field Valor simulado.
 * @param string $value Valor simulado.
 */
function get_user_by( string $field, string $value ): mixed {
	return 'login' === $field && LocalFixtureUser::LOGIN === $value ? AuthorizationStorageProbeTest::$fixture : false;
}
/**
 * Simula add_action.
 *
 * @param string   $hook Valor simulado.
 * @param callable $callback Valor simulado.
 */
function add_action( string $hook, callable $callback ): void {
	AuthorizationStorageProbeTest::$hooks[ $hook ] = $callback;
}
/**
 * Simula add_management_page.
 *
 * @param string   $title Valor simulado.
 * @param string   $menu Valor simulado.
 * @param string   $capability Valor simulado.
 * @param string   $slug Valor simulado.
 * @param callable $callback Valor simulado.
 */
function add_management_page( string $title, string $menu, string $capability, string $slug, callable $callback ): void {
	AuthorizationStorageProbeTest::$pages[ $slug ] = $callback;
}
/**
 * Simula esc_html.
 *
 * @param string $text Valor simulado.
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
/** Verifica gates e leitura sem iniciar o WordPress. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class AuthorizationStorageProbeTest extends TestCase {
	/**
	 * Estado simulado de environment.
	 *
	 * @var string
	 */
	public static string $environment = 'local';
	/**
	 * Estado simulado de capability.
	 *
	 * @var bool
	 */
	public static bool $capability = true;
	/**
	 * Estado simulado de actor.
	 *
	 * @var int
	 */
	public static int $actor = 1;
	/**
	 * Estado simulado de fixture.
	 *
	 * @var mixed
	 */
	public static mixed $fixture = false;
	/**
	 * Estado simulado de hooks.
	 *
	 * @var array
	 */
	public static array $hooks = array();
	/**
	 * Estado simulado de pages.
	 *
	 * @var array
	 */
	public static array $pages = array();
	/**
	 * Recusa gates inválidos antes de qualquer ligação.
	 *
	 * @param string $environment Ambiente.
	 * @param mixed  $flag Flag explícita.
	 * @param bool   $capability Capacidade.
	 */
	#[DataProvider( 'deniedGates' )]
	public function testGatesDenyBeforeFactory( string $environment, mixed $flag, bool $capability ): void {
		if ( null !== $flag ) {
			define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', $flag );
		}
		self::$environment = $environment;
		self::$capability  = $capability;
		$authority         = new LocalAuthorizationStorageAuthority();
		self::assertFalse( $authority->canManage( 1 ) );
		$probe = new AuthorizationStorageProbe(
			static function (): AuthorizationRepository {
				self::fail( 'A fábrica não pode ser chamada.' );
			}
		);
		$probe->register();
		$probe->registerPage();
		ob_start();
		$probe->render();
		self::assertSame( '', ob_get_clean() );
		self::assertSame( array(), self::$hooks );
		self::assertSame( array(), self::$pages );
	}
	/** Fornece combinações recusadas. */
	public static function deniedGates(): array {
		return array( array( 'production', true, true ), array( 'staging', true, true ), array( 'development', true, true ), array( 'local', null, true ), array( 'local', 'true', true ), array( 'local', 1, true ), array( 'local', false, true ), array( 'local', true, false ) );
	}
	/** Exige actor corrente e conta marcada. */
	public function testActorAndMarkedAccountAreRequired(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
		$authority = new LocalAuthorizationStorageAuthority();
		self::assertTrue( $authority->canManage( 1 ) );
		self::assertFalse( $authority->canManage( 2 ) );
		self::assertFalse( $authority->canManage( 0 ) );
		\ArnWordPressDoubles::$users[1] = new \WP_User( 1, 'lab_admin', 'admin@example.test' );
		\ArnWordPressDoubles::$users[2] = new \WP_User( 2, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		self::assertFalse( $authority->canUseAccount( 1 ) );
		self::assertFalse( $authority->canUseAccount( 2 ) );
		self::assertFalse( $authority->canUseAccount( 3 ) );
		\ArnWordPressDoubles::$meta[2][ LocalFixtureUser::META_KEY ] = '1';
		self::assertTrue( $authority->canUseAccount( 2 ) );
	}
	/** Revalida os gates entre registo e leitura. */
	public function testRecheckAndMissingFixtureAvoidFactory(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
		$probe = new AuthorizationStorageProbe(
			static function (): AuthorizationRepository {
				self::fail( 'Sem fixture não há ligação.' );
			}
		);
		$probe->register();
		self::assertArrayHasKey( 'admin_menu', self::$hooks );
		$probe->registerPage();
		self::assertArrayHasKey( 'arn-authorization-storage', self::$pages );
		ob_start();
		$probe->render();
		$html = ob_get_clean();
		self::assertStringContainsString( 'não está preparada', $html );
		self::assertStringNotContainsString( '<form', $html );
		self::$environment = 'production';
		self::$pages       = array();
		$probe->registerPage();
		ob_start();
		$probe->render();
		self::assertSame( '', ob_get_clean() );
		self::assertSame( array(), self::$pages );
		self::$environment = 'local';
		self::$capability  = false;
		ob_start();
		$probe->render();
		self::assertSame( '', ob_get_clean() );
	}
	/** A leitura ignora parâmetros externos e escapa histórico. */
	public function testReadOnlyMarkedFixtureAndControlledErrors(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
		self::$fixture                  = new \WP_User( 2, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		\ArnWordPressDoubles::$users[2] = self::$fixture;
		\ArnWordPressDoubles::$meta[2][ LocalFixtureUser::META_KEY ] = '1';
		$_GET['user_id']  = '1';
		$_POST['user_id'] = '1';
		$repository       = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::never() )->method( 'installSchema' );
		$repository->expects( self::once() )->method( 'find' )->with( 2 )->willReturn( new AuthorizationSnapshot( 2, \Arn\Intranet\Authorization\AccountState::Active, 3, array( new StoredGrant( 4, new \Arn\Intranet\Authorization\Grant( 2, \Arn\Intranet\Authorization\Role::Employee, \Arn\Intranet\Authorization\Scope::own(), 0, 1, true ) ) ) ) );
		$repository->expects( self::once() )->method( 'history' )->with( 2, 50 )->willReturn(
			array(
				array(
					'id'           => 1,
					'revision'     => 3,
					'event'        => 'grant_revoked',
					'actor_id'     => 1,
					'reason'       => '<script>',
					'decision_ref' => 'LAB-1',
					'occurred_at'  => 0,
					'new_json'     => 'secret-payload',
				),
			)
		);
		$probe = new AuthorizationStorageProbe( static fn(): AuthorizationRepository => $repository );
		ob_start();
		$probe->render();
		$html = ob_get_clean();
		self::assertStringContainsString( 'Conta fictícia ID: 2', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );
		self::assertStringNotContainsString( 'secret-payload', $html );
		self::assertStringContainsString( 'scope="row"', $html );
		self::assertSame( 2, substr_count( $html, '<caption>' ) );
		self::assertStringContainsString( '>Sim<', $html );
		self::assertStringNotContainsString( '<input', $html );
		self::assertStringNotContainsString( '<form', $html );
		$probe = new AuthorizationStorageProbe(
			static function (): AuthorizationRepository {
				throw new \RuntimeException( 'SQL email token' );
			}
		);
		ob_start();
		$probe->render();
		$html = ob_get_clean();
		self::assertStringContainsString( 'não está disponível', $html );
		self::assertStringNotContainsString( 'SQL email token', $html );
		unset( $_GET['user_id'], $_POST['user_id'] );
	}
	/** A marca é verificada novamente em cada leitura. */
	public function testUnmarkedFixtureAvoidsFactory(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
		self::$fixture                  = new \WP_User( 2, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		\ArnWordPressDoubles::$users[2] = self::$fixture;
		$probe                          = new AuthorizationStorageProbe(
			static function (): AuthorizationRepository {
				self::fail( 'Sem marca não há ligação.' );
			}
		);
		ob_start();
		$probe->render();
		self::assertStringContainsString( 'não está preparada', ob_get_clean() );
	}
}
