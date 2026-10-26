<?php
/** Closed local process states.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

// phpcs:disable Squiz.Commenting -- Self-describing closed state labels.
enum RequestState: string {
	case Draft     = 'draft';
	case Submitted = 'submitted';
	case Analysis  = 'analysis';
	case Returned  = 'returned';
	case Approved  = 'approved';
	case Rejected  = 'rejected';
	case Withdrawn = 'withdrawn';
	case Cancelled = 'cancelled';

	public function label(): string {
		return match ( $this ) {
			self::Draft => 'Rascunho', self::Submitted => 'Submetido', self::Analysis => 'Em análise', self::Returned => 'Devolvido', self::Approved => 'Aprovado', self::Rejected => 'Rejeitado', self::Withdrawn => 'Retirado', self::Cancelled => 'Cancelado',
		};
	}

	public function editable(): bool {
		return in_array( $this, array( self::Draft, self::Returned ), true );
	}

	public function pending(): bool {
		return in_array( $this, array( self::Submitted, self::Analysis ), true );
	}
}
