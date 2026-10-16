<?php
/** Immutable People aggregate and canonical digest.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

use InvalidArgumentException;

/** Revisions replace only self-service values. */
final readonly class Profile {
	/** Require a real account, positive revision and Unix timestamp.
	 *
	 * @param int                 $user_id WordPress user ID.
	 * @param ProfessionalDetails $professional Fixed professional details.
	 * @param SelfService         $self_service Editable contact details.
	 * @param int                 $revision Positive revision.
	 * @param int                 $updated_at Unix timestamp.
	 * @throws InvalidArgumentException If metadata is invalid.
	 */
	public function __construct(
		public int $user_id,
		public ProfessionalDetails $professional,
		public SelfService $self_service,
		public int $revision,
		public int $updated_at,
	) {
		if ( $user_id <= 0 || $revision <= 0 || $updated_at < 0 ) {
			throw new InvalidArgumentException( 'Perfil inválido.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure domain exception.
		}
	}

	/** Enforce owner, CAS and monotonic time before detecting a no-op.
	 *
	 * @param SelfService $self_service Replacement contacts.
	 * @param int         $expected_revision Expected revision.
	 * @param int         $actor_id Authenticated actor.
	 * @param int         $now Current Unix timestamp.
	 * @throws InvalidArgumentException If the revision is unauthorized or stale.
	 */
	public function revise( SelfService $self_service, int $expected_revision, int $actor_id, int $now ): self {
		if ( $actor_id !== $this->user_id || $expected_revision !== $this->revision || $now < $this->updated_at ) {
			throw new InvalidArgumentException( 'Revisão do perfil recusada.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure domain exception.
		}
		if ( array() === $this->changedFields( $self_service ) ) {
			return $this;
		}
		if ( PHP_INT_MAX === $this->revision ) {
			throw new InvalidArgumentException( 'Revisão do perfil excede o limite.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure domain exception.
		}
		return new self( $this->user_id, $this->professional, $self_service, $this->revision + 1, $now );
	}

	/** Return changed contact field names in a stable order.
	 *
	 * @param SelfService $self_service Replacement contacts.
	 */
	public function changedFields( SelfService $self_service ): array {
		$changed = array();
		foreach ( array( 'phone', 'office', 'bio' ) as $field ) {
			if ( $this->self_service->$field !== $self_service->$field ) {
				$changed[] = $field;
			}
		}
		return $changed;
	}

	/** Hash ordered scalar fields for audit consistency checks. */
	public function digest(): string {
		$fields = array(
			'user_id'    => $this->user_id,
			'code'       => $this->professional->code,
			'name'       => $this->professional->name,
			'email'      => $this->professional->email,
			'job'        => $this->professional->job,
			'unit_id'    => $this->professional->unit_id,
			'phone'      => $this->self_service->phone,
			'office'     => $this->self_service->office,
			'bio'        => $this->self_service->bio,
			'revision'   => $this->revision,
			'updated_at' => $this->updated_at,
		);
		return hash( 'sha256', json_encode( $fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Canonical audit encoding.
	}
}
