<?php
/**
 * Explicit immutable news audience.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use InvalidArgumentException;

/** No unit hierarchy or managerial membership is inferred. */
final readonly class Audience {
	/** Explicit identifiers without hierarchy.
	 *
	 * @var array<int|string>
	 */
	public array $targets;

	/** Validate and detach exact targets.
	 *
	 * @param string $kind Audience kind.
	 * @param array  $targets Unit or person identifiers.
	 */
	private function __construct( public string $kind, array $targets ) {
		$copy = array();
		if ( 'all' === $kind ) {
			if ( array() !== $targets ) {
				self::invalid();
			}
		} elseif ( ! in_array( $kind, array( 'units', 'people' ), true ) || array() === $targets || array_keys( $targets ) !== range( 0, count( $targets ) - 1 ) ) {
			self::invalid();
		}
		foreach ( $targets as $target ) {
			$valid = 'units' === $kind
				? is_string( $target ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{1,50}\z/', $target )
				: is_int( $target ) && $target > 0;
			if ( ! $valid || in_array( $target, $copy, true ) ) {
				self::invalid();
			}
			$copy[] = $target;
		}
		$this->targets = $copy;
	}

	/** Address all eligible readers. */
	public static function all(): self {
		return new self( 'all', array() );
	}

	/** Address explicit units.
	 *
	 * @param array $unit_ids Unit identifiers.
	 */
	public static function units( array $unit_ids ): self {
		return new self( 'units', $unit_ids );
	}

	/** Address explicit people.
	 *
	 * @param array $user_ids Positive user IDs.
	 */
	public static function people( array $user_ids ): self {
		return new self( 'people', $user_ids );
	}

	/** Check membership with server supplied unit IDs.
	 *
	 * @param int   $user_id Current user.
	 * @param array $unit_ids Current unit memberships.
	 */
	public function includes( int $user_id, array $unit_ids ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( 'all' === $this->kind ) {
			return true;
		}
		if ( 'people' === $this->kind ) {
			return in_array( $user_id, $this->targets, true );
		}
		foreach ( $unit_ids as $unit_id ) {
			if ( is_string( $unit_id ) && in_array( $unit_id, $this->targets, true ) ) {
				return true;
			}
		}
		return false;
	}

	/** Map every audience target to its authorization identifier. */
	public function permissionScope(): Scope {
		$targets = match ( $this->kind ) {
			'all' => array( 'news_all' ),
			'units' => array_map( static fn( string $id ): string => 'news_unit_' . $id, $this->targets ),
			'people' => array_map( static fn( int $id ): string => 'news_person_' . $id, $this->targets ),
		};
		return Scope::audiences( $targets );
	}

	/** Reject invalid or ambiguous audience input.
	 *
	 * @throws InvalidArgumentException Always.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
		throw new InvalidArgumentException( 'Audiência inválida.' );
	}
}
