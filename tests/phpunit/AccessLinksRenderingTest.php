<?php
/** Shared home/service access links, with separate accessible labels per instance.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed
final class AccessLinksRenderingTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_shared_accesses_escape_content_keep_empty_state_and_unique_region_names(): void {
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
		$file = dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/access-links.php';
		self::assertFileExists( $file );
		$args = array( 'services' => array() );
		ob_start();
		include $file;
		$empty = (string) ob_get_clean();
		self::assertStringContainsString( 'Ainda não há acessos disponíveis para a tua conta', $empty );
		self::assertStringNotContainsString( '<a ', $empty );
		self::assertStringNotContainsString( '<button', $empty );
		self::assertStringNotContainsString( 'Tarefas de equipa', $empty );
		$args['services'] = array(
			array(
				'title'       => 'Meu perfil <script>',
				'description' => 'Descrição <b>escapada</b>',
				'url'         => '/meu-perfil/?q=%22',
				'private'     => 'private-cookie',
			),
		);
		ob_start();
		include $file;
		$html = (string) ob_get_clean();
		self::assertStringContainsString( 'Meu perfil &lt;script&gt;', $html );
		self::assertStringContainsString( 'Descrição &lt;b&gt;escapada&lt;/b&gt;', $html );
		self::assertStringContainsString( 'href="/meu-perfil/?q=%22"', $html );
		self::assertStringNotContainsString( 'private-cookie', $html );
		self::assertStringNotContainsString( '<h1', $html );
		self::assertStringContainsString( 'aria-labelledby="arn-accesses-1"', $empty );
		self::assertStringContainsString( 'id="arn-accesses-1"', $empty );
		self::assertStringContainsString( 'aria-labelledby="arn-accesses-2"', $html );
		self::assertStringContainsString( 'id="arn-accesses-2"', $html );
	}
}
