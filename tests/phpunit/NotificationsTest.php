<?php
/** Contratos puros de notificações.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Notifications\Notification;
use PHPUnit\Framework\TestCase;
/** Não apresenta detalhes de auditoria. */
final class NotificationsTest extends TestCase {
	/** Recusa eventos fora do catálogo.
	 */
	public function testClosedMessages(): void {
		self::assertSame( 'O estado da tua conta foi alterado.', Notification::title( 'state_changed' ) );
		self::assertSame( 'Foi criado um acesso para a tua conta.', Notification::title( 'grant_created' ) );
		self::assertNull( Notification::title( 'account_created' ) );
		self::assertNull( Notification::title( '<script>' ) );
	}
	/** Recusa formatos ambíguos e overflow.
	 */
	public function testCanonicalPositiveIds(): void {
		foreach ( array( null, '', '0', '-1', '01', '+1', '1.0', array(), 1, str_repeat( '9', 30 ) ) as $invalid ) {
			self::assertNull( Notification::positive( $invalid ) );
		}
		self::assertSame( 23, Notification::positive( '23' ) );
		self::assertSame( PHP_INT_MAX, Notification::positive( (string) PHP_INT_MAX ) );
	}
	/** Exclui actor e motivos do envelope público.
	 */
	public function testProjectionContainsOnlyPublicFields(): void {
		self::assertSame(
			array(
				'id'          => 7,
				'occurred_at' => 123,
				'title'       => 'O estado da tua conta foi alterado.',
				'read_at'     => null,
			),
			Notification::project(
				(object) array(
					'id'          => '7',
					'occurred_at' => '123',
					'event'       => 'state_changed',
					'read_at'     => null,
					'reason'      => 'secret',
					'actor_id'    => 99,
				)
			)
		);
	}
}
