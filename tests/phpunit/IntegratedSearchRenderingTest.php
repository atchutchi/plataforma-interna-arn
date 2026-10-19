<?php
/** Actual search markup, plain permalinks, escaping and independent states.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.Security.EscapeOutput, WordPress.WP.AlternativeFunctions
final class IntegratedSearchRenderingTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_real_view_has_escaped_results_safe_pagination_and_empty_recovery(): void {
		define( 'ABSPATH', dirname( __DIR__, 2 ) );
		function esc_html( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
		function esc_attr( string $text ): string {
			return esc_html( $text ); }
		function esc_url( string $text ): string {
			return esc_html( $text ); }
		function get_permalink(): string {
			return '/?page_id=1'; }
		function home_url( string $path ): string {
			return $path; }
		function selected( string $value, string $other ): void {
			if ( $value === $other ) {
				echo 'selected="selected"'; } }
		function arn_intranet_portal_form_route( string $url ): void {
			echo '<input type="hidden" name="page_id" value="1">'; }
		function add_query_arg( array|string $key, mixed $value = null, string $url = '' ): string {
			$query = is_array( $key ) ? $key : array( $key => $value );
			return '/?page_id=1&' . http_build_query( $query );
		}
		$render = static function ( array $model ): string {
			$args = array( 'search' => $model );
			ob_start();
			include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/search.php';
			return (string) ob_get_clean();
		};
		$model  = array(
			'invalid'  => false,
			'searched' => true,
			'partial'  => false,
			'query'    => "São ' &",
			'type'     => 'news',
			'page'     => 2,
			'pages'    => 3,
			'total'    => 25,
			'items'    => array(
				array(
					'title'  => 'Título <script>',
					'url'    => '/?arn_news=2&safe=1',
					'type'   => 'Notícia',
					'secret' => 'private-source',
				),
			),
		);
		$html   = $render( $model );
		self::assertStringContainsString( 'Título &lt;script&gt;', $html );
		self::assertStringNotContainsString( 'private-source', $html );
		self::assertSame( 1, substr_count( $html, '<h1>' ) );
		self::assertStringContainsString( 'name="page_id" value="1"', $html );
		self::assertStringContainsString( 'aria-label="Paginação da pesquisa"', $html );
		self::assertStringContainsString( 'arn_search_page=3', $html );
		self::assertStringContainsString( 'type=news', $html );
		self::assertStringContainsString( 'value="news" selected="selected"', $html );
		self::assertStringContainsString( '25 resultados disponíveis', $html );
		$model['items'] = array();
		self::assertStringContainsString( 'Voltar à primeira página', $render( $model ) );
		$model['searched'] = false;
		self::assertStringContainsString( 'Começa por uma palavra', $render( $model ) );
		$model['invalid'] = true;
		self::assertStringContainsString( 'Verifica a pesquisa', $render( $model ) );
	}
}
