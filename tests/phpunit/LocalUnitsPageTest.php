<?php
/** A guarda recusa antes de abrir persistência.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Organization\LocalUnitsPage;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use PHPUnit\Framework\TestCase;
/** Ensaios de unidades locais. */
final class LocalUnitsPageTest extends TestCase {
	/**
	 * Verifica que a página desactivada não abre persistência.
	 */
	public function testDisabledPageNeverOpensStorage(): void {
		$page = new LocalUnitsPage(
			static function () {
				self::fail( 'Persistência aberta.' );
			}
		);
		ob_start();
		$page->render();
		self::assertSame( '', ob_get_clean() );
	}
	/**
	 * Verifica a representação canónica da revisão zero.
	 */
	public function testRevisionZeroIsCanonical(): void {
		self::assertSame( 0, WpdbLocalUnitRepository::revision( '0' ) );
		$this->expectException( RuntimeException::class );
		WpdbLocalUnitRepository::revision( '00' );
	}
}
