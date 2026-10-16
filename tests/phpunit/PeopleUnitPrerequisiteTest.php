<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Closed prerequisite scenario for People. */
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Organization\Storage\LocalUnitScenarios;
use Arn\Intranet\Organization\Storage\LocalUnitSnapshot;
use Arn\Intranet\Organization\Unit;
use Arn\Intranet\Organization\UnitType;
use Arn\Intranet\Organization\ParentStatus;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PeopleUnitPrerequisiteTest extends TestCase {
	public function test_reactivate_b_changes_only_active_state_and_rejects_repeat(): void {
		$units       = LocalUnitScenarios::apply( 'initialize', array() );
		$inactive    = LocalUnitScenarios::apply( 'deactivate-b', $units );
		$reactivated = LocalUnitScenarios::apply( 'reactivate-b', $inactive );
		self::assertSame( LocalUnitSnapshot::encode( $units ), LocalUnitSnapshot::encode( $reactivated ) );
		self::assertSame( 'Reactivar B conservando o histórico', LocalUnitScenarios::labels()['reactivate-b'] );
		$this->expectException( RuntimeException::class );
		LocalUnitScenarios::apply( 'reactivate-b', $reactivated );
	}

	public function test_reactivate_b_requires_active_parent(): void {
		$units    = LocalUnitScenarios::apply( 'deactivate-b', LocalUnitScenarios::apply( 'initialize', array() ) );
		$units[0] = new Unit( 'LAB-ROOT', 'LAB-ROOT', 'Unidade fictícia raiz', UnitType::Outro, null, ParentStatus::Raiz, false );
		$this->expectException( RuntimeException::class );
		LocalUnitScenarios::apply( 'reactivate-b', $units );
	}
}
