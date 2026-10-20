<?php
/** Closed SQL identifiers and negative task schema gates.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Tasks\TaskSchema;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/doubles/IdentityWpdbDouble.php';

// phpcs:disable Squiz.Commenting -- Negative-only contract without a SQL server.
final class TaskSchemaContractTest extends TestCase {
	public function testOnlyTheFiveSpecifiedTaskTablesCanBeResolved(): void {
		$schema = new TaskSchema( new IdentityWpdb( '', '', '', '' ) );
		foreach ( array(
			'tasks'         => 'wp_arn_tasks',
			'comments'      => 'wp_arn_tasks_comments',
			'audit'         => 'wp_arn_tasks_audit',
			'notifications' => 'wp_arn_tasks_notifications',
			'schema'        => 'wp_arn_tasks_schema',
		) as $part => $expected ) {
			self::assertSame( $expected, $schema->table( $part ) );
		}
		$this->expectException( RuntimeException::class );
		$schema->table( 'receipts' );
	}

	public function testDisabledSchemaCannotReadMetadataOrInstall(): void {
		$db     = new IdentityWpdb( '', '', '', '' );
		$schema = new TaskSchema( $db );
		foreach ( array( $schema->assertReady( ... ), $schema->install( ... ) ) as $operation ) {
			try {
				$operation();
				self::fail( 'Operação de schema aberto sem flag.' );
			} catch ( RuntimeException ) {
				self::assertSame( array(), $db->queries );
			}
		}
	}
}
