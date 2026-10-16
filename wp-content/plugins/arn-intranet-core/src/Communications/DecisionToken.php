<?php
/** Opaque actor-bound current submission proof.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use InvalidArgumentException;

/** Does not embed a private revision in the browser-visible value. */
final class DecisionToken {

	/** Bind the exact object, reviewer, version and private CAS.
	 *
	 * @param Notice $notice Current submission.
	 * @param int    $reviewer Intended second actor.
	 * @param string $secret Server-only HMAC key.
	 * @throws InvalidArgumentException For invalid submission or key.
	 * @return string
	 */
	public static function make( Notice $notice, int $reviewer, string $secret ): string {
		if ( NoticeState::Pending !== $notice->state || $reviewer < 1 || $reviewer === $notice->author_id || strlen( $secret ) < 32 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
			throw new InvalidArgumentException( 'Prova de decisão inválida.' );
		}
		return hash_hmac( 'sha256', implode( ':', array( 'arn-f112-decision-v1', $notice->id, $reviewer, $notice->submitted_version, $notice->revision ) ), $secret );
	}

	/** Stale, foreign and malformed values never decide.
	 *
	 * @param Notice $notice Current submission.
	 * @param int    $reviewer Reviewer.
	 * @param string $secret Server key.
	 * @param string $token Supplied opaque value.
	 * @return bool
	 */
	public static function valid( Notice $notice, int $reviewer, string $secret, string $token ): bool {
		if ( 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $token ) || NoticeState::Pending !== $notice->state || $reviewer < 1 || $reviewer === $notice->author_id || strlen( $secret ) < 32 ) {
			return false;
		}
		return hash_equals( self::make( $notice, $reviewer, $secret ), $token );
	}
}
