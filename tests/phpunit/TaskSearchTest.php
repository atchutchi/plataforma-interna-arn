<?php
/** Task search uses authorised pages and projects only a title and destination.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Search\FederatedSearch;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting -- Complete pagination and negative source scenarios.
final class TaskSearchTest extends TestCase {
	public function testTaskPagesRemainTwentyWhileSearchWindowsAreTen(): void {
		$source = static function ( string $key, object $query, int $page ): ?array {
			if ( 'tasks' !== $key ) {
				return null;
			}
			$items = array();
			$last  = min( $page * 20, 21 );
			for ( $id = ( $page - 1 ) * 20 + 1; $id <= $last; ++$id ) {
				$items[] = array(
					'title'  => 'Tarefa fictícia ' . $id,
					'url'    => 'http://localhost/minhas-tarefas/?entry=' . $id,
					'reason' => 'Não projectar motivo',
				);
			}
			return array(
				'total' => 21,
				'items' => $items,
			);
		};
		$ids    = array();
		foreach ( array(
			1 => 10,
			2 => 10,
			3 => 1,
		) as $page => $count ) {
			$model = ( new FederatedSearch( $source ) )->model(
				array(
					'q'               => 'fictícia',
					'type'            => 'tasks',
					'arn_search_page' => (string) $page,
				)
			);
			self::assertFalse( $model['invalid'] );
			self::assertSame( 21, $model['total'] );
			self::assertCount( $count, $model['items'] );
			foreach ( $model['items'] as $item ) {
				self::assertArrayNotHasKey( 'reason', $item );
				self::assertSame( 'Tarefa', $item['type'] );
				$ids[] = $item['url'];
			}
		}
		self::assertCount( 21, array_unique( $ids ) );
	}
}
