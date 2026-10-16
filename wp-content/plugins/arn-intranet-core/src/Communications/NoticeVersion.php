<?php
/** Notice immutable version.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use InvalidArgumentException;

/** Original version actor and timestamp never change. */
final readonly class NoticeVersion {

	/** Validated identity.
	 *
	 * @param int           $number Consecutive version.
	 * @param NoticeContent $content Complete content.
	 * @param int           $actor_id Actual writer.
	 * @param int           $created_at Server clock.
	 */
	public function __construct( public int $number, public NoticeContent $content, public int $actor_id, public int $created_at ) {
		if ( $number < 1 || $actor_id < 1 || $created_at < 0 ) {
			self::invalid();
		}
	}

	/** Strict persistence shape.
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

	/** Hydrate without casts.
	 *
	 * @param array $data Stored data.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		if ( 4 !== count( $data ) || array_diff( array_keys( $data ), array( 'number', 'content', 'actor_id', 'created_at' ) ) || ! is_int( $data['number'] ) || ! is_array( $data['content'] ) || ! is_int( $data['actor_id'] ) || ! is_int( $data['created_at'] ) ) {
			self::invalid();
		}
		return new self( $data['number'], NoticeContent::fromArray( $data['content'] ), $data['actor_id'], $data['created_at'] );
	}

	/** Refuse invalid stored evidence.
	 *
	 * @throws InvalidArgumentException For invalid stored data.
	 * @return never
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
		throw new InvalidArgumentException( 'Versão de comunicado inválida.' );
	}
}
