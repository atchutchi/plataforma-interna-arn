<?php
/** Closed support workflow.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

// phpcs:disable Squiz.Commenting
enum TicketState: string {
	case New        = 'new';
	case InProgress = 'in_progress';
	case Resolved   = 'resolved';
	public function label(): string {
		return match ( $this ) {
			self::New => 'Novo', self::InProgress => 'Em tratamento', self::Resolved => 'Resolvido' };
	}
}
