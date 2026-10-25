<?php
/** Immutable task with strictly replayed audit and comments.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\F112\PlainText;
use DomainException;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting -- The immutable/replay contract is documented in the F1-13 plan.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain errors, never user input.
/** Grant/session authority belongs to TaskPolicy and the locked repository. */
final readonly class Task {

	private function __construct(
		public int $id,
		public int $author_id,
		public string $unit_id,
		public int $assignee_id,
		public int $revision,
		public TaskState $state,
		public TaskContent $content,
		public int $updated_at,
		public string $create_request_id,
		public array $history,
		public array $comments,
		public array $attachments = array(),
	) {}

	public static function create( int $id, int $author, int $assignee, string $unit, TaskContent $content, int $now, string $request ): self {
		if ( $id < 1 || $author < 1 || $assignee < 1 || $now < 0 || ! in_array( $unit, array( 'LAB-A', 'LAB-B' ), true ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $request ) ) {
			self::invalid();
		}
		$event = self::event( 1, 'created', $author, $now, 'Criação e atribuição.', TaskState::Open, $assignee, $content, $unit, $request );
		return new self( $id, $author, $unit, $assignee, 1, TaskState::Open, $content, $now, $request, array( $event ), array() );
	}

	public function start( int $actor, int $expected, int $now, string $reason = '' ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( TaskState::Open !== $this->state || $actor !== $this->assignee_id ) {
			self::denied();
		}
		return $this->next( 'started', $actor, $now, $reason, TaskState::InProgress );
	}

	public function complete( int $actor, int $expected, int $now, string $reason = '' ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( TaskState::InProgress !== $this->state || $actor !== $this->assignee_id ) {
			self::denied();
		}
		return $this->next( 'completed', $actor, $now, $reason, TaskState::Completed );
	}

	public function cancel( int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( $this->state->terminal() || '' === self::text( $reason, 1000, false ) ) {
			self::denied();
		}
		return $this->next( 'cancelled', $actor, $now, $reason, TaskState::Cancelled );
	}

	public function reassign( int $assignee, int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( $assignee < 1 || $this->state->terminal() ) {
			self::denied();
		}
		return $assignee === $this->assignee_id ? $this : $this->next( 'reassigned', $actor, $now, $reason, $this->state, $assignee );
	}

	public function changeDue( ?string $due, int $actor, int $expected, int $now, string $reason ): self {
		$this->guard( $actor, $expected, $now, $reason );
		if ( $this->state->terminal() ) {
			self::denied();
		}
		$content = new TaskContent( $this->content->title, $this->content->description, $this->content->priority, $due );
		return $content->toArray() === $this->content->toArray() ? $this : $this->next( 'due_changed', $actor, $now, $reason, $this->state, null, $content );
	}

	public function comment( string $body, int $actor, int $expected, int $now ): self {
		$this->guard( $actor, $expected, $now, '' );
		$comments   = $this->comments;
		$comments[] = array(
			'revision'    => $this->revision + 1,
			'actor_id'    => $actor,
			'occurred_at' => $now,
			'body'        => self::text( $body, 2000, true ),
		);
		return $this->next( 'commented', $actor, $now, '', $this->state, null, null, $comments );
	}

	public function attach( Attachment $attachment, int $actor, int $expected, int $now ): self {
		$this->guard( $actor, $expected, $now, '' );
		if ( $this->state->terminal() || count( $this->attachments ) >= 8 || $attachment->revision !== $this->revision + 1 ) {
			self::denied();
		}
		$ids = array();
		foreach ( array_merge( $this->attachments, array( $attachment ) ) as $item ) {
			foreach ( array_merge( array( $item->blob ), array_values( $item->blob->derivatives ) ) as $blob ) {
				if ( isset( $ids[ $blob->id ] ) ) {
					self::invalid();
				}
				$ids[ $blob->id ] = true;
			}
		}
		return $this->next( 'attached', $actor, $now, '', $this->state, null, null, null, array_merge( $this->attachments, array( $attachment ) ) );
	}

	public function overdue( int $now ): bool {
		return $now >= 0 && ! $this->state->terminal() && null !== $this->content->due_at && $now > $this->content->due_at;
	}

	public function toArray(): array {
		$record = array(
			'id'                => $this->id,
			'author_id'         => $this->author_id,
			'unit_id'           => $this->unit_id,
			'assignee_id'       => $this->assignee_id,
			'revision'          => $this->revision,
			'state'             => $this->state->value,
			'content'           => $this->content->toArray(),
			'updated_at'        => $this->updated_at,
			'create_request_id' => $this->create_request_id,
		);
		if ( $this->attachments ) {
			$record['attachments'] = array_map( static fn( Attachment $item ): array => $item->toArray(), $this->attachments );
		}
		return $record;
	}

	/** Every stored field must be explained by the complete ordered history. */
	public static function restore( array $record, array $history, array $comments ): self {
		$keys = array( 'id', 'author_id', 'unit_id', 'assignee_id', 'revision', 'state', 'content', 'updated_at', 'create_request_id' );
		if ( array_key_exists( 'attachments', $record ) ) {
			$keys[] = 'attachments'; }
		if ( count( $record ) !== count( $keys ) || array_diff( array_keys( $record ), $keys ) || ! array_is_list( $history ) || ! $history || ! array_is_list( $comments ) ) {
			self::invalid();
		}
		foreach ( array( 'id', 'author_id', 'assignee_id', 'revision', 'updated_at' ) as $key ) {
			if ( ! is_int( $record[ $key ] ) || $record[ $key ] < ( 'updated_at' === $key ? 0 : 1 ) ) {
				self::invalid();
			}
		}
		if ( ! is_string( $record['unit_id'] ) || ! is_string( $record['state'] ) || ! is_string( $record['create_request_id'] ) || ! is_array( $record['content'] ) || count( $history ) !== $record['revision'] ) {
			self::invalid();
		}
		TaskContent::fromArray( $record['content'] );
		$by_revision = array();
		$previous    = 0;
		foreach ( $comments as $comment ) {
			if ( ! is_array( $comment ) || 4 !== count( $comment ) || array_diff( array_keys( $comment ), array( 'revision', 'actor_id', 'occurred_at', 'body' ) ) || ! is_int( $comment['revision'] ) || $comment['revision'] <= $previous || ! is_int( $comment['actor_id'] ) || $comment['actor_id'] < 1 || ! is_int( $comment['occurred_at'] ) || $comment['occurred_at'] < 0 || ! is_string( $comment['body'] ) || self::text( $comment['body'], 2000, true ) !== $comment['body'] ) {
				self::invalid();
			}
			$by_revision[ $comment['revision'] ] = $comment;
			$previous                            = $comment['revision'];
		}
		$task = null;
		foreach ( $history as $index => $event ) {
			self::validateEvent( $event, $index + 1 );
			$after   = $event['after'];
			$content = TaskContent::fromArray( $after['content'] );
			if ( 0 === $index ) {
				$task = self::create( $record['id'], $record['author_id'], $after['assignee_id'], $record['unit_id'], $content, $event['occurred_at'], $record['create_request_id'] );
			} else {
				$actor  = $event['actor_id'];
				$now    = $event['occurred_at'];
				$reason = $event['reason'];
				$task   = match ( $event['operation'] ) {
					'attached' => $task->attach( Attachment::fromArray( $after['attachments'][ array_key_last( $after['attachments'] ) ] ), $actor, $task->revision, $now ),
					'started' => $task->start( $actor, $task->revision, $now, $reason ),
					'completed' => $task->complete( $actor, $task->revision, $now, $reason ),
					'cancelled' => $task->cancel( $actor, $task->revision, $now, $reason ),
					'reassigned' => $task->reassign( $after['assignee_id'], $actor, $task->revision, $now, $reason ),
					'due_changed' => $task->changeDue( null === $content->due_at ? null : gmdate( 'Y-m-d\TH:i:s\Z', $content->due_at ), $actor, $task->revision, $now, $reason ),
					'commented' => $task->replayComment( $by_revision[ $index + 1 ] ?? null, $actor, $now ),
					default => throw new InvalidArgumentException( 'Histórico de tarefa inválido.' ),
				};
			}
			if ( ! isset( $task->history[ $index ] ) || self::canonical( $task->history[ $index ] ) !== self::canonical( $event ) ) {
				self::invalid();
			}
		}
		if ( self::canonical( $task->toArray() ) !== self::canonical( $record ) || self::canonical( $task->comments ) !== self::canonical( $comments ) ) {
			self::invalid();
		}
		return $task;
	}

	private function replayComment( ?array $comment, int $actor, int $now ): self {
		if ( null === $comment || $comment['actor_id'] !== $actor || $comment['occurred_at'] !== $now ) {
			self::invalid();
		}
		return $this->comment( $comment['body'], $actor, $this->revision, $now );
	}

	private static function validateEvent( mixed $event, int $revision ): void {
		if ( ! is_array( $event ) || 7 !== count( $event ) || array_diff( array_keys( $event ), array( 'revision', 'operation', 'actor_id', 'occurred_at', 'reason', 'after', 'comment_sha256' ) ) || ! is_int( $event['revision'] ) || $event['revision'] !== $revision || ! is_string( $event['operation'] ) || ! is_int( $event['actor_id'] ) || $event['actor_id'] < 1 || ! is_int( $event['occurred_at'] ) || $event['occurred_at'] < 0 || ! is_string( $event['reason'] ) || ! is_array( $event['after'] ) || ( null !== $event['comment_sha256'] && ( ! is_string( $event['comment_sha256'] ) || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $event['comment_sha256'] ) ) ) ) {
			self::invalid();
		}
		$after = $event['after'];
		if ( array_key_exists( 'attachments', $after ) && ( ! is_array( $after['attachments'] ) || ! array_is_list( $after['attachments'] ) || ! $after['attachments'] || count( $after['attachments'] ) > 8 ) ) {
			self::invalid();
		}
		if ( 'attached' === $event['operation'] && ( empty( $after['attachments'] ) || ! is_array( $after['attachments'][ array_key_last( $after['attachments'] ) ] ) ) ) {
			self::invalid();
		}
		if ( ! in_array( count( $after ), array( 5, 6 ), true ) || array_diff( array_keys( $after ), array( 'state', 'assignee_id', 'content', 'unit_id', 'create_request_id', 'attachments' ) ) || ! is_string( $after['state'] ) || null === TaskState::tryFrom( $after['state'] ) || ! is_int( $after['assignee_id'] ) || $after['assignee_id'] < 1 || ! is_array( $after['content'] ) || ! is_string( $after['unit_id'] ) || ! in_array( $after['unit_id'], array( 'LAB-A', 'LAB-B' ), true ) || ! is_string( $after['create_request_id'] ) || 1 !== preg_match( '/\A[a-f0-9]{32}\z/D', $after['create_request_id'] ) ) {
			self::invalid();
		}
	}

	private function guard( int $actor, int $expected, int $now, string $reason ): void {
		if ( $actor < 1 || $expected !== $this->revision || PHP_INT_MAX === $this->revision || $now < $this->updated_at ) {
			self::denied();
		}
		self::text( $reason, 1000, false );
	}

	private function next( string $operation, int $actor, int $now, string $reason, TaskState $state, ?int $assignee = null, ?TaskContent $content = null, ?array $comments = null, ?array $attachments = null ): self {
		$attachments ??= $this->attachments;
		$assignee    ??= $this->assignee_id;
		$content     ??= $this->content;
		$history       = $this->history;
		$comment_hash  = 'commented' === $operation ? hash( 'sha256', $comments[ array_key_last( $comments ) ]['body'] ) : null;
		$history[]     = self::event( $this->revision + 1, $operation, $actor, $now, self::text( $reason, 1000, false ), $state, $assignee, $content, $this->unit_id, $this->create_request_id, $comment_hash, $attachments );
		return new self( $this->id, $this->author_id, $this->unit_id, $assignee, $this->revision + 1, $state, $content, $now, $this->create_request_id, $history, $comments ?? $this->comments, $attachments );
	}

	private static function event( int $revision, string $operation, int $actor, int $now, string $reason, TaskState $state, int $assignee, TaskContent $content, string $unit, string $request, ?string $comment_hash = null, array $attachments = array() ): array {
		$event = array(
			'revision'       => $revision,
			'operation'      => $operation,
			'actor_id'       => $actor,
			'occurred_at'    => $now,
			'reason'         => $reason,
			'comment_sha256' => $comment_hash,
			'after'          => array(
				'state'             => $state->value,
				'assignee_id'       => $assignee,
				'content'           => $content->toArray(),
				'unit_id'           => $unit,
				'create_request_id' => $request,
			),
		);
		if ( $attachments ) {
			$event['after']['attachments'] = array_map( static fn( Attachment $item ): array => $item->toArray(), $attachments );
		}
		return $event;
	}

	private static function text( string $value, int $maximum, bool $required ): string {
		if ( false !== strpbrk( $value, '<>' ) ) {
			self::invalid();
		}
		return PlainText::value( $value, $maximum, true, $required );
	}

	private static function invalid(): never {
		throw new InvalidArgumentException( 'Registo de tarefa inválido.' );
	}

	/** Object-key order is immaterial. Scalar types and list order remain strict. */
	private static function canonical( array $value ): array {
		foreach ( $value as $key => $item ) {
			if ( is_array( $item ) ) {
				$value[ $key ] = self::canonical( $item );
			}
		}
		if ( ! array_is_list( $value ) ) {
			ksort( $value, SORT_STRING );
		}
		return $value;
	}

	private static function denied(): never {
		throw new DomainException( 'A tarefa mudou ou a operação não é permitida.' );
	}
}
