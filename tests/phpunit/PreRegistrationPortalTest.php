<?php
/**
 * A conta preparada só pode consultar o início pessoal e terminar a sessão.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\PreRegistration\PreRegistrationPortal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting -- Matriz de superfícies, sem dados de pessoas reais.
final class PreRegistrationPortalTest extends TestCase {
	#[DataProvider( 'routes' )]
	public function testClosedRouteMatrix( string $method, string $path, array $query, string $expected ): void {
		self::assertSame( $expected, PreRegistrationPortal::route( $method, $path, $query, '/' ) );}
	public static function routes(): array {
		return array(
			array( 'GET', '/', array(), 'home' ),
			array( 'HEAD', '/', array(), 'home' ),
			array( 'POST', '/', array(), 'deny' ),
			array( 'GET', '/', array( 's' => 'fixture' ), 'deny' ),
			array( 'GET', '/', array( 'rest_route' => '/wp/v2/users' ), 'deny' ),
			array( 'GET', '/wp-admin/', array(), 'deny' ),
			array( 'GET', '/wp-json/wp/v2/users', array(), 'deny' ),
			array( 'POST', '/wp-admin/admin-ajax.php', array( 'action' => 'heartbeat' ), 'deny' ),
			array( 'GET', '/feed/', array(), 'deny' ),
			array( 'POST', '/xmlrpc.php', array(), 'deny' ),
			array( 'GET', '/pessoas/', array(), 'deny' ),
			array( 'GET', '/documentos/', array(), 'deny' ),
			array(
				'GET',
				'/wp-login.php',
				array(
					'action'   => 'logout',
					'_wpnonce' => 'fixture',
				),
				'logout',
			),
			array( 'GET', '/wp-login.php', array( 'action' => 'arn_google_link' ), 'deny' ),
			array( 'GET', '/wp-login.php', array( 'action' => array( 'logout' ) ), 'deny' ),
			array( 'GET', '//', array(), 'deny' ),
			array( 'GET', '/%2f', array(), 'deny' ),
		);
	}
	public function testSubdirectoryInstallDoesNotOpenOtherRoutes(): void {
		self::assertSame( 'home', PreRegistrationPortal::route( 'GET', '/intranet/', array(), '/intranet/' ) );
		self::assertSame( 'deny', PreRegistrationPortal::route( 'GET', '/', array(), '/intranet/' ) );
		self::assertSame( 'logout', PreRegistrationPortal::route( 'GET', '/intranet/wp-login.php', array( 'action' => 'logout' ), '/intranet/' ) );}
}
