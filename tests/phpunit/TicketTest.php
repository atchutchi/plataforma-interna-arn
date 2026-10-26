<?php
/** Support lifecycle and current authority.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Support\{Ticket, TicketState, TicketPolicy, Input};
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, StoredGrant};
use Arn\Intranet\F112\ActorContext;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting
final class TicketTest extends TestCase {
	private function ticket(): Ticket {
		return Ticket::create( 1, 7, 'LAB-A', 'Problema fictício &amp;', "Descrição\nsegunda linha", 'normal', 100 ); }
	private function actor( int $id, Role $role, string $unit = 'LAB-A', ?Scope $scope = null ): ActorContext {
		return new ActorContext(
			new AuthorizationSnapshot( $id, AccountState::Active, 2, array( new StoredGrant( 1, new Grant( $id, $role, $scope ?? ( Role::Support === $role ? Scope::units( array( $unit ) ) : Scope::own() ), 0, null ) ) ) ),
			array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => 2,
				'expiration'         => 1000,
			),
			array( $unit ),
			array( 'LAB-A', 'LAB-B' )
		);
	}
	private function deny( callable $operation ): void {
		try {
			$operation();
		} catch ( \InvalidArgumentException ) {
			self::assertTrue( true );
			return;
		} self::fail( 'Operação indevida aceite.' ); }
	public function testConversationAndSolutionReplayExactly(): void {
		$t = $this->ticket()->change( 'reply', 7, array( 'message' => 'Informação adicional' ), 101 )->change( 'claim', 9, array(), 102 )->change( 'reply', 9, array( 'message' => 'Em análise' ), 103 )->change( 'resolve', 9, array( 'message' => 'Solução fictícia' ), 104 );
		self::assertSame( TicketState::Resolved, $t->state );
		self::assertSame( 9, $t->assigned );
		self::assertSame( $t->snapshot(), Ticket::restore( $t->history )->snapshot() );
		self::assertTrue( $t->messages[2]['solution'] );
		self::assertSame( 'LAB-TI-000001', $t->reference() );
		self::assertSame( 'new', $t->history[0]['after']['state'] );
		$this->deny( fn() => $t->change( 'reply', 7, array( 'message' => 'Após resolução' ), 105 ) );
		$this->deny( fn() => $t->change( 'reopen', 9, array(), 105 ) );
	}
	public function testClaimsAndSolutionsCannotBeTakenOverOrSelfApproved(): void {
		$t = $this->ticket();
		$this->deny( fn() => $t->change( 'claim', 7, array(), 101 ) );
		$this->deny( fn() => $t->change( 'resolve', 9, array( 'message' => 'Prematura' ), 101 ) );
		$this->deny( fn() => $t->change( 'reply', 9, array( 'message' => 'Sem assumir' ), 101 ) );
		$t = $t->change( 'claim', 9, array(), 101 );
		$this->deny( fn() => $t->change( 'claim', 10, array(), 102 ) );
		$this->deny( fn() => $t->change( 'resolve', 10, array( 'message' => 'Outro técnico' ), 102 ) );
		$this->deny( fn() => $t->change( 'resolve', 9, array( 'message' => '' ), 102 ) );
	}
	public function testConversationLimitPreservesOneSlotForTheSolution(): void {
		$t = $this->ticket()->change( 'claim', 9, array(), 101 );
		for ( $i = 0; $i < 49; ++$i ) {
			$t = $t->change( 'reply', 7, array( 'message' => 'Resposta ' . $i ), 102 + $i ); }
		$this->deny( fn() => $t->change( 'reply', 7, array( 'message' => 'Excesso' ), 200 ) );
		self::assertSame( array( 'attach', 'resolve' ), TicketPolicy::operations( $t, $this->actor( 9, Role::Support ), 200 ) );
		self::assertCount( 50, $t->change( 'resolve', 9, array( 'message' => 'Solução' ), 200 )->messages );
	}
	public function testForgedReplayIsRejected(): void {
		$t = $this->ticket()->change( 'claim', 9, array(), 101 );
		foreach ( array(
			'owner'    => 8,
			'title'    => 'Outra versão',
			'assigned' => 10,
			'revision' => 8,
		) as $field => $value ) {
			$history                       = $t->history;
			$history[1]['after'][ $field ] = $value;
			$this->deny( fn() => Ticket::restore( $history ) ); }
	}
	public function testCurrentUnitRoleSessionAndAssignmentAreAllRequired(): void {
		$t = $this->ticket();
		self::assertTrue( TicketPolicy::own( $t, $this->actor( 7, Role::Employee ), 101 ) );
		self::assertFalse( TicketPolicy::read( $t, $this->actor( 8, Role::Employee ), 101 ) );
		self::assertFalse( TicketPolicy::read( $t, $this->actor( 9, Role::Support, 'LAB-B' ), 101 ) );
		self::assertTrue( TicketPolicy::team( $t, $this->actor( 9, Role::Support, 'LAB-B', Scope::institutional() ), 101 ) );
		self::assertFalse( TicketPolicy::team( $t, $this->actor( 9, Role::TechnicalAdministrator, 'LAB-A', Scope::institutional() ), 101 ) );
		self::assertFalse( TicketPolicy::own( $t, $this->actor( 7, Role::Employee ), 1001 ) );
		self::assertSame( array( 'claim' ), TicketPolicy::operations( $t, $this->actor( 9, Role::Support ), 101 ) );
		$t = $t->change( 'claim', 9, array(), 101 );
		self::assertSame( array(), TicketPolicy::operations( $t, $this->actor( 10, Role::Support ), 102 ) );
		self::assertSame( array( 'reply', 'attach', 'resolve' ), TicketPolicy::operations( $t, $this->actor( 9, Role::Support ), 102 ) );
	}
	public function testInputNormalizesNativeLineEndingsAndClosesFields(): void {
		$post = array(
			'operation' => 'reply',
			'nonce'     => 'nonce',
			'key'       => str_repeat( 'a', 32 ),
			'id'        => '1',
			'expected'  => '2',
			'message'   => "Primeira\r\nSegunda",
		);
		self::assertSame( "Primeira\nSegunda", Input::post( $post )['message'] );
		$this->deny( fn() => Input::post( $post + array( 'assigned' => '99' ) ) );
		self::assertNull( Input::query( array( 'state' => 'closed' ) ) );
		self::assertNull( Input::query( array( 'priority' => 'urgent' ) ) );
	}
	public function testQueueAcceptsOnlyClosedTicketAttachmentIntent(): void {
		foreach ( array( 'own', 'team' ) as $view ) {
			$intent = \Arn\Intranet\Media\ConsumerJobs::intent(
				'tickets',
				1,
				2,
				'Anexo fictício',
				array(
					'label' => 'PDF fictício',
					'view'  => $view,
				)
			);
			\Arn\Intranet\Media\ConsumerIntent::validate( $intent, 1 );
			self::assertSame( 'attach', $intent['operation'] );
		}
		$intent['payload']['view'] = 'admin';
		$this->expectException( \RuntimeException::class );
		\Arn\Intranet\Media\ConsumerIntent::validate( $intent, 1 );
	}
}
