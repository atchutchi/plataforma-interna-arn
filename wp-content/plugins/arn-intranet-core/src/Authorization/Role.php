<?php
/**
 * Pacotes funcionais explícitos, sem herança implícita.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

/** Catálogo fechado de pacotes para os ensaios locais. */
enum Role {
	case Employee;
	case UnitManager;
	case HumanResources;
	case Editorial;
	case AccessAdministrator;
	case TechnicalAdministrator;
	case Leadership;
	case Support;
}
