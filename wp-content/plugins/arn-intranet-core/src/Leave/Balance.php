<?php
/** Balance is a projection of approvals, never a mutable counter.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use Arn\Intranet\Requests\RequestState;
use DomainException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.YodaConditions -- Pure approved-day ledger rules.
final class Balance {
	public static function summarize( array $requests, int $owner, int $year, int $now ): array {
		$days = array();
		$seen = array();
		if ( $owner < 1 || $year < 2000 || $year > 2100 || $now < 0 ) {
			throw new DomainException( 'Saldo inválido.' );
		}
		foreach ( $requests as$r ) {
			if ( ! $r instanceof LeaveRequest || isset( $seen[ $r->id ] ) ) {
				throw new DomainException( 'Conjunto de férias inválido.' );
			}$seen[ $r->id ] = true;
			if ( $r->owner !== $owner || $r->calendar->year !== $year || RequestState::Approved !== $r->state ) {
				continue;
			}
			foreach ( $r->days as$day ) {
				if ( isset( $days[ $day ] ) ) {
					throw new DomainException( 'Aprovações sobrepostas.' );
				}$days[ $day ] = true;}
		}
		if ( count( $days ) > 22 ) {
			throw new DomainException( 'Saldo anual excedido.' );
		}
		$past = count( array_filter( array_keys( $days ), static fn( string $date ): bool=>$date < gmdate( 'Y-m-d', $now ) ) );
		return array(
			'entitlement' => 22,
			'elapsed'     => $past,
			'future'      => count( $days ) - $past,
			'available'   => 22 - count( $days ),
		);
	}
	public static function assertSubmission( LeaveRequest $request, array $requests, int $now ): void {
		LeaveRequest::future( $request->starts, $now );
		self::overlap( $request, $requests, true );
	}
	public static function assertApproval( LeaveRequest $request, array $requests, Calendar $current, int $now ): void {
		LeaveRequest::future( $request->starts, $now );
		if ( $request->calendar->data() !== $current->data() ) {
			throw new DomainException( 'O calendário mudou. Devolve o pedido para nova submissão.' );
		}
		self::overlap( $request, $requests, true );
		$others = array_values( array_filter( $requests, static fn( LeaveRequest $r ): bool=>$r->id !== $request->id ) );
		if ( count( $request->days ) > self::summarize( $others, $request->owner, $request->calendar->year, $now )['available'] ) {
			throw new DomainException( 'O período excede o saldo disponível.' );
		}
	}
	private static function overlap( LeaveRequest $request, array $requests, bool $pending ): void {
		foreach ( $requests as$other ) {
			if ( ! $other instanceof LeaveRequest ) {
				throw new DomainException( 'Conjunto de férias inválido.' );
			}
			if ( $other->id === $request->id || $other->owner !== $request->owner ) {
				continue;
			}
			if ( ( RequestState::Approved === $other->state || ( $pending && $other->state->pending() ) ) && $request->starts <= $other->ends && $request->ends >= $other->starts ) {
				throw new DomainException( 'Já existe um pedido pendente ou aprovado nesse período.' );
			}
		}
	}
}
