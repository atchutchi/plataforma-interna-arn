<?php
/** Authorised links only, without private fields or invented destinations.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\EditorialPreview\ServiceDirectory;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting
final class ServiceDirectoryTest extends TestCase {
	private function directory( Closure $people, Closure $communications ): object {
		self::assertTrue( class_exists( ServiceDirectory::class ), 'Falta o catálogo de acessos autorizados.' );
		return new ServiceDirectory( $people, $communications );
	}

	public function test_no_authorised_destinations_produces_an_empty_list(): void {
		$source = $this->directory( static fn(): array => array(), static fn(): array => array() );
		self::assertSame( array(), $source->items() );
	}

	public function test_only_known_destinations_and_closed_copy_reach_the_view(): void {
		$source = $this->directory(
			static fn(): array => array(
				'profile' => array(
					'url'   => '/meu-perfil/',
					'label' => 'Private actor',
					'email' => 'private@example.invalid',
				),
				'unknown' => array( 'url' => '/private/' ),
			),
			static fn(): array => array( 'calendar' => array( 'url' => '/?page_id=9' ) )
		);
		$items  = $source->items();
		self::assertSame( array( 'profile', 'calendar' ), array_column( $items, 'id' ) );
		self::assertSame( array( 'id', 'title', 'description', 'url' ), array_keys( $items[0] ) );
		self::assertSame( 'Meu perfil', $items[0]['title'] );
		self::assertSame( '/?page_id=9', $items[1]['url'] );
		self::assertStringNotContainsString( 'private', json_encode( $items, JSON_THROW_ON_ERROR ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Teste puro sem WordPress.
	}

	public function test_one_failed_source_does_not_expose_its_error_or_hide_another_authorised_source(): void {
		$source = $this->directory(
			static function (): never {
				throw new RuntimeException( 'private SQL cookie' ); },
			static fn(): array => array( 'communications' => array( 'url' => '/comunicados/' ) )
		);
		self::assertSame( array( 'communications' ), array_column( $source->items(), 'id' ) );
	}

	public function test_unsafe_or_invalid_destinations_never_become_links(): void {
		$source = $this->directory(
			static fn(): array => array(
				'directory' => array( 'url' => 'javascript:alert(1)' ),
				'profile'   => array( 'url' => '//external.example.invalid/' ),
				'team'      => array( 'url' => "/team/\nprivate" ),
			),
			static fn(): array => array( 'calendar' => array( 'url' => array( '/invalid/' ) ) )
		);
		self::assertSame( array(), $source->items() );
	}
}
