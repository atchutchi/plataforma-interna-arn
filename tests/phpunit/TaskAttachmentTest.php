<?php
/** Private attachment replay and untrusted upload boundaries.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Tasks\{Task, TaskContent, Attachment, AttachmentUpload};
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting
final class TaskAttachmentTest extends TestCase {
	private function task(): Task {
		return Task::create( 1, 9, 7, 'LAB-A', new TaskContent( 'Ensaio', '', 'normal', null ), 100, str_repeat( 'a', 32 ) ); }
	private function item( int $revision, string $id = 'b' ): Attachment {
		return new Attachment( new BlobDescriptor( str_repeat( $id, 32 ), str_repeat( 'c', 64 ), 123, 'application/pdf' ), 'Relatório sintético', $revision ); }
	public function testAppendReplayAndLaterTransitionsPreserveDescriptors(): void {
		$old = $this->task();
		self::assertArrayNotHasKey( 'attachments', $old->toArray() );
		$task     = $old->attach( $this->item( 2 ), 7, 1, 101 )->start( 7, 2, 102 )->comment( 'Ensaio', 7, 3, 103 );
		$restored = Task::restore( $task->toArray(), $task->history, $task->comments );
		self::assertSame( $task->toArray(), $restored->toArray() );
		self::assertSame( $task->attachments[0]->toArray(), $restored->attachments[0]->toArray() );
		self::assertSame( 'attached', $task->history[1]['operation'] );
		self::assertCount( 0, $old->attachments );
	}
	public function testChangedReferenceCannotPassReplay(): void {
		$task                                       = $this->task()->attach( $this->item( 2 ), 7, 1, 101 );
		$record                                     = $task->toArray();
		$record['attachments'][0]['blob']['sha256'] = str_repeat( 'd', 64 );
		$this->expectException( \InvalidArgumentException::class );
		Task::restore( $record, $task->history, $task->comments );
	}
	public function testOldEventCannotIntroduceAttachments(): void {
		$task                               = $this->task()->start( 7, 1, 101 );
		$history                            = $task->history;
		$history[1]['after']['attachments'] = array( $this->item( 2 )->toArray() );
		$this->expectException( \InvalidArgumentException::class );
		Task::restore( $task->toArray(), $history, array() );
	}
	public function testDuplicateIsRejected(): void {
		$task = $this->task()->attach( $this->item( 2 ), 7, 1, 101 );
		$this->expectException( \InvalidArgumentException::class );
		$task->attach( $this->item( 3 ), 7, 2, 102 );
	}
	public function testTerminalTaskRefusesAppend(): void {
		$task = $this->task()->cancel( 9, 1, 101, 'Fecho' );
		$this->expectException( \DomainException::class );
		$task->attach( $this->item( 3 ), 9, 2, 102 );
	}
	public function testStaleRevisionRefusesAppend(): void {
		$task = $this->task()->start( 7, 1, 101 );
		$this->expectException( \DomainException::class );
		$task->attach( $this->item( 2 ), 7, 1, 102 );
	}
	public function testEightAttachmentsLimit(): void {
		$task = $this->task();
		foreach ( range( 1, 8 ) as $n ) {
			$task = $task->attach( $this->item( $n + 1, (string) $n ), 7, $n, 100 + $n ); }
		self::assertCount( 8, Task::restore( $task->toArray(), $task->history, array() )->attachments );
		$this->expectException( \DomainException::class );
		$task->attach( $this->item( 10, '9' ), 7, 9, 110 );
	}
	public function testUploadRejectsMalformedOversizeAndLocalPath(): void {
		$file = array(
			'tmp_name' => '/tmp/synthetic',
			'name'     => 'ignored.pdf',
			'type'     => 'not-trusted',
			'size'     => 123,
			'error'    => UPLOAD_ERR_OK,
		);
		self::assertSame( '/tmp/synthetic', AttachmentUpload::source( array( 'attachment' => $file ), static fn(): bool => true ) );
		self::assertNull( AttachmentUpload::source( array( 'attachment' => $file ), static fn(): bool => false ) );
		foreach ( array( array( 'size' => 1048577 ), array( 'size' => '123' ), array( 'tmp_name' => array( '/tmp/synthetic' ) ), array( 'error' => UPLOAD_ERR_PARTIAL ), array( 'name' => array() ) ) as $bad ) {
			self::assertNull( AttachmentUpload::source( array( 'attachment' => array_replace( $file, $bad ) ), static fn(): bool => true ) );
		}
		self::assertNull(
			AttachmentUpload::source(
				array(
					'attachment' => $file,
					'other'      => $file,
				),
				static fn(): bool => true
			)
		);
	}
}
