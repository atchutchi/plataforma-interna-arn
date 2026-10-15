<?php
/** Fixed professional identity fields.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

use InvalidArgumentException;

/** Professional facts cannot be changed through self service. */
final readonly class ProfessionalDetails {
	/** Validate one complete professional identity.
	 *
	 * @param string $code Fixed code.
	 * @param string $name Display name.
	 * @param string $email Email address.
	 * @param string $job Job title.
	 * @param string $unit_id Unit code.
	 * @throws InvalidArgumentException If a field is invalid.
	 */
	public function __construct(
		public string $code,
		public string $name,
		public string $email,
		public string $job,
		public string $unit_id,
	) {
		if ( 1 !== preg_match( '/\A[A-Za-z0-9_-]{1,64}\z/D', $code )
			|| 1 !== preg_match( '/\A[A-Za-z0-9_-]{1,64}\z/D', $unit_id )
			|| ! self::singleLine( $name, 120 )
			|| ! self::singleLine( $job, 100 )
			|| '' === $email || strlen( $email ) > 254 || trim( $email ) !== $email
			|| false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new InvalidArgumentException( 'Dados profissionais inválidos.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure domain exception.
		}
	}

	/** Count Unicode characters and reject controls, markup and edge spaces.
	 *
	 * @param string $value Text to validate.
	 * @param int    $limit Maximum characters.
	 */
	private static function singleLine( string $value, int $limit ): bool {
		$length = preg_match_all( '/./us', $value );
		return false !== $length && $length >= 1 && $length <= $limit
			&& 0 === preg_match( '/[\p{C}<>]/u', $value )
			&& 0 === preg_match( '/\A(?:\s|\p{Z})|(?:\s|\p{Z})\z/u', $value );
	}
}
