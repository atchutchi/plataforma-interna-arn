<?php
/** Fictional leave calculations and immutable lifecycle.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Leave\{Calendar, LeaveRequest, Balance, Input};
use Arn\Intranet\Requests\RequestState;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
final class LeaveDomainTest extends TestCase {
	private function now(): int {
		return strtotime( '2026-01-01 UTC' );}
	private function approved( int $id, string $start, string $end ): LeaveRequest {
		$c = new Calendar( 2026, 1, array() );
		return LeaveRequest::create( $id, 1, 'LAB-A', $start, $end, $c, $this->now() )->change( 'submit', 1, array( 'calendar' => $c->data() ), $this->now() )->change( 'start', 2, array(), $this->now() )->change( 'approve', 2, array( 'reason' => 'Decisão fictícia' ), $this->now() );}
	public function testFivePlusFifteenAndRemainingTwo(): void {
		$a   = $this->approved( 1, '2026-05-04', '2026-05-08' );
		$b   = $this->approved( 2, '2026-12-01', '2026-12-21' );
		$now = strtotime( '2026-10-26 UTC' );
		self::assertCount( 5, $a->days );
		self::assertCount( 15, $b->days );
		self::assertSame(
			array(
				'entitlement' => 22,
				'elapsed'     => 5,
				'future'      => 15,
				'available'   => 2,
			),
			Balance::summarize( array( $a, $b ), 1, 2026, $now )
		);
		self::assertSame( 22, Balance::summarize( array( $a, $b ), 2, 2026, $now )['available'] );
		self::assertSame( 22, Balance::summarize( array( $a, $b ), 1, 2027, $now )['available'] );
		$c = LeaveRequest::create( 3, 1, 'LAB-A', '2026-12-22', '2026-12-23', new Calendar( 2026, 1, array() ), $this->now() );
		Balance::assertApproval( $c, array( $a, $b ), $c->calendar, $this->now() );
		self::assertCount( 2, $c->days );
		$d = LeaveRequest::create( 4, 1, 'LAB-A', '2026-12-22', '2026-12-24', $c->calendar, $this->now() );
		$this->expectException( DomainException::class );
		Balance::assertApproval( $d, array( $a, $b ), $d->calendar, $this->now() );
	}
	public function testCalendarExcludesWeekendAndVersionedHoliday(): void {
		$c = new Calendar( 2026, 1, array( '2026-05-05' ) );
		self::assertSame( array( '2026-05-04', '2026-05-06', '2026-05-07', '2026-05-08' ), $c->days( '2026-05-02', '2026-05-10' ) );
		self::assertEquals( $c, Calendar::restore( $c->data() ) );
		foreach ( array( array( '2026-05-09', '2026-05-10' ), array( '2026-02-30', '2026-03-02' ), array( '2026-12-31', '2027-01-02' ), array( '2026-06-02', '2026-06-01' ) )as[$start, $end] ) {
			try {
				$c->days( $start, $end );
				self::fail( 'Invalid dates accepted' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );}
		}
	}
	public function testPendingOverlapAndNoReservation(): void {
		$c = new Calendar( 2026, 1, array() );
		$a = LeaveRequest::create( 1, 1, 'LAB-A', '2026-05-04', '2026-05-08', $c, $this->now() )->change( 'submit', 1, array( 'calendar' => $c->data() ), $this->now() );
		self::assertSame( 22, Balance::summarize( array( $a ), 1, 2026, $this->now() )['available'] );
		$b = LeaveRequest::create( 2, 1, 'LAB-A', '2026-05-07', '2026-05-08', $c, $this->now() );
		$this->expectException( DomainException::class );
		Balance::assertSubmission( $b, array( $a ), $this->now() );
	}
	public function testWithdrawalRejectionAndFutureCancellation(): void {
		$a         = $this->approved( 1, '2026-05-04', '2026-05-08' );
		$cancelled = $a->change( 'cancel', 2, array( 'reason' => 'Cancelamento fictício' ), $this->now() );
		self::assertSame( 22, Balance::summarize( array( $cancelled ), 1, 2026, $this->now() )['available'] );
		self::assertSame( RequestState::Cancelled, $cancelled->state );
		self::assertEquals( $cancelled, LeaveRequest::restore( $cancelled->history ) );
		$b = LeaveRequest::create( 2, 1, 'LAB-A', '2026-05-04', '2026-05-08', $a->calendar, $this->now() )->change( 'withdraw', 1, array(), $this->now() );
		Balance::assertSubmission( $a, array( $b ), $this->now() );
		self::assertSame( 22, Balance::summarize( array( $b ), 1, 2026, $this->now() )['available'] );
		$this->expectException( InvalidArgumentException::class );
		$a->change( 'cancel', 2, array( 'reason' => 'Recusar iniciado' ), strtotime( '2026-05-04 UTC' ) );
	}
	public function testApprovalRequiresCurrentCalendarButKeepsOriginalDays(): void {
		$a = $this->approved( 1, '2026-05-04', '2026-05-08' );
		$c = new Calendar( 2026, 2, array( '2026-05-05' ) );
		self::assertCount( 5, LeaveRequest::restore( $a->history )->days );
		$this->expectException( DomainException::class );
		Balance::assertApproval( $a, array(), $c, $this->now() );
	}
	public function testAutoApprovalAndTamperedHistoryRefused(): void {
		$a                          = $this->approved( 1, '2026-05-04', '2026-05-08' );
		$events                     = $a->history;
		$events[3]['after']['days'] = array();
		try {
			LeaveRequest::restore( $events );
			self::fail( 'Tampered days accepted' );
		} catch ( InvalidArgumentException ) {
			self::assertTrue( true );}
		$pending = LeaveRequest::restore( array_slice( $a->history, 0, 3 ) );
		$this->expectException( InvalidArgumentException::class );
		$pending->change( 'approve', 1, array( 'reason' => 'Autoaprovação' ), $this->now() );
	}
	public function testReturnAndResubmitRecalculateExplicitly(): void {
		$a         = $this->approved( 1, '2026-05-04', '2026-05-08' );
		$pending   = LeaveRequest::restore( array_slice( $a->history, 0, 3 ) );
		$returned  = $pending->change( 'return', 2, array( 'reason' => 'Actualizar calendário' ), $this->now() );
		$c         = new Calendar( 2026, 2, array( '2026-05-05' ) );
		$submitted = $returned->change( 'submit', 1, array( 'calendar' => $c->data() ), $this->now() );
		self::assertCount( 4, $submitted->days );
		self::assertSame( 2, $submitted->calendar->revision );
		self::assertEquals( $submitted, LeaveRequest::restore( $submitted->history ) );
	}
	public function testClosedInputRejectsNativeYearAndExtraFields(): void {
		self::assertNull( Input::query( array( 'year' => '2026' ) ) );
		self::assertNull(
			Input::query(
				array(
					'new'   => '1',
					'entry' => '1',
				)
			)
		);
		self::assertSame( 2026, Input::query( array( 'arn_leave_year' => '2026' ) )['year'] );
		$this->expectException( InvalidArgumentException::class );
		Input::post(
			array(
				'operation'         => 'create',
				'nonce'             => 'x',
				'key'               => str_repeat( 'a', 32 ),
				'unit'              => 'LAB-A',
				'starts'            => '2026-05-04',
				'ends'              => '2026-05-08',
				'calendar_revision' => '1',
				'owner'             => '2',
			)
		);
	}
}
