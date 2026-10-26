<?php
/** Queue tests use synthetic bytes and injected scans, not antimalware evidence.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Media\{MediaQueue, MediaPipeline, QueueRequest, QueueState};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions, WordPress.PHP.DiscouragedPHPFunctions
final class MediaQueueTest extends TestCase {
	private string $base;
	private bool $allowed = true;
	private int $now      = 1800000000;
	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-queue-' . bin2hex( random_bytes( 8 ) );
		foreach ( array( '', '/public', '/queue', '/quarantine' ) as $folder ) {
			mkdir( $this->base . $folder, 0700 ); }
		file_put_contents( $this->base . '/public/synthetic.pdf', "%PDF-1.4\n%%EOF\n" );
	}
	protected function tearDown(): void {
		$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $iterator as $entry ) {
			$entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() ); }
		rmdir( $this->base );
	}
	private function queue(): MediaQueue {
		return new MediaQueue( $this->base . '/queue', array( $this->base . '/public', $this->base . '/quarantine' ), fn( int $id ): bool => 42 === $id && $this->allowed, fn(): int => $this->now );
	}
	private function enqueue( int $count = 1, ?string $request = null ): string {
		return $this->queue()->enqueue( array_fill( 0, $count, $this->base . '/public/synthetic.pdf' ), 42, $request );
	}
	private function pipeline( ?Closure $scan = null ): Closure {
		return fn( int $actor ): MediaPipeline => new MediaPipeline( $this->base . '/quarantine', array( $this->base . '/public', $this->base . '/queue' ), $scan ?? static fn(): string => 'clean', static function (): void {}, $actor );
	}
	private function refused( callable $operation ): void {
		try {
			$operation();
		} catch ( RuntimeException ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		self::fail( 'Expected closed refusal.' );
	}
	public function testBatchIsDurableBoundedAndDoesNotProcessInEnqueue(): void {
		$id = $this->enqueue( 8 );
		$s  = $this->queue()->read( $id, 42 );
		self::assertCount( 8, $s['items'] );
		self::assertSame( array_fill( 0, 8, 'queued' ), array_column( $s['items'], 'status' ) );
		self::assertSame( array( '.', '..' ), scandir( $this->base . '/quarantine' ) );
		self::assertSame( 1, $s['revision'] );
		self::assertSame( 42, $s['history'][0]['actor'] );
		self::assertSame( $this->now, $s['history'][0]['at'] );
		$this->refused( fn() => $this->enqueue( 9 ) );
		$this->refused( fn() => $this->enqueue( 0 ) );
		self::assertCount( 1, $this->queue()->listing( 42 ) );
	}
	public function testWorkerResumesBatchWithoutRepeatingCompletedItems(): void {
		$id    = $this->enqueue( 4 );
		$calls = 0;
		$scan  = function () use ( &$calls ): string {
			++$calls;
			return 'clean';
		};
		self::assertSame( 3, $this->queue()->work( 42, 3, $this->pipeline( $scan ) ) );
		self::assertSame( array( 'ready', 'ready', 'ready', 'queued' ), array_column( $this->queue()->read( $id, 42 )['items'], 'status' ) );
		self::assertSame( 1, $this->queue()->work( 42, 3, $this->pipeline( $scan ) ) );
		self::assertSame( 0, $this->queue()->work( 42, 1, $this->pipeline( $scan ) ) );
		self::assertSame( 4, $calls );
		self::assertSame( 9, $this->queue()->read( $id, 42 )['revision'] );
	}
	public function testPdfWithDerivativesSurvivesDurableQueueReplay(): void {
		$id      = $this->enqueue();
		$factory = fn( int $actor ): MediaPipeline => new MediaPipeline(
			$this->base . '/quarantine',
			array( $this->base . '/public', $this->base . '/queue' ),
			static fn(): string => 'clean',
			static function (): void {},
			$actor,
			static function ( string $source, string $job ): void {
				foreach ( array( 'thumbnail', 'preview' ) as $variant ) {
					file_put_contents( $job . '/' . $variant . '.png', base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC', true ) );
				}
			}
		);
		self::assertSame( 1, $this->queue()->work( 42, 1, $factory ) );
		$item    = $this->queue()->read( $id, 42 )['items'][0];
		self::assertSame( 'ready', $item['status'] );
		self::assertSame( array( 'original', 'thumbnail', 'preview' ), array_keys( $item['result']['hashes'] ) );
		self::assertSame( 0, $this->queue()->work( 42, 1, $factory ) );
	}
	public function testWorkerSerializesAndRejectsConcurrentRecovery(): void {
		$id   = $this->enqueue();
		$scan = function () use ( $id ): string {
			$s = $this->queue()->read( $id, 42 );
			self::assertSame( 'processing', $s['items'][0]['status'] );
			$this->refused( fn() => $this->queue()->work( 42, 1, $this->pipeline() ) );
			$this->refused( fn() => $this->queue()->manage( $id, 2, 0, 'recover', 'Ensaio fictício de recuperação.', 42 ) );
			return 'clean';
		};
		self::assertSame( 1, $this->queue()->work( 42, 1, $this->pipeline( $scan ) ) );
		self::assertSame( 'ready', $this->queue()->read( $id, 42 )['items'][0]['status'] );
	}
	public function testRevocationBeforeResultLeavesRecoverableUncertainState(): void {
		$id   = $this->enqueue();
		$scan = function (): string {
			$this->allowed = false;
			return 'clean';
		};
		$this->refused( fn() => $this->queue()->work( 42, 1, $this->pipeline( $scan ) ) );
		$this->refused( fn() => $this->queue()->listing( 42 ) );
		$this->allowed = true;
		$s             = $this->queue()->read( $id, 42 );
		self::assertSame( 'processing', $s['items'][0]['status'] );
		self::assertSame( 0, $this->queue()->work( 42, 1, $this->pipeline() ) );
		$s = $this->queue()->manage( $id, 2, 0, 'recover', 'Worker fictício interrompido.', 42 );
		self::assertSame( 'held', $s['items'][0]['status'] );
		$this->queue()->manage( $id, 3, 0, 'retry', 'Repetir integralmente a análise.', 42 );
		$this->queue()->work( 42, 1, $this->pipeline() );
		self::assertSame( 2, $this->queue()->read( $id, 42 )['items'][0]['attempts'] );
	}
	public function testFailedScansStayHeldAndRetryIsCapped(): void {
		$id = $this->enqueue();
		foreach ( array( 'rejected', 'inconclusive', 'invalid' ) as $status ) {
			$this->queue()->work( 42, 1, $this->pipeline( static fn(): string => $status ) );
			$s = $this->queue()->read( $id, 42 );
			self::assertSame( 'held', $s['items'][0]['status'] );
			self::assertSame( array(), $s['items'][0]['result'] );
			if ( $s['items'][0]['attempts'] < 3 ) {
				$this->queue()->manage( $id, $s['revision'], 0, 'retry', 'Novo ensaio fictício controlado.', 42 ); }
		}
		$this->refused( fn() => $this->queue()->manage( $id, $s['revision'], 0, 'retry', 'Não pode ultrapassar o limite.', 42 ) );
	}
	public function testDecisionsRequireCurrentRevisionReasonAndNeverDeleteBytes(): void {
		$id     = $this->enqueue();
		$s      = $this->queue()->read( $id, 42 );
		$source = $this->base . '/queue/' . $id . '/' . $s['items'][0]['blob']['id'];
		$hash   = hash_file( 'sha256', $source );
		$this->refused( fn() => $this->queue()->manage( $id, 1, 0, 'hold', '', 42 ) );
		$this->refused( fn() => $this->queue()->manage( $id, 1, 0, 'release', 'Libertação não está disponível.', 42 ) );
		$this->queue()->manage( $id, 1, 0, 'hold', 'Retenção fictícia para revisão.', 42 );
		$this->refused( fn() => $this->queue()->manage( $id, 1, 0, 'cancel', 'Revisão antiga não é aceite.', 42 ) );
		$this->queue()->manage( $id, 2, 0, 'cancel', 'Cancelar o ensaio fictício.', 42 );
		$s = $this->queue()->manage( $id, 3, -1, 'archive', 'Arquivar com conservação integral.', 42 );
		self::assertTrue( $s['archived'] );
		self::assertCount( 4, $s['history'] );
		self::assertSame( $hash, hash_file( 'sha256', $source ) );
		self::assertSame( $hash, hash_file( 'sha256', $this->base . '/public/synthetic.pdf' ) );
		$this->refused( fn() => $this->queue()->manage( $id, 4, 0, 'retry', 'Lote arquivado não reabre.', 42 ) );
	}
	public function testTamperedOriginalNeverReachesScanner(): void {
		$id = $this->enqueue();
		$s  = $this->queue()->read( $id, 42 );
		file_put_contents( $this->base . '/queue/' . $id . '/' . $s['items'][0]['blob']['id'], "%PDF-1.4\nchanged\n" );
		$calls = 0;
		$this->queue()->work(
			42,
			1,
			$this->pipeline(
				function () use ( &$calls ): string {
					++$calls;
					return 'clean';
				}
			)
		);
		self::assertSame( 0, $calls );
		self::assertSame( 'held', $this->queue()->read( $id, 42 )['items'][0]['status'] );
	}
	public function testCorruptJournalIsNotRepairedOrProcessed(): void {
		$id   = $this->enqueue();
		$path = $this->base . '/queue/' . $id . '/journal.jsonl';
		file_put_contents( $path, '{partial', FILE_APPEND );
		$hash = hash_file( 'sha256', $path );
		$this->refused( fn() => $this->queue()->read( $id, 42 ) );
		self::assertTrue( $this->queue()->listing( 42 )[ $id ]['unavailable'] );
		self::assertSame( 0, $this->queue()->work( 42, 1, $this->pipeline() ) );
		$this->refused( fn() => $this->queue()->manage( $id, 1, 0, 'cancel', 'Não reparar um diário corrompido.', 42 ) );
		self::assertSame( $hash, hash_file( 'sha256', $path ) );
	}
	public function testWholeEventTruncationCannotUndoCancellation(): void {
		$id = $this->enqueue();
		$this->queue()->manage( $id, 1, 0, 'cancel', 'Cancelamento fictício definitivo.', 42 );
		$path  = $this->base . '/queue/' . $id . '/journal.jsonl';
		$lines = file( $path );
		file_put_contents( $path, $lines[0] );
		$this->refused( fn() => $this->queue()->read( $id, 42 ) );
		self::assertSame( 0, $this->queue()->work( 42, 1, $this->pipeline() ) );
	}
	public function testIntentWithoutJournalEventRemainsIndeterminate(): void {
		$id = $this->enqueue();
		file_put_contents( $this->base . '/queue/' . $id . '/head-000002', str_repeat( 'a', 64 ) );
		$this->refused( fn() => $this->queue()->read( $id, 42 ) );
		$this->refused( fn() => $this->queue()->manage( $id, 1, 0, 'hold', 'Não ocultar escrita interrompida.', 42 ) );
	}

	public function testRepeatedManualHoldsCannotExhaustTerminalAuditCapacity(): void {
		$id = $this->enqueue();
		$s  = $this->queue()->read( $id, 42 );
		for ( $i = 0; $i < 3; ++$i ) {
			$s = $this->queue()->manage( $id, $s['revision'], 0, 'hold', 'Retenção fictícia para revisão.', 42 );
			$s = $this->queue()->manage( $id, $s['revision'], 0, 'retry', 'Nova análise fictícia pedida.', 42 );
		}
		$this->queue()->work( 42, 1, $this->pipeline() );
		$s = $this->queue()->read( $id, 42 );
		self::assertSame( 'ready', $s['items'][0]['status'] );
		$s = $this->queue()->manage( $id, $s['revision'], 0, 'hold', str_repeat( 'á', 300 ), 42 );
		$this->refused( fn() => $this->queue()->manage( $id, $s['revision'], 0, 'retry', 'Limite de repetições atingido.', 42 ) );
		$s = $this->queue()->manage( $id, $s['revision'], 0, 'cancel', 'Cancelar sem esgotar a auditoria.', 42 );
		self::assertTrue( $this->queue()->manage( $id, $s['revision'], -1, 'archive', 'Conservar o lote arquivado.', 42 )['archived'] );
	}

	public function testMaximumUnicodeHistoryStillAllowsArchiveAndReplay(): void {
		$id     = $this->enqueue( 8 );
		$reason = str_repeat( "\u{1F600}", 300 );
		$s      = $this->queue()->read( $id, 42 );
		for ( $i = 0; $i < 8; ++$i ) {
			$s = $this->queue()->manage( $id, $s['revision'], $i, 'hold', $reason, 42 );
			$s = $this->queue()->manage( $id, $s['revision'], $i, 'retry', $reason, 42 );
			for ( $attempt = 1; $attempt <= 3; ++$attempt ) {
				$this->queue()->work( 42, 1, $this->pipeline() );
				$s = $this->queue()->read( $id, 42 );
				$s = $this->queue()->manage( $id, $s['revision'], $i, 'hold', $reason, 42 );
				$s = $this->queue()->manage( $id, $s['revision'], $i, $attempt < 3 ? 'retry' : 'cancel', $reason, 42 );
			}
		}
		$this->queue()->manage( $id, $s['revision'], -1, 'archive', $reason, 42 );
		self::assertGreaterThan( 262144, filesize( $this->base . '/queue/' . $id . '/journal.jsonl' ) );
		$s = $this->queue()->read( $id, 42 );
		self::assertTrue( $s['archived'] );
		self::assertSame( 114, $s['revision'] );
	}
	public function testMissingMarkerRefusesExistingEvent(): void {
		$id = $this->enqueue();
		unlink( $this->base . '/queue/' . $id . '/head-000001' );
		$this->refused( fn() => $this->queue()->read( $id, 42 ) );
	}

	public function testChangedAuditHashFailsClosed(): void {
		$id   = $this->enqueue();
		$path = $this->base . '/queue/' . $id . '/journal.jsonl';
		file_put_contents( $path, str_replace( '"actor":42', '"actor":43', file_get_contents( $path ) ) );
		$this->refused( fn() => $this->queue()->read( $id, 42 ) );
	}
	public function testRepeatedRequestDoesNotCreateSecondBatch(): void {
		$id = str_repeat( 'a', 32 );
		self::assertSame( $id, $this->enqueue( 1, $id ) );
		$this->refused( fn() => $this->enqueue( 1, $id ) );
		self::assertCount( 1, $this->queue()->listing( 42 ) );
	}
	public function testCapacityIncludesArchivedAndIncompleteBatches(): void {
		for ( $i = 0; $i < 50; ++$i ) {
			mkdir( $this->base . '/queue/' . sprintf( '%032x', $i ), 0700 ); }
		$this->refused( fn() => $this->enqueue() );
		self::assertCount( 50, $this->queue()->listing( 42 ) );
	}
	public function testNoAuthorityNoWorkOrDisclosure(): void {
		$id = $this->enqueue();
		$this->refused( fn() => $this->queue()->read( $id, 43 ) );
		$this->allowed = false;
		$this->refused( fn() => $this->enqueue() );
		$this->refused( fn() => $this->queue()->work( 42, 1, $this->pipeline() ) );
		$this->allowed = true;
		self::assertSame( 1, $this->queue()->read( $id, 42 )['revision'] );
	}
	public function testInvalidLimitsAndPathsAreRejected(): void {
		$this->refused( fn() => $this->queue()->read( '../public', 42 ) );
		foreach ( array( 0, 4, -1 ) as $limit ) {
			$this->refused( fn() => $this->queue()->work( 42, $limit, $this->pipeline() ) ); }
		$this->refused( fn() => new MediaQueue( $this->base . '/public', array( $this->base . '/public' ), static fn(): bool => true, fn(): int => $this->now ) );
	}
	public function testOverlargeAndUnsupportedSourcesNeverEnqueue(): void {
		file_put_contents( $this->base . '/public/large', str_repeat( 'x', 1048577 ) );
		$this->refused( fn() => $this->queue()->enqueue( array( $this->base . '/public/large' ), 42 ) );
		self::assertTrue( array_values( $this->queue()->listing( 42 ) )[0]['unavailable'] );
		file_put_contents( $this->base . '/public/text', 'synthetic unsupported input' );
		$this->refused( fn() => $this->queue()->enqueue( array( $this->base . '/public/text' ), 42 ) );
		self::assertSame( 0, $this->queue()->work( 42, 1, $this->pipeline() ) );
	}
	public function testPublicLinksAndHardLinkedJournalAreRejected(): void {
		if ( '\\' === DIRECTORY_SEPARATOR ) {
			self::markTestSkipped( 'Link creation requires host privileges on Windows.' ); }
		$id   = $this->enqueue();
		$path = $this->base . '/queue/' . $id . '/journal.jsonl';
		link( $path, $this->base . '/journal-copy' );
		$this->refused( fn() => $this->queue()->read( $id, 42 ) );
		$this->refused( fn() => $this->queue()->manage( $id, 1, 0, 'hold', 'Recusar ligação rígida no diário.', 42 ) );
	}
	public function testHttpClosedShapeNonceUploadAndRepeatedFields(): void {
		$get   = array( 'page' => 'arn-media-queue' );
		$yes   = static fn(): bool => true;
		self::assertSame( array( 'action' => 'list' ), QueueRequest::parse( 'GET', $get, array(), array(), $yes, $yes ) );
		$post  = array(
			'nonce'      => 'test',
			'action'     => 'enqueue',
			'request_id' => str_repeat( 'b', 32 ),
		);
		$files = array(
			'media' => array(
				'tmp_name' => array( '/tmp/fake' ),
				'error'    => array( 0 ),
				'size'     => array( 10 ),
				'name'     => array( 'fake.pdf' ),
				'type'     => array( 'application/pdf' ),
			),
		);
		self::assertSame( array( '/tmp/fake' ), QueueRequest::parse( 'POST', $get, $post, $files, $yes, $yes )['sources'] );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get, $post, $files, static fn(): bool => false, $yes ) );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get, $post, $files, $yes, static fn(): bool => false ) );
		$this->refused( fn() => QueueRequest::parse( 'PUT', $get, $post, $files, $yes, $yes ) );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get + array( 'extra' => '1' ), $post, $files, $yes, $yes ) );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get, $post + array( 'path' => '/tmp' ), $files, $yes, $yes ) );
		$files['media']['error'] = array( UPLOAD_ERR_PARTIAL );
		$this->refused( fn() => QueueRequest::parse( 'POST', $get, $post, $files, $yes, $yes ) );
	}
	public function testManualHttpContractRefusesArrayParametersAndExtraFiles(): void {
		$post  = array(
			'nonce'    => 'test',
			'action'   => 'hold',
			'batch'    => str_repeat( 'a', 32 ),
			'revision' => '1',
			'item'     => '0',
			'reason'   => 'Ensaio fictício de retenção.',
		);
		$parse = static fn( array $data, array $files = array() ): array => QueueRequest::parse( 'POST', array( 'page' => 'arn-media-queue' ), $data, $files, static fn(): bool => true, static fn(): bool => true );
		self::assertSame( $post, $parse( $post ) );
		$this->refused( fn() => $parse( $post, array( 'media' => array() ) ) );
		foreach ( array_keys( $post ) as $key ) {
			$invalid         = $post;
			$invalid[ $key ] = array( $post[ $key ] );
			$this->refused( fn() => $parse( $invalid ) );
		}
	}
}
