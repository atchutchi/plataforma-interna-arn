<?php
/** F1-13 immutable transitions and audit replay.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Tasks\Task;
use Arn\Intranet\Tasks\TaskContent;
use Arn\Intranet\Tasks\TaskState;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Authority is separate from a valid business transition. */
final class TaskModelTest extends TestCase {

	/** Current assignee advances the task and every change is replayable. */
	public function testLifecycleAndReplay(): void {
		$task = $this->task();
		self::assertSame( TaskState::Open, $task->state );
		self::assertFalse( $task->overdue( 100 ) );
		$started = $task->start( 7, 1, 101, 'Início.' );
		$done    = $started->complete( 7, 2, 102, 'Concluída.' );
		self::assertSame( TaskState::Open, $task->state );
		self::assertSame( TaskState::Completed, $done->state );
		self::assertSame( 3, $done->revision );
		self::assertSame( array( 'created', 'started', 'completed' ), array_column( $done->history, 'operation' ) );
		$restored = Task::restore( $done->toArray(), $done->history, $done->comments );
		self::assertSame( $done->toArray(), $restored->toArray() );
		self::assertSame( $done->history, $restored->history );
	}

	/** CAS is checked even on no-op and comments are not duplicate-safe writes. */
	public function testNoOpAndCommentsPreserveConcurrency(): void {
		$task = $this->task();
		self::assertSame( $task, $task->reassign( 7, 9, 1, 101, 'Sem alteração.' ) );
		self::assertSame( $task, $task->changeDue( null, 9, 1, 101, '' ) );
		$next = $task->comment( "Comentário\r\nseguinte", 7, 1, 101 );
		self::assertSame( 2, $next->revision );
		self::assertSame( "Comentário\nseguinte", $next->comments[0]['body'] );
		self::assertSame( '', $next->history[1]['reason'] );
		self::assertSame( $next->comments, Task::restore( $next->toArray(), $next->history, $next->comments )->comments );
		$this->expectException( DomainException::class );
		$next->comment( 'Reenvio.', 7, 1, 102 );
	}

	/** Reassignment preserves the author and removes former own-write relation. */
	public function testReassignmentDeadlineAndCancellation(): void {
		$task = $this->task()->changeDue( '2030-01-15T10:00:00Z', 9, 1, 101, 'Prazo.' );
		self::assertFalse( $task->overdue( $task->content->due_at ) );
		self::assertTrue( $task->overdue( $task->content->due_at + 1 ) );
		$next = $task->reassign( 9, 9, 2, 102, 'Reatribuição.' );
		self::assertSame( 9, $next->author_id );
		self::assertSame( 9, $next->assignee_id );
		self::assertSame( $next->toArray(), Task::restore( $next->toArray(), $next->history, array() )->toArray() );
		$cancelled = $next->cancel( 9, 3, 103, 'Cancelamento justificado.' );
		self::assertFalse( $cancelled->overdue( $task->content->due_at + 1 ) );
		self::assertSame( TaskState::Cancelled, $cancelled->state );
		self::assertSame( $cancelled->toArray(), Task::restore( $cancelled->toArray(), $cancelled->history, array() )->toArray() );
		$this->expectException( DomainException::class );
		$next->start( 7, 3, 104, '' );
	}

	/** No hidden reopening, free state editor or cancellation without a reason. */
	public function testInvalidTransitionsAndMalformedComments(): void {
		$task = $this->task();
		foreach ( array(
			static fn() => $task->complete( 7, 1, 101, '' ),
			static fn() => $task->start( 8, 1, 101, '' ),
			static fn() => $task->cancel( 9, 1, 101, '' ),
			static fn() => $task->start( 7, 1, 99, '' ),
			static fn() => $task->comment( '<b>texto</b>', 7, 1, 101 ),
			static fn() => $task->comment( str_repeat( 'á', 2001 ), 7, 1, 101 ),
			static fn() => $task->cancel( 9, 1, 101, 'Fim.' )->start( 7, 2, 102, '' ),
		) as $operation ) {
			try {
				$operation();
				self::fail( 'Mutação inválida aceite.' );
			} catch ( DomainException | InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** A valid current row cannot hide a corrupt event or comment chain. */
	public function testRejectsCorruptedHistoryAndRow(): void {
		$task                    = $this->task()->start( 7, 1, 101, '' )->comment( 'Texto.', 9, 2, 102 );
		$history                 = $task->history;
		$history[1]['actor_id']  = 8;
		$comments                = $task->comments;
		$comments[0]['actor_id'] = 7;
		foreach ( array(
			array( array_merge( $task->toArray(), array( 'assignee_id' => 8 ) ), $task->history, $task->comments ),
			array( $task->toArray(), $history, $task->comments ),
			array( $task->toArray(), $task->history, $comments ),
			array( $task->toArray(), array_slice( $task->history, 1 ), $task->comments ),
		) as $input ) {
			try {
				Task::restore( ...$input );
				self::fail( 'Cadeia corrompida aceite.' );
			} catch ( DomainException | InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** Immutable header fields must match the recorded initial ownership. */
	public function testImmutableOwnershipAndCreateIdentityAreBoundToHistory(): void {
		$task = $this->task();
		foreach ( array( array( 'unit_id' => 'LAB-B' ), array( 'create_request_id' => str_repeat( 'b', 32 ) ) ) as $change ) {
			try {
				Task::restore( array_merge( $task->toArray(), $change ), $task->history, $task->comments );
				self::fail( 'Identidade imutável adulterada aceite.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** One detached fictional aggregate. */
	public function testOpaqueTokensAndCanonicalReasonsUseStrictComparison(): void {
		$task                 = Task::create( 1, 9, 7, 'LAB-A', new TaskContent( 'Tarefa', '', 'normal', null ), 100, '0e' . str_repeat( '0', 29 ) . '1' )->start( 7, 1, 101, '0' );
		$history              = $task->history;
		$history[1]['reason'] = ' 0 ';
		foreach ( array( array( array_merge( $task->toArray(), array( 'create_request_id' => '0e' . str_repeat( '0', 29 ) . '2' ) ), $task->history ), array( $task->toArray(), $history ) ) as $input ) {
			try {
				Task::restore( $input[0], $input[1], array() );
				self::fail( 'Equivalência flexível aceite.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** A comment body must match the audit evidence for that revision. */
	public function testCorruptedCommentBodyCannotReuseItsAuditRevision(): void {
		$task                = $this->task()->comment( 'Texto original.', 7, 1, 101 );
		$comments            = $task->comments;
		$comments[0]['body'] = 'Texto adulterado.';
		$this->expectException( InvalidArgumentException::class );
		Task::restore( $task->toArray(), $task->history, $comments );
	}

	/** One detached fictional aggregate. */
	private function task(): Task {
		return Task::create( 1, 9, 7, 'LAB-A', new TaskContent( 'Parecer fictício', '', 'normal', null ), 100, str_repeat( 'a', 32 ) );
	}
}
