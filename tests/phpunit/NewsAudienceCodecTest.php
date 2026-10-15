<?php
/** Storage audience boundary tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\News\Audience;
use Arn\Intranet\News\AudienceCodec;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment

/** Canonical storage audience boundary. */
final class NewsAudienceCodecTest extends TestCase {
	public function test_roundtrip_and_null_preserve_explicit_audience(): void {
		self::assertNull( AudienceCodec::decode( null ) );
		foreach ( array( Audience::all(), Audience::units( array( 'LAB-A' ) ), Audience::people( array( 7, 9 ) ) ) as $audience ) {
			self::assertEquals( $audience, AudienceCodec::decode( AudienceCodec::encode( $audience ) ) );
		}
	}
	public function test_malformed_or_ambiguous_storage_is_rejected(): void {
		foreach ( array( 'null', '{}', '[]', '{"kind":"all","targets":[1]}', '{"kind":"people","targets":["7"]}', '{"kind":"people","targets":[7,7]}', '{"kind":"units","targets":["LAB-A"],"extra":1}', '{"kind":"all","kind":"people","targets":[7]}' ) as $json ) {
			$failed = false;
			try {
				AudienceCodec::decode( $json );
			} catch ( RuntimeException ) {
				$failed = true;
			}
			self::assertTrue( $failed );
		}
	}
}
