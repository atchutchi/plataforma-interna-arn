<?php
/** Calendar validation and immutable lifecycle.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Calendar\Event;
use Arn\Intranet\Calendar\EventContent;
use Arn\Intranet\Calendar\EventState;
use Arn\Intranet\F112\Audience;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Invalid dates and transitions cannot be silently normalised. */
final class CalendarModelTest extends TestCase {

	/** Canonical Gregorian time and exclusive all-day bounds. */
	public function test_time_dates_modes_and_https_are_strict(): void {
		$valid = $this->content();
		self::assertSame( 1894701600, $valid->start() );
		self::assertSame( 1894705200, $valid->end() );
		self::assertEquals( $valid, EventContent::fromArray( $valid->toArray() ) );
		$day = new EventContent( 'Dias', 'Descrição', Audience::all(), 'presencial', 'Sala A', '', true, null, null, '2032-02-29', '2032-03-02' );
		self::assertSame( 172800, $day->end() - $day->start() );
		$base = $valid->toArray();
		foreach ( array(
			array(
				'start_at' => '2030-02-29T10:00:00Z',
				'end_at'   => '2030-03-01T11:00:00Z',
			),
			array( 'start_at' => '2030-01-15T10:00:00+00:00' ),
			array( 'start_at' => '2030-01-15T24:00:00Z' ),
			array( 'start_at' => '2030-1-15T10:00:00Z' ),
			array( 'start_at' => "2030-01-15T10:00:00Z\0" ),
			array( 'end_at' => '2030-01-15T10:00:00Z' ),
			array( 'end_at' => '2030-01-14T11:00:00Z' ),
			array( 'start_date' => '2030-01-15' ),
			array( 'all_day' => true ),
			array( 'all_day' => 'true' ),
			array(
				'mode' => 'hybrid',
				'url'  => '',
			),
			array(
				'mode' => 'online',
				'url'  => '',
			),
			array( 'location' => '' ),
			array( 'url' => 'javascript:alert(1)' ),
			array( 'url' => 'https://user:password@example.invalid/meeting' ),
			array( 'url' => 'https://example.invalid/a%0d%0aHeader:x' ),
			array( 'url' => 'https://example.invalid/' . str_repeat( 'a', 2050 ) ),
			array( 'title' => "A\u{2029}B" ),
			array( 'unexpected' => true ),
		) as $change ) {
			$this->refused( static fn() => EventContent::fromArray( array_replace( $base, $change ) ) );
		}
		$this->refused( static fn() => new EventContent( 'A', 'B', Audience::all(), 'presencial', 'Sala', '', true, null, null, '2030-02-29', '2030-03-02' ) );
		$this->refused( static fn() => new EventContent( 'A', 'B', Audience::all(), 'presencial', 'Sala', '', true, null, null, '2032-02-29', '2032-02-29' ) );
		self::assertSame( 'online', ( new EventContent( 'A', 'B', Audience::all(), 'online', '', 'https://example.invalid/reuniao-f112', false, '2030-01-15T10:00:00Z', '2030-01-15T11:00:00Z', null, null ) )->mode );
	}

	/** Cancellation targets the last public version while a later draft stays private. */
	public function test_cancelling_a_revised_draft_audits_the_public_version(): void {
		$e = Event::draft( 1, 7, 'LAB-A', $this->content(), 10 )->publish( 7, 1, 11, 'Publicar V1.' )->revise( $this->content( 'Rascunho secreto' ), 8, 2, 12, 'Preparar V2.' )->cancel( 8, 3, 13, 'Cancelar publicação corrente.' );
		self::assertSame( 1, $e->history[3]['version'] );
		self::assertSame( 'Encontro', $e->published()->content->title );
		self::assertSame( 'Rascunho secreto', $e->latest()->content->title );
		self::assertEquals( $e, Event::fromArray( $e->toArray() ) );
	}

	/** A different competent manager never becomes the organiser. */
	public function test_public_version_survives_edit_cancel_is_terminal_except_archive(): void {
		$one = $this->content();
		$two = $this->content( 'Revisão' );
		$e   = Event::draft( 1, 7, 'LAB-A', $one, 10 )->publish( 8, 1, 11, 'Publicar.' );
		self::assertSame( $e, $e->revise( $one, 8, 2, 12, 'Sem alteração.' ) );
		$this->refused( static fn() => $e->revise( $one, 8, 1, 12, 'CAS antigo.' ) );
		$draft = $e->revise( $two, 8, 2, 12, 'Corrigir.' );
		self::assertSame( EventState::Draft, $draft->state );
		self::assertSame( 'Encontro', $draft->published()->content->title );
		self::assertSame( 7, $draft->author_id );
		$e = $draft->publish( 8, 3, 13, 'Publicar revisão.' )->cancel( 9, 4, 14, 'Motivo privado.' );
		self::assertSame( EventState::Cancelled, $e->state );
		self::assertSame( 14, $e->cancelled_at );
		self::assertSame( 'Revisão', $e->published()->content->title );
		self::assertEquals( $e, Event::fromArray( $e->toArray() ) );
		foreach ( array(
			static fn() => $e->publish( 7, 5, 15, 'Reactivar.' ),
			static fn() => $e->revise( $one, 7, 5, 15, 'Reactivar.' ),
			static fn() => $e->cancel( 7, 5, 15, 'Duplicar.' ),
			static fn() => Event::draft( 2, 7, 'LAB-B', $one, 10 ),
			static fn() => Event::draft( 2, 7, 'LAB-A', $one, 10 )->cancel( 7, 1, 11, 'Não publicado.' ),
		) as $operation ) {
			$this->refused( $operation );
		}
		$e = $e->archive( 9, 5, 15, 'Arquivar.' );
		self::assertNull( $e->published() );
		self::assertCount( 2, $e->versions );
		self::assertCount( 6, $e->history );
		$this->refused( static fn() => $e->archive( 9, 6, 16, 'Repetir.' ) );
		foreach ( array( array( 'revision' => PHP_INT_MAX ), array( 'revision' => '6' ), array( 'cancelled_at' => 13 ), array( 'extra' => true ), array( 'author_id' => 9 ) ) as $change ) {
			$this->refused( static fn() => Event::fromArray( array_replace( $e->toArray(), $change ) ) );
		}
	}

	/** Fictional hourly version.
	 *
	 * @param string $title Title.
	 * @return EventContent
	 */
	private function content( string $title = 'Encontro' ): EventContent {
		return new EventContent( $title, 'Descrição', Audience::unit( 'LAB-A' ), 'presencial', 'Sala de ensaio', '', false, '2030-01-15T10:00:00Z', '2030-01-15T11:00:00Z', null, null );
	}

	/** Assert no invalid transition succeeds.
	 *
	 * @param callable $operation Invalid operation.
	 */
	private function refused( callable $operation ): void {
		try {
			$operation();
			self::fail( 'Invalid calendar data accepted.' );
		} catch ( InvalidArgumentException ) {
			self::assertTrue( true );
		}
	}
}
