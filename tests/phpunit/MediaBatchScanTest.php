<?php
/** Grouped derivative admission uses a verdict for the whole pair.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Media\{ClamAvScanner, MediaPipeline};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
final class MediaBatchScanTest extends TestCase {
	private string $base;
	private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC';
	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-batch-scan-' . bin2hex( random_bytes( 8 ) );
		foreach ( array( '', '/public', '/quarantine' ) as $part ) {
			mkdir( $this->base . $part, 0700 );}
		file_put_contents( $this->base . '/public/input.png', base64_decode( self::PNG, true ) );
	}
	protected function tearDown(): void {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() && ! $f->isLink() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );}
		rmdir( $this->base );
	}
	public function testPairRequiresExactlyOneCompleteSummary(): void {
		$clean = "Scanned files: 2\nInfected files: 0\n";
		foreach ( array(
			array( 0, $clean, '', 'clean' ),
			array( 1, $clean, '', 'rejected' ),
			array( 2, $clean, '', 'inconclusive' ),
			array( 0, "Scanned files: 1\nInfected files: 0\n", '', 'inconclusive' ),
			array( 0, "Scanned files: 3\nInfected files: 0\n", '', 'inconclusive' ),
			array( 0, $clean . $clean, '', 'inconclusive' ),
			array( 0, $clean . 'WARNING: skipped', '', 'inconclusive' ),
			array( 0, $clean . 'Total errors: 1', '', 'inconclusive' ),
			array( 0, $clean, 'error', 'inconclusive' ),
			array( 0, "Scanned files: 2\nInfected files: 1\n", '', 'inconclusive' ),
			array( 0, '', '', 'inconclusive' ),
		) as [$code, $out, $err, $expected] ) {
			$scanner = new ClamAvScanner(
				'/scanner',
				'/signatures',
				static fn()=>array(
					'code' => $code,
					'out'  => $out,
					'err'  => $err,
				)
			);
			self::assertSame( $expected, $scanner->scanMany( array( '/one', '/two' ), '/job' ) );
		}
	}
	public function testInvalidSetAndExpiredBudgetNeverInvokeProcess(): void {
		$scanner = new ClamAvScanner(
			'/scanner',
			'/signatures',
			static function () {
				self::fail( 'No process expected' );
			}
		);
		foreach ( array( array(), array( '/a', '/a' ), array( '/a', '/b', '/c' ), array( '/a', null ), array( '/a', '' ), array( 'a' => '/a' ), array( "/a\0", '/b' ) ) as $paths ) {
			self::assertSame( 'inconclusive', $scanner->scanMany( $paths, '/job' ) );
		}
		self::assertSame( 'inconclusive', $scanner->scanMany( array( '/a', '/b' ), '/job', microtime( true ) - 1 ) );
	}
	public function testExplicitPairCannotInjectOptionsAndKeepsSafety(): void {
		$scanner = new ClamAvScanner(
			'/scanner',
			'/signatures',
			static function ( array $command ): array {
				self::assertSame( array( '--', '-first;literal', '-second' ), array_slice( $command, -3 ) );
				foreach ( array( '--official-db-only=yes', '--fail-if-cvd-older-than=3', '--alert-encrypted=yes', '--alert-exceeds-max=yes', '--follow-file-symlinks=0' ) as $flag ) {
					self::assertContains( $flag, $command );}
				self::assertNotContains( '--remove', $command );
				return array(
					'code' => 0,
					'out'  => "Scanned files: 2\nInfected files: 0\n",
					'err'  => '',
				);
			}
		);
		self::assertSame( 'clean', $scanner->scanMany( array( '-first;literal', '-second' ), '/job' ) );
	}
	private function pipeline( array &$order, Closure $pair ): MediaPipeline {
		return new MediaPipeline(
			$this->base . '/quarantine',
			array( $this->base . '/public' ),
			static function () use ( &$order ): string {
				$order[] = 'original';
				return 'clean';},
			static function ( string $path, string $job ) use ( &$order ): void {
				self::assertSame( array( 'original' ), $order );
				$order[] = 'render';
				foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
					file_put_contents( $job . '/' . $variant . '.png', base64_decode( self::PNG, true ) );}
			},
			42,
			null,
			static function ( array $paths, string $job, float $deadline ) use ( &$order, $pair ): string {
				self::assertSame( array( 'original', 'render' ), $order );
				$order[] = 'pair';
				self::assertSame( array( $job . '/thumbnail.png', $job . '/preview.png' ), $paths );
				self::assertGreaterThan( microtime( true ), $deadline );
				return $pair( $paths );
			}
		);
	}
	public function testOriginalPrecedesRenderingAndPairPreservesAuditAndHashes(): void {
		$order  = array();
		$source = $this->base . '/public/input.png';
		$before = hash_file( 'sha256', $source );
		$result = $this->pipeline( $order, static fn(): string=>'clean' )->prepare( $source );
		self::assertSame( array( 'original', 'render', 'pair' ), $order );
		self::assertSame( array( 'original', 'thumbnail', 'preview' ), array_keys( $result['hashes'] ) );
		self::assertSame( $before, hash_file( 'sha256', $source ) );
		$events = array_map( static fn( $l )=>json_decode( $l, true, 512, JSON_THROW_ON_ERROR ), file( $result['job'] . '/events.jsonl', FILE_IGNORE_NEW_LINES ) );
		self::assertSame( array( 'received', 'quarantined', 'clean', 'clean', 'clean', 'processed' ), array_column( $events, 'event' ) );
	}
	public function testAnyPairFailureOrByteChangeRetainsWholeItem(): void {
		foreach ( array( 'rejected', 'inconclusive', 'unknown', 'mutate' ) as $mode ) {
			$order = array();$pipeline = $this->pipeline(
				$order,
				static function ( array $paths ) use ( $mode ): string {
					if ( 'mutate' === $mode ) {
						file_put_contents( $paths[1], 'changed' );
						return 'clean';
					}return $mode;
				}
			);
			try {
				$pipeline->prepare( $this->base . '/public/input.png' );
				self::fail( 'Partial admission prohibited' );} catch ( RuntimeException ) {
				$this->addToAssertionCount( 1 );}
		}
		foreach ( glob( $this->base . '/quarantine/*/events.jsonl' ) as $path ) {
			$events = array_map( static fn( $l )=>json_decode( $l, true, 512, JSON_THROW_ON_ERROR ), file( $path, FILE_IGNORE_NEW_LINES ) );
			self::assertNotContains( 'processed', array_column( $events, 'event' ) );
			self::assertSame( 'not_admitted', end( $events )['event'] );
			self::assertFileExists( dirname( $path ) . '/source' );
		}
	}
}
