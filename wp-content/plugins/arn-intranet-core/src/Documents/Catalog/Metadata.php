<?php
/** Metadata for one immutable documentary version.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Documents\Catalog;

use DateTimeImmutable;
use InvalidArgumentException;

/** Stores plain text and an explicit audience without deriving privileges. */
final readonly class Metadata {
	/** Explicit IDs for one version.
	 *
	 * @var array<int>
	 */
	public array $recipient_ids;

	/** Validate exact categories, calendar dates and recipient IDs.
	 *
	 * @param string      $title            Short title.
	 * @param string      $summary          Plain text summary.
	 * @param string      $category         Closed category.
	 * @param string      $source_reference Source reference.
	 * @param string      $owner_label      Institutional owner label.
	 * @param string      $review_on        Real ISO date.
	 * @param string|null $valid_from       Informative start date.
	 * @param string|null $valid_until      Informative end date.
	 * @param array<int>  $recipient_ids    Explicit recipients.
	 * @throws InvalidArgumentException Invalid metadata.
	 */
	public function __construct(
		public string $title,
		public string $summary,
		public string $category,
		public string $source_reference,
		public string $owner_label,
		public string $review_on,
		public ?string $valid_from,
		public ?string $valid_until,
		array $recipient_ids,
	) {
		if ( ! self::plainText( $title, 1, 200 ) || ! self::plainText( $summary, 0, 4000 ) || ! self::plainText( $source_reference, 1, 200 ) || ! self::plainText( $owner_label, 1, 200 )
			|| ! in_array( $category, array( 'circular', 'nota-interna', 'despacho', 'regulamento', 'comunicacao-sindical' ), true )
			|| ! self::date( $review_on ) || ( null !== $valid_from && ! self::date( $valid_from ) ) || ( null !== $valid_until && ! self::date( $valid_until ) )
			|| ( null !== $valid_from && null !== $valid_until && $valid_from > $valid_until )
			|| count( $recipient_ids ) < 1 || count( $recipient_ids ) > 100 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
			throw new InvalidArgumentException( 'Metadados inválidos.' );
		}
		$seen = array();
		foreach ( $recipient_ids as $recipient_id ) {
			if ( ! is_int( $recipient_id ) || $recipient_id <= 0 || isset( $seen[ $recipient_id ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
				throw new InvalidArgumentException( 'Destinatários inválidos.' );
			}
			$seen[ $recipient_id ] = true;
		}
		$this->recipient_ids = array_values( $recipient_ids );
	}

	/** Validate a plain Unicode field against character limits.
	 *
	 * @param string $value   Input.
	 * @param int    $minimum Minimum characters.
	 * @param int    $maximum Maximum characters.
	 * @return bool
	 */
	private static function plainText( string $value, int $minimum, int $maximum ): bool {
		$length = preg_match_all( '/./us', $value );
		return false !== $length && $length >= $minimum && $length <= $maximum
			&& ( 0 === $minimum || 1 === preg_match( '/[^\s\p{Z}]/u', $value ) )
			&& 0 === preg_match( '/[\p{C}<>]/u', $value );
	}

	/** Validate a real calendar date in canonical ISO form.
	 *
	 * @param string $value Input.
	 * @return bool
	 */
	private static function date( string $value ): bool {
		if ( 1 !== preg_match( '/\A[0-9]{4}-[0-9]{2}-[0-9]{2}\z/D', $value ) ) {
			return false;
		}
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value );
		return false !== $date && $date->format( 'Y-m-d' ) === $value;
	}
}
