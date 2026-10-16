<?php
/** Relatório de pré-validação privado e imutável.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\PreRegistration\PrivateWorkspacePreflightReport;
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;
use PHPUnit\Framework\TestCase;
require_once __DIR__ . '/doubles/WorkspaceInventoryFixture.php';
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Temporários próprios, protegidos e removidos por caminho exacto.
final class PrivateWorkspacePreflightReportTest extends TestCase {
	public function testKeepsEveryRunPrivateAndDoesNotOverwriteEvidence(): void {
		$root = sys_get_temp_dir() . '/arn_workspace_' . bin2hex( random_bytes( 8 ) );
		mkdir( $root, 0700 );
		WorkspaceInventoryFixture::protect( $root );
		$files = array();
		try {
			$report  = array(
				'totals'  => array(
					'create'   => 0,
					'resume'   => 0,
					'conflict' => 1,
				),
				'entries' => array(
					array(
						'primary_email' => 'fixture@example.invalid',
						'code'          => 'native_collision',
					),
				),
			);
			$writer  = new PrivateWorkspacePreflightReport( $root, dirname( __DIR__, 2 ), array( dirname( __DIR__, 2 ) ) );
			$files[] = $writer->save( $report );
			$first   = file_get_contents( $files[0] );
			$files[] = $writer->save( $report );
			self::assertNotSame( $files[0], $files[1] );
			self::assertSame( $first, file_get_contents( $files[0] ) );
			self::assertSame( $report, json_decode( $first, true, 512, JSON_THROW_ON_ERROR ) );
			$this->expectException( \RuntimeException::class );
			( new PrivateWorkspacePreflightReport( $root, $root, array() ) )->save( $report );
		} finally {
			foreach ( $files as $file ) {
				unlink( $file );
			}rmdir( $root );
		}
	}
}
