<?php
/** Notice links select the authorized view independently of the current page.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use Arn\Intranet\Leave\{Calendar, LeaveRequest, View};
use PHPUnit\Framework\Attributes\{RunInSeparateProcess, PreserveGlobalState};
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.WP.AlternativeFunctions
final class LeaveNoticeViewTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testBothNoticeDirectionsUseTheirOwnDestination(): void {
		function get_page_by_path( string $slug ): object {
			return (object) array( 'slug' => $slug );
		}
		function get_permalink( ?object $page = null ): string {
			return '/' . ( $page->slug ?? 'current-page' ) . '/';
		}
		function add_query_arg( string $key, int $value, string $url ): string {
			return $url . '?' . $key . '=' . $value;
		}
		function esc_url( string $url ): string {
			return $url;
		}
		$request = LeaveRequest::create( 8, 4, 'LAB-A', '2027-02-01', '2027-02-05', new Calendar( 2027, 1, array() ), strtotime( '2026-10-26 UTC' ) );
		$method  = new ReflectionMethod( View::class, 'notices' );
		foreach ( array(
			'own'  => 'minhas-ferias',
			'team' => 'gerir-ferias',
		) as $view => $slug ) {
			ob_start();
			$method->invoke(
				null,
				array(
					'notices' => array(
						array(
							'request'    => $request,
							'view'       => $view,
							'kind'       => 'submit',
							'created_at' => 10,
							'read_at'    => 11,
						),
					),
				)
			);
			$html = ob_get_clean();
			self::assertStringContainsString( '/' . $slug . '/?entry=8', $html );
		}
	}
}
