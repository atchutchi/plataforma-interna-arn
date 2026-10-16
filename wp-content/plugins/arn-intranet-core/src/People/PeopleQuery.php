<?php
/** Validated directory filters.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

/** Native WordPress page remains separate from arn_people_page. */
final readonly class PeopleQuery {
	/** Construct only after parse validation.
	 *
	 * @param string $q Name search.
	 * @param string $unit Unit filter.
	 * @param string $job Job filter.
	 * @param int    $page People page number.
	 */
	private function __construct( public string $q, public string $unit, public string $job, public int $page ) {}

	/** Reject malformed present inputs before any profile source is opened.
	 *
	 * @param array $input GET parameters.
	 */
	public static function parse( array $input ): ?self {
		$q    = $input['q'] ?? '';
		$unit = $input['unit'] ?? '';
		$job  = $input['job'] ?? '';
		$page = $input['arn_people_page'] ?? '';
		if ( ( array_key_exists( 'q', $input ) && ! is_string( $input['q'] ) )
			|| ( array_key_exists( 'unit', $input ) && ! is_string( $input['unit'] ) )
			|| ( array_key_exists( 'job', $input ) && ! is_string( $input['job'] ) )
			|| ( array_key_exists( 'arn_people_page', $input ) && ! is_string( $input['arn_people_page'] ) )
			|| ! self::text( $q, 120 ) || ! self::text( $job, 100 )
			|| ! in_array( $unit, array( '', 'LAB-A', 'LAB-B' ), true ) ) {
			return null;
		}
		if ( '' === $page ) {
			return new self( $q, $unit, $job, 1 );
		}
		if ( 1 !== preg_match( '/\A[1-9][0-9]*\z/D', $page ) || (string) (int) $page !== $page ) {
			return null;
		}
		return new self( $q, $unit, $job, (int) $page );
	}

	/** One-line plain Unicode filter, including literal punctuation.
	 *
	 * @param string $value Query text.
	 * @param int    $limit Maximum characters.
	 */
	private static function text( string $value, int $limit ): bool {
		$length = preg_match_all( '/./us', $value );
		return false !== $length && $length <= $limit && 0 === preg_match( '/[\p{C}<>]/u', $value );
	}
}
