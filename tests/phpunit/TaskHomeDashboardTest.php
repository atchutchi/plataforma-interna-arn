<?php
/** Personal pending-task summary, without business writes or private DTO fields.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\EditorialPreview\HomeDashboard;
use Arn\Intranet\Tasks\{Task, TaskContent, TaskSelection};
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Pure summary contract without WordPress.
final class TaskHomeDashboardTest extends TestCase {

	private function destinations(): array {
		return array(
			array(
				'id'  => 'tasks_own',
				'url' => '/?page_id=40',
			),
		);
	}

	private function row( int $id ): array {
		return array(
			'id'          => $id,
			'title'       => 'Tarefa fictícia ' . $id,
			'state'       => 'open',
			'priority'    => 'high',
			'due_at'      => 1792368000,
			'overdue'     => true,
			'description' => 'private description',
			'comments'    => array( 'private comment' ),
			'reason'      => 'private reason',
			'assignee_id' => 7,
			'author_id'   => 9,
		);
	}

	public function test_pending_selection_excludes_terminal_states_before_pagination(): void {
		$tasks = array();
		foreach ( range( 1, 25 ) as $id ) {
			$task = Task::create( $id, 9, 7, 'LAB-A', new TaskContent( 'Título ' . $id, '', 'normal', 25 === $id ? '2030-01-15T10:00:00Z' : null ), 100, str_pad( dechex( $id ), 32, '0', STR_PAD_LEFT ) );
			if ( 21 >= $id ) {
				$task = $task->cancel( 9, 1, 101, 'Fim fictício' );
			} elseif ( 22 === $id ) {
				$task = $task->start( 7, 1, 101 )->complete( 7, 2, 102 );
			} elseif ( 23 === $id ) {
				$task = $task->start( 7, 1, 101 );
			}
			$tasks[] = $task;
		}
		$selection = TaskSelection::pending();
		$result    = $selection->apply( array_reverse( $tasks ) );
		self::assertSame( 3, $result['total'] );
		self::assertSame( array( 25, 23, 24 ), array_column( $result['items'], 'id' ) );
		self::assertSame( 1, $selection->page );
		self::assertNull( TaskSelection::parse( array( 'state' => 'pending' ) ) );
		self::assertNull( TaskSelection::parse( array( 'pending_only' => '1' ) ) );
	}

	public function test_summary_has_three_rows_and_only_safe_fields(): void {
		$model   = ( new HomeDashboard(
			fn(): array => array(
				'items' => array_map( $this->row( ... ), range( 1, 5 ) ),
				'total' => 50,
			)
		) )->model( $this->destinations() );
		$section = $model['tasks_own'];
		self::assertTrue( $section['available'] );
		self::assertCount( 3, $section['items'] );
		self::assertSame( array( 'title', 'url', 'date', 'state', 'priority', 'overdue' ), array_keys( $section['items'][0] ) );
		self::assertSame( '/?page_id=40&entry=1', $section['items'][0]['url'] );
		self::assertSame( 'Por iniciar', $section['items'][0]['state'] );
		self::assertSame( 'Alta', $section['items'][0]['priority'] );
		self::assertStringEndsWith( ' UTC', $section['items'][0]['date'] );
		self::assertStringNotContainsString( 'private', json_encode( $model, JSON_THROW_ON_ERROR ) );
		self::assertArrayNotHasKey( 'total', $section );
	}

	public function test_team_destination_does_not_enable_personal_source(): void {
		$reads = 0;
		$model = ( new HomeDashboard(
			static function () use ( &$reads ): array {
				++$reads;
				return array();
			}
		) )->model(
			array(
				array(
					'id'  => 'tasks_team',
					'url' => '/tarefas-da-equipa/',
				),
			)
		);
		self::assertSame( 0, $reads );
		self::assertFalse( $model['tasks_own']['available'] );
		self::assertSame( array(), $model['tasks_own']['items'] );
	}

	public function test_empty_personal_source_is_distinct_from_failed_source(): void {
		$empty  = ( new HomeDashboard( static fn(): array => array( 'items' => array() ) ) )->model( $this->destinations() );
		$failed = ( new HomeDashboard( static fn() => throw new RuntimeException( 'private source' ) ) )->model( $this->destinations() );
		self::assertTrue( $empty['tasks_own']['available'] );
		self::assertFalse( $failed['tasks_own']['available'] );
		self::assertSame( '', $failed['tasks_own']['url'] );
		self::assertSame( array(), $failed['tasks_own']['items'] );
	}

	public function test_invalid_task_projection_fails_closed_without_hiding_calendar(): void {
		foreach ( array( array( 'id' => '1' ), array( 'state' => 'completed' ), array( 'priority' => 'urgent' ), array( 'due_at' => '1' ), array( 'overdue' => 1 ) ) as $invalid ) {
			$destinations = array_merge(
				$this->destinations(),
				array(
					array(
						'id'  => 'calendar',
						'url' => '/calendario/',
					),
				)
			);
			$model        = ( new HomeDashboard( fn( string $module ): array => array( 'items' => 'tasks_own' === $module ? array( array_replace( $this->row( 1 ), $invalid ) ) : array() ) ) )->model( $destinations );
			self::assertFalse( $model['tasks_own']['available'] );
			self::assertTrue( $model['calendar']['available'] );
		}
	}

	public function test_no_due_date_and_in_progress_are_explicit(): void {
		$row   = array_replace(
			$this->row( 1 ),
			array(
				'due_at'  => null,
				'state'   => 'in_progress',
				'overdue' => false,
			)
		);
		$model = ( new HomeDashboard( static fn(): array => array( 'items' => array( $row ) ) ) )->model( $this->destinations() );
		self::assertSame( 'Sem prazo', $model['tasks_own']['items'][0]['date'] );
		self::assertSame( 'Em curso', $model['tasks_own']['items'][0]['state'] );
		self::assertFalse( $model['tasks_own']['items'][0]['overdue'] );
	}
}
