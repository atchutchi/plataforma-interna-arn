<?php
/**
 * Conflitos ficam fora do repositório, deduplicados e sem claims.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\LocalIdentityLink;
use Arn\Intranet\Identity\PreRegistration\PrivateWorkspaceConflictReporter;
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/doubles/WorkspaceInventoryFixture.php';

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Ficheiros privados criados pelo próprio ensaio, limpeza exacta.
final class PrivateWorkspaceConflictReporterTest extends TestCase {
	public function testConflictContainsOnlyOpaqueReferencesAndIsDeduplicated(): void {
		$root = sys_get_temp_dir() . '/arn_workspace_' . bin2hex( random_bytes( 8 ) );
		mkdir( $root, 0700 );
		WorkspaceInventoryFixture::protect( $root );
		try {
			$reporter = new PrivateWorkspaceConflictReporter( $root, dirname( __DIR__, 2 ), array( dirname( __DIR__, 2 ) ) );
			$link     = new LocalIdentityLink( 20, 'https://accounts.google.com', 'synthetic-private-sub', true, true );
			$reporter->record( str_repeat( 'a', 32 ), $link, 1894705200 );
			$reporter->record( str_repeat( 'a', 32 ), $link, 1894705201 );
			$bytes = file_get_contents( $root . '/claim-conflicts.json' );
			$rows  = json_decode( $bytes, true, 512, JSON_THROW_ON_ERROR );
			self::assertCount( 1, $rows );
			self::assertSame( 1894705200, array_values( $rows )[0]['first_seen'] );
			self::assertStringNotContainsString( 'synthetic-private-sub', $bytes );
			self::assertStringNotContainsString( 'accounts.google.com', $bytes );
		} finally {
			if ( is_file( $root . '/claim-conflicts.json' ) ) {
				unlink( $root . '/claim-conflicts.json' );
			}rmdir( $root );}
	}
}
