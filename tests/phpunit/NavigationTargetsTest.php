<?php
/**
 * Local navigation destination recognition without WordPress or network.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\EditorialPreview\NavigationTargets;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting
final class NavigationTargetsTest extends TestCase {
	public function test_module_paths_query_routes_fragments_and_site_base_are_recognised(): void {
		self::assertTrue( class_exists( NavigationTargets::class ) );
		$targets = new NavigationTargets(
			'http://localhost:8889/intranet/',
			array(
				'profile' => 12,
				'team'    => 13,
			)
		);
		foreach ( array(
			'/intranet/meu-perfil/'                  => 'profile',
			'http://localhost:8889/intranet/meu-perfil/?tab=contacto#dados' => 'profile',
			'//localhost:8889/intranet/index.php/minha-equipa/' => 'team',
			'/intranet/%6dinha-equipa/'              => 'team',
			'/intranet/./minha-equipa/'              => 'team',
			'/intranet/../intranet/minha-equipa/'    => 'team',
			'/intranet/%2e/minha-equipa/'            => 'team',
			'/intranet/.?page_id=13'                 => 'team',
			'/intranet/pasta/..?page_id=13'          => 'team',
			'?page_id=12&tab=contacto'               => 'profile',
			'/intranet/?pagename=calendario#eventos' => 'calendar',
		) as $url => $expected ) {
			self::assertSame( $expected, $targets->identify( array( 'url' => $url ) ) );
		}
	}

	public function test_external_urls_unrelated_children_and_invalid_inputs_are_preserved(): void {
		self::assertTrue( class_exists( NavigationTargets::class ) );
		$targets = new NavigationTargets( 'http://localhost:8889/intranet/', array( 'profile' => 12 ) );
		foreach ( array( 'https://other.example.invalid/intranet/meu-perfil/', 'http://localhost:8888/intranet/meu-perfil/', '/intranet/departamento/calendario/', '/intranet-extra/meu-perfil/', '/meu-perfil/', '#contactos', 'mailto:fixture@example.invalid', '/intranet/?page_id[]=12', '/intranet/?pagename[]=meu-perfil', '/intranet/?page_id=987', '', array() ) as $url ) {
			self::assertNull( $targets->identify( array( 'url' => $url ) ) );
		}
		self::assertNull(
			$targets->identify(
				array(
					'url'  => 'https://other.example.invalid/',
					'id'   => 12,
					'type' => 'page',
				)
			)
		);
	}

	public function test_native_page_id_without_url_is_recognised_but_custom_ids_are_not(): void {
		self::assertTrue( class_exists( NavigationTargets::class ) );
		$targets = new NavigationTargets( 'http://localhost:8889/', array( 'profile' => 12 ) );
		self::assertSame(
			'profile',
			$targets->identify(
				array(
					'id'   => 12,
					'type' => 'page',
				)
			)
		);
		self::assertNull(
			$targets->identify(
				array(
					'id'   => 12,
					'kind' => 'custom',
				)
			)
		);
	}
}
