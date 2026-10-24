<?php
/** Private admission tests. Injected scanner verdicts are not antimalware evidence.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, ImportBatch, PrivateBlobStore};
use Arn\Intranet\Media\{ClamAvScanner, LocalProcess, MediaPipeline};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions, WordPress.PHP.NoSilencedErrors
final class MediaProcessingTest extends TestCase {
	private string $base;
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC';

	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-media-' . bin2hex( random_bytes( 8 ) );
		foreach ( array( '', '/public', '/private', '/quarantine' ) as $folder ) {
			mkdir( $this->base . $folder, 0700 );
		}
	}

	protected function tearDown(): void {
		// Only fixtures below this test's freshly generated root.
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $entry ) {
			$entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() );
		}
		rmdir( $this->base );
	}

	private function source( string $bytes = "%PDF-1.4\n%%EOF\n" ): string {
		$path = $this->base . '/public/input';
		file_put_contents( $path, $bytes );
		return $path;
	}

	private function pipeline( ?Closure $scanner = null, ?Closure $images = null ): MediaPipeline {
		return new MediaPipeline(
			$this->base . '/quarantine',
			array( $this->base . '/public', $this->base . '/private' ),
			$scanner ?? static fn(): string => 'clean',
			$images ?? static function ( string $source, string $job ): void {
				foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
					file_put_contents( $job . '/' . $variant . '.png', base64_decode( self::PNG, true ) );
				}
			},
			42
		);
	}

	public function testScannerRequiresCompleteStrictResult(): void {
		$clean = "Scanned files: 1\nInfected files: 0\n";
		foreach ( array(
			array( 0, $clean, '', 'clean' ),
			array( 1, 'FOUND', '', 'rejected' ),
			array( 2, $clean, '', 'inconclusive' ),
			array( 0, '', '', 'inconclusive' ),
			array( 0, "Scanned files: 0\nInfected files: 0\n", '', 'inconclusive' ),
			array( 0, "Scanned files: 2\nInfected files: 0\n", '', 'inconclusive' ),
			array( 0, $clean, 'stale database', 'inconclusive' ),
			array( 0, $clean . 'WARNING: limit', '', 'inconclusive' ),
			array( 0, $clean . 'ERROR', '', 'inconclusive' ),
			array( 0, $clean . 'Total errors: 1', '', 'inconclusive' ),
		) as [$code, $out, $err, $expected] ) {
			$scanner = new ClamAvScanner(
				'/scanner',
				'/signatures',
				static fn(): array => array(
					'code' => $code,
					'out'  => $out,
					'err'  => $err,
				)
			);
			self::assertSame( $expected, $scanner->scan( '/file', '/job' ) );
		}
		$scanner = new ClamAvScanner( '/absent', '/absent' );
		self::assertSame( 'inconclusive', $scanner->scan( '/absent', $this->base ) );
	}

	public function testScannerArgumentsCannotDisableSafetyOrRemoveSource(): void {
		$scanner = new ClamAvScanner(
			'/scanner',
			'/signatures',
			static function ( array $command ): array {
				self::assertContains( '--official-db-only=yes', $command );
				self::assertContains( '--fail-if-cvd-older-than=3', $command );
				self::assertContains( '--alert-exceeds-max=yes', $command );
				self::assertContains( '--alert-encrypted=yes', $command );
				self::assertNotContains( '--remove', $command );
				self::assertSame( array( '--', '-name;evil' ), array_slice( $command, -2 ) );
				return array(
					'code' => 0,
					'out'  => "Scanned files: 1\nInfected files: 0\n",
					'err'  => '',
				);
			}
		);
		self::assertSame( 'clean', $scanner->scan( '-name;evil', '/job' ) );
	}

	public function testPdfPreservesSourceAndAuditsBeforePreparation(): void {
		$source   = $this->source();
		$hash     = hash_file( 'sha256', $source );
		$prepared = $this->pipeline()->prepare( $source );
		self::assertSame( array( 'original' ), array_keys( $prepared['paths'] ) );
		self::assertSame( $hash, $prepared['hashes']['original'] );
		self::assertSame( $hash, hash_file( 'sha256', $source ) );
		$events = array_map( static fn( string $line ): array => json_decode( $line, true, 512, JSON_THROW_ON_ERROR ), file( $prepared['job'] . '/events.jsonl', FILE_IGNORE_NEW_LINES ) );
		self::assertSame( array( 'received', 'quarantined', 'clean', 'processed' ), array_column( $events, 'event' ) );
		self::assertSame( array( 42 ), array_unique( array_column( $events, 'actor_id' ) ) );
		self::assertStringNotContainsString( $source, file_get_contents( $prepared['job'] . '/events.jsonl' ) );
	}

	public function testRejectedOrUnknownVerdictNeverInvokesDecoder(): void {
		$source = $this->source( base64_decode( self::PNG, true ) );
		foreach ( array( 'rejected', 'inconclusive', 'unknown' ) as $verdict ) {
			try {
				$this->pipeline(
					static fn(): string => $verdict,
					static function (): never {
						self::fail( 'Decoder ran before clean result.' );
					}
				)->prepare( $source );
				self::fail( 'Unsafe result admitted.' );
			} catch ( RuntimeException ) {
				self::assertFileExists( $source );
			}
		}
		self::assertCount( 3, glob( $this->base . '/quarantine/*/source' ) );
		self::assertSame( array(), glob( $this->base . '/private/*' ) );
	}

	public function testCleanScanCannotApproveChangedBytes(): void {
		$pipeline = $this->pipeline(
			static function ( string $path ): string {
				file_put_contents( $path, "%PDF-1.5\n%%EOF\n" );
				return 'clean';
			}
		);
		$this->expectException( RuntimeException::class );
		$pipeline->prepare( $this->source() );
	}

	public function testEarlierDerivativeCannotChangeDuringLaterScan(): void {
		$count    = 0;
		$pipeline = $this->pipeline(
			static function ( string $path, string $job ) use ( &$count ): string {
				if ( 3 === ++$count ) {
					file_put_contents( $job . '/thumbnail.png', 'changed' );
				}
				return 'clean';
			}
		);
		$this->expectException( RuntimeException::class );
		$pipeline->prepare( $this->source( base64_decode( self::PNG, true ) ) );
	}

	public function testBatchCopiesOriginalAndBothVerifiedDerivatives(): void {
		$source = $this->source( base64_decode( self::PNG, true ) );
		$store  = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'image/png' ), 1048576 );
		$result = ( new ImportBatch( $store, $this->pipeline() ) )->run( static fn( ImportBatch $batch ): BlobDescriptor => $batch->importFile( $source ), static fn(): null => null );
		self::assertSame( array( 'thumbnail', 'preview' ), array_keys( $result->derivatives ) );
		self::assertCount( 3, glob( $this->base . '/private/*' ) );
		foreach ( array( $result, ...array_values( $result->derivatives ) ) as $blob ) {
			$handle = $store->open( $blob );
			self::assertSame( $blob->sha256, hash( 'sha256', stream_get_contents( $handle ) ) );
			fclose( $handle );
		}
		self::assertFileExists( $source );
	}

	public function testCopiesOfOldVersionsAreScannedAgain(): void {
		$store  = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'application/pdf' ), 1048576 );
		$source = $store->importFile( $this->source() );
		try {
			( new ImportBatch( $store, $this->pipeline( static fn(): string => 'rejected' ) ) )->run( static fn( ImportBatch $batch ): BlobDescriptor => $batch->duplicate( $source ), static fn(): null => null );
			self::fail( 'Old source bypassed scanner.' );
		} catch ( RuntimeException ) {
			self::assertCount( 1, glob( $this->base . '/private/*' ) );
			self::assertFileExists( $this->base . '/private/' . $source->id );
		}
	}

	public function testPerBatchFileCountAndOversizeFailClosed(): void {
		$pipeline = $this->pipeline();
		$source   = $this->source();
		for ( $i = 0; $i < 8; ++$i ) {
			$pipeline->prepare( $source );
		}
		try {
			$pipeline->prepare( $source );
			self::fail( 'Ninth file admitted.' );
		} catch ( RuntimeException ) {
			self::assertCount( 8, glob( $this->base . '/quarantine/*/source' ) );
		}
		$this->expectException( RuntimeException::class );
		$this->pipeline()->prepare( $this->source( str_repeat( 'x', 1048577 ) ) );
	}

	public function testQuarantineCannotBeInPublicOrDeliveryRoot(): void {
		$this->expectException( RuntimeException::class );
		new MediaPipeline( $this->base . '/public', array( $this->base . '/public' ), static fn(): string => 'clean', static fn(): null => null, 42 );
	}

	public function testProcessHandlesSpacesAndShellCharactersLiterally(): void {
		$result = LocalProcess::run( array( PHP_BINARY, '-r', 'echo $argv[1];', 'literal ; $() & value' ), $this->base, 3 );
		self::assertSame( 0, $result['code'] );
		self::assertSame( 'literal ; $() & value', $result['out'] );
	}

	public function testConcurrentProcessingAndFullQuarantineRefuseBeforeCopy(): void {
		$lock = fopen( $this->base . '/quarantine/capacity.lock', 'c+b' );
		flock( $lock, LOCK_EX );
		try {
			$this->pipeline()->prepare( $this->source() );
			self::fail( 'Concurrent processor admitted.' );
		} catch ( RuntimeException ) {
			self::assertSame( array(), glob( $this->base . '/quarantine/*/source' ) );
		} finally {
			fclose( $lock );
		}
		for ( $i = 0; $i < 100; ++$i ) {
			mkdir( $this->base . '/quarantine/' . str_pad( dechex( $i ), 32, '0', STR_PAD_LEFT ) );
		}
		$this->expectException( RuntimeException::class );
		$this->pipeline()->prepare( $this->source() );
	}

	public function testDecoderFailureAndMissingDerivativeNeverAdmit(): void {
		$source = $this->source( base64_decode( self::PNG, true ) );
		foreach ( array( true, false ) as $throws ) {
			try {
				$this->pipeline(
					null,
					static function () use ( $throws ): void {
						if ( $throws ) {
							throw new RuntimeException( 'Decoder failed.' );
						}
					}
				)->prepare( $source );
				self::fail( 'Missing derivative accepted.' );
			} catch ( RuntimeException ) {
				self::assertFileExists( $source );
			}
		}
	}

	public function testWorkerRejectsMalformedInput(): void {
		$source = $this->source( 'not an image' );
		$worker = dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src/Media/ImageWorker.php';
		$result = LocalProcess::run( array( PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=gd', $worker, $source, dirname( $source ) ), $this->base, 10 );
		self::assertSame( 2, $result['code'] );
		self::assertFileDoesNotExist( dirname( $source ) . '/preview.png' );
	}

	public function testProcessDeadlineInterruptsChild(): void {
		$start = microtime( true );
		try {
			LocalProcess::run( array( PHP_BINARY, '-r', 'if(function_exists("pcntl_signal")){pcntl_async_signals(true);pcntl_signal(SIGTERM,SIG_IGN);}sleep(20);' ), $this->base, 1 );
			self::fail( 'Timeout accepted.' );
		} catch ( RuntimeException ) {
			self::assertLessThan( 5, microtime( true ) - $start );
		}
	}

	public function testWorkerGeneratesBoundedPngWithoutLoadingWebIni(): void {
		$source = $this->source( base64_decode( self::PNG, true ) );
		$worker = dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src/Media/ImageWorker.php';
		$result = LocalProcess::run( array( PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=gd', $worker, $source, dirname( $source ) ), $this->base, 10 );
		self::assertSame( 0, $result['code'], $result['out'] . $result['err'] );
		foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
			$size = getimagesize( dirname( $source ) . '/' . $variant . '.png' );
			self::assertSame( array( 1, 1, IMAGETYPE_PNG ), array_slice( $size, 0, 3 ) );
		}
	}
	public function testWorkerResizesLargeImageAndPreservesSource(): void {
		$source = $this->source();
		$image  = imagecreatetruecolor( 1600, 900 );
		imagepng( $image, $source );
		$hash   = hash_file( 'sha256', $source );
		$worker = dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src/Media/ImageWorker.php';
		$result = LocalProcess::run( array( PHP_BINARY, '-n', '-d', 'extension_dir=' . ini_get( 'extension_dir' ), '-d', 'extension=gd', $worker, $source, dirname( $source ) ), $this->base, 10 );
		self::assertSame( 0, $result['code'], $result['out'] . $result['err'] );
		foreach ( array(
			'thumbnail' => array( 320, 180 ),
			'preview'   => array( 1280, 720 ),
		) as $variant => $dimensions ) {
			self::assertSame( $dimensions, array_slice( getimagesize( dirname( $source ) . '/' . $variant . '.png' ), 0, 2 ) );
		}
		self::assertSame( $hash, hash_file( 'sha256', $source ) );
	}

	public function testExpiredScannerDeadlineDoesNotRun(): void {
		$scanner = new ClamAvScanner(
			'/scanner',
			'/db',
			static function (): array {
				self::fail( 'Expired scanner started.' );
			}
		);
		self::assertSame( 'inconclusive', $scanner->scan( '/file', '/job', microtime( true ) - 1 ) );
	}
}
