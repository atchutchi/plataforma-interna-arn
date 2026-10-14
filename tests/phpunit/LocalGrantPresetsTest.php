<?php
/** Catálogo fechado sem dependências WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization;

use PHPUnit\Framework\TestCase;

/** Apenas dois cenários fictícios podem ser criados. */
final class LocalGrantPresetsTest extends TestCase {
	/** Dados exactos e novos registos sempre vigentes. */
	public function testClosedPresets(): void {
		self::assertSame( array( 'employee-own', 'unit-manager-a' ), array_keys( LocalGrantPresets::labels() ) );
		$own  = LocalGrantPresets::grant( 'employee-own', 42, 123 );
		$unit = LocalGrantPresets::grant( 'unit-manager-a', 42, 123 );
		self::assertSame( LocalGrantPresets::labels()['employee-own'], LocalGrantPresets::describe( $own ) );
		self::assertSame( LocalGrantPresets::labels()['unit-manager-a'], LocalGrantPresets::describe( $unit ) );
		self::assertSame( Role::Employee, $own->role );
		self::assertEquals( Scope::own(), $own->scope );
		self::assertSame( Role::UnitManager, $unit->role );
		self::assertEquals( Scope::units( array( 'unidade-ficticia-a' ) ), $unit->scope );
		foreach ( array( $own, $unit ) as $grant ) {
			self::assertSame( 42, $grant->user_id );
			self::assertSame( 123, $grant->starts_at );
			self::assertNull( $grant->expires_at );
			self::assertFalse( $grant->revoked );
		}
	}
	/** Pacotes administrativos nunca pertencem ao catálogo. */
	public function testUnknownPresetFailsClosed(): void {
		$this->expectException( \InvalidArgumentException::class );
		LocalGrantPresets::grant( 'system-administrator', 42, 123 );
	}
	/** Um âmbito diferente não pode receber rótulo de âmbito próprio. */
	public function testDifferentScopeIsNotMislabelled(): void {
		$grant = new Grant( 42, Role::Employee, Scope::units( array( 'outra-unidade' ) ), 0, null );
		self::assertSame( 'Outro cenário fictício, consultar auditoria', LocalGrantPresets::describe( $grant ) );
	}
}
