<?php
/** Synthetic operational diagnostics and immutable receipts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Media\{HealthReports, HealthAccess, InventorySnapshot, MediaHealth};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
final class MediaHealthTest extends TestCase {
	private string $base;
	private bool $allowed = true;
	private int $now      = 1800000000;
	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-health-' . bin2hex( random_bytes( 8 ) );
		foreach ( array( '', '/public', '/reports', '/store' ) as $dir ) {
			mkdir( $this->base . $dir, 0700 ); }
	}
	protected function tearDown(): void {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $entry ) {
			$entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() ); }
		rmdir( $this->base );
	}
	private function service(): HealthReports {
		return new HealthReports( $this->base . '/reports', array( $this->base . '/public', $this->base . '/store' ), fn( int $id ): bool => 42 === $id && $this->allowed, fn(): int => $this->now );
	}
	private function probeResult(): array {
		return array(
			'scanner' => 'available',
			'image'   => 'unavailable',
			'pdf'     => 'disabled',
			'storage' => array(
				'queue'      => null,
				'quarantine' => null,
				'delivery'   => null,
			),
		);
	}
	private function refused( callable $call ): void {
		try {
			$call();
		} catch ( RuntimeException ) {
			$this->addToAssertionCount( 1 );
			return; }
		self::fail( 'Expected refusal.' );
	}
	public function testGetDoesNotCreateFilesAndCompletedRequestDoesNotRepeat(): void {
		$s  = $this->service();
		$id = str_repeat( 'a', 32 );
		self::assertSame( array(), $s->listing( 42 ) );
		self::assertSame( array( '.', '..' ), scandir( $this->base . '/reports' ) );
		$calls = 0;
		$probe = function () use ( &$calls ): array {
			++$calls;
			return $this->probeResult();
		};
		$row   = $s->run( 42, $id, $probe );
		self::assertSame( 'complete', $row['status'] );
		self::assertSame( 42, $row['start']['actor'] );
		self::assertSame( $this->now, $row['end']['at'] );
		$hash = hash_file( 'sha256', $this->base . '/reports/' . $id . '/result.json' );
		self::assertSame( $row, $this->service()->run( 42, $id, $probe ) );
		self::assertSame( 1, $calls );
		self::assertSame( $hash, hash_file( 'sha256', $this->base . '/reports/' . $id . '/result.json' ) );
		self::assertSame( 'unavailable', $row['end']['result']['image'] );
	}
	public function testRevokedAuthorityNeverConfirmsProbeAndBytesRemain(): void {
		$id = str_repeat( 'b', 32 );
		$this->refused(
			fn() => $this->service()->run(
				42,
				$id,
				function ( string $job ): array {
					file_put_contents( $job . '/synthetic', 'fictional' );
					$this->allowed = false;
					return $this->probeResult();
					}
			)
		);
		self::assertFileDoesNotExist( $this->base . '/reports/' . $id . '/result.json' );
		self::assertSame( 'fictional', file_get_contents( $this->base . '/reports/' . $id . '/synthetic' ) );
		$this->refused( fn() => $this->service()->listing( 42 ) );
		$this->allowed = true;
		self::assertSame( 'incomplete', $this->service()->listing( 42 )[ $id ]['status'] );
		$this->refused( fn() => $this->service()->run( 42, $id, fn(): array => $this->probeResult() ) );
	}
	public function testCorruptMarkerCannotBecomeSuccessOrBeRepaired(): void {
		$id = str_repeat( 'c', 32 );
		$this->service()->run( 42, $id, fn(): array => $this->probeResult() );
		file_put_contents( $this->base . '/reports/' . $id . '/result.sha256', str_repeat( '0', 64 ) );
		self::assertSame( 'unavailable', $this->service()->listing( 42 )[ $id ]['status'] );
		$this->refused( fn() => $this->service()->run( 42, str_repeat( 'd', 32 ), fn(): array => $this->probeResult() ) );
	}
	public function testInterruptedProbePreservesStartAndOtherReceipts(): void {
		$id = str_repeat( 'd', 32 );
		$this->refused(
			fn() => $this->service()->run(
				42,
				$id,
				static function (): array {
				throw new RuntimeException( 'Interrupted synthetic probe.' );
				}
			)
		);
		$hash = hash_file( 'sha256', $this->base . '/reports/' . $id . '/start.json' );
		$this->service()->run( 42, str_repeat( 'e', 32 ), fn(): array => $this->probeResult() );
		self::assertSame( 'incomplete', $this->service()->listing( 42 )[ $id ]['status'] );
		self::assertSame( $hash, hash_file( 'sha256', $this->base . '/reports/' . $id . '/start.json' ) );
	}
	public function testConcurrentProbeRefusedAndOuterCompletes(): void {
		$row = $this->service()->run(
			42,
			str_repeat( 'e', 32 ),
			function (): array {
				$this->refused( fn() => $this->service()->run( 42, str_repeat( 'f', 32 ), fn(): array => $this->probeResult() ) );
				return $this->probeResult();
			}
		);
		self::assertSame( 'complete', $row['status'] );
		self::assertCount( 1, $this->service()->listing( 42 ) );
	}
	public function testAuthorityAndMalformedIdRefuseBeforeProbe(): void {
		$probe = static function (): array {
			self::fail( 'Must not probe.' );
		};
		$this->refused( fn() => $this->service()->run( 7, str_repeat( 'a', 32 ), $probe ) );
		$this->refused( fn() => $this->service()->run( 42, '../unsafe', $probe ) );
		self::assertSame( array( '.', '..' ), scandir( $this->base . '/reports' ) );
	}
	public function testInvalidProbeContractRemainsIncomplete(): void {
		$this->refused( fn() => $this->service()->run( 42, str_repeat( 'a', 32 ), fn(): array => $this->probeResult() + array( 'path' => '/private' ) ) );
		self::assertSame( 'incomplete', $this->service()->listing( 42 )[ str_repeat( 'a', 32 ) ]['status'] );
		$r            = $this->probeResult();
		$r['scanner'] = 'skipped';
		$this->refused( fn() => HealthReports::validate( $r ) );
	}
	public function testInventoryCountsMetadataWithoutChangingBytes(): void {
		$path  = $this->base . '/store';
		$batch = str_repeat( 'a', 32 );
		mkdir( $path . '/' . $batch );
		file_put_contents( $path . '/' . $batch . '/source', 'ficticio' );
		$m = InventorySnapshot::read( $path, array( $this->base . '/public' ), static fn(): int => 123456 );
		self::assertSame( 1, $m['files'] );
		self::assertSame( 8, $m['bytes'] );
		self::assertSame( 1, $m['batches'] );
		self::assertSame( 123456, $m['free'] );
		self::assertSame( 'ficticio', file_get_contents( $path . '/' . $batch . '/source' ) );
		$this->refused( fn() => InventorySnapshot::read( $path, array( $this->base . '/public' ), static fn(): bool => false ) );
	}
	public function testDeepInventoryAndPublicRootAreRefused(): void {
		$path = $this->base . '/store';
		for ( $i = 0; $i < 5; ++$i ) {
			$path .= '/child';
			mkdir( $path ); }
		$this->refused( fn() => InventorySnapshot::read( $this->base . '/store', array( $this->base . '/public' ) ) );
		$this->refused( fn() => InventorySnapshot::read( $this->base . '/public', array( $this->base . '/public' ) ) );
	}
	public function testHardLinkedInventoryAndReceiptAreRefused(): void {
		$source = $this->base . '/store/source';
		file_put_contents( $source, 'ficticio' );
		self::assertTrue( link( $source, $this->base . '/store/other' ) );
		$this->refused( fn() => InventorySnapshot::read( $this->base . '/store', array( $this->base . '/public' ) ) );
		$id = str_repeat( 'a', 32 );
		$this->service()->run( 42, $id, fn(): array => $this->probeResult() );
		self::assertTrue( link( $this->base . '/reports/' . $id . '/result.json', $this->base . '/receipt-link' ) );
		self::assertSame( 'unavailable', $this->service()->listing( 42 )[ $id ]['status'] );
	}
	public function testRequestStrictnessAndNoNonceForGet(): void {
		$get   = array( 'page' => 'arn-media-health' );
		$post  = array(
			'action'     => 'check',
			'nonce'      => 'valid',
			'request_id' => str_repeat( 'a', 32 ),
		);
		$nonce = static fn( string $n ): bool => 'valid' === $n;
		self::assertNull( HealthAccess::request( 'GET', $get, array(), array(), $nonce ) );
		self::assertSame( $post['request_id'], HealthAccess::request( 'POST', $get, $post, array(), $nonce ) );
		foreach ( array( 'HEAD', 'PUT', 'DELETE' ) as $method ) {
			$this->refused( fn() => HealthAccess::request( $method, $get, array(), array(), $nonce ) ); }
		$this->refused( fn() => HealthAccess::request( 'POST', $get, $post, array(), static fn(): bool => false ) );
		$this->refused( fn() => HealthAccess::request( 'POST', $get, $post + array( 'path' => '/private' ), array(), $nonce ) );
		$this->refused( fn() => HealthAccess::request( 'GET', $get + array( 'probe' => '1' ), array(), array(), $nonce ) );
		$this->refused( fn() => HealthAccess::request( 'POST', $get, $post, array( 'file' => array() ), $nonce ) );
	}
	public function testSyntheticPdfOffsetsAndLengthAreConsistent(): void {
		$pdf = MediaHealth::pdf();
		self::assertStringStartsWith( '%PDF-1.4', $pdf );
		preg_match( '/startxref\n([0-9]+)/', $pdf, $xref );
		self::assertStringStartsWith( 'xref', substr( $pdf, (int) $xref[1] ) );
		preg_match_all( '/([0-9]{10}) 00000 n/', $pdf, $offsets );
		foreach ( $offsets[1] as $i => $offset ) {
			self::assertStringStartsWith( ( $i + 1 ) . ' 0 obj', substr( $pdf, (int) $offset ) ); }
	}
	public function testFiftyReportsAreConservedAndNextProbeIsRefused(): void {
		for ( $i = 0; $i < 50; ++$i ) {
			$this->service()->run( 42, sprintf( '%032x', $i ), fn(): array => $this->probeResult() ); }
		self::assertCount( 50, $this->service()->listing( 42 ) );
		$this->refused(
			fn() => $this->service()->run(
				42,
				str_repeat( 'f', 32 ),
				static function (): array {
				self::fail( 'Capacity must be checked first.' );
				}
			)
		);
		self::assertCount( 50, $this->service()->listing( 42 ) );
	}
	public function testTooManyEntriesRefusePartialInventory(): void {
		for ( $i = 0; $i < 5001; ++$i ) {
			file_put_contents( $this->base . '/store/' . $i, '' ); }
		$this->refused( fn() => InventorySnapshot::read( $this->base . '/store', array( $this->base . '/public' ) ) );
	}
	public function testClockCannotMoveBackwardsAcrossProbe(): void {
		$id = str_repeat( 'a', 32 );
		$this->refused(
			fn() => $this->service()->run(
				42,
				$id,
				function (): array {
					--$this->now;
					return $this->probeResult();
					}
			)
		);
		self::assertSame( 'incomplete', $this->service()->listing( 42 )[ $id ]['status'] );
	}
	public function testFifoReceiptIsRefusedBeforeOpening(): void {
		if ( ! function_exists( 'posix_mkfifo' ) || ! function_exists( 'pcntl_alarm' ) ) {
			self::markTestSkipped( 'POSIX FIFO and process alarm required.' );
		}
		$id = str_repeat( 'a', 32 );
		$this->service()->run( 42, $id, fn(): array => $this->probeResult() );
		$path = $this->base . '/reports/' . $id . '/start.json';
		unlink( $path );
		self::assertTrue( posix_mkfifo( $path, 0600 ) );
		$old            = pcntl_signal_get_handler( SIGALRM );
		$previous_async = pcntl_async_signals( true );
		$alarm_fired    = false;
		pcntl_signal(
			SIGALRM,
			static function () use ( &$alarm_fired ): void {
				$alarm_fired = true;
				throw new LogicException( 'Blocked on FIFO.' );
			}
		);
		pcntl_alarm( 2 );
		try {
			self::assertSame( 'unavailable', $this->service()->listing( 42 )[ $id ]['status'] );
			self::assertFalse( $alarm_fired );
		} finally {
			pcntl_alarm( 0 );
			pcntl_signal( SIGALRM, $old );
			pcntl_async_signals( $previous_async );
		}
	}
}
