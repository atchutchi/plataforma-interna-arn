<?php
/** Immutable request with replayable process history.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.YodaConditions -- Domain operations and strict ordered payloads.
final readonly class InternalRequest {
	private function __construct( public int $id, public int $owner, public RequestType $type, public int $revision, public RequestState $state, public string $title, public string $body, public int $updated_at, public array $substitute, public array $attachments, public array $history ) {}

	public static function create( int $id, int $owner, RequestType $type, string $title, string $body, int $now ): self {
		if ( $id < 1 || $owner < 1 || ! $type->active || $now < 0 ) {
			throw new InvalidArgumentException( 'Novo pedido inválido.' );
		}
		self::content( $title, $body );
		$empty = new self( $id, $owner, $type, 0, RequestState::Draft, $title, $body, $now, array(), array(), array() );
		return $empty->record( 'create', $owner, $now, '', $empty->snapshot() );
	}

	public function reference(): string {
		return 'LAB-PED-' . str_pad( (string) $this->id, 6, '0', STR_PAD_LEFT );
	}

	/** The repository separately verifies current competence, session and scope. */
	public function change( string $operation, int $actor, array $fields, int $now ): self {
		if ( $actor < 1 || $now < $this->updated_at || $this->revision >= 1000 ) {
			throw new InvalidArgumentException( 'Alteração de pedido inválida.' );
		}
		$keys = match ( $operation ) {
			'edit' => array( 'title', 'body' ), 'submit' => array( 'type' ), 'start', 'withdraw', 'revoke_substitute' => array(), 'return', 'approve', 'reject', 'cancel' => array( 'reason' ), 'substitute' => array( 'user', 'starts', 'ends', 'reason' ), 'attach' => array( 'attachment' ), default => null,
		};
		if ( null === $keys || array_keys( $fields ) !== $keys ) {
			throw new InvalidArgumentException( 'Campos de pedido inválidos.' );
		}
		$next   = $this->snapshot();
		$reason = '';
		if ( in_array( $operation, array( 'edit', 'submit', 'withdraw', 'attach' ), true ) && $actor !== $this->owner ) {
			throw new InvalidArgumentException( 'Autoria de pedido inválida.' );
		}
		if ( in_array( $operation, array( 'start', 'return', 'approve', 'reject', 'cancel' ), true ) && ! $this->reviewer( $actor, $now ) ) {
			throw new InvalidArgumentException( 'Decisor de pedido inválido.' );
		}
		if ( isset( $fields['reason'] ) ) {
			if ( ! is_string( $fields['reason'] ) ) {
				throw new InvalidArgumentException( 'Fundamentação inválida.' );
			}
			$reason = PlainText::value( $fields['reason'], 1000, true );
		}
		switch ( $operation ) {
			case 'edit':
				self::require( $this->state->editable() );
				self::content( $fields['title'], $fields['body'] );
				$next['title'] = $fields['title'];
				$next['body']  = $fields['body'];
				break;
			case 'submit':
				self::require( $this->state->editable() );
				$type = RequestType::restore( $fields['type'] );
				self::require( $type->active && $type->id === $this->type->id && $type->unit === $this->type->unit && $type->revision >= $this->type->revision );
				$next['type']       = $type->data();
				$next['state']      = RequestState::Submitted->value;
				$next['substitute'] = array();
				break;
			case 'start':
				self::require( RequestState::Submitted === $this->state );
				$next['state'] = RequestState::Analysis->value;
				break;
			case 'return':
			case 'approve':
			case 'reject':
				self::require( RequestState::Analysis === $this->state );
				$next['state'] = match ( $operation ) {
					'return' => 'returned', 'approve' => 'approved', 'reject' => 'rejected' };
				$next['substitute'] = array();
				break;
			case 'withdraw':
				self::require( $this->state->pending() || $this->state->editable() );
				$next['state']      = RequestState::Withdrawn->value;
				$next['substitute'] = array();
				break;
			case 'cancel':
				self::require( RequestState::Approved === $this->state );
				$next['state'] = RequestState::Cancelled->value;
				break;
			case 'substitute':
				self::require( $this->state->pending() && $actor === $this->type->reviewer && $actor !== $this->owner );
				self::require( is_int( $fields['user'] ) && $fields['user'] > 0 && $fields['user'] !== $actor && $fields['user'] !== $this->owner && is_int( $fields['starts'] ) && is_int( $fields['ends'] ) && $fields['starts'] >= $now && $fields['ends'] > $fields['starts'] && $fields['ends'] - $now <= 30 * 86400 );
				$next['substitute'] = array(
					'user'   => $fields['user'],
					'starts' => $fields['starts'],
					'ends'   => $fields['ends'],
				);
				break;
			case 'revoke_substitute':
				self::require( $this->state->pending() && $actor === $this->type->reviewer && $actor !== $this->owner && array() !== $this->substitute );
				$next['substitute'] = array();
				break;
			case 'attach':
				self::require( $this->state->editable() && $this->type->attachments && count( $this->attachments ) < 8 );
				$attachment = \Arn\Intranet\Tasks\Attachment::fromArray( $fields['attachment'] );
				self::require( $attachment->revision === $this->revision + 1 );
				foreach ( $this->attachments as $existing ) {
					self::require( $existing['blob']['id'] !== $attachment->blob->id );
				}
				$next['attachments'][] = $attachment->toArray();
				break;
		}
		return $this->record( $operation, $actor, $now, $reason, $next );
	}

	public function reviewer( int $actor, int $now ): bool {
		return $actor !== $this->owner && ( $actor === $this->type->reviewer || ( ( $this->substitute['user'] ?? 0 ) === $actor && $now >= $this->substitute['starts'] && $now < $this->substitute['ends'] ) );
	}

	public function snapshot(): array {
		return array(
			'id'          => $this->id,
			'owner'       => $this->owner,
			'type'        => $this->type->data(),
			'revision'    => $this->revision,
			'state'       => $this->state->value,
			'title'       => $this->title,
			'body'        => $this->body,
			'updated_at'  => $this->updated_at,
			'substitute'  => $this->substitute,
			'attachments' => $this->attachments,
		);
	}

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
		return new self( $this->id, $this->owner, RequestType::restore( $next['type'] ), $next['revision'], RequestState::from( $next['state'] ), $next['title'], $next['body'], $now, $next['substitute'], $next['attachments'], $history );
	}

	/** Reconstruct every operation, never trust a stored final state alone. */
	public static function restore( array $events ): self {
		self::require( array_is_list( $events ) && count( $events ) > 0 && count( $events ) <= 1000 );
		$result = null;
		foreach ( $events as $event ) {
			self::require( is_array( $event ) && array_keys( $event ) === array( 'operation', 'actor', 'at', 'reason', 'after' ) && is_array( $event['after'] ) );
			$after = $event['after'];
			self::require( array_keys( $after ) === array( 'id', 'owner', 'type', 'revision', 'state', 'title', 'body', 'updated_at', 'substitute', 'attachments' ) );
			self::require( is_string( $event['operation'] ) && is_int( $event['actor'] ) && is_int( $event['at'] ) && is_string( $event['reason'] ) && is_array( $after['type'] ) && is_array( $after['substitute'] ) && is_array( $after['attachments'] ) && array_is_list( $after['attachments'] ) );
			if ( 'substitute' === $event['operation'] ) {
				self::require( array_keys( $after['substitute'] ) === array( 'user', 'starts', 'ends' ) );
			}
			if ( 'attach' === $event['operation'] ) {
				self::require( count( $after['attachments'] ) > 0 );
			}
			if ( null === $result ) {
				self::require( 'create' === $event['operation'] && '' === $event['reason'] && $event['actor'] === $after['owner'] );
				$result = self::create( $after['id'], $after['owner'], RequestType::restore( $after['type'] ), $after['title'], $after['body'], $event['at'] );
			} else {
				$fields = match ( $event['operation'] ) {
					'edit' => array(
						'title' => $after['title'],
						'body'  => $after['body'],
					), 'submit' => array( 'type' => $after['type'] ), 'start', 'withdraw', 'revoke_substitute' => array(), 'return', 'approve', 'reject', 'cancel' => array( 'reason' => $event['reason'] ), 'substitute' => array(
						'user'   => $after['substitute']['user'],
						'starts' => $after['substitute']['starts'],
						'ends'   => $after['substitute']['ends'],
						'reason' => $event['reason'],
					), 'attach' => array( 'attachment' => $after['attachments'][ array_key_last( $after['attachments'] ) ] ), default => throw new InvalidArgumentException( 'Evento de pedido inválido.' ),
				};
				$result = $result->change( $event['operation'], $event['actor'], $fields, $event['at'] );
			}
			self::require( $result->history[ array_key_last( $result->history ) ] === $event );
		}
		return $result;
	}

	private static function content( string $title, string $body ): void {
		self::require( $title === PlainText::value( $title, 160, false ) && $body === PlainText::value( $body, 5000, true ) );
	}

	private static function require( bool $condition ): void {
		if ( ! $condition ) {
			throw new InvalidArgumentException( 'Estado ou histórico de pedido inválido.' );
		}
	}
}
