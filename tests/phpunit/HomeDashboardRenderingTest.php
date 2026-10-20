<?php
/** Real summary markup, with escaping and accessible empty states.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed
final class HomeDashboardRenderingTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_authorised_and_unavailable_sections_have_escaped_content_and_distinct_titles(): void {
		define( 'ABSPATH', dirname( __DIR__, 2 ) );
		function esc_html( string $value ): string {
			return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
		function esc_url( string $value ): string {
			return esc_html( $value ); }
		function esc_attr( string $value ): string {
			return esc_html( $value ); }
		function wp_unique_id( string $prefix ): string {
			static $id = 0;
			return $prefix . ( ++$id ); }
		$args = array(
			'dashboard' => array(
				'communications' => array(
					'available' => true,
					'url'       => '/?page_id=1',
					'items'     => array(
						array(
							'title'   => 'Título <script>',
							'date'    => 'Data <b>',
							'url'     => '/?page_id=1&entry=2',
							'private' => 'secret-cookie',
						),
					),
				),
				'calendar'       => array(
					'available' => false,
					'url'       => '',
					'items'     => array(),
				),
			),
		);
		ob_start();
		include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/home-dashboard.php';
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'Título &lt;script&gt;', $html );
		self::assertStringContainsString( 'Data &lt;b&gt;', $html );
		self::assertStringContainsString( '/?page_id=1&amp;entry=2', $html );
		self::assertStringContainsString( 'Todos os comunicados', $html );
		self::assertStringNotContainsString( 'Ver calendário', $html );
		self::assertStringNotContainsString( 'secret-cookie', $html );
		self::assertStringContainsString( 'Não há informação disponível para a tua conta nesta secção.', $html );
		self::assertStringNotContainsString( '<h1', $html );
		self::assertStringContainsString( 'aria-labelledby="arn-dashboard-1"', $html );
		self::assertStringContainsString( 'id="arn-dashboard-2"', $html );
		$args['dashboard']['tasks_own'] = array(
			'available' => true,
			'url'       => '/minhas-tarefas/',
			'items'     => array(
				array(
					'title'    => 'Tarefa <script>',
					'date'     => '20/10/2026 10:00 UTC',
					'url'      => '/minhas-tarefas/?entry=3',
					'state'    => 'Em curso',
					'priority' => 'Alta',
					'overdue'  => true,
					'reason'   => 'private reason',
				),
			),
		);
		ob_start();
		include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/home-dashboard.php';
		$tasks = (string) ob_get_clean();
		self::assertStringContainsString( 'Tarefas pendentes', $tasks );
		self::assertStringContainsString( 'Todas as minhas tarefas', $tasks );
		self::assertStringContainsString( 'Tarefa &lt;script&gt;', $tasks );
		self::assertStringContainsString( 'Em curso · Prioridade alta', $tasks );
		self::assertStringContainsString( 'Prazo ultrapassado', $tasks );
		self::assertStringNotContainsString( 'private reason', $tasks );
		self::assertStringNotContainsString( '<form', $tasks );
		$args['dashboard']['tasks_own']['items'] = array();
		ob_start();
		include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/home-dashboard.php';
		self::assertStringContainsString( 'Não tens tarefas pendentes neste momento.', (string) ob_get_clean() );
	}
}
