<?php
/** Strict UTC event version content.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Calendar;

use Arn\Intranet\F112\Audience;
use Arn\Intranet\F112\PlainText;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/** No date inference, remote URL lookup or permissive timestamp parsing. */
final readonly class EventContent {

	/** Normalised title.
	 *
	 * @var string
	 */
	public string $title;
	/** Normalised description.
	 *
	 * @var string
	 */
	public string $description;
	/** Normalised meeting location.
	 *
	 * @var string
	 */
	public string $location;
	/** Validated literal HTTPS URL.
	 *
	 * @var string
	 */
	public string $url;
	/** Start UTC seconds.
	 *
	 * @var int
	 */
	private int $start;
	/** Exclusive end UTC seconds.
	 *
	 * @var int
	 */
	private int $end;

	/** Validate modality and one complete kind of interval.
	 *
	 * @param string      $title Title.
	 * @param string      $description Description.
	 * @param Audience    $audience Recipients.
	 * @param string      $mode presencial/online/hybrid.
	 * @param string      $location Location.
	 * @param string      $url HTTPS link.
	 * @param bool        $all_day All-day date mode.
	 * @param string|null $start_at Canonical UTC start.
	 * @param string|null $end_at Canonical UTC end.
	 * @param string|null $start_date All-day start.
	 * @param string|null $end_date_exclusive All-day exclusive end.
	 */
	public function __construct( string $title, string $description, public Audience $audience, public string $mode, string $location, string $url, public bool $all_day, public ?string $start_at, public ?string $end_at, public ?string $start_date, public ?string $end_date_exclusive ) {
		$this->title       = PlainText::value( $title, 160, false );
		$this->description = PlainText::value( $description, 10000, true );
		$this->location    = PlainText::value( $location, 240, false, false );
		$this->url         = PlainText::value( $url, 2048, false, false );
		if ( ! in_array( $mode, array( 'presencial', 'online', 'hybrid' ), true ) || ( in_array( $mode, array( 'presencial', 'hybrid' ), true ) && '' === $this->location ) || ( in_array( $mode, array( 'online', 'hybrid' ), true ) && '' === $this->url ) ) {
			self::invalid();
		}
		if ( '' !== $this->url ) {
			$parts = parse_url( $this->url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure domain model runs without WordPress and validates every consumed component.
			if ( false === filter_var( $this->url, FILTER_VALIDATE_URL ) || ! is_array( $parts ) || 'https' !== strtolower( $parts['scheme'] ?? '' ) || '' === ( $parts['host'] ?? '' ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || 1 === preg_match( '/%(?:0[0-9a-f]|1[0-9a-f]|7f)/i', $this->url ) ) {
				self::invalid();
			}
		}
		if ( $all_day ) {
			if ( null !== $start_at || null !== $end_at || null === $start_date || null === $end_date_exclusive ) {
				self::invalid();
			}
			$this->start = self::date( $start_date, true );
			$this->end   = self::date( $end_date_exclusive, true );
		} else {
			if ( null !== $start_date || null !== $end_date_exclusive || null === $start_at || null === $end_at ) {
				self::invalid();
			}
			$this->start = self::date( $start_at, false );
			$this->end   = self::date( $end_at, false );
		}
		if ( $this->end <= $this->start ) {
			self::invalid();
		}
	}

	/** Start UTC timestamp, possibly before Unix epoch for historical events.
	 *
	 * @return int
	 */
	public function start(): int {
		return $this->start;
	}

	/** Exclusive end UTC timestamp.
	 *
	 * @return int
	 */
	public function end(): int {
		return $this->end;
	}

	/** Private persistence format.
	 *
	 * @return array
	 */
	public function toArray(): array {
		return array(
			'title'              => $this->title,
			'description'        => $this->description,
			'audience'           => $this->audience->toArray(),
			'mode'               => $this->mode,
			'location'           => $this->location,
			'url'                => $this->url,
			'all_day'            => $this->all_day,
			'start_at'           => $this->start_at,
			'end_at'             => $this->end_at,
			'start_date'         => $this->start_date,
			'end_date_exclusive' => $this->end_date_exclusive,
		);
	}

	/** No casts, unknown fields or stored text repair.
	 *
	 * @param array $data Stored version.
	 * @return self
	 */
	public static function fromArray( array $data ): self {
		$keys = array( 'title', 'description', 'audience', 'mode', 'location', 'url', 'all_day', 'start_at', 'end_at', 'start_date', 'end_date_exclusive' );
		if ( count( $data ) !== count( $keys ) || array_diff( array_keys( $data ), $keys ) || ! is_array( $data['audience'] ) || ! is_bool( $data['all_day'] ) ) {
			self::invalid();
		}
		foreach ( array( 'title', 'description', 'mode', 'location', 'url' ) as $key ) {
			if ( ! is_string( $data[ $key ] ) ) {
				self::invalid();
			}
		}
		foreach ( array( 'start_at', 'end_at', 'start_date', 'end_date_exclusive' ) as $key ) {
			if ( null !== $data[ $key ] && ! is_string( $data[ $key ] ) ) {
				self::invalid();
			}
		}
		$result = new self( $data['title'], $data['description'], Audience::fromArray( $data['audience'] ), $data['mode'], $data['location'], $data['url'], $data['all_day'], $data['start_at'], $data['end_at'], $data['start_date'], $data['end_date_exclusive'] );
		foreach ( array( 'title', 'description', 'location', 'url' ) as $key ) {
			if ( $result->$key !== $data[ $key ] ) {
				self::invalid();
			}
		}
		return $result;
	}

	/** Gregorian roundtrip, without coercing impossible dates.
	 *
	 * @param string $value Canonical input.
	 * @param bool   $date_only Date at UTC midnight.
	 * @return int
	 */
	private static function date( string $value, bool $date_only ): int {
		$format  = $date_only ? 'Y-m-d' : 'Y-m-d\TH:i:s\Z';
		$pattern = $date_only ? '/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}\z/' : '/\A(?!0000)[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z\z/';
		if ( 1 !== preg_match( $pattern, $value ) ) {
			self::invalid();
		}
		$date   = DateTimeImmutable::createFromFormat( '!' . $format, $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( false === $date || ( false !== $errors && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) || $date->format( $format ) !== $value ) {
			self::invalid();
		}
		return $date->getTimestamp();
	}

	/** Reject invalid calendar evidence.
	 *
	 * @return never
	 * @throws InvalidArgumentException For invalid version content.
	 */
	private static function invalid(): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain error.
		throw new InvalidArgumentException( 'Conteúdo de evento inválido.' );
	}
}
