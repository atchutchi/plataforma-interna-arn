<?php
/** Task writes, audit, comments and recipient notices share one transaction.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\Authorization\{Action, Scope};
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, FileRecord, FileSchema, PrivateBlobStore, WpdbFileRegistry};
use Arn\Intranet\Cms\UploadAdmission;
use Closure;
use DomainException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting -- The repository contract is specified in the F1-13 plan and SQL acceptance.
// phpcs:disable WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
/** Every protected load is narrowed by current authority before loading payload. */
final class TaskRepository {
	private readonly TaskSchema $schema;

	public function __construct( private readonly IdentityWpdb $database, private readonly CurrentActor $actors, private readonly Closure $clock ) {
		if ( $actors->connection() !== $database ) {
			throw new RuntimeException( 'Composição de tarefas inválida.' );
		}
		$this->schema = new TaskSchema( $database );
	}

	public function create( TaskContent $content, int $assignee, string $request ): Task {
		if ( $assignee < 1 || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $request ) ) {
			throw new InvalidArgumentException( 'Pedido de criação inválido.' );
		}
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $content, $assignee, $request ): Task {
				$units = array_values( array_filter( $actor->units, static fn( string $unit ): bool => TaskPolicy::create( $unit, $actor, $now ) ) );
				if ( 1 !== count( $units ) ) {
					self::denied(); }
				$unit = $units[0];
				$rows = $this->rows( $this->database->prepare( 'SELECT id FROM %i WHERE create_request_id=%s FOR UPDATE', $this->schema->table( 'tasks' ), $request ) );
				if ( $rows ) {
					$existing = $this->load( self::number( $rows[0]->id ), 'team', $actor, $now );
					if ( null === $existing || $existing->author_id !== $actor->id() || $existing->unit_id !== $unit || $existing->history[0]['after']['assignee_id'] !== $assignee || $existing->history[0]['after']['content'] !== $content->toArray() ) {
						self::denied(); }
					return $existing;
				}
				if ( ! isset( $people[ $assignee ] ) || ! TaskPolicy::recipient( $people[ $assignee ], $unit, $now ) ) {
					self::denied(); }
				$this->assertWrite(
					$this->database->insert(
						$this->schema->table( 'tasks' ),
						array(
							'author_id'         => $actor->id(),
							'unit_id'           => $unit,
							'assignee_id'       => $assignee,
							'revision'          => 1,
							'state'             => 'open',
							'due_at'            => $content->due_at,
							'updated_at'        => $now,
							'create_request_id' => $request,
							'payload'           => '',
							'digest'            => str_repeat( '0', 64 ),
						),
						array( '%d', '%s', '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s' )
					)
				);
				$task = Task::create( self::number( $this->database->insert_id ), $actor->id(), $assignee, $unit, $content, $now, $request );
				$this->persist( null, $task, $people, $now );
				return $task;
			}
		);
	}

	/** Registration and task revision share the authority transaction after processing. */
	public function attach( int $id, BlobDescriptor $blob, string $label, int $expected, int $expected_actor ): Task {
		( new FileSchema( $this->database ) )->assertReady();
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $id, $blob, $label, $expected, $expected_actor ): Task {
				$before = $this->load( $id, 'either', $actor, $now );
				if ( $actor->id() !== $expected_actor || null === $before || ! TaskPolicy::manage( $before, 'attach', $actor, $now ) ) {
					self::denied(); }
				$policy = ( new UploadAdmission( $this->database ) )->policy();
				UploadAdmission::assertAllowed( $policy, 'document', $blob );
				$after = $before->attach( new Attachment( $blob, $label, $expected + 1 ), $actor->id(), $expected, $now );
				( new WpdbFileRegistry( $this->database ) )->registerInTransaction( new FileRecord( $blob, 'task_attachment', $id, $after->revision, 'original', $actor->id(), $now ) );
				if ( ! TaskPolicy::manage( $before, 'attach', $actor, ( $this->clock )() ) ) {
					self::denied(); }
				$this->persist( $before, $after, $people, $now );
				if ( ! TaskPolicy::manage( $before, 'attach', $actor, ( $this->clock )() ) ) {
					self::denied(); }
				return $after;
			}
		);
	}

	/** Open bytes only while the current task and its authority remain locked. */
	public function file( int $id, string $blob_id, string $variant, PrivateBlobStore $store ): ?array {
		if ( $id < 1 || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $blob_id ) || ! in_array( $variant, array( 'original', 'thumbnail', 'preview' ), true ) ) {
			return null; }
		( new FileSchema( $this->database ) )->assertReady();
		$opened = null;
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $people, int $now ) use ( $id, $blob_id, $variant, $store, &$opened ): ?array {
					$task = $this->load( $id, 'either', $actor, $now );
					if ( null === $task ) {
						return null; }
					foreach ( $task->attachments as $item ) {
						if ( $item->blob->id !== $blob_id ) {
							continue; }
						$blob = 'original' === $variant ? $item->blob : ( $item->blob->derivatives[ $variant ] ?? null );
						if ( null === $blob ) {
							return null; }
						$schema = new FileSchema( $this->database );
						$rows   = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE blob_id=%s FOR UPDATE', $schema->table( 'files' ), $blob->id ) );
						$event  = $task->history[ $item->revision - 1 ];
						if ( 1 !== count( $rows ) ) {
							return null; }
						$r = $rows[0];
						if ( 'task_attachment' !== $r->object_type || self::number( $r->object_id ) !== $id || self::number( $r->version ) !== $item->revision || $r->variant !== $variant || $r->sha256 !== $blob->sha256 || self::number( $r->size_bytes ) !== $blob->size || $r->mime !== $blob->mime || self::number( $r->actor_id ) !== $event['actor_id'] || self::number( $r->created_at ) !== $event['occurred_at'] ) {
							return null; }
						if ( ! TaskPolicy::read( $task, $actor, ( $this->clock )() ) ) {
							self::denied(); }
						$opened = $store->open( $blob );
						if ( ! TaskPolicy::read( $task, $actor, ( $this->clock )() ) ) {
							self::denied(); }
						return array(
							'handle'   => $opened,
							'blob'     => $blob,
							'revision' => $item->revision,
						);
					}
					return null;
				}
			);
		} catch ( Throwable $error ) {
			if ( is_resource( $opened ) ) {
				fclose( $opened ); } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Verified private stream, never a public WordPress file.
			if ( $error instanceof DomainException ) {
				return null; }
			throw $error;
		}
	}

	public function find( int $id, string $mode = 'own' ): ?Task {
		if ( $id < 1 || ! in_array( $mode, array( 'own', 'team', 'either' ), true ) ) {
			return null; }
		try {
			return $this->transaction( fn( ActorContext $actor, array $people, int $now ): ?Task => $this->load( $id, $mode, $actor, $now ) );
		} catch ( DomainException ) {
			return null; }
	}

	/** Surface capabilities and eligible targets use the same locked authority. */
	public function access( string $mode ): ?array {
		if ( ! in_array( $mode, array( 'own', 'team' ), true ) ) {
			return null;
		}
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $people, int $now ) use ( $mode ): ?array {
					if ( ! $this->scope( $mode, $actor, $now ) ) {
						return null;
					}
					$units     = array_values( array_filter( $actor->units, static fn( string $unit ): bool => TaskPolicy::create( $unit, $actor, $now ) ) );
					$create    = 'team' === $mode && 1 === count( $units );
					$assignees = array();
					if ( $create ) {
						foreach ( $people as $id => $person ) {
							if ( TaskPolicy::recipient( $person, $units[0], $now ) ) {
								$assignees[] = $id;
							}
						}
					}
					$members = array();
					if ( 'team' === $mode ) {
						foreach ( $people as $id => $person ) {
							foreach ( $actor->units as $unit ) {
								if ( in_array( $unit, $actor->active_units, true ) && in_array( $unit, $person->units, true ) && $actor->allows( Action::ReadUnitTask, Scope::units( array( $unit ) ), $actor->id(), $now ) ) {
									$members[] = $id;
									break;
								}
							}
						}
					}
					return array(
						'actor_id'  => $actor->id(),
						'create'    => $create,
						'assignees' => $assignees,
						'members'   => $members,
					);
				}
			);
		} catch ( DomainException ) {
			return null;
		}
	}

	/** Projection stays inside the transaction, including private-reason policy. */
	public function detail( int $id, string $mode ): ?array {
		if ( $id < 1 || ! in_array( $mode, array( 'own', 'team' ), true ) ) {
			return null;
		}
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $people, int $now ) use ( $id, $mode ): ?array {
					$task = $this->load( $id, $mode, $actor, $now );
					return null === $task ? null : TaskProjection::detail( $task, $actor, $now );
				}
			);
		} catch ( DomainException ) {
			return null;
		}
	}

	public function listing( TaskSelection $selection, string $mode ): ?array {
		if ( ! in_array( $mode, array( 'own', 'team' ), true ) ) {
			return null;
		}
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $people, int $now ) use ( $selection, $mode ): ?array {
					$clauses = $this->scope( $mode, $actor, $now );
					if ( ! $clauses ) {
						return null;
					}
					$items = array();
					foreach ( $this->rows( $this->database->prepare( 'SELECT id FROM %i WHERE (', $this->schema->table( 'tasks' ) ) . implode( ' OR ', $clauses ) . ') ORDER BY id FOR UPDATE' ) as $row ) {
						$task = $this->load( self::number( $row->id ), $mode, $actor, $now );
						if ( null !== $task ) {
							$items[] = $task;
						}
					}
					$selected          = $selection->apply( $items );
					$selected['items'] = array_map( static fn( Task $task ): array => TaskProjection::row( $task, $actor, $now ), $selected['items'] );
					return $selected;
				}
			);
		} catch ( DomainException ) {
			return null;
		}
	}

	public function change( int $id, string $operation, int $expected, array $fields ): Task {
		self::validateFields( $operation, $fields );
		if ( $id < 1 || $expected < 1 ) {
			throw new InvalidArgumentException( 'Revisão de tarefa inválida.' ); }
		return $this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $id, $operation, $expected, $fields ): Task {
				$before = $this->load( $id, 'either', $actor, $now );
				if ( null === $before || ! TaskPolicy::manage( $before, $operation, $actor, $now ) ) {
					self::denied(); }
				if ( 'reassign' === $operation && ( ! isset( $people[ $fields['assignee_id'] ] ) || ! TaskPolicy::recipient( $people[ $fields['assignee_id'] ], $before->unit_id, $now ) ) ) {
					self::denied(); }
				$reason = $fields['reason'] ?? '';
				$after  = match ( $operation ) {
					'start' => $before->start( $actor->id(), $expected, $now, $reason ),
					'complete' => $before->complete( $actor->id(), $expected, $now, $reason ),
					'cancel' => $before->cancel( $actor->id(), $expected, $now, $reason ),
					'reassign' => $before->reassign( $fields['assignee_id'], $actor->id(), $expected, $now, $reason ),
					'change_due' => $before->changeDue( $fields['due'], $actor->id(), $expected, $now, $reason ),
					'comment' => $before->comment( $fields['body'], $actor->id(), $expected, $now ),
				};
				$this->persist( $before, $after, $people, $now );
				return $after;
			}
		);
	}

	/** Only the current assignee's eligible notices, never the former relation. */
	public function notifications(): array {
		try {
			return $this->transaction(
				function ( ActorContext $actor, array $people, int $now ): array {
					if ( ! $actor->allows( Action::ReadOwnTask, Scope::own(), $actor->id(), $now ) ) {
						return array(); }
					$result = array();
					foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE recipient_id=%d ORDER BY id DESC', $this->schema->table( 'notifications' ), $actor->id() ) ) as $row ) {
						$task = $this->load( self::number( $row->task_id ), 'own', $actor, $now );
						if ( null !== $task ) {
							$result[] = $this->notice( $row, $task, $actor ); }
					}
					return $result;
				}
			);
		} catch ( DomainException ) {
			return array(); }
	}

	public function markRead( int $id ): void {
		if ( $id < 1 ) {
			self::denied(); }
		$this->transaction(
			function ( ActorContext $actor, array $people, int $now ) use ( $id ): void {
				if ( ! $actor->allows( Action::ReadOwnTask, Scope::own(), $actor->id(), $now ) ) {
					self::denied(); }
				$sql  = $this->database->prepare( 'SELECT * FROM %i WHERE id=%d AND recipient_id=%d', $this->schema->table( 'notifications' ), $id, $actor->id() );
				$rows = $this->rows( $sql );
				$task = $rows ? $this->load( self::number( $rows[0]->task_id ), 'own', $actor, $now ) : null;
				if ( null === $task ) {
					self::denied(); }
				$locked = $this->rows( $sql . ' FOR UPDATE' );
				if ( 1 !== count( $locked ) ) {
					self::denied(); }
				$notice = $this->notice( $locked[0], $task, $actor );
				if ( $now < $notice['created_at'] ) {
					self::denied(); }
				$this->execute( $this->database->prepare( 'UPDATE %i SET read_at=COALESCE(read_at,%d) WHERE id=%d AND recipient_id=%d', $this->schema->table( 'notifications' ), $now, $id, $actor->id() ) );
			}
		);
	}

	private function transaction( Closure $operation ): mixed {
		if ( ! Configuration::current() ) {
			self::denied(); }
		if ( $this->database->inTransaction() ) {
			throw new RuntimeException( 'Ligação de tarefas já ocupada.' ); }
		$this->schema->assertReady();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$people = $this->actors->locked();
			$actor  = $people[ $this->actors->currentId() ] ?? null;
			$now    = ( $this->clock )();
			if ( ! is_int( $now ) || $now < 0 || ! $actor instanceof ActorContext || ! $actor->valid( $now ) ) {
				self::denied(); }
			$result = $operation( $actor, $people, $now );
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable $error ) {
			$this->database->query( 'ROLLBACK' );
			if ( $error instanceof DomainException || $error instanceof InvalidArgumentException ) {
				throw $error; }
			throw new RuntimeException( 'Persistência de tarefas indisponível.' );
		}
	}

	private function scope( string $mode, ActorContext $actor, int $now ): array {
		$clauses = array();
		foreach ( $actor->units as $unit ) {
			if ( ! in_array( $unit, $actor->active_units, true ) ) {
				continue; }
			if ( 'own' !== $mode && $actor->allows( Action::ReadUnitTask, Scope::units( array( $unit ) ), $actor->id(), $now ) ) {
				$clauses[] = $this->database->prepare( 'unit_id=%s', $unit );
			} elseif ( 'team' !== $mode && $actor->allows( Action::ReadOwnTask, Scope::own(), $actor->id(), $now ) ) {
				$clauses[] = $this->database->prepare( '(unit_id=%s AND assignee_id=%d)', $unit, $actor->id() );
			}
		}
		return $clauses;
	}

	private function load( int $id, string $mode, ActorContext $actor, int $now ): ?Task {
		$clauses = $this->scope( $mode, $actor, $now );
		if ( ! $clauses ) {
			return null; }
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d AND (', $this->schema->table( 'tasks' ), $id ) . implode( ' OR ', $clauses ) . ') FOR UPDATE' );
		if ( ! $rows ) {
			return null; }
		$task    = $this->hydrate( $rows[0] );
		$allowed = match ( $mode ) {
			'own' => TaskPolicy::own( $task, $actor, $now ), 'team' => TaskPolicy::team( $task, $actor, $now ), default => TaskPolicy::read( $task, $actor, $now ) };
		return $allowed ? $task : null;
	}

	private function hydrate( object $row ): Task {
		$id       = self::number( $row->id );
		$audit    = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE task_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $id ) );
		$events   = array_map( static fn( object $entry ): array => self::decode( $entry->payload ), $audit );
		$comments = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE task_id=%d ORDER BY id', $this->schema->table( 'comments' ), $id ) ) as $comment ) {
			self::number( $comment->id );
			$comments[] = array(
				'revision'    => self::number( $comment->revision ),
				'actor_id'    => self::number( $comment->actor_id ),
				'occurred_at' => self::number( $comment->occurred_at, true ),
				'body'        => $comment->body,
			);
		}
		$task = Task::restore( self::decode( $row->payload ), $events, $comments );
		$due  = null === $row->due_at ? null : self::number( $row->due_at, true );
		if ( $task->id !== $id || self::number( $row->author_id ) !== $task->author_id || self::number( $row->assignee_id ) !== $task->assignee_id || $task->unit_id !== $row->unit_id || self::number( $row->revision ) !== $task->revision || $task->state->value !== $row->state || self::number( $row->updated_at, true ) !== $task->updated_at || $task->content->due_at !== $due || $task->create_request_id !== $row->create_request_id || hash( 'sha256', self::json( $task->toArray() ) ) !== $row->digest ) {
			throw new RuntimeException( 'Cabeçalho de tarefa incompatível.' );
		}
		$previous = str_repeat( '0', 64 );
		foreach ( $task->history as $index => $event ) {
			$record                = $task->toArray();
			$record['assignee_id'] = $event['after']['assignee_id'];
			$record['revision']    = $event['revision'];
			$record['state']       = $event['after']['state'];
			$record['content']     = $event['after']['content'];
			$record['updated_at']  = $event['occurred_at'];
			unset( $record['attachments'] );
			if ( isset( $event['after']['attachments'] ) ) {
				$record['attachments'] = $event['after']['attachments']; }
			$hash  = hash( 'sha256', self::json( $record ) );
			$entry = $audit[ $index ];
			if ( self::number( $entry->revision ) !== $event['revision'] || self::number( $entry->actor_id ) !== $event['actor_id'] || self::number( $entry->occurred_at, true ) !== $event['occurred_at'] || $entry->event !== $event['operation'] || $entry->reason !== $event['reason'] || $entry->before_hash !== $previous || $entry->after_hash !== $hash ) {
				throw new RuntimeException( 'Auditoria de tarefa incompatível.' ); }
			$previous = $hash;
		}
		if ( $previous !== $row->digest ) {
			throw new RuntimeException( 'Cadeia de tarefa incompatível.' ); }
		return $task;
	}

	private function persist( ?Task $before, Task $after, array $people, int $now ): void {
		if ( $before === $after ) {
			return; }
		$payload = self::json( $after->toArray() );
		$digest  = hash( 'sha256', $payload );
		// wpdb validates each column's charset, rather than treating a mixed
		// ASCII/UTF-8 table as an ASCII-only raw SQL query.
		$this->assertWrite(
			$this->database->update(
				$this->schema->table( 'tasks' ),
				array(
					'assignee_id' => $after->assignee_id,
					'revision'    => $after->revision,
					'state'       => $after->state->value,
					'due_at'      => $after->content->due_at,
					'updated_at'  => $after->updated_at,
					'payload'     => $payload,
					'digest'      => $digest,
				),
				array(
					'id'       => $after->id,
					'revision' => $before?->revision ?? 1,
				),
				array( '%d', '%d', '%s', '%d', '%d', '%s', '%s' ),
				array( '%d', '%d' )
			)
		);
		$event = $after->history[ $after->revision - 1 ];
		if ( 'commented' === $event['operation'] ) {
			$comment = $after->comments[ array_key_last( $after->comments ) ];
			$this->assertWrite(
				$this->database->insert(
					$this->schema->table( 'comments' ),
					array(
						'task_id'     => $after->id,
						'revision'    => $after->revision,
						'actor_id'    => $comment['actor_id'],
						'occurred_at' => $comment['occurred_at'],
						'body'        => $comment['body'],
					),
					array( '%d', '%d', '%d', '%d', '%s' )
				)
			);
		}
		$this->assertWrite(
			$this->database->insert(
				$this->schema->table( 'audit' ),
				array(
					'task_id'     => $after->id,
					'revision'    => $after->revision,
					'event'       => $event['operation'],
					'actor_id'    => $event['actor_id'],
					'occurred_at' => $event['occurred_at'],
					'reason'      => $event['reason'],
					'before_hash' => null === $before ? str_repeat( '0', 64 ) : hash( 'sha256', self::json( $before->toArray() ) ),
					'after_hash'  => $digest,
					'payload'     => self::json( $event ),
				),
				array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
			)
		);
		// The conservative local preset notifies only the current eligible assignee.
		$recipient = $people[ $after->assignee_id ] ?? null;
		if ( $recipient instanceof ActorContext && TaskPolicy::recipient( $recipient, $after->unit_id, $now ) ) {
			$this->assertWrite(
				$this->database->insert(
					$this->schema->table( 'notifications' ),
					array(
						'task_id'      => $after->id,
						'revision'     => $after->revision,
						'recipient_id' => $after->assignee_id,
						'kind'         => self::kind( $event['operation'] ),
						'created_at'   => $now,
						'read_at'      => null,
					),
					array( '%d', '%d', '%d', '%s', '%d', '%d' )
				)
			);
		}
	}

	private function notice( object $row, Task $task, ActorContext $actor ): array {
		$revision = self::number( $row->revision );
		$event    = $task->history[ $revision - 1 ] ?? null;
		$created  = self::number( $row->created_at, true );
		$read     = null === $row->read_at ? null : self::number( $row->read_at, true );
		if ( self::number( $row->task_id ) !== $task->id || self::number( $row->recipient_id ) !== $actor->id() || null === $event || $event['after']['assignee_id'] !== $actor->id() || self::kind( $event['operation'] ) !== $row->kind || $created !== $event['occurred_at'] || ( null !== $read && $read < $created ) ) {
			throw new RuntimeException( 'Aviso de tarefa incompatível.' ); }
		return array(
			'id'         => self::number( $row->id ),
			'task_id'    => $task->id,
			'revision'   => $revision,
			'kind'       => $row->kind,
			'title'      => $task->content->title,
			'created_at' => $created,
			'read_at'    => $read,
		);
	}

	private static function kind( string $operation ): string {
		return match ( $operation ) {
			'attached' => 'task_attached', 'created' => 'task_assigned', 'reassigned' => 'task_reassigned', 'due_changed' => 'task_due_changed', 'commented' => 'task_commented', 'started', 'completed', 'cancelled' => 'task_state_changed', default => throw new RuntimeException( 'Evento de tarefa incompatível.' ) };
	}

	private static function validateFields( string $operation, array $fields ): void {
		$keys = match ( $operation ) {
			'start', 'complete', 'cancel' => array( 'reason' ), 'reassign' => array( 'assignee_id', 'reason' ), 'change_due' => array( 'due', 'reason' ), 'comment' => array( 'body' ), default => null };
		if ( null === $keys || count( $keys ) !== count( $fields ) || array_diff( array_keys( $fields ), $keys ) ) {
			throw new InvalidArgumentException( 'Campos de tarefa inválidos.' ); }
		foreach ( $fields as $key => $value ) {
			if ( ( 'assignee_id' === $key && ( ! is_int( $value ) || $value < 1 ) ) || ( 'due' === $key && null !== $value && ! is_string( $value ) ) || ( in_array( $key, array( 'reason', 'body' ), true ) && ! is_string( $value ) ) ) {
				throw new InvalidArgumentException( 'Campo de tarefa inválido.' ); }
		}
	}

	private static function number( mixed $value, bool $zero = false ): int {
		$text = is_int( $value ) ? (string) $value : $value;
		if ( ! is_string( $text ) || 1 !== preg_match( '/\A(?:0|[1-9][0-9]*)\z/D', $text ) || strlen( $text ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $text ) === strlen( (string) PHP_INT_MAX ) && strcmp( $text, (string) PHP_INT_MAX ) > 0 ) || ( ! $zero && '0' === $text ) ) {
			throw new RuntimeException( 'Identificador de tarefa incompatível.' ); }
		return (int) $text;
	}

	private static function json( array $value ): string {
		return json_encode( $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
	}

	private static function decode( string $value ): array {
		$result = json_decode( $value, true, 512, JSON_THROW_ON_ERROR );
		if ( ! is_array( $result ) ) {
			throw new RuntimeException( 'Payload de tarefa incompatível.' ); }
		return $result;
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura de tarefas indisponível.' ); }
		return $rows;
	}

	private function execute( string $sql ): int|bool {
		$result = $this->database->query( $sql );
		if ( false === $result || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Escrita de tarefas indisponível.' ); }
		return $result;
	}

	private function assertWrite( mixed $result ): void {
		if ( 1 !== $result || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Escrita de tarefa incompleta.' );
		}
	}

	private static function denied(): never {
		throw new DomainException( 'A tarefa mudou ou a operação não é permitida.' );
	}
}
