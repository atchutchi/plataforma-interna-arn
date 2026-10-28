<?php
/** Versioned fictional working-day calendar, with no external holiday source.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.YodaConditions -- Strict pure calendar contract.
final readonly class Calendar {
	public function __construct( public int $year, public int $revision, public array $holidays ) {
		if ( $year < 2000 || $year > 2100 || $revision < 1 || $revision > 1000 || count( $holidays ) > 100 || ! array_is_list( $holidays ) ) {
			self::deny();
		}
		$previous = '';
		foreach ( $holidays as$date ) {
			if ( ! is_string( $date ) || self::date( $date )->format( 'Y' ) !== (string) $year || $date <= $previous ) {
				self::deny();
			}$previous = $date;}
	}
	public static function date( string $value ): DateTimeImmutable {
		$date = DateTimeImmutable::createFromFormat( '!Y-m-d', $value, new DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			self::deny();
		}
		return $date;
	}
	public function days( string $starts, string $ends ): array {
		$first = self::date( $starts );
		$last  = self::date( $ends );
		if ( $first > $last || $first->format( 'Y' ) !== (string) $this->year || $last->format( 'Y' ) !== (string) $this->year ) {
			throw new InvalidArgumentException( 'O período deve ficar dentro do mesmo ano civil.' );
		}
		$days = array();for ( $day = $first;$day <= $last;$day = $day->modify( '+1 day' ) ) {
			if ( (int) $day->format( 'N' ) < 6 && ! in_array( $day->format( 'Y-m-d' ), $this->holidays, true ) ) {
				$days[] = $day->format( 'Y-m-d' );
			}
		}
		if ( ! $days || count( $days ) > 22 ) {
			throw new InvalidArgumentException( 'Escolhe um período entre um e 22 dias úteis.' );
		}
		return $days;
	}
	public function data(): array {
		return array(
			'year'     => $this->year,
			'revision' => $this->revision,
			'holidays' => $this->holidays,
		);}
	public static function restore( array $data ): self {
		if ( array_keys( $data ) !== array( 'year', 'revision', 'holidays' ) || ! is_int( $data['year'] ) || ! is_int( $data['revision'] ) || ! is_array( $data['holidays'] ) ) {
			self::deny();
		}
		return new self( $data['year'], $data['revision'], $data['holidays'] );
	}
	private static function deny(): never {
		throw new InvalidArgumentException( 'Calendário fictício inválido.' );}
}
