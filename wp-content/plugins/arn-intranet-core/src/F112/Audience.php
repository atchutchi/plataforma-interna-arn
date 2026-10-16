<?php
/** Closed fictional audiences, without real staff identifiers.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Arn\Intranet\Authorization\Scope;
use InvalidArgumentException;

/** One explicit all-or-unit recipient set. */
final readonly class Audience {

	/** Validated factory values.
	 *
	 * @param string      $type Kind.
	 * @param string|null $unit_id Unit.
	 */
	private function __construct( public string $type, public ?string $unit_id ) {}

	/** All fictional recipients.
	 *
	 * @return self
	 */
	public static function all(): self {
		return new self( 'all', null );
	}

	/** Exactly one leaf unit.
	 *
	 * @param string $unit_id Unit.
	 * @return self
	 */
	public static function unit( string $unit_id ): self {
		if ( ! in_array( $unit_id, array( 'LAB-A', 'LAB-B' ), true ) ) {
			self::invalid();
		}
		return new self( 'unit', $unit_id );
	}

	/** Explicit serialisable fields.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'type'    => $this->type,
			'unit_id' => $this->unit_id,
		);
	}

	/** No extra keys or permissive casts.
	 *
	 * @param array $data Stored value.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		if ( 2 !== count( $data ) || array_diff( array_keys( $data ), array( 'type', 'unit_id' ) ) ) {
			self::invalid();
		}
		if ( 'all' === $data['type'] && null === $data['unit_id'] ) {
			return self::all();
		}
		if ( 'unit' === $data['type'] && is_string( $data['unit_id'] ) ) {
			return self::unit( $data['unit_id'] );
		}
		self::invalid();
	}

	/** Module-specific scope, never inferred from another module.
	 *
	 * @return Scope
	 */
	public function scope(): Scope {
		return Scope::audiences( array( null === $this->unit_id ? 'f112_all' : 'f112_unit_' . $this->unit_id ) );
	}

	/** Stop invalid hydration.
	 *
	 * @throws InvalidArgumentException For invalid stored data.
	 * @return never
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
		throw new InvalidArgumentException( 'Audiência inválida.' );
	}
}
