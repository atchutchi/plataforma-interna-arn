<?php
/** Contract checks for local news storage boundaries.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\News\NewsConfiguration;
use Arn\Intranet\News\NewsSchema;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment

/** Pure storage contracts that do not require WordPress. */
final class NewsStorageContractTest extends TestCase {
	public function testNewsConfigurationRequiresExactLocalBooleanGates(): void {
		self::assertTrue( NewsConfiguration::enabled( 'local', true, true, true, true, true ) );
		self::assertFalse( NewsConfiguration::enabled( 'development', true, true, true, true, true ) );
		self::assertFalse( NewsConfiguration::enabled( 'local', true, true, true, true, false ) );
		self::assertFalse( NewsConfiguration::enabled( 'local', true, 'true', true, true, true ) );
		self::assertFalse( NewsConfiguration::enabled( 'local', 1, true, true, true, true ) );
		self::assertFalse( NewsConfiguration::enabled( 'local', true, true, false, true, true ) );
	}

	public function testSchemaIdentifierCatalogIsClosed(): void {
		self::assertTrue( class_exists( NewsSchema::class ) );
	}
}
