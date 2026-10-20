<?php
/** Read-only summaries of the current authorised publications.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

use Arn\Intranet\Calendar\AgendaSelection;
use Arn\Intranet\Communications\NoticeSelection;
use Arn\Intranet\F112\Portal;
use Arn\Intranet\Tasks\{Portal as TaskPortal, TaskSelection, View as TaskView};
use Closure;
use Throwable;

// phpcs:disable Squiz.Commenting -- Sources retain object, audience, session and time authority.
final class HomeDashboard {
	private readonly Closure $source;
	public function __construct( ?Closure $source = null ) {
		$this->source = $source ?? static fn( string $module ): ?array => 'tasks_own' === $module ? ( new TaskPortal() )->listing( TaskSelection::pending(), 'own' ) : ( new Portal( $module ) )->listing(
			'communications' === $module ? NoticeSelection::parse( array() ) : AgendaSelection::parse( array() )
		);
	}

	/** The destinations come from ServiceDirectory, never request parameters. */
	public function model( array $destinations ): array {
		$result = array();
		foreach ( array( 'communications', 'calendar', 'tasks_own' ) as $module ) {
			$result[ $module ] = array(
				'available' => false,
				'url'       => '',
				'items'     => array(),
			);
			foreach ( $destinations as $destination ) {
				if ( ( $destination['id'] ?? null ) !== $module || ! is_string( $destination['url'] ?? null ) || '' === $destination['url'] ) {
					continue;
				}
				try {
					$list = ( $this->source )( $module );
					if ( ! is_array( $list ) || ! is_array( $list['items'] ?? null ) ) {
						break;
					}
					$items = array();
					foreach ( array_slice( $list['items'], 0, 3 ) as $item ) {
						if ( 'tasks_own' === $module ) {
							$items[] = self::taskItem( $item, $destination['url'] );
							continue;
						}
						if ( ! is_array( $item ) || ! is_int( $item['id'] ?? null ) || $item['id'] < 1 || ! is_string( $item['title'] ?? null ) || ! is_int( $item['published_at'] ?? null ) ) {
							throw new \UnexpectedValueException( 'Projecção indisponível.' );
						}
						$row = array(
							'title' => $item['title'],
							'url'   => $destination['url'] . ( str_contains( $destination['url'], '?' ) ? '&' : '?' ) . 'entry=' . $item['id'],
							'date'  => gmdate( 'd/m/Y', $item['published_at'] ),
						);
						if ( 'calendar' === $module ) {
							if ( ! is_int( $item['start'] ?? null ) || ! is_int( $item['end'] ?? null ) || ! is_bool( $item['all_day'] ?? null ) || ! is_bool( $item['cancelled'] ?? null ) || $item['end'] <= $item['start'] ) {
								throw new \UnexpectedValueException( 'Intervalo indisponível.' );
							}
							if ( $item['cancelled'] ) {
								continue;
							}
							$first       = gmdate( 'd/m/Y', $item['start'] );
							$last        = gmdate( 'd/m/Y', $item['end'] - 1 );
							$row['date'] = $item['all_day'] ? $first . ( $first === $last ? ' · Dia inteiro' : ' a ' . $last . ' · Dias inteiros' ) : gmdate( 'd/m/Y H:i', $item['start'] ) . ' a ' . gmdate( 'd/m/Y H:i', $item['end'] ) . ' UTC';
						}
						$items[] = $row;
					}
					$result[ $module ] = array(
						'available' => true,
						'url'       => $destination['url'],
						'items'     => $items,
					);
				} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A source failure is private and does not hide the other module.
					// Leave this independent section unavailable.
				}
				break;
			}
		}
		return $result;
	}

	private static function taskItem( mixed $item, string $base ): array {
		if ( ! is_array( $item ) || ! is_int( $item['id'] ?? null ) || $item['id'] < 1 || ! is_string( $item['title'] ?? null ) || ! in_array( $item['state'] ?? null, array( 'open', 'in_progress' ), true ) || ! is_string( $item['priority'] ?? null ) || ! isset( TaskView::PRIORITIES[ $item['priority'] ] ) || ! array_key_exists( 'due_at', $item ) || ( null !== $item['due_at'] && ! is_int( $item['due_at'] ) ) || ! is_bool( $item['overdue'] ?? null ) ) {
			throw new \UnexpectedValueException( 'Projecção indisponível.' );
		}
		return array(
			'title'    => $item['title'],
			'url'      => $base . ( str_contains( $base, '?' ) ? '&' : '?' ) . 'entry=' . $item['id'],
			'date'     => TaskView::date( $item['due_at'] ),
			'state'    => TaskView::STATES[ $item['state'] ],
			'priority' => TaskView::PRIORITIES[ $item['priority'] ],
			'overdue'  => $item['overdue'],
		);
	}
}
