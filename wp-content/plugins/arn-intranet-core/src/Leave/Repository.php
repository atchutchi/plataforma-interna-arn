<?php
/** Transactional fictional leave, calendars, audit receipts and notices.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\Requests\{CurrentActor, RequestPolicy, RequestState};
use Closure;
use DomainException;
use RuntimeException;
use Throwable;
// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.PHP.YodaConditions
final class Repository {
	private readonly Schema $schema;
	private ?Closure $final_guard = null;
	public function __construct( private readonly IdentityWpdb $db, private readonly CurrentActor $actors, private readonly Closure $clock ) {
		if ( $actors->connection() !== $db ) {
			throw new RuntimeException( 'Autoridade fora da transacção.' );
		}$this->schema = new Schema( $db );}
	private function transaction( Closure $operation ): mixed {
		if ( ! Configuration::current() || $this->db->inTransaction() ) {
			self::deny();
		}
		$this->schema->assertReady();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$this->final_guard = null;
			$people            = $this->actors->locked();
			$actor             = $people[ $this->actors->currentId() ] ?? null;
			$now               = ( $this->clock )();
			if ( ! is_int( $now ) || ! $actor instanceof ActorContext || ! $actor->valid( $now ) ) {
				self::deny();
			}
			// Bounded laboratory catalogue: serialize allocation and cross-object rules.
			$this->rows( $this->db->prepare( 'SELECT id FROM %i WHERE id=1 FOR UPDATE', $this->schema->table( 'schema' ) ) );
			$result = $operation( $actor, $people, $now );
			$after  = $this->actors->locked();
			$end    = ( $this->clock )();
			if ( ! is_int( $end ) || $end < $now || gmdate( 'Y-m-d', $end ) !== gmdate( 'Y-m-d', $now ) || $this->actors->currentId() !== $actor->id() || ! $after[ $actor->id() ]->valid( $end ) ) {
				self::deny();
			}
			foreach ( $people as $person ) {
				foreach ( $person->snapshot->policyGrants() as $grant ) {
					if ( $grant->validAt( $now ) && ! $grant->validAt( $end ) ) {
						self::deny();
					}
				}
			}
			if ( null !== $this->final_guard && ! ( $this->final_guard )( $after[ $actor->id() ], $end ) ) {
				self::deny();
			}
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable $error ) {
			$this->db->query( 'ROLLBACK' );
			throw new RuntimeException( 'Operação não confirmada. Consulta o histórico antes de repetir.', 0, $error );
		}
	}


	private function accessFor( string $view, ActorContext $actor, array $people, int $now ): ?array {
		if ( ! in_array( $view, array( 'own', 'team' ), true ) ) {
			return null;
		}
		$units = array_values( array_filter( array( 'LAB-A', 'LAB-B' ), static fn( string $unit ): bool=>'own' === $view ? RequestPolicy::create( $unit, $actor, $now ) : RequestPolicy::manageUnit( $unit, $actor, $now ) ) );
		return $units ? array(
			'actor'     => $actor->id(),
			'units'     => $units,
			'configure' => 'team' === $view && RequestPolicy::configure( $actor, $now ),
		) : null;
	}
	public function access( string $view ): ?array {
		return $this->transaction( fn( ActorContext $a, array $p, int $n ): ?array=>$this->accessFor( $view, $a, $p, $n ) );}
	public function navigation(): array {
		return $this->transaction(
			fn( ActorContext $a, array $p, int $n ): array => array(
				'own'  => $this->accessFor( 'own', $a, $p, $n ),
				'team' => $this->accessFor( 'team', $a, $p, $n ),
			)
		);
	}
	private function all(): array {
		return array_map( fn( int $id ): LeaveRequest=>$this->loadRequest( $id ), $this->ids( 'requests' ) );}
	private function visible( LeaveRequest $r, string $view, ActorContext $a, int $now ): bool {
		return match ( $view ) {
			'own'=>LeavePolicy::own( $r, $a, $now ), 'team'=>LeavePolicy::team( $r, $a, $now ), default=>false};}
	public function listing( string $view, int $year, int $page = 1 ): ?array {
		if ( $year < 2000 || $year > 2100 || $page < 1 || $page > 100 ) {
			return null;
		}
		return $this->transaction(
			function ( ActorContext $a, array $people, int $now ) use ( $view, $year, $page ): ?array {
				$access = $this->accessFor( $view, $a, $people, $now );
				if ( null === $access ) {
					return null;
				}
				$all      = $this->all();
				$items    = array_values( array_filter( array_reverse( $all ), fn( LeaveRequest $r ): bool=>$r->calendar->year === $year && $this->visible( $r, $view, $a, $now ) ) );
				$balances = array();
				foreach ( $people as$id => $person ) {
					if ( 'own' === $view ? $id !== $a->id() : ! array_intersect( $person->units, $access['units'] ) ) {
						continue;
					}
					$balances[ $id ] = Balance::summarize( $all, $id, $year, $now );
				}
				return array(
					'items'    => array_slice( $items, ( $page - 1 ) * 20, 20 ),
					'total'    => count( $items ),
					'page'     => $page,
					'year'     => $year,
					'calendar' => $this->loadCalendar( $year ),
					'balances' => $balances,
					'approved' => array_values( array_filter( $items, static fn( LeaveRequest $r ): bool=>RequestState::Approved === $r->state ) ),
				);
			}
		);
	}
	public function detail( int $id, string $view ): ?array {
		return $this->transaction(
			function ( ActorContext $a, array $p, int $now ) use ( $id, $view ): ?array {
				$r = $this->loadRequest( $id );
				if ( null === $r || ! $this->visible( $r, $view, $a, $now ) ) {
					return null;
				}
				return array(
					'request'    => $r,
					'calendar'   => $this->loadCalendar( $r->calendar->year ),
					'operations' => array_values( array_intersect( LeavePolicy::operations( $r, $a, $now ), 'own' === $view ? array( 'edit', 'submit', 'withdraw' ) : array( 'start', 'return', 'approve', 'reject', 'cancel' ) ) ),
				);
			}
		);
	}
	public function saveCalendar( int $year, int $expected, array $holidays, string $reason, string $key ): Calendar {
		return $this->transaction(
			function ( ActorContext $a, array $p, int $now ) use ( $year, $expected, $holidays, $reason, $key ): Calendar {
				if ( ! RequestPolicy::configure( $a, $now ) ) {
					self::deny();
				}
				$reason  = \Arn\Intranet\F112\PlainText::value( $reason, 1000, true );
				$command = self::json( array( 'calendar', $year, $expected, $holidays, $reason ) );
				if ( null !== $this->receipt( $a->id(), $key, $command ) ) {
					return $this->loadCalendar( $year );
				}
				$before = $this->load( 'calendars', $year );
				if ( ( $before[0]['revision'] ?? 0 ) !== $expected ) {
					self::deny();
				}
				$calendar = new Calendar( $year, $expected + 1, $holidays );
				if ( null === $before ) {
					if ( count( $this->ids( 'calendars' ) ) >= 50 ) {
							self::deny();
					}$this->execute( $this->db->prepare( 'INSERT INTO %i (id,revision,payload,digest) VALUES (%d,0,%s,%s)', $this->schema->table( 'calendars' ), $year, '', str_repeat( '0', 64 ) ) );}
				$after = array(
					'id'       => $year,
					'revision' => $calendar->revision,
					'calendar' => $calendar->data(),
				);
				$event = array(
					'operation' => 'calendar_saved',
					'actor'     => $a->id(),
					'at'        => $now,
					'reason'    => $reason,
					'after'     => $after,
				);
				$this->persist( 'calendars', $year, $expected, $before[0] ?? null, $after, $event, $key, $command );
				return $calendar;
			}
		);
	}
	public function create( string $unit, string $starts, string $ends, int $calendar_revision, string $key ): LeaveRequest {
		return $this->transaction(
			function ( ActorContext $a, array $p, int $now ) use ( $unit, $starts, $ends, $calendar_revision, $key ): LeaveRequest {
				if ( ! RequestPolicy::create( $unit, $a, $now ) ) {
					self::deny();
				}
				$command = self::json( array( 'create', $unit, $starts, $ends, $calendar_revision ) );
				$receipt = $this->receipt( $a->id(), $key, $command );
				if ( null !== $receipt ) {
					$r = $this->loadRequest( $receipt['after']['id'] );
					if ( null === $r || ! LeavePolicy::own( $r, $a, $now ) ) {
							self::deny();
					}return $r;}
				$calendar = $this->loadCalendar( (int) Calendar::date( $starts )->format( 'Y' ) );
				if ( null === $calendar || $calendar->revision !== $calendar_revision ) {
					self::deny();
				}
				$r = LeaveRequest::create( $this->allocate( 'requests', 500 ), $a->id(), $unit, $starts, $ends, $calendar, $now );
				$this->persistRequest( null, $r, $p, $key, $command );
				return $r;
			}
		);
	}
	public function change( int $id, int $expected, string $op, array $fields, string $key ): LeaveRequest {
		return $this->transaction(
			function ( ActorContext $a, array $p, int $now ) use ( $id, $expected, $op, $fields, $key ): LeaveRequest {
				$r = $this->loadRequest( $id );
				if ( null === $r || ! LeavePolicy::read( $r, $a, $now ) ) {
					self::deny();
				}
				$command = self::json( array( $op, $id, $expected, $fields ) );
				if ( null !== $this->receipt( $a->id(), $key, $command ) ) {
					return $r;
				}
				if ( $r->revision !== $expected || ! in_array( $op, LeavePolicy::operations( $r, $a, $now ), true ) ) {
					self::deny();
				}
				if ( in_array( $op, array( 'edit', 'submit', 'approve' ), true ) ) {
					$keys = match ( $op ) {
						'edit'=>array( 'starts', 'ends', 'calendar_revision' ),'submit'=>array( 'calendar_revision' ),'approve'=>array( 'reason', 'calendar_revision' )};
					if ( array_keys( $fields ) !== $keys ) {
						self::deny();
					}
					$year     = 'edit' === $op ? (int) Calendar::date( $fields['starts'] )->format( 'Y' ) : $r->calendar->year;
					$calendar = $this->loadCalendar( $year );
					if ( null === $calendar || $calendar->revision !== $fields['calendar_revision'] ) {
						self::deny();
					}
					unset( $fields['calendar_revision'] );
					if ( 'approve' === $op ) {
						Balance::assertApproval( $r, $this->all(), $calendar, $now );
					} else {
						$fields['calendar'] = $calendar->data();
					}
				}
				$after = $r->change( $op, $a->id(), $fields, $now );
				if ( 'submit' === $op ) {
					Balance::assertSubmission( $after, $this->all(), $now );
				}
				$this->final_guard = static fn( ActorContext $current, int $end ): bool=>in_array( $op, LeavePolicy::operations( $r, $current, $end ), true );
				$this->persistRequest( $r, $after, $p, $key, $command );
				return $after;
			}
		);
	}
	public function projection( string $purpose, string $query = '', int $page = 1 ): ?array {
		if ( ! \Arn\Intranet\EditorialPreview\ServiceSelection::valid( $purpose, $query, $page ) ) {
			return null;
		}
		return $this->transaction(
			function ( ActorContext $a, array $p, int $now ) use ( $purpose, $query, $page ): ?array {
				$own  = $this->accessFor( 'own', $a, $p, $now );
				$team = 'search' === $purpose ? $this->accessFor( 'team', $a, $p, $now ) : null;
				if ( null === $own && null === $team ) {
					return null;
				}
				$rows = array();
				foreach ( $this->all()as$r ) {
					$view = null !== $own && LeavePolicy::own( $r, $a, $now ) ? 'own' : ( null !== $team && LeavePolicy::team( $r, $a, $now ) ? 'team' : null );
					if ( null === $view ) {
						continue;
					}
					$rows[] = array(
						'id'         => $r->id,
						'title'      => $r->title(),
						'reference'  => $r->reference(),
						'state'      => $r->state->label(),
						'updated_at' => $r->updated_at,
						'view'       => $view,
						'pending'    => $r->state->editable() || $r->state->pending(),
					);
				}
				return \Arn\Intranet\EditorialPreview\ServiceSelection::select( $rows, $purpose, $query, $page );
			}
		);
	}
	private function ids( string $kind ): array {
		$rows = $this->rows( $this->db->prepare( 'SELECT id FROM %i ORDER BY id LIMIT 501', $this->schema->table( $kind ) ) );
		if ( count( $rows ) > ( 'calendars' === $kind ? 50 : 500 ) ) {
			self::deny();
		}
		return array_map( static fn( object $row ): int => self::number( $row->id ), $rows );
	}
	private function allocate( string $kind, int $limit ): int {
		if ( count( $this->ids( $kind ) ) >= $limit ) {
			self::deny();
		}
		$this->execute( $this->db->prepare( 'INSERT INTO %i (revision,payload,digest) VALUES (0,%s,%s)', $this->schema->table( $kind ), '', str_repeat( '0', 64 ) ) );
		return self::number( $this->db->insert_id );
	}
	private function loadCalendar( int $id ): ?Calendar {
		$data = $this->load( 'calendars', $id );
		return null === $data ? null : Calendar::restore( $data[0]['calendar'] );
	}
	private function loadRequest( int $id ): ?LeaveRequest {
		$data = $this->load( 'requests', $id );
		return null === $data ? null : LeaveRequest::restore( $data[1] );
	}
	private function load( string $kind, int $id ): ?array {
		$rows = $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', $this->schema->table( $kind ), $id ) );
		if ( ! $rows ) {
			return null;
		}
		$row    = $rows[0];
		$events = array();
		$hash   = str_repeat( '0', 64 );
		$last   = null;
		foreach ( $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE kind=%s AND object_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $kind, $id ) ) as $a ) {
			$event    = self::decode( $a->payload );
			$revision = count( $events ) + 1;
			if ( array_keys( $event ) !== array( 'operation', 'actor', 'at', 'reason', 'after' ) || ! is_int( $event['actor'] ) || $event['actor'] < 1 || ! is_int( $event['at'] ) || $event['at'] < 0 || ! is_string( $event['reason'] ) || ! is_array( $event['after'] ) ) {
				self::deny();
			}
			$after = $event['after'];
			if ( $a->before_hash !== $hash || self::number( $a->revision ) !== $revision || $after['revision'] !== $revision || $after['id'] !== $id || self::number( $a->actor_id ) !== $event['actor'] || (string) $event['at'] !== (string) $a->occurred_at || $event['operation'] !== $a->event || ( null !== $last && $event['at'] < $last['at'] ) ) {
				self::deny();
			}
			if ( 'calendars' === $kind ) {
				$calendar = Calendar::restore( $after['calendar'] ?? array() );
				if ( 'calendar_saved' !== $event['operation'] || '' === $event['reason'] || $calendar->year !== $id || $calendar->revision !== $revision || array_keys( $after ) !== array( 'id', 'revision', 'calendar' ) ) {
					self::deny();
				}
			}
			$hash = hash( 'sha256', self::json( $after ) );
			if ( ! hash_equals( $hash, $a->after_hash ) ) {
				self::deny();
			}
			$events[] = $event;
			$last     = $event;
		}
		if ( ! $events || count( $events ) > 1000 || self::number( $row->revision ) !== count( $events ) || $hash !== $row->digest || $row->payload !== self::json( $last['after'] ) ) {
			self::deny();
		}
		if ( 'requests' === $kind && LeaveRequest::restore( $events )->snapshot() !== $last['after'] ) {
			self::deny();
		}
		return array( $last['after'], $events );
	}

	private function receipt( int $actor, string $key, string $command ): ?array {
		$rows = $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE request_key=%s', $this->schema->table( 'audit' ), self::key( $actor, $key ) ) );
		if ( ! $rows ) {
			return null;
		}
		$row = $rows[0];
		if ( self::number( $row->actor_id ) !== $actor || ! hash_equals( hash( 'sha256', $command ), $row->command_hash ) ) {
			self::deny();
		}
		$data = $this->load( $row->kind, self::number( $row->object_id ) );
		return $data[1][ self::number( $row->revision ) - 1 ] ?? throw new RuntimeException( 'Recibo inválido.' );
	}
	private function persist( string $kind, int $id, int $expected, ?array $before, array $after, array $event, string $key, string $command ): void {
		$json   = self::json( $after );
		$digest = hash( 'sha256', $json );
		$this->execute( $this->db->prepare( 'UPDATE %i SET revision=%d,payload=CONVERT(UNHEX(%s) USING utf8mb4),digest=%s WHERE id=%d AND revision=%d', $this->schema->table( $kind ), $expected + 1, bin2hex( $json ), $digest, $id, $expected ) );
		if ( 1 !== $this->db->rows_affected ) {
			self::deny();
		}
		$this->execute( $this->db->prepare( 'INSERT INTO %i (kind,object_id,revision,event,actor_id,occurred_at,before_hash,after_hash,request_key,command_hash,payload) VALUES (%s,%d,%d,%s,%d,%d,%s,%s,%s,%s,CONVERT(UNHEX(%s) USING utf8mb4))', $this->schema->table( 'audit' ), $kind, $id, $expected + 1, $event['operation'], $event['actor'], $event['at'], null === $before ? str_repeat( '0', 64 ) : hash( 'sha256', self::json( $before ) ), $digest, self::key( $event['actor'], $key ), hash( 'sha256', $command ), bin2hex( self::json( $event ) ) ) );
	}

	private function persistRequest( ?LeaveRequest $before, LeaveRequest $after, array $people, string $key, string $command ): void {
		$event = $after->history[ $after->revision - 1 ];
		$this->persist( 'requests', $after->id, $before?->revision ?? 0, $before?->snapshot(), $after->snapshot(), $event, $key, $command );
		$recipients = array();
		if ( in_array( $event['operation'], array( 'submit', 'withdraw' ), true ) && ( 'submit' === $event['operation'] || in_array( 'submit', array_column( $after->history, 'operation' ), true ) ) ) {
			foreach ( $people as$id => $person ) {
				if ( $id !== $after->owner && RequestPolicy::eligible( $person, $after->unit, $event['at'], true ) ) {
							$recipients[] = $id;
				}
			}
		} elseif ( in_array( $event['operation'], array( 'start', 'return', 'approve', 'reject', 'cancel' ), true ) && isset( $people[ $after->owner ] ) && RequestPolicy::eligible( $people[ $after->owner ], $after->unit, $event['at'], false ) ) {
			$recipients[] = $after->owner;
		}
		foreach ( $recipients as$id ) {
			$this->execute( $this->db->prepare( 'INSERT INTO %i (object_id,revision,recipient_id,kind,created_at,read_at) VALUES (%d,%d,%d,%s,%d,NULL)', $this->schema->table( 'notifications' ), $after->id, $after->revision, $id, $event['operation'], $event['at'] ) );
		}
	}
	public function notifications(): array {
		return $this->transaction(
			function ( ActorContext $a, array $p, int $now ): array {
				$result = array();foreach ( $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE recipient_id=%d ORDER BY id DESC LIMIT 100', $this->schema->table( 'notifications' ), $a->id() ) )as$row ) {
					$r     = $this->loadRequest( self::number( $row->object_id ) );
					$event = $r?->history[ self::number( $row->revision ) - 1 ] ?? null;
					if ( null === $r || null === $event || $event['operation'] !== $row->kind || (string) $event['at'] !== (string) $row->created_at ) {
						self::deny();
					}
					if ( LeavePolicy::read( $r, $a, $now ) ) {
						$result[] = array(
							'id'         => self::number( $row->id ),
							'request'    => $r,
							'kind'       => $row->kind,
							'created_at' => (int) $row->created_at,
							'read_at'    => null === $row->read_at ? null : (int) $row->read_at,
							'view'       => LeavePolicy::own( $r, $a, $now ) ? 'own' : 'team',
						);
					}
				}return $result;
			}
		);
	}
	public function markRead( int $id ): void {
		$this->transaction(
			function ( ActorContext $a, array $p, int $now ) use ( $id ): void {
				$rows = $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE id=%d AND recipient_id=%d FOR UPDATE', $this->schema->table( 'notifications' ), $id, $a->id() ) );
				$r    = $rows ? $this->loadRequest( self::number( $rows[0]->object_id ) ) : null;
				if ( null === $r || ! LeavePolicy::read( $r, $a, $now ) ) {
					self::deny();
				}$this->execute( $this->db->prepare( 'UPDATE %i SET read_at=COALESCE(read_at,%d) WHERE id=%d AND recipient_id=%d', $this->schema->table( 'notifications' ), $now, $id, $a->id() ) );
			}
		);}
	private static function key( int $actor, string $key ): string {
		if ( 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $key ) ) {
			self::deny();
		}
		return hash( 'sha256', $actor . ':' . $key );
	}
	private static function number( mixed $value ): int {
		if ( ! ( is_int( $value ) || is_string( $value ) ) || 1 !== preg_match( '/\A[1-9][0-9]{0,9}\z/D', (string) $value ) ) {
			self::deny();
		}
		return (int) $value;
	}
	private static function json( array $data ): string {
		return json_encode( $data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}
	private static function decode( string $value ): array {
		if ( strlen( $value ) > 1048576 ) {
			self::deny();
		}
		$data = json_decode( $value, true, 32, JSON_THROW_ON_ERROR );
		return is_array( $data ) ? $data : throw new RuntimeException( 'Payload de pedido inválido.' );
	}
	private function rows( string $sql ): array {
		$rows = $this->db->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->db->last_error ) {
			self::deny();
		}
		return $rows;
	}
	private function execute( string $sql ): void {
		if ( false === $this->db->query( $sql ) || '' !== $this->db->last_error ) {
			self::deny();
		}
	}
	private static function deny(): never {
		throw new DomainException( 'Pedido alterado ou operação indisponível.' );
	}
}
