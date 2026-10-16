<?php
/** Immutable plain-text notice version content.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\F112\Audience;
use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;

/** Circuit never grants authority. */
final readonly class NoticeContent {

	/** Normalised title.
	 *
	 * @var string
	 */
	public string $title;
	/** Normalised body.
	 *
	 * @var string
	 */
	public string $body;

	/** Validate a complete version.
	 *
	 * @param string   $title Title.
	 * @param string   $body Body.
	 * @param Audience $audience Recipients.
	 * @param string   $circuit Local workflow.
	 * @param int|null $valid_until Exclusive expiry.
	 */
	public function __construct( string $title, string $body, public Audience $audience, public string $circuit, public ?int $valid_until ) {
		$this->title = PlainText::value( $title, 160, false );
		$this->body  = PlainText::value( $body, 10000, true );
		if ( ! in_array( $circuit, array( 'team-direct', 'institutional-review' ), true ) || ( 'team-direct' === $circuit && null === $audience->unit_id ) || ( null !== $valid_until && $valid_until <= 0 ) ) {
			self::invalid();
		}
	}

	/** Persistent data.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'title'       => $this->title,
			'body'        => $this->body,
			'audience'    => $this->audience->toArray(),
			'circuit'     => $this->circuit,
			'valid_until' => $this->valid_until,
		);
	}

	/** Strict stored shape.
	 *
	 * @param array $data Stored data.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		if ( 5 !== count( $data ) || array_diff( array_keys( $data ), array( 'title', 'body', 'audience', 'circuit', 'valid_until' ) ) || ! is_string( $data['title'] ) || ! is_string( $data['body'] ) || ! is_array( $data['audience'] ) || ! is_string( $data['circuit'] ) || ( null !== $data['valid_until'] && ! is_int( $data['valid_until'] ) ) ) {
			self::invalid();
		}
		$result = new self( $data['title'], $data['body'], Audience::fromArray( $data['audience'] ), $data['circuit'], $data['valid_until'] );
		if ( $result->title !== $data['title'] || $result->body !== $data['body'] ) { // Stored text must already be canonical.
			self::invalid();
		}
		return $result;
	}

	/** Refuse invalid data.
	 *
	 * @throws InvalidArgumentException For invalid stored data.
	 * @return never
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
		throw new InvalidArgumentException( 'Conteúdo de comunicado inválido.' );
	}
}
