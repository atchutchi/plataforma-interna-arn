<?php
/** Fictional request process and access regressions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Requests\{InternalRequest, RequestState, RequestType, RequestPolicy, Input, Configuration};
use Arn\Intranet\Authorization\{AccountState, Action, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, StoredGrant};
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\Tasks\Attachment;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting -- Focused domain invariants, no implementation-shaped assertions.
final class InternalRequestTest extends TestCase {
	private function type( int $revision = 1, int $reviewer = 9 ): RequestType {
		return new RequestType( 1, $revision, 'Pedido fictício de material', 'LAB-A', $reviewer, true, 'Assunto', 'Descrição', 'Apenas laboratório.', true );
	}
	private function draft(): InternalRequest {
		return InternalRequest::create( 1, 7, $this->type(), 'Pedido Alfa &amp; teste', 'Descrição fictícia', 100 );
	}
	private function submitted(): InternalRequest {
		return $this->draft()->change( 'submit', 7, array( 'type' => $this->type()->data() ), 101 );
	}
	private function actor( int $id, Role $role, string $unit = 'LAB-A', ?Scope $scope = null ): ActorContext {
		return new ActorContext(
			new AuthorizationSnapshot( $id, AccountState::Active, 2, array( new StoredGrant( 1, new Grant( $id, $role, $scope ?? ( Role::HumanResources === $role ? Scope::units( array( $unit ) ) : Scope::own() ), 0, null ) ) ) ),
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
	public function testFullReturnResubmitApprovalAndCancellationKeepHistory(): void {
		$r = $this->submitted()->change( 'start', 9, array(), 102 )->change( 'return', 9, array( 'reason' => 'Completar a descrição.' ), 103 );
		$r = $r->change(
			'edit',
			7,
			array(
				'title' => 'Pedido corrigido',
				'body'  => 'Descrição completa.',
			),
			104
		)->change( 'submit', 7, array( 'type' => $this->type( 2 )->data() ), 105 )->change( 'start', 9, array(), 106 )->change( 'approve', 9, array( 'reason' => 'Conforme no laboratório.' ), 107 )->change( 'cancel', 9, array( 'reason' => 'Cancelamento fictício.' ), 108 );
		self::assertSame( RequestState::Cancelled, $r->state );
		self::assertSame( 'approved', $r->history[7]['after']['state'] );
		self::assertSame( 'Pedido Alfa &amp; teste', $r->history[0]['after']['title'] );
		self::assertSame( $r->snapshot(), InternalRequest::restore( $r->history )->snapshot() );
		self::assertSame( 'LAB-PED-000001', $r->reference() );
	}
	public function testRHDraftAndUnsubmittedWithdrawalRemainPrivate(): void {
		$rh = $this->actor( 9, Role::HumanResources );
		self::assertTrue( RequestPolicy::own( $this->draft(), $this->actor( 7, Role::Employee ), 101 ) );
		self::assertFalse( RequestPolicy::read( $this->draft(), $rh, 101 ) );
		self::assertFalse( RequestPolicy::read( $this->draft()->change( 'withdraw', 7, array(), 101 ), $rh, 102 ) );
		self::assertTrue( RequestPolicy::read( $this->submitted(), $rh, 102 ) );
		self::assertFalse( RequestPolicy::read( $this->submitted(), $this->actor( 8, Role::HumanResources, 'LAB-B' ), 102 ) );
		self::assertFalse( RequestPolicy::read( $this->submitted(), $this->actor( 1, Role::TechnicalAdministrator ), 102 ) );
	}
	public function testInstitutionalScopeDoesNotRequireInventedMembership(): void {
		$rh = $this->actor( 9, Role::HumanResources, 'LAB-A', Scope::institutional() );
		self::assertTrue( RequestPolicy::manageUnit( 'LAB-B', $rh, 102 ) );
		self::assertTrue( RequestPolicy::configure( $rh, 102 ) );
		self::assertFalse( RequestPolicy::configure( $this->actor( 10, Role::HumanResources ), 102 ) );
	}
	public function testTimedSubstituteRequiresCurrentRHAndCannotOutliveItsWindow(): void {
		$r      = $this->submitted()->change(
			'substitute',
			9,
			array(
				'user'   => 10,
				'starts' => 110,
				'ends'   => 120,
				'reason' => 'Ausência fictícia.',
			),
			102
		);
		$deputy = $this->actor( 10, Role::HumanResources );
		self::assertNotContains( 'start', RequestPolicy::operations( $r, $deputy, 109 ) );
		self::assertContains( 'start', RequestPolicy::operations( $r, $deputy, 110 ) );
		self::assertNotContains( 'start', RequestPolicy::operations( $r, $deputy, 120 ) );
		self::assertNotContains( 'start', RequestPolicy::operations( $r, $this->actor( 10, Role::Employee ), 111 ) );
		$r = $r->change( 'start', 10, array(), 111 )->change( 'revoke_substitute', 9, array(), 112 );
		self::assertFalse( $r->reviewer( 10, 113 ) );
		self::assertTrue( $r->reviewer( 9, 113 ) );
		self::assertSame( $r->snapshot(), InternalRequest::restore( $r->history )->snapshot() );
	}
	public function testSelfApprovalAndForeignDecisionAreClosed(): void {
		$r = InternalRequest::create( 1, 9, $this->type(), 'Título', 'Corpo', 100 )->change( 'submit', 9, array( 'type' => $this->type()->data() ), 101 );
		self::assertNotContains( 'start', RequestPolicy::operations( $r, $this->actor( 9, Role::HumanResources ), 102 ) );
		self::assertNotContains( 'approve', RequestPolicy::operations( $r, $this->actor( 9, Role::HumanResources ), 102 ) );
		self::assertFalse( $r->reviewer( 9, 102 ) );
		$this->expectException( InvalidArgumentException::class );
		$r->change( 'start', 9, array(), 102 );
	}
	public function testAccountStateSessionGenerationAndExpiryCloseBothViews(): void {
		$good = $this->actor( 7, Role::Employee );
		foreach ( array( array_replace( $good->session, array( 'arn_authz_revision' => 1 ) ), array_replace( $good->session, array( 'expiration' => 101 ) ), array() ) as $session ) {
			self::assertFalse( RequestPolicy::read( $this->submitted(), new ActorContext( $good->snapshot, $session, $good->units, $good->active_units ), 102 ) );
		}
		$suspended = new AuthorizationSnapshot( 7, AccountState::Suspended, 2, $good->snapshot->stored_grants );
		self::assertFalse( RequestPolicy::read( $this->submitted(), new ActorContext( $suspended, $good->session, $good->units, $good->active_units ), 102 ) );
		self::assertFalse( RequestPolicy::read( $this->submitted(), new ActorContext( $good->snapshot, $good->session, $good->units, array( 'LAB-B' ) ), 102 ) );
	}
	public function testOfflineEligibilityDoesNotBecomeRouteProof(): void {
		$good    = $this->actor( 9, Role::HumanResources );
		$offline = new ActorContext( $good->snapshot, array(), $good->units, $good->active_units );
		self::assertTrue( RequestPolicy::eligible( $offline, 'LAB-A', 102, true ) );
		self::assertFalse( RequestPolicy::read( $this->submitted(), $offline, 102 ) );
	}
	public static function invalidChanges(): array {
		return array(
			array( 'approve', 9, array( 'reason' => 'Sem análise' ) ),
			array(
				'edit',
				9,
				array(
					'title' => 'Outro',
					'body'  => 'Texto',
				),
			),
			array( 'start', 10, array() ),
			array( 'withdraw', 9, array() ),
			array( 'start', 9, array( 'extra' => true ) ),
			array(
				'substitute',
				9,
				array(
					'user'   => 7,
					'starts' => 110,
					'ends'   => 120,
					'reason' => 'Autor proibido',
				),
			),
			array(
				'substitute',
				9,
				array(
					'user'   => 10,
					'starts' => 110,
					'ends'   => 4000000,
					'reason' => 'Excessivo',
				),
			),
			array( 'unknown', 9, array() ),
		);
	}
	#[DataProvider( 'invalidChanges' )]
	public function testIllegalTransitionsRefused( string $op, int $actor, array $fields ): void {
		$this->expectException( InvalidArgumentException::class );
		$this->submitted()->change( $op, $actor, $fields, 102 );
	}
	public function testDecisionRequiresReasonAndCannotReopen(): void {
		$r = $this->submitted()->change( 'start', 9, array(), 102 );
		try {
			$r->change( 'reject', 9, array( 'reason' => '' ), 103 );
			self::fail( 'Fundamentação em falta.' );
		} catch ( InvalidArgumentException ) {
			self::assertSame( RequestState::Analysis, $r->state );
		}
		$r = $r->change( 'reject', 9, array( 'reason' => 'Não adequado.' ), 103 );
		self::assertSame( $r->snapshot(), InternalRequest::restore( $r->history )->snapshot() );
		$this->expectException( InvalidArgumentException::class );
		$r->change( 'approve', 9, array( 'reason' => 'Segunda decisão.' ), 104 );
	}
	public function testHistoryTamperingFailsClosed(): void {
		$events                     = $this->submitted()->change( 'start', 9, array(), 102 )->history;
		$events[1]['after']['body'] = 'Texto substituído sem edição';
		$this->expectException( InvalidArgumentException::class );
		InternalRequest::restore( $events );
	}
	public function testAttachmentOnlyAuthorInEditableStateAndCorrectRevision(): void {
		$attachment = new Attachment( new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 100, 'application/pdf' ), 'Anexo fictício', 2 );
		$r          = $this->draft()->change( 'attach', 7, array( 'attachment' => $attachment->toArray() ), 101 );
		self::assertCount( 1, $r->attachments );
		self::assertSame( $r->snapshot(), InternalRequest::restore( $r->history )->snapshot() );
		$this->expectException( InvalidArgumentException::class );
		$r->change( 'attach', 7, array( 'attachment' => $attachment->toArray() ), 102 );
	}
	public function testTypeCannotInventInstitutionalDefaults(): void {
		$this->expectException( InvalidArgumentException::class );
		new RequestType( 1, 1, 'Reembolso de despesas', 'LAB-A', 9, true, 'Título', 'Descrição', '', true );
	}
	public function testFormDatesAreStrictUTCAndUnknownFieldsRejected(): void {
		$post   = array(
			'operation' => 'substitute',
			'nonce'     => 'test',
			'key'       => str_repeat( 'a', 32 ),
			'id'        => '1',
			'expected'  => '2',
			'user'      => '10',
			'starts'    => '2030-02-01T10:30',
			'ends'      => '2030-02-02T11:30',
			'reason'    => 'Teste',
		);
		$parsed = Input::post( $post );
		self::assertSame( '2030-02-01T10:30:00+00:00', gmdate( 'c', $parsed['starts'] ) );
		self::assertNull( Input::query( array( 'entry' => array( '1' ) ) ) );
		self::assertNull(
			Input::query(
				array(
					'entry' => '1',
					'new'   => '1',
				)
			)
		);
		$this->expectException( InvalidArgumentException::class );
		Input::post( $post + array( 'permissions' => 'admin' ) );
	}
	public function testProductionAndTruthySwitchesStayClosed(): void {
		self::assertTrue( Configuration::enabled( 'local', true, true, true, true ) );
		self::assertFalse( Configuration::enabled( 'production', true, true, true, true ) );
		self::assertFalse( Configuration::enabled( 'local', 'true', true, true, true ) );
		self::assertFalse( $this->actor( 1, Role::TechnicalAdministrator )->allows( Action::ManageInternalRequest, Scope::institutional(), 1, 102 ) );
	}
	public function testMalformedHistoryIsRejectedBeforeMissingOffsetAccess(): void {
		$events             = $this->draft()->history;
		$events[0]['after'] = array();
		$this->expectException( InvalidArgumentException::class );
		InternalRequest::restore( $events );
	}
	public function testTypeFormUsesNoWordPressNameQueryVariable(): void {
		$post   = array(
			'operation'    => 'type',
			'nonce'        => 'fixture',
			'key'          => str_repeat( 'a', 32 ),
			'id'           => '0',
			'expected'     => '0',
			'type_name'    => 'Pedido fictício de apoio',
			'unit'         => 'LAB-A',
			'reviewer'     => '9',
			'active'       => '1',
			'title_label'  => 'Assunto',
			'body_label'   => 'Descrição',
			'instructions' => '',
			'attachments'  => '1',
		);
		$parsed = Input::post( $post );
		self::assertSame( $post['type_name'], Input::fields( $parsed )['name'] );
		$post['name'] = $post['type_name'];
		unset( $post['type_name'] );
		$this->expectException( InvalidArgumentException::class );
		Input::post( $post );
	}
	public function testNativeMultilinePostIsCanonicalBeforeDomainValidation(): void {
		$post = Input::post(
			array(
				'operation'     => 'create',
				'nonce'         => 'fixture',
				'key'           => str_repeat( 'a', 32 ),
				'type_id'       => '1',
				'type_revision' => '1',
				'title'         => 'Pedido fictício',
				'body'          => "Primeiro parágrafo\r\nSegundo parágrafo",
			)
		);
		self::assertSame( "Primeiro parágrafo\nSegundo parágrafo", InternalRequest::create( 1, 7, $this->type(), $post['title'], $post['body'], 100 )->body );
		$post = Input::post(
			array(
				'operation'    => 'type',
				'nonce'        => 'fixture',
				'key'          => str_repeat( 'b', 32 ),
				'id'           => '0',
				'expected'     => '0',
				'type_name'    => 'Pedido fictício de apoio',
				'unit'         => 'LAB-A',
				'reviewer'     => '9',
				'active'       => '1',
				'title_label'  => 'Assunto',
				'body_label'   => 'Descrição',
				'instructions' => "Primeira linha\r\nSegunda linha",
				'attachments'  => '1',
			)
		);
		self::assertSame( "Primeira linha\nSegunda linha", ( new RequestType( 1, 1, ...Input::fields( $post ) ) )->instructions );
	}
	public function testPreviouslyAcceptedAttachmentsSurviveTypePolicyChange(): void {
		$attachment          = new Attachment( new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 100, 'application/pdf' ), 'Anexo fictício', 2 );
		$r                   = $this->draft()->change( 'attach', 7, array( 'attachment' => $attachment->toArray() ), 101 );
		$type                = $this->type( 2 )->data();
		$type['attachments'] = false;
		$r                   = $r->change( 'submit', 7, array( 'type' => $type ), 102 )->change( 'start', 9, array(), 103 )->change( 'return', 9, array( 'reason' => 'Corrigir texto' ), 104 );
		self::assertCount( 1, $r->attachments );
		self::assertSame( $r->snapshot(), InternalRequest::restore( $r->history )->snapshot() );
		$this->expectException( InvalidArgumentException::class );
		$r->change( 'attach', 7, array( 'attachment' => $attachment->toArray() ), 105 );
	}
}
