<?php
/** Selection only after task authority has narrowed the source.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use Arn\Intranet\F112\{Input as SharedInput, PlainText};
use InvalidArgumentException;

// phpcs:disable Squiz.Commenting -- Closed selection contracts are covered by transport and SQL tests.
final readonly class TaskSelection {
	private function __construct( public int $page, public string $q, public string $state, public ?int $assignee ) {}

	public static function parse( array $query ): ?self {
		if ( array_diff( array_keys( $query ), array( 'page', 'q', 'state', 'assignee' ) ) ) {
			return null;
		}
		foreach ( $query as $value ) {
			if ( ! is_string( $value ) ) {
				return null;
			}
		}
		$page     = SharedInput::id( $query['page'] ?? '1' );
		$assignee = '' === ( $query['assignee'] ?? '' ) ? null : SharedInput::id( $query['assignee'] );
		$state    = $query['state'] ?? '';
		if ( null === $page || ( '' !== ( $query['assignee'] ?? '' ) && null === $assignee ) || ( '' !== $state && null === TaskState::tryFrom( $state ) ) ) {
			return null;
		}
		try {
			if ( false !== strpbrk( $query['q'] ?? '', '<>' ) ) {
				return null;
			}
			$q = PlainText::value( $query['q'] ?? '', 120, false, false );
		} catch ( InvalidArgumentException ) {
			return null;
		}
		return new self( $page, $q, $state, $assignee );
	}

	public function apply( array $authorised ): array {
		$items = array_values( array_filter( $authorised, fn( Task $task ): bool => ( '' === $this->state || $task->state->value === $this->state ) && ( null === $this->assignee || $task->assignee_id === $this->assignee ) && ( '' === $this->q || false !== mb_stripos( $task->content->title . "\n" . $task->content->description, $this->q, 0, 'UTF-8' ) ) ) );
		usort( $items, static fn( Task $a, Task $b ): int => array( null === $a->content->due_at, $a->content->due_at ?? 0, $a->id ) <=> array( null === $b->content->due_at, $b->content->due_at ?? 0, $b->id ) );
		$total = count( $items );
		$pages = intdiv( $total, 20 ) + ( 0 !== $total % 20 ? 1 : 0 );
		return array(
			'items' => $this->page > $pages ? array() : array_slice( $items, ( $this->page - 1 ) * 20, 20 ),
			'total' => $total,
			'page'  => $this->page,
			'pages' => $pages,
		);
	}
}
