<?php
/** Immutable settings revisions and change validation.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use DomainException;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting -- The serialized state is an exact closed contract.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** No-op returns the same state, so storage must not append an audit record. */
final readonly class SettingsState {
	private function __construct( public int $revision, public UploadPolicy $policy, public int $actor_id, public int $occurred_at, public string $reason ) {}

	public static function initial(): self {
		return new self( 1, UploadPolicy::defaults(), 0, 0, '' );
	}

	public function change( UploadPolicy $policy, int $expected, int $actor_id, int $occurred_at, string $reason ): self {
		if ( $expected !== $this->revision ) {
			throw new DomainException( 'Os parâmetros mudaram. Revê os valores actuais antes de guardar.' );
		}
		self::validateChange( $actor_id, $occurred_at, $reason );
		if ( $occurred_at < $this->occurred_at ) {
			throw new InvalidArgumentException( 'Data da alteração inválida.' );
		}
		if ( $policy->toArray() === $this->policy->toArray() ) {
			return $this;
		}
		if ( PHP_INT_MAX === $this->revision ) {
			throw new DomainException( 'Revisão de parâmetros esgotada.' );
		}
		return new self( $this->revision + 1, $policy, $actor_id, $occurred_at, trim( $reason ) );
	}

	public function toArray(): array {
		return array(
			'revision'    => $this->revision,
			'policy'      => $this->policy->toArray(),
			'actor_id'    => $this->actor_id,
			'occurred_at' => $this->occurred_at,
			'reason'      => $this->reason,
		);
	}

	public static function fromArray( array $value ): self {
		if ( 5 !== count( $value ) || array_diff( array_keys( $value ), array( 'revision', 'policy', 'actor_id', 'occurred_at', 'reason' ) ) || ! is_int( $value['revision'] ?? null ) || $value['revision'] < 1 || ! is_array( $value['policy'] ?? null ) || ! is_int( $value['actor_id'] ?? null ) || ! is_int( $value['occurred_at'] ?? null ) || ! is_string( $value['reason'] ?? null ) ) {
			throw new InvalidArgumentException( 'Estado de parâmetros incompatível.' );
		}
		$policy = UploadPolicy::fromArray( $value['policy'] );
		if ( 1 === $value['revision'] ) {
			if ( 0 !== $value['actor_id'] || 0 !== $value['occurred_at'] || '' !== $value['reason'] || $policy->toArray() !== UploadPolicy::defaults()->toArray() ) {
				throw new InvalidArgumentException( 'Estado inicial de parâmetros incompatível.' );
			}
			return self::initial();
		}
		self::validateChange( $value['actor_id'], $value['occurred_at'], $value['reason'] );
		if ( trim( $value['reason'] ) !== $value['reason'] ) {
			throw new InvalidArgumentException( 'Motivo guardado incompatível.' );
		}
		return new self( $value['revision'], $policy, $value['actor_id'], $value['occurred_at'], $value['reason'] );
	}

	private static function validateChange( int $actor_id, int $occurred_at, string $reason ): void {
		if ( $actor_id < 1 || $occurred_at < 0 || 1 !== preg_match( '//u', $reason ) || '' === trim( $reason ) || mb_strlen( $reason, 'UTF-8' ) > 1000 || 1 === preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $reason ) ) {
			throw new InvalidArgumentException( 'Indica um motivo válido com até 1000 caracteres.' );
		}
	}
}
