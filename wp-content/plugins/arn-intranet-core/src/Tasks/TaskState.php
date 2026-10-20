<?php
/** Closed task states.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

/** Reopening is deliberately absent from the local workflow. */
enum TaskState: string {
	case Open       = 'open';
	case InProgress = 'in_progress';
	case Completed  = 'completed';
	case Cancelled  = 'cancelled';

	/** Completed and cancelled tasks cannot be assigned or rescheduled. */
	public function terminal(): bool {
		return self::Completed === $this || self::Cancelled === $this;
	}
}
