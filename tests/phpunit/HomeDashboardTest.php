<?php
/** Closed home summaries, without WordPress or private publication fields.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\EditorialPreview\HomeDashboard;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
final class HomeDashboardTest extends TestCase {
	private function destinations(): array {
		return array(
			array(
				'id'  => 'communications',
				'url' => '/?page_id=21',
			),
			array(
				'id'  => 'calendar',
				'url' => '/calendario/',
			),
		);
	}
	private function row( int $id ): array {
		return array(
			'id'           => $id,
			'title'        => 'Publicação ' . $id,
			'published_at' => 1792368000,
			'body'         => 'private body',
			'author_id'    => 9,
			'token'        => 'private-token',
			'start'        => 1792454400,
			'end'          => 1792540800,
			'all_day'      => true,
			'cancelled'    => false,
		);
	}
	public function test_unavailable_destinations_do_not_read_sources(): void {
		$reads = 0;
		$model = ( new HomeDashboard(
			static function () use ( &$reads ): array {
				++$reads;
				return array();
			}
		) )->model(
			array(
				array(
					'id'  => 'unknown',
					'url' => '/private/',
				),
			)
		);
		self::assertSame( 0, $reads );
		self::assertFalse( $model['communications']['available'] );
		self::assertSame( array(), $model['calendar']['items'] );
	}
	public function test_only_three_authorised_rows_and_closed_fields_reach_the_home(): void {
		$rows  = array_map( $this->row( ... ), range( 1, 5 ) );
		$model = ( new HomeDashboard(
			static fn(): array => array(
				'items'  => $rows,
				'total'  => 999,
				'secret' => 'private-source',
			)
		) )->model( $this->destinations() );
		self::assertCount( 3, $model['communications']['items'] );
		self::assertSame( array( 'Publicação 1', 'Publicação 2', 'Publicação 3' ), array_column( $model['communications']['items'], 'title' ) );
		self::assertSame( array( 'title', 'url', 'date' ), array_keys( $model['communications']['items'][0] ) );
		self::assertSame( '/?page_id=21&entry=1', $model['communications']['items'][0]['url'] );
		self::assertSame( '/calendario/?entry=1', $model['calendar']['items'][0]['url'] );
		self::assertStringNotContainsString( 'private', json_encode( $model, JSON_THROW_ON_ERROR ) );
		self::assertArrayNotHasKey( 'total', $model['communications'] );
		self::assertSame( '20/10/2026 · Dia inteiro', $model['calendar']['items'][0]['date'] );
	}
	public function test_each_source_failure_is_isolated_and_distinct_from_authorised_empty(): void {
		$model = ( new HomeDashboard(
			static function ( string $module ): array {
				if ( 'communications' === $module ) {
					throw new RuntimeException( 'private database session' ); }
				return array( 'items' => array() );
			}
		) )->model( $this->destinations() );
		self::assertFalse( $model['communications']['available'] );
		self::assertSame( '', $model['communications']['url'] );
		self::assertTrue( $model['calendar']['available'] );
		self::assertSame( array(), $model['calendar']['items'] );
		self::assertStringNotContainsString( 'private', json_encode( $model, JSON_THROW_ON_ERROR ) );
	}
	public function test_invalid_projection_closes_only_its_section(): void {
		foreach ( array( null, array(), array( 'items' => 'invalid' ), array( 'items' => array( array( 'id' => '1' ) ) ) ) as $invalid ) {
			$model = ( new HomeDashboard( static fn( string $module ): ?array => 'communications' === $module ? $invalid : array( 'items' => array() ) ) )->model( $this->destinations() );
			self::assertFalse( $model['communications']['available'] );
			self::assertTrue( $model['calendar']['available'] );
		}
	}
	public function test_all_day_exclusive_end_and_timed_utc_dates_are_preserved(): void {
		$rows               = array( $this->row( 1 ), $this->row( 2 ) );
		$rows[0]['end']    += 86400;
		$rows[1]['all_day'] = false;
		$model              = ( new HomeDashboard( static fn(): array => array( 'items' => $rows ) ) )->model( $this->destinations() );
		self::assertSame( '20/10/2026 a 21/10/2026 · Dias inteiros', $model['calendar']['items'][0]['date'] );
		self::assertSame( '20/10/2026 00:00 a 21/10/2026 00:00 UTC', $model['calendar']['items'][1]['date'] );
	}
}
