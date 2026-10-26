<?php
/** Immutable support ticket with replayable visible conversation.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use Arn\Intranet\F112\PlainText;
use Arn\Intranet\Tasks\Attachment;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final readonly class Ticket {
	private function __construct( public int $id, public int $owner, public string $unit, public int $revision, public TicketState $state, public string $title, public string $body, public string $priority, public int $assigned, public int $updated_at, public array $messages, public array $attachments, public array $history ) {}
	public static function create( int $id, int $owner, string $unit, string $title, string $body, string $priority, int $now ): self {
		self::require( $id > 0 && $owner > 0 && in_array( $unit, array( 'LAB-A', 'LAB-B' ), true ) && $now >= 0 && in_array( $priority, array( 'low', 'normal', 'high' ), true ) );
		self::require( $title === PlainText::value( $title, 160, false ) && $body === PlainText::value( $body, 5000, true ) );
		$r = new self( $id, $owner, $unit, 0, TicketState::New, $title, $body, $priority, 0, $now, array(), array(), array() );
		return $r->record( 'create', $owner, $now, '', $r->snapshot() );
	}
	public function reference(): string {
		return 'LAB-TI-' . str_pad( (string) $this->id, 6, '0', STR_PAD_LEFT ); }
	public function change( string $op, int $actor, array $fields, int $now ): self {
		$keys = match ( $op ) {
			'claim' => array(), 'reply', 'resolve' => array( 'message' ), 'attach' => array( 'attachment' ), default => null };
		self::require( null !== $keys && array_keys( $fields ) === $keys && $actor > 0 && $now >= $this->updated_at && $this->revision < 1000 && TicketState::Resolved !== $this->state );
		$next = $this->snapshot();
		if ( 'claim' === $op ) {
			self::require( TicketState::New === $this->state && 0 === $this->assigned && $actor !== $this->owner );
			$next['assigned'] = $actor;
			$next['state']    = TicketState::InProgress->value;
		} elseif ( 'attach' === $op ) {
			self::require( ( $actor === $this->owner || $actor === $this->assigned ) && count( $this->attachments ) < 8 );
			$item = Attachment::fromArray( $fields['attachment'] );
			self::require( $item->revision === $this->revision + 1 );
			foreach ( $this->attachments as $existing ) {
				self::require( $existing['blob']['id'] !== $item->blob->id ); }
			$next['attachments'][] = $item->toArray();
		} else {
			self::require( is_string( $fields['message'] ) && count( $this->messages ) < 50 );
			$message = PlainText::value( $fields['message'], 2000, true );
			self::require( $message === $fields['message'] );
			if ( 'resolve' === $op ) {
				self::require( TicketState::InProgress === $this->state && $actor === $this->assigned && $actor !== $this->owner );
				$next['state'] = TicketState::Resolved->value;
			} else {
				self::require( ( $actor === $this->owner || $actor === $this->assigned ) && count( $this->messages ) < 49 ); }
			$next['messages'][] = array(
				'actor'    => $actor,
				'at'       => $now,
				'body'     => $message,
				'solution' => 'resolve' === $op,
			);
		}
		return $this->record( $op, $actor, $now, '', $next );
	}
	public function snapshot(): array {
		return array(
			'id'          => $this->id,
			'owner'       => $this->owner,
			'unit'        => $this->unit,
			'revision'    => $this->revision,
			'state'       => $this->state->value,
			'title'       => $this->title,
			'body'        => $this->body,
			'priority'    => $this->priority,
			'assigned'    => $this->assigned,
			'updated_at'  => $this->updated_at,
			'messages'    => $this->messages,
			'attachments' => $this->attachments,
		);
	}
	private function record( string $op, int $actor, int $now, string $reason, array $next ): self {
		$next['revision']   = $this->revision + 1;
		$next['updated_at'] = $now;
		$events             = $this->history;
		$events[]           = array(
			'operation' => $op,
			'actor'     => $actor,
			'at'        => $now,
			'reason'    => $reason,
			'after'     => $next,
		);
		return new self( $this->id, $this->owner, $this->unit, $next['revision'], TicketState::from( $next['state'] ), $this->title, $this->body, $this->priority, $next['assigned'], $now, $next['messages'], $next['attachments'], $events );
	}
	public static function restore( array $events ): self {
		self::require( array_is_list( $events ) && count( $events ) > 0 && count( $events ) <= 1000 );
		$r = null;
		foreach ( $events as $e ) {
			self::require( is_array( $e ) && array_keys( $e ) === array( 'operation', 'actor', 'at', 'reason', 'after' ) && is_array( $e['after'] ) && '' === $e['reason'] );
			$s = $e['after'];
			self::require( array_keys( $s ) === array( 'id', 'owner', 'unit', 'revision', 'state', 'title', 'body', 'priority', 'assigned', 'updated_at', 'messages', 'attachments' ) && is_array( $s['messages'] ) && array_is_list( $s['messages'] ) && is_array( $s['attachments'] ) && array_is_list( $s['attachments'] ) );
			if ( null === $r ) {
				self::require( 'create' === $e['operation'] && $e['actor'] === $s['owner'] );
				$r = self::create( $s['id'], $s['owner'], $s['unit'], $s['title'], $s['body'], $s['priority'], $e['at'] );
			} else {
				$fields = match ( $e['operation'] ) {
					'claim' => array(),
					'reply', 'resolve' => array( 'message' => $s['messages'][ array_key_last( $s['messages'] ) ]['body'] ?? null ),
					'attach' => array( 'attachment' => $s['attachments'][ array_key_last( $s['attachments'] ) ] ?? array() ),
					default => throw new InvalidArgumentException( 'Evento de ticket inválido.' ),
				};
				$r = $r->change( $e['operation'], $e['actor'], $fields, $e['at'] );
			}
			self::require( $r->history[ array_key_last( $r->history ) ] === $e );
		}
		return $r;
	}
	private static function require( bool $ok ): void {
		if ( ! $ok ) {
			throw new InvalidArgumentException( 'Estado ou histórico de ticket inválido.' ); } }
}
