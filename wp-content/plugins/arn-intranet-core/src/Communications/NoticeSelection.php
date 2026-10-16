<?php
/** Server-side filtering of already authorised notices.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

/** Authorisation belongs before this selection, including before counting. */
final readonly class NoticeSelection {

	/** Validated query values.
	 *
	 * @param int    $page Canonical positive page.
	 * @param string $q Literal text.
	 * @param string $unit Closed unit filter.
	 * @param string $state Closed state filter, CMS only.
	 */
	private function __construct( public int $page, public string $q, public string $unit, public string $state ) {}

	/** Refuse structured, unknown and overflowing parameters.
	 *
	 * @param array $query Allowlisted query.
	 * @return self|null
	 */
	public static function parse( array $query ): ?self {
		if ( array_diff( array_keys( $query ), array( 'page', 'q', 'unit', 'state' ) ) ) {
			return null;
		}
		foreach ( $query as $value ) {
			if ( ! is_string( $value ) ) {
				return null;
			}
		}
		$page = $query['page'] ?? '1';
		if ( 1 !== preg_match( '/\A[1-9][0-9]*\z/', $page ) || strlen( $page ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $page ) === strlen( (string) PHP_INT_MAX ) && strcmp( $page, (string) PHP_INT_MAX ) > 0 ) || ! in_array( $query['unit'] ?? '', array( '', 'LAB-A', 'LAB-B' ), true ) || ( '' !== ( $query['state'] ?? '' ) && null === NoticeState::tryFrom( $query['state'] ) ) ) {
			return null;
		}
		try {
			$q = PlainText::value( $query['q'] ?? '', 100, false, false );
		} catch ( InvalidArgumentException ) {
			return null;
		}
		return new self( (int) $page, $q, $query['unit'] ?? '', $query['state'] ?? '' );
	}

	/** Public filtering reads only public fields, never the unpublished version.
	 *
	 * @param array $authorised Already-authorised aggregates.
	 * @param int   $now Clock.
	 * @param bool  $management Server-only choice for CMS selection.
	 * @return array
	 */
	public function apply( array $authorised, int $now, bool $management = false ): array {
		$items = array();
		foreach ( $authorised as $notice ) {
			if ( ! $notice instanceof Notice ) {
				continue;
			}
			$version = $management ? $notice->latest() : $notice->published( $now );
			if ( null === $version || ( '' !== $this->unit && $version->content->audience->unit_id !== $this->unit ) || ( $management && '' !== $this->state && $notice->state->value !== $this->state ) || ( ! $management && '' !== $this->state ) || ( '' !== $this->q && false === mb_stripos( $version->content->title . "\n" . $version->content->body, $this->q, 0, 'UTF-8' ) ) ) {
				continue;
			}
			$items[] = $notice;
		}
		usort(
			$items,
			static function ( Notice $a, Notice $b ): int {
				$order = ( $b->published_at ?? 0 ) <=> ( $a->published_at ?? 0 );
				return 0 !== $order ? $order : $b->id <=> $a->id;
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
