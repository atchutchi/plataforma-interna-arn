<?php
/** Private PDF preview contracts. Scanner doubles are not antimalware evidence.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Media\{MediaPipeline, PdfPreview};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
final class PdfPreviewTest extends TestCase {
	private string $root;
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC';
	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/arn-pdf-' . bin2hex( random_bytes( 8 ) );
		mkdir( $this->root );
		mkdir( $this->root . '/quarantine' );
		mkdir( $this->root . '/public' );
		file_put_contents( $this->root . '/source', "%PDF-1.4\n%%EOF\n" );
	}
	protected function tearDown(): void {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) {
			$entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
		}
		rmdir( $this->root );
	}
	public function testPdfDerivativeScanAndAuditBeforeRegistration(): void {
		$scans    = array();
		$pipeline = new MediaPipeline(
			$this->root . '/quarantine',
			array( $this->root . '/public' ),
			static function ( string $path ) use ( &$scans ): string {
				$scans[] = basename( $path );
				return 'clean'; },
			static function (): void {
				self::fail( 'Image renderer selected for PDF.' ); },
			42,
			static function ( string $source, string $job ) use ( &$scans ): void {
				self::assertSame( array( 'source' ), $scans );
				foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
					file_put_contents( $job . '/' . $variant . '.png', base64_decode( self::PNG, true ) );
				}
			}
		);
		$result   = $pipeline->prepare( $this->root . '/source' );
		self::assertSame( array( 'original', 'thumbnail', 'preview' ), array_keys( $result['hashes'] ) );
		self::assertSame( array( 'source', 'thumbnail.png', 'preview.png' ), $scans );
		self::assertSame( hash_file( 'sha256', $this->root . '/source' ), $result['hashes']['original'] );
		self::assertStringContainsString( 'processed', file_get_contents( $result['job'] . '/events.jsonl' ) );
		$derived  = new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'd', 64 ), 1, 'image/png' );
		$original = new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'c', 64 ), 1, 'application/pdf', array( 'preview' => $derived ) );
		self::assertSame( $derived, $original->derivatives['preview'] );
	}
	public function testPdfDecoderFailureConservesQuarantineAndNeverAdmits(): void {
		$pipeline = new MediaPipeline(
			$this->root . '/quarantine',
			array( $this->root . '/public' ),
			static fn(): string => 'clean',
			static function (): void {},
			42,
			static function (): never {
				throw new RuntimeException( 'decoder unavailable' );
			}
		);
		try {
			$pipeline->prepare( $this->root . '/source' );
			self::fail( 'Decoder failure admitted.' );
		} catch ( RuntimeException ) {
			$files = glob( $this->root . '/quarantine/*/events.jsonl' );
			self::assertCount( 1, $files );
			self::assertStringContainsString( 'not_admitted', file_get_contents( $files[0] ) );
			self::assertFileExists( dirname( $files[0] ) . '/source' );
		}
	}
	public function testAvailabilityUsesAuthorisedReaderClosesStreamAndKeepsOriginalId(): void {
		foreach ( array( 'arn_document_file=', 'arn_news_media=', 'arn_task=12&arn_task_file=' ) as $query ) {
			$url    = 'http://localhost/?' . $query . str_repeat( 'a', 32 );
			$handle = fopen( $this->root . '/source', 'rb' );
			$reader = static fn(): array => array(
				'handle' => $handle,
				'blob'   => new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( 'd', 64 ), 1, 'image/png' ),
			);
			self::assertSame( $url . '&arn_media_variant=preview', PdfPreview::url( $url, 'application/pdf', $reader ) );
			self::assertFalse( is_resource( $handle ) );
			self::assertNull( PdfPreview::url( $url, 'application/pdf', static fn(): ?array => null ) );
		}
	}
	public function testInvalidRoutesAndImagesNeverRead(): void {
		$reader = static function (): never {
			self::fail( 'Invalid route queried a reader.' );
		};
		foreach ( array( '?arn_document_file[]=a', '?arn_task=0&arn_task_file=' . str_repeat( 'a', 32 ), '?arn_document_file=' . str_repeat( 'a', 32 ) . '&extra=1' ) as $query ) {
			self::assertNull( PdfPreview::url( 'http://localhost/' . $query, 'application/pdf', $reader ) );
		}
		self::assertNull( PdfPreview::url( 'http://localhost/?arn_document_file=' . str_repeat( 'a', 32 ), 'image/png', $reader ) );
	}
}
