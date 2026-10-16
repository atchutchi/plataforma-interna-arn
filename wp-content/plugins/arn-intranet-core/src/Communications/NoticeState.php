<?php
/** Notice workflow states.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

/** Persisted exact state identifiers. */
enum NoticeState: string {
	case Draft     = 'Draft';
	case Pending   = 'Pending';
	case Rejected  = 'Rejected';
	case Published = 'Published';
	case Archived  = 'Archived';
}
