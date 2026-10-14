<?php
/** Fronteira desprotegida recusa antes de abrir SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\Records\CurrentLocalAccount;
use Arn\Intranet\Notifications\LocalNotificationsPage;
use PHPUnit\Framework\TestCase;
/** Ensaios de POST sem autoridade. */
final class LocalNotificationsPageTest extends TestCase {
	/** Recusa pedidos sem autoridade antes de abrir SQL.
	 */
	public function testInvalidRequestsNeverOpenDatabase(): void {
		$page = new LocalNotificationsPage(
			new CurrentLocalAccount(
				static function () {
					self::fail( 'SQL não autorizado.' );
				},
				static fn()=>0
			),
			static function () {
				self::fail( 'SQL não autorizado.' );
			}
		);
		foreach ( array( 'GET', 'POST', 'PUT' ) as $method ) {
			self::assertFalse(
				$page->process(
					array(
						'event_id' => '1',
						'revision' => '1',
						'_wpnonce' => 'forged',
						'user_id'  => '99',
					),
					$method
				)
			);
		}
	}
}
