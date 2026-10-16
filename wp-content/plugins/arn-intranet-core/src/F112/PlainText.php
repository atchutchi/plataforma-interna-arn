<?php
/** Unicode input for local communications and calendar.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use InvalidArgumentException;

/** Literal text, never an HTML sanitiser. Escape at the output boundary. */
final class PlainText {

	/** Validate before trimming so forbidden trailing controls are not erased.
	 *
	 * @param string $value Raw text.
	 * @param int    $maximum Character limit.
	 * @param bool   $multiline Permit LF in a body.
	 * @param bool   $required Require nonempty content.
	 * @throws InvalidArgumentException For invalid text.
	 * @return string
	 */
	public static function value( string $value, int $maximum, bool $multiline, bool $required = true ): string {
		if ( $multiline ) {
			$value = str_replace( "\r\n", "\n", $value );
		}
		$controls = $multiline ? '/[\x00-\x09\x0b-\x1f\x7f\p{Cf}]/u' : '/[\x00-\x1f\x7f\p{Cf}\x{2028}\x{2029}]/u';
		if ( $maximum < 1 || ! mb_check_encoding( $value, 'UTF-8' ) || 1 === preg_match( $controls, $value ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
			throw new InvalidArgumentException( 'Texto inválido.' );
		}
		$value = preg_replace( '/\A[\s\p{Z}]+|[\s\p{Z}]+\z/u', '', $value );
		if ( ! is_string( $value ) || ( $required && '' === $value ) || mb_strlen( $value, 'UTF-8' ) > $maximum ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
			throw new InvalidArgumentException( 'Texto inválido.' );
		}
		return $value;
	}
}
