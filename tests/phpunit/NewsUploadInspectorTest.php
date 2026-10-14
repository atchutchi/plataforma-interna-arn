<?php
/** Actual byte validation before a trusted PHP upload reaches storage.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.WP.AlternativeFunctions -- Isolated temporary binary fixtures without WordPress bootstrap.

use Arn\Intranet\News\NewsUploadInspector;
use PHPUnit\Framework\TestCase;

/** Browser file names and MIME claims do not decide the field error. */
final class NewsUploadInspectorTest extends TestCase {
	private string $path;

	protected function setUp(): void {
		$this->path = tempnam( sys_get_temp_dir(), 'arn-news-test-' );
	}

	protected function tearDown(): void {
		unlink( $this->path );
	}

	/** A compact, valid PNG lets us exercise real image dimensions. */
	private function png( int $width, int $height ): string {
		$chunk = static function ( string $type, string $data ): string {
			$bytes = $type . $data;
			return pack( 'N', strlen( $data ) ) . $bytes . pack( 'N', crc32( $bytes ) );
		};
		$row   = "\0" . str_repeat( "\x20\x50\x80\xff", $width );
		return "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNC5', $width, $height, 8, 6, 0, 0, 0 ) )
			. $chunk( 'IDAT', gzcompress( str_repeat( $row, $height ) ) ) . $chunk( 'IEND', '' );
	}

	public function testValidFormatsAndImageDimensions(): void {
		file_put_contents( $this->path, $this->png( 4, 4 ) );
		self::assertNull( NewsUploadInspector::error( $this->path, 'featured' ) );
		self::assertNull( NewsUploadInspector::error( $this->path, 'gallery' ) );
		self::assertNull( NewsUploadInspector::error( $this->path, 'attachment' ) );
		self::assertNull( NewsUploadInspector::error( __DIR__ . '/fixtures/news-valid.jpg', 'featured' ) );
		self::assertNull( NewsUploadInspector::error( __DIR__ . '/fixtures/news-valid.jpg', 'gallery' ) );
		file_put_contents( $this->path, "%PDF-1.4\n%%EOF\n" );
		self::assertNull( NewsUploadInspector::error( $this->path, 'attachment' ) );
		self::assertStringContainsString( 'PNG ou JPEG', NewsUploadInspector::error( $this->path, 'featured' ) );
	}

	public function testInvalidBytesDimensionsAndSizeGiveActionableFieldErrors(): void {
		file_put_contents( $this->path, '<?php echo "not an image";' );
		self::assertStringContainsString( 'PNG ou JPEG', NewsUploadInspector::error( $this->path, 'featured' ) );
		self::assertStringContainsString( 'PDF, PNG ou JPEG', NewsUploadInspector::error( $this->path, 'attachment' ) );
		file_put_contents( $this->path, $this->png( 5000, 10 ) );
		self::assertStringContainsString( '4096', NewsUploadInspector::error( $this->path, 'gallery' ) );
		file_put_contents( $this->path, "%PDF-1.4\n" . str_repeat( 'A', 1048576 ) . "\n%%EOF\n" );
		self::assertStringContainsString( '1 MiB', NewsUploadInspector::error( $this->path, 'attachment' ) );
	}
}
