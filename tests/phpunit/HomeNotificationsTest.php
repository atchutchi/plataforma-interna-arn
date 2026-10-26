<?php
/** Closed notification projection, independent source failures and receipts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\EditorialPreview\HomeNotifications;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
final class HomeNotificationsTest extends TestCase {

	private function row( int $id, int $time = 100 ): array {
		return array(
			'id'           => $id,
			'title'        => 'Aviso fictício ' . $id,
			'event'        => 'Publicação',
			'created_at'   => $time,
			'read_at'      => null,
			'url'          => '/avisos/?entry=' . $id,
			'reason'       => 'private-reason',
			'recipient_id' => 999,
		);
	}

	public function test_latest_five_are_sorted_without_private_fields_or_counts(): void {
		$source = fn( string $module ): array => array(
			'url'   => '/modulo/',
			'items' => array( $this->row( 1, 'tasks_own' === $module ? 200 : 100 ), $this->row( 2, 300 ) ),
			'total' => 999,
		);
		$model  = ( new HomeNotifications( $source ) )->model();
		self::assertTrue( $model['available'] );
		self::assertCount( 5, $model['items'] );
		self::assertSame( array( 'Acessos', 'Calendário', 'Comunicados', 'Notícias', 'Pedidos internos' ), array_column( $model['items'], 'module' ) );
		self::assertSame( array( 'title', 'url', 'module', 'event', 'date', 'read' ), array_keys( $model['items'][0] ) );
		self::assertSame( '01/01/1970 00:05 UTC', $model['items'][0]['date'] );
		self::assertStringNotContainsString( 'private', json_encode( $model, JSON_THROW_ON_ERROR ) );
		self::assertStringNotContainsString( '999', json_encode( $model, JSON_THROW_ON_ERROR ) );
	}

	public function test_unavailable_sources_are_distinct_from_authorised_empty(): void {
		$closed = ( new HomeNotifications( static fn(): ?array => null ) )->model();
		self::assertFalse( $closed['available'] );
		self::assertSame( array(), $closed['links'] );
		$empty = ( new HomeNotifications(
			static fn(): array => array(
				'url'   => '/avisos/',
				'items' => array(),
			)
		) )->model();
		self::assertTrue( $empty['available'] );
		self::assertSame( array(), $empty['items'] );
		self::assertCount( 7, $empty['links'] );
	}

	public function test_source_exception_and_invalid_row_close_only_their_source(): void {
		$model = ( new HomeNotifications(
			function ( string $module ): array {
				if ( 'news' === $module ) {
					throw new RuntimeException( 'private source details' ); }
				$row = $this->row( 1 );
				if ( 'calendar' === $module ) {
					$row['read_at'] = 99; }
				return array(
					'url'   => '/avisos/',
					'items' => array( $row ),
				);
			}
		) )->model();
		self::assertCount( 5, $model['items'] );
		self::assertCount( 5, $model['links'] );
		self::assertNotContains( 'Notícias', array_column( $model['items'], 'module' ) );
		self::assertNotContains( 'Calendário', array_column( $model['items'], 'module' ) );
		self::assertStringNotContainsString( 'private', json_encode( $model, JSON_THROW_ON_ERROR ) );
	}

	public function test_receipts_are_reflected_without_mutating_the_source(): void {
		$rows               = array( $this->row( 1 ), $this->row( 2 ) );
		$rows[1]['read_at'] = 101;
		$before             = $rows;
		$model              = ( new HomeNotifications(
			static fn( string $module ): ?array => 'tasks_own' === $module ? array(
				'url'   => '/tarefas/',
				'items' => $rows,
			) : null
		) )->model();
		self::assertSame( array( true, false ), array_column( $model['items'], 'read' ) );
		self::assertSame( $before, $rows );
	}

	public function test_duplicate_conflict_closes_the_source_but_identical_rows_are_deduplicated(): void {
		$row   = $this->row( 1 );
		$model = ( new HomeNotifications(
			static fn( string $module ): ?array => 'tasks_own' === $module ? array(
				'url'   => '/tarefas/',
				'items' => array( $row, $row ),
			) : null
		) )->model();
		self::assertCount( 1, $model['items'] );
		$other = array_replace( $row, array( 'title' => 'Outro título' ) );
		$model = ( new HomeNotifications(
			static fn(): array => array(
				'url'   => '/tarefas/',
				'items' => array( $row, $other ),
			)
		) )->model();
		self::assertFalse( $model['available'] );
		self::assertSame( array(), $model['items'] );
	}

	public function test_only_first_twenty_native_candidates_are_considered(): void {
		$rows  = array_map( fn( int $id ): array => $this->row( $id, $id ), range( 1, 21 ) );
		$model = ( new HomeNotifications(
			static fn( string $module ): ?array => 'tasks_own' === $module ? array(
				'url'   => '/tarefas/',
				'items' => $rows,
			) : null
		) )->model();
		self::assertSame( 'Aviso fictício 20', $model['items'][0]['title'] );
		self::assertCount( 5, $model['items'] );
	}

	public function test_unsafe_urls_close_the_source(): void {
		foreach ( array( '//outside.invalid/', 'javascript:alert(1)', "https://example.invalid/\nsecret", '' ) as $url ) {
			$row   = array_replace( $this->row( 1 ), array( 'url' => $url ) );
			$model = ( new HomeNotifications(
				static fn(): array => array(
					'url'   => '/safe/',
					'items' => array( $row ),
				)
			) )->model();
			self::assertFalse( $model['available'] );
		}
	}
}
