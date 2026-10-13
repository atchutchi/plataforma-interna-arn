<?php
/**
 * Gate efectivo do adaptador local, com WordPress simulado em memória.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Os doubles privados desta classe ficam no mesmo ficheiro de teste autorizado.
/** Devolve o ambiente efectivo simulado. */
function wp_get_environment_type(): string {
	return AuthorizationProbeTest::$environment;
}
/**
 * Devolve a capacidade efectiva simulada.
 *
 * @param string $capability Capacidade pedida.
 */
function current_user_can( string $capability ): bool {
	return 'manage_options' === $capability && AuthorizationProbeTest::$capability;
}
/**
 * Regista hooks em memória.
 *
 * @param string   $hook Hook pedido.
 * @param callable $callback Callback registado.
 */
function add_action( string $hook, callable $callback ): void {
	AuthorizationProbeTest::$hooks[ $hook ] = $callback;
}
/**
 * Regista páginas em memória.
 *
 * @param string   $title Título.
 * @param string   $menu Menu.
 * @param string   $capability Capacidade.
 * @param string   $slug Identificador.
 * @param callable $callback Callback.
 */
function add_management_page( string $title, string $menu, string $capability, string $slug, callable $callback ): void {
	AuthorizationProbeTest::$pages[ $slug ] = array( $title, $menu, $capability, $callback );
}
/**
 * Escapa texto como o WordPress.
 *
 * @param string $text Texto a escapar.
 */
function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}

/** Revalida ambiente, flag e capacidade em todos os pontos de entrada. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class AuthorizationProbeTest extends TestCase {
	/**
	 * Ambiente efectivo simulado.
	 *
	 * @var string
	 */
	public static string $environment = 'local';
	/**
	 * Capacidade efectiva simulada.
	 *
	 * @var bool
	 */
	public static bool $capability = true;
	/**
	 * Hooks registados em memória.
	 *
	 * @var array
	 */
	public static array $hooks = array();
	/**
	 * Páginas registadas em memória.
	 *
	 * @var array
	 */
	public static array $pages = array();
	/**
	 * Gates recusados não registam nem apresentam cenários.
	 *
	 * @param string $environment Ambiente.
	 * @param mixed  $flag Flag.
	 * @param bool   $capability Capacidade.
	 */
	#[DataProvider( 'deniedGates' )]
	public function testEffectiveGateDeniesRegistrationAndRendering( string $environment, mixed $flag, bool $capability ): void {
		if ( null !== $flag ) {
			define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE', $flag );
		}
		self::$environment = $environment;
		self::$capability  = $capability;
		$probe             = new AuthorizationProbe();
		$probe->register();
		$probe->registerPage();
		self::assertSame( array(), self::$hooks );
		self::assertSame( array(), self::$pages );
		ob_start();
		$probe->render();
		self::assertSame( '', ob_get_clean() );
	}
	/** Combinações que nunca podem abrir o laboratório. */
	public static function deniedGates(): array {
		return array( array( 'production', true, true ), array( 'staging', true, true ), array( 'development', true, true ), array( 'local', null, true ), array( 'local', false, true ), array( 'local', 'true', true ), array( 'local', 1, true ), array( 'local', true, false ) );
	}
	/** Gate autorizado apresenta tabela acessível sem formulários. */
	public function testLocalAdministratorSeesAccessibleReadOnlyResults(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE', true );
		$probe = new AuthorizationProbe();
		$probe->register();
		self::assertArrayHasKey( 'admin_menu', self::$hooks );
		( self::$hooks['admin_menu'] )();
		self::assertSame( 'manage_options', self::$pages['arn-authorization-probe'][2] );
		ob_start();
		$probe->render();
		$html = ob_get_clean();
		self::assertStringContainsString( '<caption>', $html );
		self::assertStringContainsString( 'scope="col"', $html );
		self::assertStringContainsString( 'scope="row"', $html );
		self::assertStringContainsString( 'dados fictícios', $html );
		self::assertStringContainsString( 'Permitido', $html );
		self::assertStringContainsString( 'Recusado', $html );
		self::assertSame( 15, substr_count( $html, '>Coincide<' ) );
		self::assertStringNotContainsString( '<form', $html );
		self::assertStringNotContainsString( '<input', $html );
	}
	/** Uma mudança efectiva após registo fecha a página e o menu. */
	public function testEffectiveGateIsRecheckedAfterRegistration(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE', true );
		$probe = new AuthorizationProbe();
		$probe->register();
		foreach ( array( array( 'production', true ), array( 'local', false ) ) as [$environment, $capability] ) {
			self::$environment = $environment;
			self::$capability  = $capability;
			$probe->registerPage();
			self::assertSame( array(), self::$pages );
			ob_start();
			$probe->render();
			self::assertSame( '', ob_get_clean() );
		}
	}
}
