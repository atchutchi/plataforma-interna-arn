<?php
/**
 * Estados de associação, sem concessões.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity\PreRegistration;

/**
 * Não substitui o estado funcional de F1-03.
 */
enum PreRegistrationState: string {
	case AwaitingGoogle = 'AwaitingGoogle';
	case Linked         = 'Linked';
	case Disabled       = 'Disabled';
}
