<?php
/** Synthetic supervision tests, including interrupted writes and concurrent controls.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Media\{QueueSupervisor, SupervisorAccess, QueueRequest, MediaQueue, MediaPipeline};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
final class QueueSupervisorTest extends TestCase {
	private string $base;
	private int $now           = 1800000000;
	private bool $allowed      = true;
	private ?Closure $on_sleep = null;
	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-supervisor-' . bin2hex( random_bytes( 8 ) );
		foreach ( array( '', '/private', '/public', '/queue', '/quarantine' ) as $part ) {
			mkdir( $this->base . $part, 0700 ); }
	}
	protected function tearDown(): void {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $f ) {
			$f->isDir() && ! $f->isLink() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() ); }
		rmdir( $this->base );
	}
	private function service(): QueueSupervisor {
		return new QueueSupervisor(
			$this->base . '/private',
			array( $this->base . '/public', $this->base . '/queue' ),
			fn( int $id ): bool => 42 === $id && $this->allowed,
			fn(): int => $this->now,
			function ( int $seconds ): void {
				$this->now += $seconds;
				if ( null !== $this->on_sleep ) {
					( $this->on_sleep )(); }
			}
		);
	}
	private function refused( callable $operation ): void {
		try {
			$operation();
		} catch ( RuntimeException ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		self::fail( 'Expected refusal.' );
	}
	public function testCyclesAreSpacedBoundedAuditedAndNotBusinessWrites(): void {
		$starts = array();
		$s      = $this->service()->run(
			42,
			3,
			10,
			2,
			function ( int $actor, int $limit ) use ( &$starts ): int {
				self::assertSame( array( 42, 2 ), array( $actor, $limit ) );
				$starts[]   = $this->now;
				$this->now += 4;
				return 2;
			}
		);
		self::assertSame( array( 1800000000, 1800000014, 1800000028 ), $starts );
		self::assertSame( 'finished', $s['status'] );
		self::assertSame( 6, $s['processed'] );
		self::assertSame( 8, $s['revision'] );
		self::assertSame( $s, $this->service()->status( 42 ) );
		self::assertSame( array( '.', '..' ), scandir( $this->base . '/queue' ) );
	}
	public function testStopDuringCycleDoesNotInterruptWorkOrStartAnother(): void {
		$calls = 0;
		$s     = $this->service()->run(
			42,
			3,
			10,
			1,
			function () use ( &$calls ): int {
				++$calls;
				$state = $this->service()->status( 42 );
				$this->service()->stop( 42, $state['revision'], 'Paragem fictícia solicitada.' );
				$this->refused( fn() => $this->service()->acknowledge( 42, $state['revision'] + 1, 'Processo ainda activo no ensaio.' ) );
				$this->refused( fn() => $this->service()->run( 42, 1, 10, 1, static fn(): int => 0 ) );
				return 1;
			}
		);
		self::assertSame( 1, $calls );
		self::assertSame( 'stopped', $s['status'] );
		self::assertSame( 1, $s['processed'] );
		self::assertSame( array( 'start', 'begin', 'stop', 'complete', 'finish' ), array_column( $s['history'], 'type' ) );
	}
	public function testStopDuringWaitAndStaleDecision(): void {
		$this->on_sleep = function (): void {
			$s = $this->service()->status( 42 );
			$this->refused( fn() => $this->service()->stop( 42, $s['revision'] - 1, 'Revisão antiga do operador.' ) );
			$this->service()->stop( 42, $s['revision'], 'Paragem durante a espera.' );
		};
		$s              = $this->service()->run( 42, 10, 300, 1, static fn(): int => 0 );
		self::assertSame( 1, $s['cycle'] );
		self::assertSame( 'stopped', $s['status'] );
	}
	public function testFailureStopsAndDoesNotLeakRawError(): void {
		$this->refused(
			fn() => $this->service()->run(
				42,
				10,
				10,
				1,
				static function (): int {
				throw new RuntimeException( 'private scanner path' );
				}
			)
		);
		$s = $this->service()->status( 42 );
		self::assertSame( 'failed', $s['status'] );
		self::assertSame( 1, $s['cycle'] );
		self::assertStringNotContainsString( 'private scanner path', json_encode( $s ) );
	}
	public function testAuthorityLossLeavesUnconfirmedRunAndExplicitAcknowledgement(): void {
		$this->refused(
			fn() => $this->service()->run(
				42,
				2,
				10,
				1,
				function (): int {
					$this->allowed = false;
					return 1;
				}
			)
		);
		$this->refused( fn() => $this->service()->status( 42 ) );
		$this->allowed = true;
		$s             = $this->service()->status( 42 );
		self::assertSame( 'working', $s['status'] );
		$this->refused( fn() => $this->service()->run( 42, 1, 10, 1, static fn(): int => 0 ) );
		$s = $this->service()->stop( 42, $s['revision'], 'Pedido após processo terminar.' );
		$s = $this->service()->acknowledge( 42, $s['revision'], 'Confirmada interrupção fictícia.' );
		self::assertSame( 'interrupted', $s['status'] );
		self::assertSame( 'ack', $s['history'][ array_key_last( $s['history'] ) ]['type'] );
		self::assertSame( 'finished', $this->service()->run( 42, 1, 10, 1, static fn(): int => 0 )['status'] );
	}
	public function testRevocationDuringWaitStopsBeforeAnotherCycle(): void {
		$this->on_sleep = function (): void {
			$this->allowed = false;
		};
		$calls          = 0;
		$this->refused(
			function () use ( &$calls ): void {
				$this->service()->run(
					42,
					2,
					10,
					1,
					function () use ( &$calls ): int {
						++$calls;
						return 0;
					}
				);
			}
		);
		self::assertSame( 1, $calls );
		$this->allowed = true;
		self::assertSame( 'waiting', $this->service()->status( 42 )['status'] );
	}
	public function testInvalidOptionsAndForeignOperatorFailBeforeWork(): void {
		self::assertSame( array( 3, 30, 1 ), SupervisorAccess::options( array(), array() ) );
		foreach ( array( array( 'cycles' => '11' ), array( 'interval' => '9' ), array( 'interval' => '301' ), array( 'limit' => '4' ), array( 'cycles' => 1 ), array( 'cycles' => '01' ), array( 'user' => 'someone' ) ) as $options ) {
			$this->refused( fn() => SupervisorAccess::options( array(), $options ) ); }
		$this->refused( fn() => $this->service()->run( 43, 1, 10, 1, static fn(): int => 0 ) );
		$this->refused( fn() => $this->service()->run( 42, 11, 10, 1, static fn(): int => 0 ) );
		self::assertNull( $this->service()->status( 42 ) );
	}
	public function testMissingFinalEventAndPartialWriteCannotBeAcknowledgedOrRestarted(): void {
		$s = $this->service()->run( 42, 1, 10, 1, static fn(): int => 0 );
		unlink( $this->base . '/private/event-000004' );
		$this->refused( fn() => $this->service()->status( 42 ) );
		$this->refused( fn() => $this->service()->acknowledge( 42, $s['revision'], 'Não reparar um diário truncado.' ) );
		$this->refused( fn() => $this->service()->run( 42, 1, 10, 1, static fn(): int => 0 ) );
		file_put_contents( $this->base . '/private/event-000004', '{' );
		$this->expectException( JsonException::class );
		$this->service()->status( 42 );
	}
	public function testChangedEventAndUnexpectedFilesAreRejected(): void {
		$this->service()->run( 42, 1, 10, 1, static fn(): int => 0 );
		$path = $this->base . '/private/event-000001';
		file_put_contents( $path, str_replace( '1800000000', '1800000001', file_get_contents( $path ) ) );
		$this->refused( fn() => $this->service()->status( 42 ) );
	}
	public function testClockCannotCauseUnboundedWaiting(): void {
		$this->on_sleep = function (): void {
			$this->now -= 2;
		};
		$this->refused( fn() => $this->service()->run( 42, 2, 10, 1, static fn(): int => 0 ) );
		self::assertSame( 'failed', $this->service()->status( 42 )['status'] );
	}
	public function testHttpDecisionContractRejectsNonceFilesAndExtraFields(): void {
		$get  = array( 'page' => 'arn-media-queue' );
		$post = array(
			'action'   => 'supervisor_stop',
			'nonce'    => 'ok',
			'reason'   => 'Paragem fictícia auditada.',
			'revision' => '3',
		);
		self::assertSame( $post, QueueRequest::parse( 'POST', $get, $post, array(), static fn(): bool => true, static fn(): bool => false ) );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get, $post, array(), static fn(): bool => false, static fn(): bool => false ) );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get, $post + array( 'path' => '/tmp' ), array(), static fn(): bool => true, static fn(): bool => false ) );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get, $post, array( 'media' => array() ), static fn(): bool => true, static fn(): bool => false ) );
	}
	public function testRealQueueRetainsFailedEntryAndDoesNotProcessNext(): void {
		file_put_contents( $this->base . '/public/fake.pdf', "%PDF-1.4\n%%EOF\n" );
		$q        = new MediaQueue( $this->base . '/queue', array( $this->base . '/public' ), static fn( int $id ): bool => 42 === $id, fn(): int => $this->now );
		$id       = $q->enqueue( array_fill( 0, 2, $this->base . '/public/fake.pdf' ), 42 );
		$pipeline = fn(): MediaPipeline => new MediaPipeline( $this->base . '/quarantine', array( $this->base . '/public' ), static fn(): string => 'infected', static function (): void {}, 42 );
		$this->refused( fn() => $this->service()->run( 42, 2, 10, 3, fn( int $actor, int $limit ): int => $q->work( $actor, $limit, $pipeline, true ) ) );
		self::assertSame( array( 'held', 'queued' ), array_column( $q->read( $id, 42 )['items'], 'status' ) );
		self::assertSame( 'failed', $this->service()->status( 42 )['status'] );
	}
	public function testTemporaryJournalContentionWaitsWithoutFailingTheRun(): void {
		$code    = '$h=fopen($argv[1],"c+b");flock($h,LOCK_EX);fwrite(STDOUT,"locked\n");fflush(STDOUT);usleep(250000);fclose($h);';
		$process = proc_open(
			array( PHP_BINARY, '-r', $code, $this->base . '/private/journal.lock' ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes
		);
		self::assertIsResource( $process );
		try {
			self::assertSame( "locked\n", fgets( $pipes[1] ) );
			self::assertSame( 'finished', $this->service()->run( 42, 1, 10, 1, static fn(): int => 0 )['status'] );
		} finally {
			foreach ( $pipes as $pipe ) {
				fclose( $pipe ); }
			self::assertSame( 0, proc_close( $process ) );
		}
	}
	public function testStopWinsAtomicAdvanceAfterAnOlderReadIncludingFinalCycle(): void {
		$append = new ReflectionMethod( QueueSupervisor::class, 'append' );
		foreach ( array( false, true ) as $after_last_cycle ) {
			$service = $this->service();
			$append->invoke(
				$service,
				42,
				'start',
				array(
					'run'      => bin2hex( random_bytes( 16 ) ),
					'cycles'   => 1,
					'interval' => 10,
					'limit'    => 1,
				)
			);
			if ( $after_last_cycle ) {
				$append->invoke( $service, 42, 'begin' );
				$append->invoke( $service, 42, 'complete', array( 'count' => 0 ) );
			}
			$old = $service->status( 42 );
			$service->stop( 42, $old['revision'], 'Paragem entre observação e avanço.' );
			$state = $append->invoke( $service, 42, 'advance' );
			self::assertSame( 'stopped', $state['status'] );
			self::assertSame( $after_last_cycle ? 1 : 0, $state['cycle'] );
		}
	}
}
