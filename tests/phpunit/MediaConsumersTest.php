<?php
/** Synthetic queue consumers. Scanner closures are not antimalware evidence.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Media\{ConsumerIntent, ConsumerJobs, ConsumerPage, MediaQueue, MediaPipeline, PreparedBatch};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
final class MediaConsumersTest extends TestCase {
	private string $base;
	private int $now      = 1800000000;
	private bool $allowed = true;
	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-consumers-' . bin2hex( random_bytes( 8 ) );
		foreach ( array( '', '/public', '/queue', '/quarantine' ) as $dir ) {
			mkdir( $this->base . $dir, 0700 ); }
		file_put_contents( $this->base . '/public/source.pdf', "%PDF-1.4\n%%EOF\n" );
	}
	protected function tearDown(): void {
		foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->base, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST ) as $entry ) {
			$entry->isDir() && ! $entry->isLink() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() ); }
		rmdir( $this->base );
	}
	private function queue( bool $owner = false ): MediaQueue {
		return new MediaQueue( $this->base . '/queue', array( $this->base . '/public', $this->base . '/quarantine' ), fn( int $id ): bool => $this->allowed && in_array( $id, array( 1, 42, 43 ), true ), fn(): int => $this->now, $owner );
	}
	private function intent(): array {
		return ConsumerJobs::intent(
			'tasks',
			7,
			3,
			'Inclusão fictícia de anexo',
			array(
				'label' => 'Documento fictício',
				'view'  => 'own',
			)
		); }
	private function enqueue( ?array $intent = null ): string {
		return $this->queue( true )->enqueue( array( $this->base . '/public/source.pdf' ), 42, null, $intent ?? $this->intent() ); }
	private function ready( string $id ): array {
		$this->queue()->work( 1, 3, fn( int $id ): MediaPipeline => new MediaPipeline( $this->base . '/quarantine', array( $this->base . '/public', $this->base . '/queue' ), static fn(): string => 'clean', static function (): void {}, $id ) );
		return $this->queue( true )->read( $id, 42 );
	}
	private function prepared( array $state ): PreparedBatch {
		return new PreparedBatch( $state, $this->base . '/quarantine', array( $this->base . '/public', $this->base . '/queue' ), $this->now ); }
	private function refused( callable $call ): void {
		try {
			$call();
		} catch ( RuntimeException | InvalidArgumentException | TypeError ) {
			$this->addToAssertionCount( 1 );
			return;
		} self::fail( 'Expected refusal.' ); }

	public function testOwnerOnlyListingReadAndMutationDoNotDiscloseOtherBatches(): void {
		$id = $this->enqueue();
		self::assertSame( array(), $this->queue( true )->listing( 43 ) );
		self::assertCount( 1, $this->queue()->listing( 1 ) );
		$this->refused( fn() => $this->queue( true )->read( $id, 43 ) );
		$this->refused( fn() => $this->queue( true )->manage( $id, 1, 0, 'cancel', 'Pedido de outra pessoa', 43 ) );
		$this->refused( fn() => $this->queue()->complete( $id, 1, 1, static function (): void {}, static fn(): int => 99 ) );
		self::assertSame( 1, $this->queue()->read( $id, 42 )['revision'] );
	}
	public function testCompletionRequiresReadyAndCurrentRevision(): void {
		$id     = $this->enqueue();
		$writes = 0;
		$write  = static function () use ( &$writes ): int {
			++$writes;
			return 99;
		};
		$this->refused( fn() => $this->queue( true )->complete( $id, 1, 42, static function (): void {}, $write ) );
		$s = $this->ready( $id );
		$this->refused( fn() => $this->queue( true )->complete( $id, 1, 42, static function (): void {}, $write ) );
		self::assertSame( 0, $writes );
		self::assertSame( 99, $this->queue( true )->complete( $id, $s['revision'], 42, static function (): void {}, $write ) );
		self::assertSame( 99, $this->queue( true )->complete( $id, $s['revision'], 42, static function (): void {}, $write ) );
		self::assertSame( 1, $writes );
		$s = $this->queue()->read( $id, 42 );
		self::assertSame( 'applied', $s['consumption'] );
		self::assertSame( 99, $s['receipt'] );
		self::assertSame( array( 42, 1, 1, 42, 42 ), array_column( $s['history'], 'actor' ) );
		$this->refused( fn() => $this->queue()->manage( $id, $s['revision'], 0, 'retry', 'Não repetir um envio concluído', 1 ) );
	}
	public function testUncertainBusinessResultIsNeverRepeatedOrRecovered(): void {
		$id     = $this->enqueue();
		$s      = $this->ready( $id );
		$writes = 0;
		$write  = static function () use ( &$writes ): int {
			++$writes;
			throw new RuntimeException( 'Lost response after commit' );
		};
		$this->refused( fn() => $this->queue()->complete( $id, $s['revision'], 42, static function (): void {}, $write ) );
		$s = $this->queue()->read( $id, 42 );
		self::assertSame( 'applying', $s['consumption'] );
		$this->refused( fn() => $this->queue()->complete( $id, $s['revision'], 42, static function (): void {}, $write ) );
		foreach ( array( 'hold', 'cancel', 'recover', 'retry', 'archive' ) as $action ) {
			$this->refused( fn() => $this->queue()->manage( $id, $s['revision'], 'archive' === $action ? -1 : 0, $action, 'Não alterar resultado indeterminado', 1 ) ); }
		self::assertSame( 1, $writes );
	}
	public function testPreflightRevocationAndExpiredAnalysisDoNotConsume(): void {
		$id = $this->enqueue();
		$s  = $this->ready( $id );
		$this->refused(
			fn() => $this->queue()->complete(
				$id,
				$s['revision'],
				42,
				static function (): void {
				throw new RuntimeException( 'Revoked' );
				},
				static fn(): int => 9
			)
		);
		self::assertSame( $s, $this->queue()->read( $id, 42 ) );
		$this->now += 86401;
		$this->refused( fn() => $this->queue()->complete( $id, $s['revision'], 42, fn() => $this->prepared( $s ), static fn(): int => 9 ) );
		self::assertSame( $s, $this->queue()->read( $id, 42 ) );
	}
	public function testWorkerAndManualActionsCannotRaceCompletion(): void {
		$id = $this->enqueue();
		$s  = $this->ready( $id );
		$this->queue()->complete(
			$id,
			$s['revision'],
			42,
			static function (): void {},
			function () use ( $id ): int {
				$s = $this->queue()->read( $id, 42 );
				$this->refused( fn() => $this->queue()->manage( $id, $s['revision'], 0, 'hold', 'Concorrência fictícia', 1 ) );
				$this->refused( fn() => $this->queue()->complete( $id, $s['revision'], 42, static function (): void {}, static fn(): int => 4 ) );
				return 9;
			}
		);
		self::assertSame( 9, $this->queue()->read( $id, 42 )['receipt'] );
	}
	public function testRevocationAfterBusinessWriteLeavesUnknownAndDoesNotReplay(): void {
		$id = $this->enqueue();
		$s  = $this->ready( $id );
		$this->refused(
			fn() => $this->queue()->complete(
				$id,
				$s['revision'],
				42,
				static function (): void {},
				function (): int {
					$this->allowed = false;
					return 4;
				}
			)
		);
		$this->allowed = true;
		self::assertSame( 'applying', $this->queue()->read( $id, 42 )['consumption'] );
	}
	public function testReplayStillRevalidatesAccess(): void {
		$id = $this->enqueue();
		$s  = $this->ready( $id );
		$this->queue()->complete( $id, $s['revision'], 42, static function (): void {}, static fn(): int => 9 );
		$this->refused(
			fn() => $this->queue()->complete(
				$id,
				$s['revision'],
				42,
				static function (): void {
				throw new RuntimeException( 'Permission lost' );
				},
				static fn(): int => 10
			)
		);
		self::assertSame( 9, $this->queue()->read( $id, 42 )['receipt'] );
	}
	public function testPreparedResultsRequireExactBytesAndCannotBeReused(): void {
		$id = $this->enqueue();
		$s  = $this->ready( $id );
		$p  = $this->prepared( $s );
		file_put_contents( $this->base . '/public/other.pdf', "%PDF-1.4\nother\n%%EOF" );
		$this->refused( fn() => $p->prepare( $this->base . '/public/other.pdf' ) );
		$result = $p->prepare( $this->base . '/public/source.pdf' );
		self::assertSame( $s['items'][0]['blob']['sha256'], $result['hashes']['original'] );
		$this->refused( fn() => $p->prepare( $this->base . '/public/source.pdf' ) );
	}
	public function testPreparedResultsRejectChangesAfterInitialVerification(): void {
		$id = $this->enqueue();
		$s  = $this->ready( $id );
		$p  = $this->prepared( $s );
		file_put_contents( $this->base . '/quarantine/' . $s['items'][0]['result']['attempt'] . '/source', 'changed' );
		$this->refused( fn() => $p->prepare( $this->base . '/public/source.pdf' ) );
		$this->refused( fn() => $this->prepared( $s ) );
	}
	public function testIntentIsClosedAndRejectsPathsExtraFieldsAndOversize(): void {
		$c = $this->intent();
		ConsumerIntent::validate( $c, 1 );
		$this->refused( fn() => ConsumerIntent::validate( $c + array( 'actor' => 43 ), 1 ) );
		$c['payload']['path'] = '/private/source';
		$this->refused( fn() => $this->enqueue( $c ) );
		$c              = $this->intent();
		$c['operation'] = 'publish';
		$this->refused( fn() => $this->enqueue( $c ) );
		$c             = $this->intent();
		$c['expected'] = '3';
		$this->refused( fn() => $this->enqueue( $c ) );
		self::assertCount( 0, $this->queue()->listing( 42 ) );
	}
	public function testNewsUnicodeContextRoundtripsAndRejectsUnownedRetainedSource(): void {
		$c  = ConsumerJobs::intent(
			'news',
			0,
			0,
			'Ensaio editorial fictício',
			array(
				'title'    => 'Notícia fictícia',
				'body'     => str_repeat( 'á', 9000 ),
				'category' => 'circular',
				'audience' => array(
					'kind'    => 'all',
					'targets' => array(),
				),
				'media'    => array(
					array(
						'kind'        => 'attachment',
						'existing'    => null,
						'description' => 'Documento fictício',
					),
				),
			)
		);
		$id = $this->enqueue( $c );
		self::assertSame( $c, $this->queue()->read( $id, 42 )['context'] );
		$c['payload']['media'][0]['existing'] = str_repeat( 'a', 32 );
		$this->refused( fn() => $this->enqueue( $c ) );
		$c['payload']['media'][0]['existing'] = null;
		$c['payload']['body']                 = str_repeat( 'á', 70000 );
		$this->refused( fn() => $this->enqueue( $c ) );
	}
	public function testDocumentMetadataRoundtripsAndCannotPublishOrRetainOnCreate(): void {
		$m  = new \Arn\Intranet\Documents\Catalog\Metadata( 'Regulamento fictício', '', 'regulamento', 'LAB-1', 'Unidade fictícia A', '2026-12-31', null, null, array( 42 ) );
		$c  = ConsumerJobs::intent(
			'documents',
			0,
			0,
			'Ensaio documental fictício',
			array(
				'metadata' => get_object_vars( $m ),
				'retained' => false,
			)
		);
		$id = $this->enqueue( $c );
		self::assertSame( $c, $this->queue()->read( $id, 42 )['context'] );
		$c['payload']['retained'] = true;
		$this->refused( fn() => $this->enqueue( $c ) );
	}
	public function testConsumerHttpShapeRequiresExactOwnerNonceAndNoUploadsOrPaths(): void {
		$id     = str_repeat( 'a', 32 );
		$get    = array( 'arn_media_job' => $id );
		$post   = array(
			'revision' => '3',
			'nonce'    => 'fixture',
		);
		$verify = static fn( $n, $i, $r ): bool => 'fixture' === $n && $id === $i && '3' === $r;
		self::assertTrue( ConsumerPage::shape( 'GET', $get, array(), array(), $verify ) );
		self::assertTrue( ConsumerPage::shape( 'HEAD', $get, array(), array(), $verify ) );
		self::assertTrue( ConsumerPage::shape( 'POST', $get, $post, array(), $verify ) );
		self::assertFalse( ConsumerPage::shape( 'POST', $get, $post, array(), static fn(): bool => false ) );
		self::assertFalse( ConsumerPage::shape( 'POST', $get, $post + array( 'actor' => '43' ), array(), $verify ) );
		self::assertFalse( ConsumerPage::shape( 'POST', $get + array( 'path' => '/tmp/x' ), $post, array(), $verify ) );
		self::assertFalse( ConsumerPage::shape( 'GET', $get, $post, array(), $verify ) );
		self::assertFalse( ConsumerPage::shape( 'POST', $get, $post, array( 'upload' => array() ), $verify ) );
	}
}
