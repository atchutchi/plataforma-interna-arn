<?php
/** Calendar state values.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Calendar;

/** Cancelled permits only archive. */
enum EventState: string {
	case Draft     = 'Draft';
	case Published = 'Published';
	case Cancelled = 'Cancelled';
	case Archived  = 'Archived';
}
