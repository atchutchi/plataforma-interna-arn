<?php
/** Atomic calendar persistence and recipient notices.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Calendar;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\F112\{ActorContext, Configuration, CurrentActor};
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting -- Internal repository contract is documented in the F1-12 plan and SQL acceptance.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
/** Every write revalidates current authority inside the caller-owned transaction. */
final class EventRepository {
	private readonly EventSchema $schema;

	public function __construct( private readonly IdentityWpdb $database, private readonly CurrentActor $actors, private readonly Closure $clock, private readonly string $token_secret ) {
		if ( $actors->connection() !== $database || 'calendar' !== $actors->module || strlen( $token_secret ) < 32 ) {
			throw new RuntimeException( 'Composição de eventos inválida.' );
		}
		$this->schema = new EventSchema( $database );
	}

	public function create( EventContent $content, string $reason ): Event {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $content, $reason ): Event {
				$unit_id   = $actor->allows( Action::ManageInstitutionalEvent, Scope::institutional(), $actor->id(), $now ) ? null : $content->audience->unit_id;
				$candidate = Event::draft( 1, $actor->id(), $unit_id, $content, $now, $reason );
				if ( ! EventPolicy::manage( $candidate, 'revise', $actor, $now ) ) {
					self::denied();
				}
				$id    = $this->anchor( $actor->id(), $now );
				$event = Event::draft( $id, $actor->id(), $unit_id, $content, $now, $reason );
				$this->persist( null, $event, $recipients, $now );
				return $event;
			}
		);
	}

	/** Internal model for an eligible recipient or competent CMS actor, never a public DTO. */
	public function find( int $id, string $mode = 'either' ): ?Event {
		if ( $id < 1 || ! in_array( $mode, array( 'read', 'manage', 'either' ), true ) ) {
			return null;
		}
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $recipients, int $now ) use ( $id, $mode ): ?Event {
					if ( ! $this->canUse( $actor, $now ) ) {
						return null;
					}
					$event = $this->load( $id );
					if ( null === $event ) {
						return null;
					}
					$allowed = match ( $mode ) {
						'read' => EventPolicy::read( $event, $actor, $now ), 'manage' => EventPolicy::manage( $event, 'view', $actor, $now ), default => EventPolicy::read( $event, $actor, $now ) || EventPolicy::manage( $event, 'view', $actor, $now ) };
					return $allowed ? $event : null;
				}
			);
		} catch ( DomainException ) {
			return null;
		}
	}

	public function revise( int $id, EventContent $content, int $expected, string $reason ): Event {
		return $this->change( $id, 'revise', $expected, $reason, $content );
	}

	public function publish( int $id, int $expected, string $reason ): Event {
		return $this->change( $id, 'publish', $expected, $reason );
	}

	public function archive( int $id, int $expected, string $reason ): Event {
		return $this->change( $id, 'archive', $expected, $reason );
	}

	public function cancel( int $id, int $expected, string $reason ): Event {
		return $this->change( $id, 'cancel', $expected, $reason );
	}

	private function change( int $id, string $operation, int $expected, string $reason, ?EventContent $content = null ): Event {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $id, $operation, $expected, $reason, $content ): Event {
				if ( ! $this->canManage( $actor, $now ) ) {
					self::denied();
				}
				$before = $this->load( $id, true );
				if ( null === $before || ! EventPolicy::manage( $before, $operation, $actor, $now ) ) {
					self::denied();
				}
				if ( $before->revision !== $expected ) {
					throw new DomainException( 'Conflito F112.' );
				}
				$after = match ( $operation ) {
					'revise' => $before->revise( $content, $actor->id(), $expected, $now, $reason ),
					'publish' => $before->publish( $actor->id(), $expected, $now, $reason ),
					'cancel' => $before->cancel( $actor->id(), $expected, $now, $reason ),
					'archive' => $before->archive( $actor->id(), $expected, $now, $reason ),
				};
				// A new audience requires current authority, not only authority over the old draft.
				if ( ! EventPolicy::manage( $after, 'view', $actor, $now ) ) {
					self::denied();
				}
				$this->persist( $before, $after, $recipients, $now );
				return $after;
			}
		);
	}

	public function visible( AgendaSelection $selection ): array {
		return $this->listing( $selection, false );
	}

		/** Publication DTOs use the same locked clock as scope and selection. */
	public function publishedList( AgendaSelection $selection ): array {
		return $this->listing( $selection, false, true );
	}

	public function publishedDetail( int $id ): ?array {
		if ( $id < 1 ) {
			return null;
		}
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $recipients, int $now ) use ( $id ): ?array {
					if ( ! $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now ) ) {
						return null;
					}
					$item = $this->load( $id );
					return null !== $item && EventPolicy::read( $item, $actor, $now ) ? \Arn\Intranet\F112\View::published( $item, $now ) : null;
				}
			);
		} catch ( DomainException ) {
			return null;
		}
	}

	public function manageable( AgendaSelection $selection ): array {
		return $this->listing( $selection, true );
	}

	private function listing( AgendaSelection $selection, bool $management, bool $project = false ): array {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $selection, $management, $project ): array {
				$authorised = array();
				$permitted  = $management ? $this->canManage( $actor, $now ) : $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now );
				if ( $permitted ) {
					foreach ( $this->rows( $this->database->prepare( 'SELECT id FROM %i ORDER BY id', $this->schema->table( 'objects' ) ) ) as $row ) {
						$event = $this->load( self::integer( $row->id ) );
						if ( null !== $event && ( $management ? EventPolicy::manage( $event, 'view', $actor, $now ) : EventPolicy::read( $event, $actor, $now ) ) ) {
							$authorised[] = $event;
						}
					}
				}
				$result = $selection->apply( $authorised, $now, $management );
				if ( $project ) {
					$result['items'] = array_map( static fn( Event $item ): array => \Arn\Intranet\F112\View::published( $item, $now ), $result['items'] );
				}
				return $result;
			}
		);
	}

	public function history( int $id ): array {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ) use ( $id ): array {
				if ( ! $this->canManage( $actor, $now ) ) {
					self::denied();
				}
				$event = $this->load( $id );
				if ( null === $event || ! EventPolicy::manage( $event, 'view', $actor, $now ) ) {
					self::denied();
				}
				return $event->history;
			}
		);
	}

	public function notices(): array {
		return $this->transaction(
			function ( ActorContext $actor, array $recipients, int $now ): array {
				if ( ! $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now ) ) {
					return array();
				}
				$visible = array();
				foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE recipient_id=%d ORDER BY id DESC', $this->schema->table( 'notifications' ), $actor->id() ) ) as $row ) {
					$event = $this->load( self::integer( $row->object_id ) );
					if ( null !== $event && $this->noticeEligible( $row, $event, $actor, $now ) ) {
						$visible[] = $this->noticeProjection( $row, $event );
					}
				}
				return $visible;
			}
		);
	}

	public function markRead( int $notice_id, ?int $now = null ): void {
		$this->transaction(
			function ( ActorContext $actor, array $recipients, int $clock ) use ( $notice_id, $now ): void {
				if ( $notice_id < 1 || ( null !== $now && $now !== $clock ) || ! $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $clock ) ) {
					self::denied();
				}
				$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d AND recipient_id=%d', $this->schema->table( 'notifications' ), $notice_id, $actor->id() ) );
				if ( 1 !== count( $rows ) ) {
					self::denied();
				}
				$event  = $this->load( self::integer( $rows[0]->object_id ), true );
				$locked = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d AND recipient_id=%d FOR UPDATE', $this->schema->table( 'notifications' ), $notice_id, $actor->id() ) );
				if ( null === $event || 1 !== count( $locked ) || ! $this->noticeEligible( $locked[0], $event, $actor, $clock ) ) {
					self::denied();
				}
				if ( null === $locked[0]->read_at ) {
					$this->execute( $this->database->prepare( 'UPDATE %i SET read_at=%d WHERE id=%d AND recipient_id=%d AND read_at IS NULL', $this->schema->table( 'notifications' ), $clock, $notice_id, $actor->id() ), 1 );
				}
			}
		);
	}

	private function noticeEligible( object $row, Event $event, ActorContext $actor, int $now ): bool {
		if ( ! EventPolicy::read( $event, $actor, $now ) || self::integer( $row->recipient_id ) !== $actor->id() || self::integer( $row->version ) !== $event->published_version ) {
			return false;
		}
		if ( EventState::Cancelled === $event->state ) {
			foreach ( $event->history as $entry ) {
				if ( 'cancelled' === $entry['operation'] ) {
					return 'cancelled' === $row->kind && self::integer( $row->revision ) === $entry['revision'] && self::integer( $row->created_at, 0 ) === $event->cancelled_at;
				}
			}
			return false;
		}
		return 'published' === $row->kind && self::integer( $row->revision ) === $event->published_revision;
	}

	private function noticeProjection( object $row, Event $event ): array {
		return array(
			'id'         => self::integer( $row->id ),
			'object_id'  => $event->id,
			'kind'       => $row->kind,
			'title'      => $event->published()->content->title,
			'created_at' => self::integer( $row->created_at, 0 ),
			'read_at'    => null === $row->read_at ? null : self::integer( $row->read_at, 0 ),
		);
	}

	private function canManage( ActorContext $actor, int $now ): bool {
		foreach ( $actor->active_units as $unit ) {
			if ( $actor->allows( Action::ManageUnitEvent, Scope::units( array( $unit ) ), $actor->id(), $now ) ) {
				return true;
			}
		}
		return $actor->allows( Action::ManageInstitutionalEvent, Scope::institutional(), $actor->id(), $now );
	}

	private function canUse( ActorContext $actor, int $now ): bool {
		return $this->canManage( $actor, $now ) || $actor->allows( Action::ReadContent, Scope::own(), $actor->id(), $now );
	}

	private function anchor( int $actor, int $now ): int {
		$table  = $GLOBALS['wpdb']->posts;
		$engine = $this->rows( $this->database->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) );
		if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE ) {
			throw new RuntimeException( 'Âncora de eventos não transaccional.' );
		}
		$date = gmdate( 'Y-m-d H:i:s', $now );
		$data = array(
			'post_author'           => $actor,
			'post_date'             => $date,
			'post_date_gmt'         => $date,
			'post_content'          => '',
			'post_title'            => 'Evento privado',
			'post_excerpt'          => 'arn-f112:' . $this->database->prefix . ':calendar',
			'post_status'           => 'private',
			'comment_status'        => 'closed',
			'ping_status'           => 'closed',
			'post_password'         => '',
			'post_name'             => '',
			'to_ping'               => '',
			'pinged'                => '',
			'post_modified'         => $date,
			'post_modified_gmt'     => $date,
			'post_content_filtered' => '',
			'post_parent'           => 0,
			'guid'                  => '',
			'menu_order'            => 0,
			'post_type'             => 'arn_evento',
			'post_mime_type'        => '',
			'comment_count'         => 0,
		);
		if ( 1 !== $this->database->insert( $table, $data ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Criação de âncora de evento indisponível.' );
		}
		return self::integer( $this->database->insert_id );
	}

	private function load( int $id, bool $lock = false ): ?Event {
		if ( $id < 1 ) {
			return null;
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d', $this->schema->table( 'objects' ), $id ) . ( $lock ? ' FOR UPDATE' : '' ) );
		if ( ! $rows ) {
			return null;
		}
		$row  = $rows[0];
		$data = json_decode( $row->payload, true, 64, JSON_THROW_ON_ERROR );
		if ( 1 !== count( $rows ) || ! is_array( $data ) ) {
			throw new RuntimeException( 'Agregado de eventos inválido.' );
		}
		$event  = Event::fromArray( $data );
		$digest = hash( 'sha256', self::encode( $event->toArray() ) );
		if ( $event->id !== $id || self::integer( $row->author_id ) !== $event->author_id || $row->unit_id !== $event->unit_id || self::integer( $row->revision ) !== $event->revision || $row->digest !== $digest || self::encode( $event->toArray() ) !== $row->payload ) {
			throw new RuntimeException( 'Digest de eventos inválido.' );
		}
		$anchor = $this->rows( $this->database->prepare( 'SELECT post_author,post_type,post_status,post_content,post_title,post_excerpt FROM %i WHERE ID=%d', $GLOBALS['wpdb']->posts, $id ) );
		if ( 1 !== count( $anchor ) || self::integer( $anchor[0]->post_author ) !== $event->author_id || 'arn_evento' !== $anchor[0]->post_type || 'private' !== $anchor[0]->post_status || '' !== $anchor[0]->post_content || 'Evento privado' !== $anchor[0]->post_title || 'arn-f112:' . $this->database->prefix . ':calendar' !== $anchor[0]->post_excerpt ) {
			throw new RuntimeException( 'Âncora de evento incompatível.' );
		}
		$versions = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE object_id=%d ORDER BY `number`', $this->schema->table( 'versions' ), $id ) );
		if ( count( $versions ) !== count( $event->versions ) ) {
			throw new RuntimeException( 'Versões de evento incompletas.' );
		}
		foreach ( $versions as $i => $version ) {
			if ( self::integer( $version->object_id ) !== $id || self::integer( $version->number ) !== $i + 1 || self::encode( $event->versions[ $i ]->toArray() ) !== $version->payload ) {
				throw new RuntimeException( 'Versões de evento incompatíveis.' );
			}
		}
		$audit = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE object_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $id ) );
		if ( count( $audit ) !== $event->revision ) {
			throw new RuntimeException( 'Auditoria de evento incompleta.' );
		}
		$previous = str_repeat( '0', 64 );
		foreach ( $audit as $i => $audit_row ) {
			$entry = $event->history[ $i ];
			if ( self::integer( $audit_row->object_id ) !== $id || self::integer( $audit_row->revision ) !== $i + 1 || $audit_row->event !== $entry['operation'] || self::integer( $audit_row->actor_id ) !== $entry['actor_id'] || self::integer( $audit_row->occurred_at, 0 ) !== $entry['occurred_at'] || $audit_row->reason !== $entry['reason'] || self::encode( $entry ) !== $audit_row->payload || $audit_row->before_hash !== $previous || 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $audit_row->after_hash ) ) {
				throw new RuntimeException( 'Cadeia de auditoria de evento inválida.' );
			}
			$previous = $audit_row->after_hash;
		}
		if ( $previous !== $digest ) {
			throw new RuntimeException( 'Última auditoria de evento inválida.' );
		}
		return $event;
	}

	private function persist( ?Event $before, Event $after, array $recipients, int $now ): void {
		if ( $before === $after ) {
			return;
		}
		$payload = self::encode( $after->toArray() );
		$digest  = hash( 'sha256', $payload );
		$data    = array(
			'id'        => $after->id,
			'author_id' => $after->author_id,
			'unit_id'   => $after->unit_id,
			'revision'  => $after->revision,
			'payload'   => $payload,
			'digest'    => $digest,
		);
		$written = null === $before ? $this->database->insert( $this->schema->table( 'objects' ), $data ) : $this->database->update(
			$this->schema->table( 'objects' ),
			$data,
			array(
				'id'       => $after->id,
				'revision' => $before->revision,
			)
		);
		$this->assertWrite( $written );
		if ( null === $before || count( $after->versions ) > count( $before->versions ) ) {
			$this->assertWrite(
				$this->database->insert(
					$this->schema->table( 'versions' ),
					array(
						'object_id' => $after->id,
						'number'    => $after->latest()->number,
						'payload'   => self::encode( $after->latest()->toArray() ),
					)
				)
			);
		}
		$entry = $after->history[ $after->revision - 1 ];
		$this->assertWrite(
			$this->database->insert(
				$this->schema->table( 'audit' ),
				array(
					'object_id'   => $after->id,
					'revision'    => $after->revision,
					'event'       => $entry['operation'],
					'actor_id'    => $entry['actor_id'],
					'occurred_at' => $entry['occurred_at'],
					'reason'      => $entry['reason'],
					'before_hash' => null === $before ? str_repeat( '0', 64 ) : hash( 'sha256', self::encode( $before->toArray() ) ),
					'after_hash'  => $digest,
					'payload'     => self::encode( $entry ),
				)
			)
		);
		if ( in_array( $entry['operation'], array( 'published', 'cancelled' ), true ) ) {
			$public = $after->published();
			foreach ( $recipients as $recipient ) {
				if ( $recipient->canReceive( $public->content->audience, $now ) ) {
					$this->assertWrite(
						$this->database->insert(
							$this->schema->table( 'notifications' ),
							array(
								'object_id'    => $after->id,
								'version'      => $public->number,
								'revision'     => $after->revision,
								'recipient_id' => $recipient->id(),
								'kind'         => 'cancelled' === $entry['operation'] ? 'cancelled' : 'published',
								'created_at'   => $now,
								'read_at'      => null,
							)
						)
					);
				}
			}
		}
	}

	private function transaction( Closure $operation ): mixed {
		if ( ! Configuration::current( 'calendar' ) ) {
			self::denied();
		}
		$previous = $this->database->suppress_errors( true );
		try {
			$this->schema->assertReady();
			if ( '0' !== (string) $this->database->get_var( 'SELECT @@session.in_transaction' ) || '' !== $this->database->last_error ) {
				throw new RuntimeException( 'Ligação de evento ocupada.' );
			}
			if ( null === $this->actors->read() ) {
				self::denied();
			}
			$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
			$this->execute( 'START TRANSACTION' );
			try {
				$recipients = $this->actors->locked();
				$actor      = $recipients[ $this->actors->currentId() ] ?? null;
				$now        = ( $this->clock )();
				if ( ! $actor instanceof ActorContext || ! is_int( $now ) || ! $actor->valid( $now ) ) {
					self::denied();
				}
				$result = $operation( $actor, $recipients, $now );
				$this->execute( 'COMMIT' );
				return $result;
			} catch ( DomainException | InvalidArgumentException $error ) {
				$this->database->query( 'ROLLBACK' );
				throw $error;
			} catch ( Throwable ) {
				$this->database->query( 'ROLLBACK' );
				throw new RuntimeException( 'Persistência de eventos indisponível.' );
			}
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura de eventos indisponível.' );
		}
		return $rows;
	}

	private function execute( string $sql, ?int $expected = null ): void {
		$result = $this->database->query( $sql );
		if ( false === $result || '' !== $this->database->last_error || ( null !== $expected && $result !== $expected ) ) {
			throw new RuntimeException( 'Escrita de eventos indisponível.' );
		}
	}

	private function assertWrite( mixed $result ): void {
		if ( 1 !== $result || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Escrita de evento incompleta.' );
		}
	}

	private static function integer( mixed $value, int $minimum = 1 ): int {
		if ( is_int( $value ) ) {
			if ( $value >= $minimum ) {
				return $value;
			}
		} elseif ( is_string( $value ) && 1 === preg_match( '/\A(?:0|[1-9][0-9]*)\z/', $value ) && strlen( $value ) <= strlen( (string) PHP_INT_MAX ) && ( strlen( $value ) < strlen( (string) PHP_INT_MAX ) || strcmp( $value, (string) PHP_INT_MAX ) <= 0 ) && (int) $value >= $minimum ) {
			return (int) $value;
		}
		throw new RuntimeException( 'Inteiro de evento inválido.' );
	}

	private static function encode( array $value ): string {
		return json_encode( $value, JSON_THROW_ON_ERROR );
	}

	private static function denied(): never {
		throw new DomainException( 'Operação F112 recusada.' );
	}
}
