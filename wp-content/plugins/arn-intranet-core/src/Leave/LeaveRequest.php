<?php
/** Immutable leave request reconstructed from its complete event history.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use Arn\Intranet\Requests\RequestState;
use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.YodaConditions -- Closed replayable local domain.
final readonly class LeaveRequest {
	private function __construct( public int $id, public int $owner, public string $unit, public int $revision, public RequestState $state, public string $starts, public string $ends, public Calendar $calendar, public array $days, public int $updated_at, public array $history ) {}
	public static function create( int $id, int $owner, string $unit, string $starts, string $ends, Calendar $calendar, int $now ): self {
		if ( $id < 1 || $owner < 1 || ! in_array( $unit, array( 'LAB-A', 'LAB-B' ), true ) || $now < 0 ) {
			self::deny();
		}
		self::future( $starts, $now );
		$days = $calendar->days( $starts, $ends );
		$r    = new self( $id, $owner, $unit, 0, RequestState::Draft, $starts, $ends, $calendar, $days, $now, array() );
		return $r->record( 'create', $owner, $now, '', $r->snapshot() );
	}
	public function reference(): string {
		return 'LAB-FER-' . str_pad( (string) $this->id, 6, '0', STR_PAD_LEFT );}
	public function title(): string {
		return 'Férias de ' . $this->starts . ' a ' . $this->ends;}
	public function change( string $operation, int $actor, array $fields, int $now ): self {
		if ( $actor < 1 || $now < $this->updated_at || $this->revision >= 1000 ) {
			self::deny();
		}
		$keys = match ( $operation ) {
			'edit'=>array( 'starts', 'ends', 'calendar' ), 'submit'=>array( 'calendar' ), 'start', 'withdraw'=>array(), 'return', 'approve', 'reject', 'cancel'=>array( 'reason' ), default=>null};
		if ( null === $keys || array_keys( $fields ) !== $keys ) {
			self::deny();
		}
		$own = in_array( $operation, array( 'edit', 'submit', 'withdraw' ), true );
		if ( $own ? $actor !== $this->owner : $actor === $this->owner ) {
			self::deny();
		}
		$next   = $this->snapshot();
		$reason = '';
		if ( isset( $fields['reason'] ) ) {
			if ( ! is_string( $fields['reason'] ) ) {
				self::deny();
			}$reason = PlainText::value( $fields['reason'], 1000, true );}
		switch ( $operation ) {
			case 'edit':
				self::require( $this->state->editable() );
				if ( ! is_string( $fields['starts'] ) || ! is_string( $fields['ends'] ) || ! is_array( $fields['calendar'] ) ) {
					self::deny();
				}
				$calendar = Calendar::restore( $fields['calendar'] );
				self::future( $fields['starts'], $now );
				$next['starts']   = $fields['starts'];
				$next['ends']     = $fields['ends'];
				$next['calendar'] = $calendar->data();
				$next['days']     = $calendar->days( $fields['starts'], $fields['ends'] );
				break;
			case 'submit':
				self::require( $this->state->editable() );
				self::future( $this->starts, $now );
				if ( ! is_array( $fields['calendar'] ) ) {
					self::deny();
				}$calendar = Calendar::restore( $fields['calendar'] );
				self::require( $calendar->year === $this->calendar->year && $calendar->revision >= $this->calendar->revision );
				$next['calendar'] = $calendar->data();
				$next['days']     = $calendar->days( $this->starts, $this->ends );
				$next['state']    = 'submitted';
				break;
			case 'start':
				self::require( RequestState::Submitted === $this->state );
				$next['state'] = 'analysis';
				break;
			case 'return':
			case 'approve':
			case 'reject':
				self::require( RequestState::Analysis === $this->state );
				if ( 'approve' === $operation ) {
					self::future( $this->starts, $now );
				}
				$next['state'] = match ( $operation ) {
					'return'=>'returned', 'approve'=>'approved', 'reject'=>'rejected'};
				break;
			case 'withdraw':
				self::require( $this->state->pending() || $this->state->editable() );
				$next['state'] = 'withdrawn';
				break;
			case 'cancel':
				self::require( RequestState::Approved === $this->state );
				if ( $this->starts <= gmdate( 'Y-m-d', $now ) ) {
					throw new InvalidArgumentException( 'O cancelamento de um período já iniciado está bloqueado neste laboratório.' );
				}
				$next['state'] = 'cancelled';
				break;
		}
		return $this->record( $operation, $actor, $now, $reason, $next );
	}
	public function snapshot(): array {
		return array(
			'id'         => $this->id,
			'owner'      => $this->owner,
			'unit'       => $this->unit,
			'revision'   => $this->revision,
			'state'      => $this->state->value,
			'starts'     => $this->starts,
			'ends'       => $this->ends,
			'calendar'   => $this->calendar->data(),
			'days'       => $this->days,
			'updated_at' => $this->updated_at,
		);}
	private function record( string $operation, int $actor, int $now, string $reason, array $next ): self {
		$next['revision']   = $this->revision + 1;
		$next['updated_at'] = $now;
		$history            = $this->history;
		$history[]          = array(
			'operation' => $operation,
			'actor'     => $actor,
			'at'        => $now,
			'reason'    => $reason,
			'after'     => $next,
		);
		return new self( $this->id, $this->owner, $this->unit, $next['revision'], RequestState::from( $next['state'] ), $next['starts'], $next['ends'], Calendar::restore( $next['calendar'] ), $next['days'], $now, $history );
	}
	public static function restore( array $events ): self {
		if ( ! array_is_list( $events ) || ! $events || count( $events ) > 1000 ) {
			self::deny();
		}$r = null;
		foreach ( $events as$index => $event ) {
			if ( ! is_array( $event ) || array_keys( $event ) !== array( 'operation', 'actor', 'at', 'reason', 'after' ) || ! is_string( $event['operation'] ) || ! is_int( $event['actor'] ) || ! is_int( $event['at'] ) || ! is_string( $event['reason'] ) || ! is_array( $event['after'] ) ) {
				self::deny();
			}
			$a = $event['after'];
			if ( array_keys( $a ) !== array( 'id', 'owner', 'unit', 'revision', 'state', 'starts', 'ends', 'calendar', 'days', 'updated_at' ) || ! is_int( $a['id'] ) || ! is_int( $a['owner'] ) || ! is_string( $a['unit'] ) || ! is_string( $a['starts'] ) || ! is_string( $a['ends'] ) || ! is_array( $a['calendar'] ) ) {
				self::deny();
			}
			if ( 0 === $index ) {
				if ( 'create' !== $event['operation'] || $event['actor'] !== $a['owner'] || '' !== $event['reason'] ) {
					self::deny();
				}$r = self::create( $a['id'], $a['owner'], $a['unit'], $a['starts'], $a['ends'], Calendar::restore( $a['calendar'] ), $event['at'] );} else {
				$fields = match ( $event['operation'] ) {
					'edit'=>array(
						'starts'   => $a['starts'],
						'ends'     => $a['ends'],
						'calendar' => $a['calendar'],
					), 'submit'=>array( 'calendar' => $a['calendar'] ), 'start', 'withdraw'=>array(), 'return', 'approve', 'reject', 'cancel'=>array( 'reason' => $event['reason'] ), default=>throw new InvalidArgumentException( 'Histórico de férias inválido.' )};
					$r = $r->change( $event['operation'], $event['actor'], $fields, $event['at'] );}
				if ( $r->snapshot() !== $a || $r->history[ $index ] !== $event ) {
					self::deny();
				}
		}
		return $r;
	}
	public static function future( string $starts, int $now ): void {
		Calendar::date( $starts );
		if ( $starts <= gmdate( 'Y-m-d', $now ) ) {
			throw new InvalidArgumentException( 'O período deve começar depois de hoje.' );
		}}
	private static function require( bool $condition ): void {
		if ( ! $condition ) {
			self::deny();
		}}
	private static function deny(): never {
		throw new InvalidArgumentException( 'Pedido de férias inválido ou alteração indisponível.' );}
}
