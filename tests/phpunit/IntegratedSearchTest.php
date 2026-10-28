<?php
/** Federated pagination and closed, authorised result projection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Search\Query;
use Arn\Intranet\Search\FederatedSearch;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
final class IntegratedSearchTest extends TestCase {
	private function provider( array $sizes, array &$calls ): Closure {
		return static function ( string $source, Query $query, int $page ) use ( $sizes, &$calls ): ?array {
			$calls[] = array( $source, $query->q, $page );
			if ( ! isset( $sizes[ $source ] ) ) {
				return null;
			}
			$size = FederatedSearch::SOURCES[ $source ]['size'];
			$all  = array();
			for ( $i = 1; $i <= $sizes[ $source ]; ++$i ) {
				$all[] = array(
					'title'    => $source . ' ' . $i,
					'url'      => '/detail/' . $source . '/' . $i,
					'type'     => 'untrusted',
					'body'     => 'private-body',
					'revision' => 19,
				);
			}
			return array(
				'items' => array_slice( $all, ( $page - 1 ) * $size, $size ),
				'total' => count( $all ),
			);
		};
	}

	public function test_empty_or_invalid_input_never_opens_a_source(): void {
		$calls  = array();
		$search = new FederatedSearch( $this->provider( array( 'news' => 1 ), $calls ) );
		self::assertFalse( $search->model( array() )['searched'] );
		foreach ( array(
			array( 'q' => array( 'secret' ) ),
			array( 'q' => "bad\n" ),
			array( 'q' => '<script>' ),
			array( 'q' => "\xff" ),
			array( 'q' => str_repeat( 'x', 101 ) ),
			array(
				'q'    => 'x',
				'type' => 'drafts',
			),
			array(
				'q'    => 'x',
				'unit' => 'LAB-B',
			),
			array(
				'q'               => 'x',
				'arn_search_page' => '01',
			),
			array(
				'q'          => 'x',
				'arn_search' => array(),
			),
			array(
				'q'       => 'x',
				'page_id' => '0',
			),
		) as $input ) {
			self::assertTrue( $search->model( $input )['invalid'] );
		}
		self::assertSame( array(), $calls );
	}

	public function test_pagination_crosses_sources_and_projects_only_closed_fields(): void {
		$calls  = array();
		$search = new FederatedSearch(
			$this->provider(
				array(
					'directory'      => 7,
					'news'           => 15,
					'communications' => 25,
				),
				$calls
			)
		);
		$model  = $search->model(
			array(
				'q'               => '  São %_  ',
				'arn_search_page' => '2',
			)
		);
		self::assertSame( 47, $model['total'] );
		self::assertSame( 5, $model['pages'] );
		self::assertSame( range( 4, 13 ), array_map( static fn( array $row ): int => (int) substr( $row['title'], 5 ), $model['items'] ) );
		self::assertSame( array( 'title', 'url', 'type' ), array_keys( $model['items'][0] ) );
		self::assertSame( 'Notícia', $model['items'][0]['type'] );
		self::assertStringNotContainsString( 'private-body', json_encode( $model ) );
		self::assertContains( array( 'news', 'São %_', 2 ), $calls );
		self::assertContains( array( 'tasks', 'São %_', 1 ), $calls );
		self::assertCount( 12, $calls );
		$model = $search->model(
			array(
				'q'               => 'x',
				'arn_search_page' => '999999',
			)
		);
		self::assertSame( 47, $model['total'] );
		self::assertSame( array(), $model['items'] );
	}

	public function test_type_filter_avoids_other_sources_and_keeps_all_calendar_states(): void {
		$calls  = array();
		$search = new FederatedSearch(
			$this->provider(
				array(
					'calendar'           => 1,
					'calendar_history'   => 1,
					'calendar_cancelled' => 1,
					'news'               => 6,
				),
				$calls
			)
		);
		$model  = $search->model(
			array(
				'q'    => 'x',
				'type' => 'calendar',
			)
		);
		self::assertSame( array( 'Evento', 'Evento passado', 'Evento cancelado' ), array_column( $model['items'], 'type' ) );
		self::assertSame( 3, $model['total'] );
		self::assertSame( array( 'calendar', 'calendar_history', 'calendar_cancelled' ), array_column( $calls, 0 ) );
	}

	public function test_initial_failure_or_malformed_url_exposes_no_source_payload(): void {
		$search = new FederatedSearch(
			static function ( string $source ): ?array {
				if ( 'news' === $source ) {
					throw new RuntimeException( 'private-database-error' );
				}
				return array(
					'total' => 1,
					'items' => array(
						array(
							'title' => 'secret-title',
							'url'   => 'javascript:alert(1)',
						),
					),
				);
			}
		);
		$model  = $search->model( array( 'q' => 'x' ) );
		self::assertSame( 0, $model['total'] );
		self::assertSame( array(), $model['items'] );
		self::assertStringNotContainsString( 'secret-title', json_encode( $model ) );
		self::assertStringNotContainsString( 'private-database-error', json_encode( $model ) );
	}

	public function test_later_page_failure_recalculates_counts_and_page_without_the_failed_source(): void {
		$calls  = array();
		$base   = $this->provider(
			array(
				'news'           => 15,
				'communications' => 20,
			),
			$calls
		);
		$search = new FederatedSearch( static fn( string $source, Query $query, int $page ): ?array => 'news' === $source && 2 === $page ? null : $base( $source, $query, $page ) );
		$model  = $search->model(
			array(
				'q'               => 'x',
				'arn_search_page' => '2',
			)
		);
		self::assertTrue( $model['partial'] );
		self::assertSame( 20, $model['total'] );
		self::assertSame( 2, $model['pages'] );
		self::assertSame( 'communications 11', $model['items'][0]['title'] );
		self::assertCount( 10, $model['items'] );
	}

	public function test_changed_total_is_refused_instead_of_combining_two_snapshots(): void {
		$calls  = array();
		$base   = $this->provider( array( 'news' => 15 ), $calls );
		$search = new FederatedSearch(
			static function ( string $source, Query $query, int $page ) use ( $base ): ?array {
				$row = $base( $source, $query, $page );
				if ( 2 === $page && null !== $row ) {
					$row['total'] = 16; }
				return $row;
			}
		);
		$model  = $search->model(
			array(
				'q'               => 'x',
				'arn_search_page' => '2',
			)
		);
		self::assertSame( 0, $model['total'] );
		self::assertTrue( $model['partial'] );
		self::assertSame( array(), $model['items'] );
	}

	public function test_large_document_page_uses_the_same_canonical_range_as_the_native_source(): void {
		$calls  = array();
		$search = new FederatedSearch( $this->provider( array( 'documents' => 10010 ), $calls ) );
		$model  = $search->model(
			array(
				'q'               => 'x',
				'type'            => 'documents',
				'arn_search_page' => '1000',
			)
		);
		self::assertSame( 10010, $model['total'] );
		self::assertSame( 'documents 9991', $model['items'][0]['title'] );
		self::assertContains( array( 'documents', 'x', 1000 ), $calls );
	}
}
