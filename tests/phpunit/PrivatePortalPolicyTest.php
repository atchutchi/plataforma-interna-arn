<?php
/**
 * O portal fecha tudo a quem não tem sessão, exceto a entrada.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Access\PrivatePortalPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Páginas, REST e AJAX sem sessão.
 */
final class PrivatePortalPolicyTest extends TestCase {

	/**
	 * Só a página de login é alcançável sem sessão.
	 *
	 * @param string $path     Caminho.
	 * @param bool   $is_login WordPress identificou o login.
	 * @param bool   $expected Permitido.
	 */
	#[DataProvider( 'frontendCases' )]
	public function testFrontendRequiresSession( string $path, bool $is_login, bool $expected ): void {
		self::assertSame( $expected, PrivatePortalPolicy::anonymousRequestAllowed( $path, $is_login, false ) );
	}

	/**
	 * Casos de frontend.
	 *
	 * @return array<string, array{0: string, 1: bool, 2: bool}>
	 */
	public static function frontendCases(): array {
		return array(
			'inicio'              => array( '/', false, false ),
			'pagina'              => array( '/noticias/', false, false ),
			'feed'                => array( '/feed/', false, false ),
			'sitemap'             => array( '/wp-sitemap.xml', false, false ),
			'autor'               => array( '/author/alguem/', false, false ),
			'ficheiro em uploads' => array( '/wp-content/uploads/2026/10/documento.pdf', false, false ),
			'login por caminho'   => array( '/wp-login.php', false, true ),
			'login com query'     => array( '/wp-login.php?redirect_to=%2F', false, true ),
			'login identificado'  => array( '/qualquer', true, true ),
			'rest sem sessao'     => array( '/wp-json/wp/v2/users', false, false ),
		);
	}

	/**
	 * A REST fica fechada sem sessão.
	 */
	public function testRestRequiresSession(): void {
		foreach ( array( '/wp/v2/users', '/wp/v2/posts', '/wp/v2/media/1', '/oembed/1.0/embed', '/', '' ) as $route ) {
			self::assertFalse( PrivatePortalPolicy::anonymousRestRouteAllowed( $route ), $route );
		}
	}

	/**
	 * Só o heartbeat da página de login passa em admin-ajax.php.
	 */
	public function testAjaxOnlyAllowsHeartbeat(): void {
		self::assertTrue( PrivatePortalPolicy::anonymousRequestAllowed( '/wp-admin/admin-ajax.php', false, true, 'heartbeat' ) );
		self::assertFalse( PrivatePortalPolicy::anonymousRequestAllowed( '/wp-admin/admin-ajax.php', false, true, 'query-attachments' ) );
		self::assertFalse( PrivatePortalPolicy::anonymousRequestAllowed( '/wp-admin/admin-ajax.php', false, true, '' ) );
	}

	/**
	 * As superfícies públicas desligadas incluem feeds, sitemaps, oEmbed e XML-RPC.
	 */
	public function testPublicSurfacesAreDisabled(): void {
		$surfaces = PrivatePortalPolicy::disabledPublicSurfaces();

		foreach ( array( 'feeds', 'sitemaps', 'oembed', 'xmlrpc' ) as $surface ) {
			self::assertContains( $surface, $surfaces );
		}
	}
}
