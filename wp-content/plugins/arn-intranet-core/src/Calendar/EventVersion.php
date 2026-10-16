<?php
/** Immutable event version.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Calendar;

use InvalidArgumentException;

/** Version actor is distinct from the immutable event organiser. */
final readonly class EventVersion {

	/** Validate version identity.
	 *
	 * @param int          $number Version number.
	 * @param EventContent $content Complete content.
	 * @param int          $actor_id Actual writer.
	 * @param int          $created_at Clock.
	 */
	public function __construct( public int $number, public EventContent $content, public int $actor_id, public int $created_at ) {
		if ( $number < 1 || $actor_id < 1 || $created_at < 0 ) {
			self::invalid();
		}
	}

	/** Persistent version.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'number'     => $this->number,
			'content'    => $this->content->toArray(),
			'actor_id'   => $this->actor_id,
			'created_at' => $this->created_at,
		);
	}

	/** Strict hydration.
	 *
	 * @param array $data Stored data.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		if ( 4 !== count( $data ) || array_diff( array_keys( $data ), array( 'number', 'content', 'actor_id', 'created_at' ) ) || ! is_int( $data['number'] ) || ! is_array( $data['content'] ) || ! is_int( $data['actor_id'] ) || ! is_int( $data['created_at'] ) ) {
			self::invalid();
		}
		return new self( $data['number'], EventContent::fromArray( $data['content'] ), $data['actor_id'], $data['created_at'] );
	}

	/** Refuse invalid data.
	 *
	 * @return never
	 * @throws InvalidArgumentException For invalid identity.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
		throw new InvalidArgumentException( 'Versão de evento inválida.' );
	}
}
