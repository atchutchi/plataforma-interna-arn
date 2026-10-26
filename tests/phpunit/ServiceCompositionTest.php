<?php
/** Minimal service summaries and closed search composition.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\EditorialPreview\{ServiceSelection, HomeDashboard, HomeNotifications};
use Arn\Intranet\Search\{Query, FederatedSearch};
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions
final class ServiceCompositionTest extends TestCase {
	private function row( int $id, bool $pending = true, string $view = 'own' ): array {
		return array(
			'id'         => $id,
			'title'      => 'Assunto ' . $id,
			'reference'  => 'LAB-' . $id,
			'state'      => 'Novo',
			'updated_at' => 100 + $id,
			'view'       => $view,
			'pending'    => $pending,
			'body'       => 'private-body',
			'token'      => 'private-token',
		);
	}
	public function testHomeFiltersBeforePaginationAndNeverIncludesTeamOrPrivateFields(): void {
		$rows = array( $this->row( 1 ), $this->row( 2 ), $this->row( 3 ), $this->row( 4 ), $this->row( 40, true, 'team' ) );
		foreach ( range( 10, 35 ) as $id ) {
			$rows[] = $this->row( $id, false ); }
		$list = ServiceSelection::select( $rows, 'home', '', 1 );
		self::assertSame( array( 4, 3, 2 ), array_column( $list['items'], 'id' ) );
		self::assertSame( 4, $list['total'] );
		self::assertStringNotContainsString( 'private', json_encode( $list, JSON_THROW_ON_ERROR ) );
		self::assertArrayNotHasKey( 'pending', $list['items'][0] );
	}
	public function testSearchFiltersReferencesAndPaginatesMinimalRows(): void {
		$rows = array_map( $this->row( ... ), range( 1, 25 ) );
		$list = ServiceSelection::select( $rows, 'search', 'Assunto', 2 );
		self::assertSame( array( 5, 4, 3, 2, 1 ), array_column( $list['items'], 'id' ) );
		self::assertSame( 25, $list['total'] );
		self::assertSame( array( 25 ), array_column( ServiceSelection::select( $rows, 'search', 'LAB-25', 1 )['items'], 'id' ) );
		self::assertFalse( ServiceSelection::valid( 'team', '', 1 ) );
		self::assertFalse( ServiceSelection::valid( 'search', '', 0 ) );
	}
	public function testHomeOnlyReadsAuthorizedServiceAndProjectsNoCountOrBody(): void {
		$calls = array();
		$model = ( new HomeDashboard(
			function ( string $module ) use ( &$calls ): array {
				$calls[] = $module;
				return array(
					'items' => array( $this->row( 1 ) ),
					'total' => 999,
				);
			}
		) )->model(
			array(
				array(
					'id'  => 'tickets_own',
					'url' => '/meus-tickets/',
				),
			)
		);
		self::assertSame( array( 'tickets_own' ), $calls );
		self::assertFalse( $model['requests_own']['available'] );
		self::assertSame( '/meus-tickets/?entry=1', $model['tickets_own']['items'][0]['url'] );
		self::assertStringNotContainsString( 'private', json_encode( $model, JSON_THROW_ON_ERROR ) );
		self::assertArrayNotHasKey( 'total', $model['tickets_own'] );
	}
	public function testBothServiceSearchTypesUseIndependentSources(): void {
		foreach ( array( 'requests', 'tickets' ) as $type ) {
			$calls = array();
			$model = ( new FederatedSearch(
				static function ( string $source ) use ( &$calls ): array {
					$calls[] = $source;
					return array(
						'items' => array(),
						'total' => 0,
					);
				}
			) )->model(
				array(
					'q'    => 'Fictício',
					'type' => $type,
				)
			);
			self::assertSame( array( $type ), $calls );
			self::assertFalse( $model['invalid'] );
			self::assertSame( $type, Query::parse( array( 'type' => $type ) )->type );
		}
	}
}
