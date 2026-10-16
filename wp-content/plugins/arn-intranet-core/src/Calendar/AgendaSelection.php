<?php
/** UTC list agenda over authorised records.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Calendar;

use Arn\Intranet\F112\PlainText;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** Select and count only after authorisation has been applied. */
final readonly class AgendaSelection {

	/** Validated filters.
	 *
	 * @param int      $page Page.
	 * @param string   $q Literal query.
	 * @param string   $unit Unit filter.
	 * @param string   $state future/history/cancelled.
	 * @param string   $month Canonical month or empty.
	 * @param int|null $window_start UTC month start.
	 * @param int|null $window_end Exclusive month end.
	 */
	private function __construct( public int $page, public string $q, public string $unit, public string $state, public string $month, public ?int $window_start, public ?int $window_end ) {}

	/** Closed scalar query format with canonical pagination and Gregorian month.
	 *
	 * @param array $query Allowlisted query.
	 * @return self|null
	 */
	public static function parse( array $query ): ?self {
		if ( array_diff( array_keys( $query ), array( 'page', 'q', 'unit', 'state', 'month' ) ) ) {
			return null;
		}
		foreach ( $query as $value ) {
			if ( ! is_string( $value ) ) {
				return null;
			}
		}
		$page  = $query['page'] ?? '1';
		$month = $query['month'] ?? '';
		$start = null;
		$end   = null;
		if ( 1 !== preg_match( '/\A[1-9][0-9]*\z/', $page ) || strlen( $page ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $page ) === strlen( (string) PHP_INT_MAX ) && strcmp( $page, (string) PHP_INT_MAX ) > 0 ) || ! in_array( $query['unit'] ?? '', array( '', 'LAB-A', 'LAB-B' ), true ) || ! in_array( $query['state'] ?? 'future', array( 'future', 'history', 'cancelled' ), true ) ) {
			return null;
		}
		if ( '' !== $month ) {
			if ( 1 !== preg_match( '/\A(?!0000)[0-9]{4}-(?:0[1-9]|1[0-2])\z/', $month ) ) {
				return null;
			}
			$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', new DateTimeZone( 'UTC' ) );
			if ( false === $date || $date->format( 'Y-m' ) !== $month ) {
				return null;
			}
			$start = $date->getTimestamp();
			$end   = $date->modify( '+1 month' )->getTimestamp();
		}
		try {
			$q = PlainText::value( $query['q'] ?? '', 100, false, false );
		} catch ( InvalidArgumentException ) {
			return null;
		}
		return new self( (int) $page, $q, $query['unit'] ?? '', $query['state'] ?? 'future', $month, $start, $end );
	}

	/** Half-open intersection, one row per event and no private-version search.
	 *
	 * @param array $authorised Already-authorised aggregates.
	 * @param int   $now Server clock.
	 * @param bool  $management Server-only CMS mode.
	 * @return array
	 */
	public function apply( array $authorised, int $now, bool $management = false ): array {
		$items = array();
		foreach ( $authorised as $event ) {
			if ( ! $event instanceof Event ) {
				continue;
			}
			$version = $management ? $event->latest() : $event->published();
			if ( null === $version || ( ! $management && $now < $event->published_at ) ) {
				continue;
			}
			$content = $version->content;
			if ( ! $management && ( ( 'cancelled' === $this->state ) !== ( EventState::Cancelled === $event->state ) || ( 'future' === $this->state && $content->end() <= $now ) || ( 'history' === $this->state && $content->end() > $now ) ) ) {
				continue;
			}
			if ( ( '' !== $this->unit && $content->audience->unit_id !== $this->unit ) || ( null !== $this->window_start && ( $content->start() >= $this->window_end || $content->end() <= $this->window_start ) ) || ( '' !== $this->q && false === mb_stripos( $content->title . "\n" . $content->description . "\n" . $content->location, $this->q, 0, 'UTF-8' ) ) ) {
				continue;
			}
			$items[] = $event;
		}
		usort(
			$items,
			static function ( Event $a, Event $b ) use ( $management ): int {
				$one   = $management ? $a->latest() : $a->published();
				$two   = $management ? $b->latest() : $b->published();
				$order = $one->content->start() <=> $two->content->start();
				return 0 !== $order ? $order : $a->id <=> $b->id;
			}
		);
		$total = count( $items );
		$pages = intdiv( $total, 20 ) + ( 0 !== $total % 20 ? 1 : 0 );
		return array(
			'items' => $this->page > $pages ? array() : array_slice( $items, ( $this->page - 1 ) * 20, 20 ),
			'total' => $total,
			'page'  => $this->page,
			'pages' => $pages,
		);
	}
}
