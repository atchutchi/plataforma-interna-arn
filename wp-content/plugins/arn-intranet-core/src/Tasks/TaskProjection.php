<?php
/** Recipient-safe task views, separate from persistence and private audit.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\F112\ActorContext;
use DomainException;

// phpcs:disable Squiz.Commenting -- The projection boundary is documented in the F1-13 plan.
/** The create token and raw audit payload never enter a browser DTO. */
final class TaskProjection {

	public static function row( Task $task, ActorContext $actor, int $now ): array {
		if ( ! TaskPolicy::read( $task, $actor, $now ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant domain message.
			throw new DomainException( 'Tarefa indisponível.' );
		}
		return array(
			'id'          => $task->id,
			'title'       => $task->content->title,
			'priority'    => $task->content->priority,
			'due_at'      => $task->content->due_at,
			'state'       => $task->state->value,
			'revision'    => $task->revision,
			'assignee_id' => $task->assignee_id,
			'author_id'   => $task->author_id,
			'unit_id'     => $task->unit_id,
			'updated_at'  => $task->updated_at,
			'overdue'     => $task->overdue( $now ),
		);
	}

	public static function detail( Task $task, ActorContext $actor, int $now ): array {
		$row     = self::row( $task, $actor, $now );
		$manager = TaskPolicy::team( $task, $actor, $now );
		$history = array();
		foreach ( $task->history as $event ) {
			$item = array(
				'revision'    => $event['revision'],
				'operation'   => $event['operation'],
				'actor_id'    => $event['actor_id'],
				'occurred_at' => $event['occurred_at'],
				'state'       => $event['after']['state'],
				'assignee_id' => $event['after']['assignee_id'],
				'due_at'      => $event['after']['content']['due_at'],
			);
			if ( $manager ) {
				$item['reason'] = $event['reason'];
			}
			$history[] = $item;
		}
		$operations = array_values( array_filter( array( 'start', 'complete', 'cancel', 'reassign', 'change_due', 'comment' ), static fn( string $operation ): bool => TaskPolicy::manage( $task, $operation, $actor, $now ) ) );
		return array_merge(
			$row,
			array(
				'description' => $task->content->description,
				'history'     => $history,
				'comments'    => $task->comments,
				'operations'  => $operations,
			)
		);
	}
}
