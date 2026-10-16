<?php
/** UTC semi-open agenda windows.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Calendar\AgendaSelection;
use Arn\Intranet\Calendar\Event;
use Arn\Intranet\Calendar\EventContent;
use Arn\Intranet\F112\Audience;
use PHPUnit\Framework\TestCase;

/** Events spanning multiple days appear once with their original interval. */
final class AgendaSelectionTest extends TestCase {
	/** CMS workflow filters are distinct from recipient time filters. */
	public function test_cms_workflow_is_filtered_without_becoming_a_public_selection(): void {
		$published = $this->event( 1, '2030-01-31T10:00:00Z', '2030-02-01T11:00:00Z' );
		$draft     = Event::draft( 2, 7, null, $published->latest()->content, 10 );
		$query     = AgendaSelection::parse( array( 'state' => 'Draft' ), true );
		self::assertSame( array( 2 ), $this->ids( $query->apply( array( $published, $draft ), 20, true ) ) );
		self::assertSame( 0, $query->apply( array( $published, $draft ), 20 )['total'] );
		self::assertNull( AgendaSelection::parse( array( 'state' => 'Draft' ) ) );
		self::assertNull( AgendaSelection::parse( array( 'state' => 'future' ), true ) );
	}

	/** Running/future, historical and cancelled sets are disjoint by default. */
	public function test_agenda_state_and_month_intersection(): void {
		$past      = $this->event( 1, '2030-01-14T10:00:00Z', '2030-01-15T10:00:00Z' );
		$running   = $this->event( 2, '2030-01-15T09:00:00Z', '2030-01-15T11:00:00Z' );
		$future    = $this->event( 3, '2030-01-31T10:00:00Z', '2030-02-02T11:00:00Z' );
		$boundary  = $this->event( 4, '2030-01-31T10:00:00Z', '2030-02-01T00:00:00Z' );
		$cancelled = $this->event( 5, '2030-01-31T10:00:00Z', '2030-02-01T11:00:00Z' )->cancel( 7, 2, 12, 'Cancelamento privado.' );
		$all       = array( $future, $past, $boundary, $running, $cancelled );
		$now       = 1894701600;
		self::assertSame( array( 2, 3, 4 ), $this->ids( AgendaSelection::parse( array() )->apply( $all, $now ) ) );
		self::assertSame( array( 1 ), $this->ids( AgendaSelection::parse( array( 'state' => 'history' ) )->apply( $all, $now ) ) );
		self::assertSame( array( 5 ), $this->ids( AgendaSelection::parse( array( 'state' => 'cancelled' ) )->apply( $all, $now ) ) );
		self::assertSame( array( 3 ), $this->ids( AgendaSelection::parse( array( 'month' => '2030-02' ) )->apply( $all, $now ) ) );
		self::assertSame( array( 2, 3, 4 ), $this->ids( AgendaSelection::parse( array( 'month' => '2030-01' ) )->apply( $all, $now ) ) );
		$day = Event::draft( 6, 7, null, new EventContent( 'Dias', 'B', Audience::all(), 'presencial', 'Sala', '', true, null, null, '2030-01-31', '2030-02-02' ), 10 )->publish( 7, 1, 11, 'Publicar.' );
		self::assertSame( array( 6 ), $this->ids( AgendaSelection::parse( array( 'month' => '2030-02' ) )->apply( array( $day ), $now ) ) );
		self::assertSame( 1, AgendaSelection::parse( array( 'month' => '2030-01' ) )->apply( array( $day ), $now )['total'] );
		foreach ( array( array( 'month' => '2030-13' ), array( 'month' => '2030-2' ), array( 'month' => '0000-01' ), array( 'page' => '01' ), array( 'page' => str_repeat( '9', 100 ) ), array( 'q' => array() ), array( 'q' => str_repeat( 'é', 101 ) ), array( 'state' => 'all' ), array( 'unit' => 'LAB-ROOT' ), array( 'unknown' => '1' ) ) as $bad ) {
			self::assertNull( AgendaSelection::parse( $bad ) );
		}
	}

	/** Canonical pagination and literal Unicode search. */
	public function test_pagination_and_literal_search(): void {
		$events = array();
		for ( $i = 1; $i <= 21; ++$i ) {
			$events[] = $this->event( $i, '2030-01-31T10:00:00Z', '2030-02-01T11:00:00Z' );
		}
		self::assertCount( 20, AgendaSelection::parse( array() )->apply( $events, 1894701600 )['items'] );
		self::assertCount(
			1,
			AgendaSelection::parse(
				array(
					'page' => '2',
					'q'    => '[Á]',
				)
			)->apply(
				$events,
				1894701600
			)['items']
		);
		self::assertSame( array(), AgendaSelection::parse( array( 'page' => (string) PHP_INT_MAX ) )->apply( $events, 1894701600 )['items'] );
		self::assertSame( 0, AgendaSelection::parse( array( 'q' => '.*' ) )->apply( $events, 1894701600 )['total'] );
	}

	/** Construct an independently timed fictional event.
	 *
	 * @param int    $id ID.
	 * @param string $start UTC start.
	 * @param string $end UTC end.
	 * @return Event
	 */
	private function event( int $id, string $start, string $end ): Event {
		return Event::draft( $id, 7, null, new EventContent( 'Evento [Á] ' . $id, 'Descrição', Audience::all(), 'presencial', 'Sala', '', false, $start, $end, null, null ), 10 )->publish( 7, 1, 11, 'Publicar.' );
	}

	/** Extract visible identifiers.
	 *
	 * @param array $result Authorised result.
	 * @return array
	 */
	private function ids( array $result ): array {
		return array_map( static fn( Event $event ): int => $event->id, $result['items'] );
	}
}
