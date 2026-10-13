<?php
/**
 * Tipos distintos de âmbito de autorização.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

/** Os tipos não estabelecem hierarquias nem pertenças implícitas. */
enum ScopeType {
	case Own;
	case Units;
	case People;
	case Queues;
	case Audiences;
	case Institutional;
}
