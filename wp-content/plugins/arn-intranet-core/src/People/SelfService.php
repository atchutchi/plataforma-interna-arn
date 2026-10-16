<?php
/** Editable contact fields.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

use InvalidArgumentException;

/** Only these three fields may be supplied for a profile revision. */
final readonly class SelfService {
	/** Validate phone, office and biography without interpreting markup.
	 *
	 * @param string $phone Phone contact.
	 * @param string $office Office location.
	 * @param string $bio Short biography.
	 * @throws InvalidArgumentException If a field is invalid.
	 */
	public function __construct( public string $phone, public string $office, public string $bio ) {
		$phone_length  = preg_match_all( '/./us', $phone );
		$office_length = preg_match_all( '/./us', $office );
		$bio_length    = preg_match_all( '/./us', $bio );
		if ( false === $phone_length || $phone_length > 32
			|| ( '' !== $phone && ( 1 !== preg_match( '/\A[0-9 +()-]+\z/D', $phone ) || 1 !== preg_match( '/[0-9]/', $phone ) ) )
			|| false === $office_length || $office_length > 100 || 1 === preg_match( '/[\p{C}\p{Zl}\p{Zp}<>]/u', $office )
			|| false === $bio_length || $bio_length > 500 || 1 === preg_match( '/[\p{C}<>]/u', str_replace( "\n", '', $bio ) ) ) {
			throw new InvalidArgumentException( 'Campos de autosserviço inválidos.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Pure domain exception.
		}
	}
}
