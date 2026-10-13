<?php
/**
 * Estados explícitos da conta no snapshot de autorização.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

/** Uma conta só pode autorizar operações quando está activa. */
enum AccountState {
	case Pending;
	case Active;
	case Suspended;
}
