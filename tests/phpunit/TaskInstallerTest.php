<?php
/** Explicit task installation proofs without WordPress storage.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Tasks\{InstallationState, LocalInstaller, Pages};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure installation value contracts.
final class TaskInstallerTest extends TestCase {
	public function testStrictLocalCliGate(): void {
		self::assertTrue( LocalInstaller::eligible( 'local', true, true, true, true, true, true ) );
		foreach ( array( false, null, 0, 1, 'true' ) as $flag ) {
			self::assertFalse( LocalInstaller::eligible( 'local', $flag, true, true, true, true, true ) );
		}
		self::assertFalse( LocalInstaller::eligible( 'development', true, true, true, true, true, true ) );
		self::assertFalse( LocalInstaller::eligible( 'local', true, true, true, true, false, true ) );
		self::assertFalse( LocalInstaller::eligible( 'local', true, true, true, true, true, false ) );
	}

	public function testCompleteMarkerRejectsPartialAndDuplicateMaps(): void {
		$prefix = 'arn_tsk_0123456789_';
		$proof  = array(
			'version' => 1,
			'prefix'  => $prefix,
			'users'   => array(
				'a'       => 7,
				'b'       => 8,
				'manager' => 9,
			),
			'pages'   => array(
				'own'  => 11,
				'team' => 12,
			),
		);
		self::assertSame( $proof, InstallationState::decode( json_encode( $proof ), $prefix ) );
		$partial = $proof;
		unset( $partial['pages']['team'] );
		self::assertNull( InstallationState::decode( json_encode( $partial ), $prefix ) );
		$duplicate               = $proof;
		$duplicate['users']['b'] = 7;
		self::assertNull( InstallationState::decode( json_encode( $duplicate ), $prefix ) );
		self::assertNull( InstallationState::decode( json_encode( $proof ), 'arn_tsk_abcdef1234_' ) );
		self::assertNull( InstallationState::decode( '{bad', $prefix ) );
	}

	public function testFixedPagesContainOnlyDynamicMarkers(): void {
		self::assertTrue( Pages::isReservedSlug( 'minhas-tarefas' ) );
		self::assertTrue( Pages::isReservedSlug( 'tarefas-da-equipa' ) );
		self::assertFalse( Pages::isReservedSlug( 'noticias' ) );
		self::assertSame( 'minhas-tarefas', ( new Pages() )->slug( 'own' ) );
		self::assertSame( 'tarefas-da-equipa', ( new Pages() )->slug( 'team' ) );
		self::assertSame( 'arn_tsk_0123456789-minhas-tarefas', ( new Pages( 'arn_tsk_0123456789_' ) )->slug( 'own' ) );
		self::assertSame( '<!-- wp:arn/tasks-view {"view":"team"} /-->', Pages::content( 'team' ) );
	}
}
