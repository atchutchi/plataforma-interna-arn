<?php
/** Short-lived server proof of an exact preview.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Register;

use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Confirmation {
	public function __construct( private readonly string $secret ) {
		if ( strlen( $secret ) < 32 ) {
			throw new RuntimeException( 'Confirmação indisponível.' ); }
	}
	public function issue( string $batch, int $actor, string $session, int $now ): string {
		if ( $now < 0 || $now > PHP_INT_MAX - 600 || $actor < 1 || strlen( $session ) < 16 || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $batch ) ) {
			throw new RuntimeException( 'Confirmação indisponível.' ); }
		$expiry = $now + 600;
		return $expiry . '.' . $batch . '.' . hash_hmac( 'sha256', $actor . ':' . hash( 'sha256', $session ) . ':' . $expiry . ':' . $batch, $this->secret );
	}
	public function validate( string $proof, int $actor, string $session, int $now ): string {
		if ( 1 !== preg_match( '/^([1-9][0-9]{0,11})\.([a-f0-9]{64})\.([a-f0-9]{64})$/D', $proof, $m ) || $now < 0 || (int) $m[1] < $now || (int) $m[1] > $now + 600 || $actor < 1 || strlen( $session ) < 16 || ! hash_equals( $m[3], hash_hmac( 'sha256', $actor . ':' . hash( 'sha256', $session ) . ':' . $m[1] . ':' . $m[2], $this->secret ) ) ) {
			throw new RuntimeException( 'Confirmação expirada ou inválida. Valida novamente.' ); }
		return $m[2];
	}
}
