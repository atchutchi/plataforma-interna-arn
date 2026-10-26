<?php
/** Atomic fictional tickets, conversation, audit and notices.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, FileRecord, FileSchema, PrivateBlobStore, WpdbFileRegistry};
use Arn\Intranet\Tasks\Attachment;
use Arn\Intranet\Cms\UploadAdmission;
use Closure;
use DomainException;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions, WordPress.PHP.YodaConditions -- Explicit transaction, bound identifiers and strict audited payloads.
final class Repository {
	private readonly Schema $schema;
	private ?Closure $final_guard = null;
	public function __construct( private readonly IdentityWpdb $db, private readonly CurrentActor $actors, private readonly Closure $clock ) {
		if ( $actors->connection() !== $db ) {
			throw new RuntimeException( 'Autoridade fora da transacção.' );
		}
		$this->schema = new Schema( $db );
	}

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
			if ( ! is_int( $end ) || $end < $now || $this->actors->currentId() !== $actor->id() || ! $after[ $actor->id() ]->valid( $end ) ) {
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


	private function accessFor( string $view, ActorContext $actor, int $now ): ?array {
		if ( ! in_array( $view, array( 'own', 'team' ), true ) ) {
			return null; }
		$units = array_values( array_filter( array( 'LAB-A', 'LAB-B' ), static fn( string $u ): bool => 'own' === $view ? TicketPolicy::create( $u, $actor, $now ) : TicketPolicy::manageUnit( $u, $actor, $now ) ) );
		return $units ? array(
			'actor' => $actor->id(),
			'units' => $units,
		) : null;
	}
	public function access( string $view ): ?array {
		return $this->transaction( fn( ActorContext $actor, array $people, int $now ): ?array => $this->accessFor( $view, $actor, $now ) ); }
	public function listing( string $view, string $query = '', string $state = '', int $page = 1, string $priority = '' ): ?array {
		if ( $page < 1 || $page > 100 || mb_strlen( $query ) > 100 || ( '' !== $state && null === TicketState::tryFrom( $state ) ) || ! in_array( $priority, array( '', 'low', 'normal', 'high' ), true ) ) {
			return null; }
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $view, $query, $state, $page, $priority ): ?array {
				$access = $this->accessFor( $view, $actor, $now );
				if ( null === $access ) {
					return null; }
				$items = array();
				foreach ( array_reverse( $this->ids( 'tickets' ) ) as $id ) {
					$t = $this->loadTicket( $id );
					if ( $this->visible( $t, $view, $actor, $now ) && ( '' === $state || $t->state->value === $state ) && ( '' === $priority || $t->priority === $priority ) && ( '' === $query || false !== mb_stripos( $t->reference() . ' ' . $t->title, $query ) ) ) {
						$items[] = $t; }
				}
				return array(
					'access' => $access,
					'items'  => array_slice( $items, ( $page - 1 ) * 20, 20 ),
					'total'  => count( $items ),
					'page'   => $page,
				);
			}
		);
	}
	public function detail( int $id, string $view ): ?array {
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $id, $view ): ?array {
				$t = $this->loadTicket( $id );
				if ( null === $t || ! $this->visible( $t, $view, $actor, $now ) ) {
					return null; }
				$ops = TicketPolicy::operations( $t, $actor, $now );
				if ( 'own' === $view ) {
					$ops = array_values( array_intersect( $ops, array( 'reply', 'attach' ) ) ); } elseif ( $actor->id() === $t->owner ) {
									$ops = array(); }
					return array(
						'ticket'     => $t,
						'revision'   => $t->revision,
						'operations' => $ops,
						'access'     => $this->accessFor( $view, $actor, $now ),
					);
			}
		);
	}
	public function create( string $unit, string $title, string $body, string $priority, string $key ): Ticket {
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $unit, $title, $body, $priority, $key ): Ticket {
				if ( ! TicketPolicy::create( $unit, $actor, $now ) ) {
					self::deny(); }
				$command = self::json( array( 'create', $unit, $title, $body, $priority ) );
				$receipt = $this->receipt( $actor->id(), $key, $command );
				if ( null !== $receipt ) {
					$t = $this->loadTicket( $receipt['after']['id'] );
					if ( null === $t || ! TicketPolicy::own( $t, $actor, $now ) ) {
									self::deny();
					} return $t; }
				$t                 = Ticket::create( $this->allocate( 'tickets', 500 ), $actor->id(), $unit, $title, $body, $priority, $now );
				$this->final_guard = static fn( ActorContext $current, int $end ): bool => TicketPolicy::create( $unit, $current, $end );
				$this->persistTicket( null, $t, $people, $key, $command );
				return $t;
			}
		);
	}
	public function change( int $id, int $expected, string $op, array $fields, string $key ): Ticket {
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $id, $expected, $op, $fields, $key ): Ticket {
				$t = $this->loadTicket( $id );
				if ( null === $t || ! TicketPolicy::read( $t, $actor, $now ) || 'attach' === $op ) {
					self::deny(); }
				$command = self::json( array( $op, $id, $expected, $fields ) );
				if ( null !== $this->receipt( $actor->id(), $key, $command ) ) {
					return $t; }
				if ( $t->revision !== $expected || ! in_array( $op, TicketPolicy::operations( $t, $actor, $now ), true ) ) {
					self::deny(); }
				$after             = $t->change( $op, $actor->id(), $fields, $now );
				$this->final_guard = static fn( ActorContext $current, int $end ): bool => in_array( $op, TicketPolicy::operations( $t, $current, $end ), true );
				$this->persistTicket( $t, $after, $people, $key, $command );
				return $after;
			}
		);
	}
	private function visible( Ticket $r, string $view, ActorContext $actor, int $now ): bool {
		return match ( $view ) {
			'own' => TicketPolicy::own( $r, $actor, $now ), 'team' => TicketPolicy::team( $r, $actor, $now ), default => false };
	}
	public function attach( int $id, BlobDescriptor $blob, string $label, int $expected, int $expected_actor ): Ticket {
		( new FileSchema( $this->db ) )->assertReady();
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $id, $blob, $label, $expected, $expected_actor ): Ticket {
				$r = $this->loadTicket( $id );
				if ( null === $r || $actor->id() !== $expected_actor || $r->revision !== $expected || ! in_array( 'attach', TicketPolicy::operations( $r, $actor, $now ), true ) ) {
						self::deny();
				}
				$policy = ( new UploadAdmission( $this->db ) )->policy();
				UploadAdmission::assertAllowed( $policy, 'document', $blob );
				$attachment = new Attachment( $blob, $label, $expected + 1 );
				$after      = $r->change( 'attach', $actor->id(), array( 'attachment' => $attachment->toArray() ), $now );
				( new WpdbFileRegistry( $this->db ) )->registerInTransaction( new FileRecord( $blob, 'ticket_attachment', $id, $after->revision, 'original', $actor->id(), $now ) );
				$this->final_guard = static fn( ActorContext $current, int $end ): bool => in_array( 'attach', TicketPolicy::operations( $r, $current, $end ), true );
				$this->persistTicket( $r, $after, $people, $blob->id, self::json( array( 'attach', $id, $expected, $attachment->toArray() ) ) );
				return $after;
			}
		);
	}
	public function file( int $id, string $blob_id, string $variant, PrivateBlobStore $store ): ?array {
		if ( $id < 1 || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $blob_id ) || ! in_array( $variant, array( 'original', 'thumbnail', 'preview' ), true ) ) {
			return null;
		}
		( new FileSchema( $this->db ) )->assertReady();
		$opened = null;
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $people, int $now ) use ( $id, $blob_id, $variant, $store, &$opened ): ?array {
					$r = $this->loadTicket( $id );
					if ( null === $r || ! TicketPolicy::read( $r, $actor, $now ) ) {
							return null;
					}
					foreach ( $r->attachments as $data ) {
						$item = Attachment::fromArray( $data );
						if ( $item->blob->id !== $blob_id ) {
							continue;
						}
						$blob = 'original' === $variant ? $item->blob : ( $item->blob->derivatives[ $variant ] ?? null );
						if ( null === $blob ) {
							return null;
						}
						$schema = new FileSchema( $this->db );
						$rows   = $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE blob_id=%s FOR UPDATE', $schema->table( 'files' ), $blob->id ) );
						$event  = $r->history[ $item->revision - 1 ];
						if ( count( $rows ) !== 1 ) {
							return null;
						}
						$f = $rows[0];
						if ( $f->object_type !== 'ticket_attachment' || self::number( $f->object_id ) !== $id || self::number( $f->version ) !== $item->revision || $f->variant !== $variant || $f->sha256 !== $blob->sha256 || self::number( $f->size_bytes ) !== $blob->size || $f->mime !== $blob->mime || self::number( $f->actor_id ) !== $event['actor'] || self::number( $f->created_at ) !== $event['at'] ) {
							return null;
						}
						$this->final_guard = static fn( ActorContext $current, int $end ): bool => TicketPolicy::read( $r, $current, $end );
						$opened            = $store->open( $blob );
						return array(
							'handle'   => $opened,
							'blob'     => $blob,
							'revision' => $item->revision,
						);
					}
					return null;
				}
			);
		} catch ( Throwable ) {
			if ( is_resource( $opened ) ) {
				fclose( $opened );
			}
			return null;
		}
	}
	private function ids( string $kind ): array {
		$rows = $this->rows( $this->db->prepare( 'SELECT id FROM %i ORDER BY id LIMIT 501', $this->schema->table( $kind ) ) );
		if ( count( $rows ) > 500 ) {
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
	private function loadTicket( int $id ): ?Ticket {
		$data = $this->load( 'tickets', $id );
		return null === $data ? null : Ticket::restore( $data[1] );
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
		if ( 'tickets' === $kind && Ticket::restore( $events )->snapshot() !== $last['after'] ) {
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
	private function persistTicket( ?Ticket $before, Ticket $after, array $people, string $key, string $command ): void {
		$event = $after->history[ $after->revision - 1 ];
		$this->persist( 'tickets', $after->id, $before?->revision ?? 0, $before?->snapshot(), $after->snapshot(), $event, $key, $command );
		$recipients = 'create' === $event['operation'] ? array_keys( array_filter( $people, static fn( ActorContext $person ): bool => $person->id() !== $after->owner && TicketPolicy::eligible( $person, $after->unit, $event['at'], true ) ) ) : array( $event['actor'] === $after->owner ? $after->assigned : $after->owner );
		foreach ( array_unique( $recipients ) as $recipient ) {
			if ( isset( $people[ $recipient ] ) && TicketPolicy::eligible( $people[ $recipient ], $after->unit, $event['at'], $recipient !== $after->owner ) ) {
				$this->execute( $this->db->prepare( 'INSERT INTO %i (object_id,revision,recipient_id,kind,created_at,read_at) VALUES (%d,%d,%d,%s,%d,NULL)', $this->schema->table( 'notifications' ), $after->id, $after->revision, $recipient, $event['operation'], $event['at'] ) );
			}
		}
	}
	public function notifications(): array {
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ): array {
				$result = array();
				foreach ( $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE recipient_id=%d ORDER BY id DESC LIMIT 100', $this->schema->table( 'notifications' ), $actor->id() ) ) as $row ) {
						$r     = $this->loadTicket( self::number( $row->object_id ) );
						$event = $r?->history[ self::number( $row->revision ) - 1 ] ?? null;
					if ( null === $r || null === $event || $event['operation'] !== $row->kind || (string) $event['at'] !== (string) $row->created_at ) {
						self::deny();
					}
					if ( TicketPolicy::read( $r, $actor, $now ) ) {
						$result[] = array(
							'id'     => self::number( $row->id ),
							'ticket' => $r,
							'kind'   => $row->kind,
							'read'   => null !== $row->read_at,
						);
					}
				}
				return $result;
			}
		);
	}
	public function markRead( int $id ): void {
		$this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $id ): void {
				$rows = $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE id=%d AND recipient_id=%d FOR UPDATE', $this->schema->table( 'notifications' ), $id, $actor->id() ) );
				$r    = $rows ? $this->loadTicket( self::number( $rows[0]->object_id ) ) : null;
				if ( null === $r || ! TicketPolicy::read( $r, $actor, $now ) ) {
						self::deny();
				}
				$this->execute( $this->db->prepare( 'UPDATE %i SET read_at=COALESCE(read_at,%d) WHERE id=%d AND recipient_id=%d', $this->schema->table( 'notifications' ), $now, $id, $actor->id() ) );
			}
		);
	}
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
		return is_array( $data ) ? $data : throw new RuntimeException( 'Payload de ticket inválido.' );
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
		throw new DomainException( 'Ticket alterado ou operação indisponível.' );
	}
}
