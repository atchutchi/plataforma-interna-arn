<?php
/** Closed native transport before any storage access.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Support\Controller;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting -- Protocol rejection must precede source access.
final class TicketsControllerTest extends TestCase {
	public function testInvalidTransportNeverTouchesStorage(): void {
		$source = new class() {
			public function access( string $view ): never {
				throw new \LogicException( 'Storage was reached.' );
			}
		};
		$post   = array(
			'operation' => 'claim',
			'nonce'     => 'invalid',
			'key'       => str_repeat( 'a', 32 ),
			'id'        => '1',
			'expected'  => '1',
		);
		$verify = static fn(): bool => false;
		foreach ( array(
			array( 'PATCH', 'team', array(), array() ),
			array( 'GET', 'own', array( 'entry' => array( '1' ) ), array() ),
			array( 'GET', 'own', array(), $post ),
			array( 'POST', 'team', array( 'entry' => '1' ), $post ),
			array( 'POST', 'team', array( 'entry' => '2' ), $post ),
			array( 'POST', 'team', array( 'entry' => '1' ), $post + array( 'role' => 'admin' ) ),
			array( 'GET', 'own', array( 'types' => '1' ), array() ),
		) as [$method, $view, $get, $body] ) {
			self::assertNull( Controller::decide( $method, $view, $get, $body, $source, 7, $verify ) );
		}
	}
}
