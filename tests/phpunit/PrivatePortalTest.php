<?php
/**
 * Regressões da guarda REST depois da validação de cookies do WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Access\PrivatePortal;
use ArnWordPressDoubles;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Um resultado true do core não substitui a presença de um utilizador autenticado.
 */
final class PrivatePortalTest extends TestCase {

	/**
	 * Inicia cada cenário sem utilizador autenticado.
	 */
	protected function setUp(): void {
		ArnWordPressDoubles::reset();
		$GLOBALS['wp'] = (object) array( 'query_vars' => array( 'rest_route' => '/wp/v2/posts' ) );
	}

	/**
	 * Remove apenas o contexto de rota criado por estes testes.
	 */
	protected function tearDown(): void {
		unset( $GLOBALS['wp'] );
	}

	/**
	 * Sem nonce, o core retira o utilizador e devolve true. O portal deve recusar.
	 */
	public function testCoreSuccessWithoutAnAuthenticatedUserDoesNotOpenRest(): void {
		$result = PrivatePortal::requireSessionForRest( true );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'arn_rest_login_required', $result->get_error_code() );
		self::assertSame( array( 'status' => 401 ), $result->get_error_data() );
	}

	/**
	 * O pedido anónimo normal também não passa.
	 */
	public function testAnonymousRestRequestIsRefused(): void {
		self::assertInstanceOf( WP_Error::class, PrivatePortal::requireSessionForRest( null ) );
	}

	/**
	 * Um erro de nonce ou de credenciais conserva o código e o estado HTTP.
	 */
	public function testPreviousRestAuthenticationErrorIsPreserved(): void {
		$error = new WP_Error( 'rest_cookie_invalid_nonce', 'Nonce inválido.', array( 'status' => 403 ) );

		self::assertSame( $error, PrivatePortal::requireSessionForRest( $error ) );
		ArnWordPressDoubles::$logged_in = true;
		self::assertSame( $error, PrivatePortal::requireSessionForRest( $error ) );
	}

	/**
	 * A guarda mantém o editor CMS quando o utilizador continua autenticado.
	 */
	public function testAuthenticatedRestRequestKeepsThePreviousResult(): void {
		ArnWordPressDoubles::$logged_in = true;

		self::assertTrue( PrivatePortal::requireSessionForRest( true ) );
		self::assertNull( PrivatePortal::requireSessionForRest( null ) );
	}
}
