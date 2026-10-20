<?php
/** Real HTML output of the service landing page, without booting WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed
final class ServiceRenderingTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_empty_and_authorised_service_views_have_no_fake_actions_or_private_fields(): void {
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
		function get_template_part( string $slug, ?string $name, array $args ): void {
			include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/' . $slug . '.php'; }
		$file = dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/services.php';
		self::assertFileExists( $file, 'Falta a vista funcional de serviços.' );
		$args = array( 'services' => array() );
		ob_start();
		include $file;
		$empty = (string) ob_get_clean();
		self::assertStringContainsString( 'Ainda não há acessos disponíveis para a tua conta', $empty );
		self::assertStringNotContainsString( 'Tarefas de equipa', $empty );
		self::assertStringNotContainsString( '<a ', $empty );
		self::assertStringNotContainsString( '<button', $empty );
		$args['services'] = array(
			array(
				'title'       => 'Meu perfil <script>',
				'description' => 'Texto <b>seguro</b>',
				'url'         => '/meu-perfil/?q=%22',
				'private'     => 'private-cookie',
			),
		);
		ob_start();
		include $file;
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'href="/meu-perfil/?q=%22"', $html );
		self::assertStringContainsString( 'Meu perfil &lt;script&gt;', $html );
		self::assertStringContainsString( 'Texto &lt;b&gt;seguro&lt;/b&gt;', $html );
		self::assertStringNotContainsString( 'private-cookie', $html );
		self::assertStringNotContainsString( '<h1', $html );
		self::assertStringNotContainsString( '<button', $html );
	}
}
