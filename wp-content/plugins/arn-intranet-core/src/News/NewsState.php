<?php
/**
 * Editorial states.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

enum NewsState {
	case Draft;
	case Pending;
	case Rejected;
	case Published;
	case Archived;
}
