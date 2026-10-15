<?php
/** Composed news filters close both published and administrative sources.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\EditorialPreview;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.WP.AlternativeFunctions, Universal.Files.SeparateFunctionsFromOO.Mixed
/** A real synthetic manifest is counted at the read boundary. */
final class NewsPortalCompositionTest extends TestCase {
	/** Number of actual manifest reads in the isolated child process.
	 *
	 * @var int
	 */
	public static int $source_reads = 0;

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testInvalidPublishedFiltersPreventAdministrativeManifestReads(): void {
		function wp_get_environment_type(): string {
			return 'local'; }
		function current_user_can( string $capability ): bool {
			return 'manage_options' === $capability; }
		function file_get_contents( string $path, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null ): string|false {
			// phpcs:ignore Squiz.Classes.SelfMemberReference.NotUsed -- This is a namespaced spy function, outside the test class scope.
			++NewsPortalCompositionTest::$source_reads;
			return \file_get_contents( $path, $use_include_path, $context, $offset, $length );
		}
		function add_query_arg( array|string $args, string $value, ?string $url = null ): string {
			return ( $url ?? $value ) . '?fixture=1'; }
		function get_permalink(): string {
			return 'https://example.test/noticias/'; }
		function home_url( string $path ): string {
			return 'https://example.test' . $path; }

		$base = sys_get_temp_dir() . '/arn-news-composition-' . bin2hex( random_bytes( 6 ) );
		mkdir( $base . '/source', 0700, true );
		mkdir( $base . '/web', 0700 );
		mkdir( $base . '/content', 0700 );
		define( 'ARN_ALLOW_LOCAL_EDITORIAL_PREVIEW', true );
		define( 'ARN_LOCAL_EDITORIAL_DIR', $base . '/source' );
		define( 'ABSPATH', $base . '/web' );
		define( 'WP_CONTENT_DIR', $base . '/content' );
		$items = array();
		foreach ( array(
			'circular'             => 5,
			'nota-interna'         => 4,
			'despacho'             => 11,
			'regulamento'          => 1,
			'comunicacao-sindical' => 1,
		) as $category => $count ) {
			for ( $number = 1; $number <= $count; ++$number ) {
				$id      = $category . '-' . $number;
				$items[] = array(
					'id'           => $id,
					'title'        => 'Fonte sintética ' . $id,
					'category'     => $category,
					'date'         => '2026-10-14',
					'reference'    => 'LAB',
					'summary'      => 'Revisão administrativa',
					'body'         => array( 'Texto sintético.' ),
					'source_label' => 'Fixture',
					'file'         => $id . '.pdf',
					'sha256'       => str_repeat( 'a', 64 ),
					'format'       => 'PDF',
					'note'         => '',
				);
			}
		}
		file_put_contents(
			$base . '/source/manifest.json',
			json_encode(
				array(
					'version' => 1,
					'items'   => $items,
				),
				JSON_THROW_ON_ERROR
			)
		);
		try {
			self::assertTrue( Portal::allowed() );
			$valid = Portal::model( 'news', array() );
			self::assertSame( 1, self::$source_reads );
			self::assertSame( 21, $valid['review']['total'] );
			self::assertSame( 22, array_sum( $valid['review']['counts'] ) );
			self::assertSame( 1, $valid['review']['counts']['comunicacao-sindical'] );
			self::assertSame( 0, $valid['total'] );
			self::assertFalse( $valid['available'] );
			foreach ( array( array( 'arn_from' => array( '2026-10-14' ) ), array( 'arn_to' => array( 'x' ) ), array( 'arn_from' => '2026-02-30' ), array( 'arn_to' => '2026-13-01' ), array( 'q' => null ), array( 'category' => null ), array( 'category' => 'regulamento' ), array( 'category' => 'categoria-invalida' ) ) as $input ) {
				self::$source_reads = 0;
				$closed             = Portal::model( 'news', $input );
				self::assertSame( 0, self::$source_reads, 'Filtros recusados não podem ler o manifesto.' );
				self::assertFalse( $closed['available'] );
				self::assertSame( 0, $closed['total'] );
				self::assertArrayNotHasKey( 'review', $closed );
			}
		} finally {
			unlink( $base . '/source/manifest.json' );
			rmdir( $base . '/source' );
			rmdir( $base . '/web' );
			rmdir( $base . '/content' );
			rmdir( $base );
		}
	}
}
