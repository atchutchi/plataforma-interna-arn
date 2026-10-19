<?php
/** Native dashboard destinations preserve each module's menu guard.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Administration\{DashboardLinks, DashboardSections};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting -- Boundary tests of closed navigation input.
final class DashboardSectionsTest extends TestCase {
	private function sections( array $menu, array $submenu = array(), array $caps = array( 'read' => true ), string $environment = 'local' ): array {
		return DashboardSections::visible( DashboardLinks::visible( $caps, $environment, false, false ), $menu, $submenu, $caps, $environment );
	}
	public function testTechnicalAdministratorDoesNotInheritBusinessMenus(): void {
		$groups = $this->sections(
			array(),
			array(),
			array(
				'read'           => true,
				'manage_options' => true,
			)
		);
		self::assertSame( array( 'account' ), array_keys( $groups ) );
		self::assertSame( array( 'profile' ), array_column( $groups['account']['links'], 'id' ) );
	}
	public function testOnlyExactRegisteredFunctionalDestinationsAreProjectedOnce(): void {
		$groups = $this->sections(
			array(
				array( '<script>Forged label</script>', 'read', 'arn-newsroom' ),
				array( 'Duplicate', 'read', 'arn-newsroom' ),
				array( 'Documents', 'read', 'arn-document-catalog' ),
				array( 'Notice', 'read', 'arn-communications' ),
				array( 'Calendar', 'read', 'arn-calendar' ),
				array( 'Tasks', 'read', 'arn-tasks' ),
			)
		);
		self::assertSame( array( 'management', 'account' ), array_keys( $groups ) );
		self::assertSame( array( 'news', 'documents', 'communications', 'calendar' ), array_column( $groups['management']['links'], 'id' ) );
		self::assertSame( 'Notícias', $groups['management']['links'][0]['title'] );
		self::assertSame( 'admin.php?page=arn-newsroom', $groups['management']['links'][0]['path'] );
	}
	public function testMalformedAndExternalMenuTargetsNeverBecomeLinks(): void {
		self::assertSame( array( 'account' ), array_keys( $this->sections( array( null, 'arn-newsroom', array(), array( 'X', 'manage_options', 'arn-newsroom' ), array( 'X', 'read', 'https://invalid.test/?page=arn-newsroom' ), array( 'X', 'read', 'arn-newsroom&operation=publish' ) ) ) ) );
	}
	public function testRemovedMenuClosesTheNextProjectionWithoutCaching(): void {
		$menu = array( array( 'News', 'read', 'arn-newsroom' ) );
		self::assertArrayHasKey( 'management', $this->sections( $menu ) );
		self::assertArrayNotHasKey( 'management', $this->sections( array() ) );
		foreach ( array( 'development', 'staging', 'production', '' ) as $environment ) {
			self::assertArrayNotHasKey( 'management', $this->sections( $menu, environment: $environment ) );
		}
	}
	public function testToolsRequireLocalExactParentRegistrationAndNativeCapability(): void {
		$items = array( array( 'Accounts', 'manage_options', 'arn-local-account-state' ), array( 'Grants', 'manage_options', 'arn-local-grants' ), array( 'Units', 'manage_options', 'arn-local-units' ), array( 'Import', 'manage_options', 'arn-local-import-preview' ) );
		$subs  = array( 'tools.php' => $items );
		$caps  = array(
			'read'           => true,
			'manage_options' => true,
		);
		$links = $this->sections( array(), $subs, $caps )['laboratory']['links'];
		self::assertSame( array( 'accounts', 'grants', 'units', 'import' ), array_column( $links, 'id' ) );
		self::assertSame( 'tools.php?page=arn-local-import-preview', $links[3]['path'] );
		self::assertArrayNotHasKey( 'laboratory', $this->sections( array(), array( 'options.php' => $items ), $caps ) );
		self::assertArrayNotHasKey( 'laboratory', $this->sections( array(), $subs ) );
		self::assertArrayNotHasKey( 'laboratory', $this->sections( array(), $subs, $caps, 'production' ) );
	}
	public function testCapabilitiesRequireStrictBooleanAndNativeLinksRemainSeparate(): void {
		foreach ( array( false, 1, 'true', null ) as $value ) {
			self::assertSame(
				array(),
				$this->sections(
					array( array( 'News', 'read', 'arn-newsroom' ) ),
					caps: array(
						'read'           => $value,
						'manage_options' => true,
					)
				)
			);
			self::assertArrayNotHasKey(
				'laboratory',
				$this->sections(
					array(),
					array( 'tools.php' => array( array( 'Accounts', 'manage_options', 'arn-local-account-state' ) ) ),
					array(
						'read'           => true,
						'manage_options' => $value,
					)
				)
			);
		}
		$groups = $this->sections(
			array(),
			caps: array(
				'read'         => true,
				'edit_pages'   => true,
				'edit_posts'   => true,
				'upload_files' => true,
			)
		);
		self::assertSame( array( 'cms', 'account' ), array_keys( $groups ) );
		self::assertSame( array( 'pages', 'posts', 'media' ), array_column( $groups['cms']['links'], 'id' ) );
		self::assertSame( 'Artigos WordPress', $groups['cms']['links'][1]['title'] );
	}
	public function testMalformedToolsParentIsOmittedWithoutInterruptingTheDashboard(): void {
		foreach ( array( 'invalid', new \stdClass(), false ) as $parent ) {
			$groups = $this->sections(
				array(),
				array( 'tools.php' => $parent ),
				array(
					'read'           => true,
					'manage_options' => true,
				)
			);
			self::assertSame( array( 'account' ), array_keys( $groups ) );
		}
	}
}
