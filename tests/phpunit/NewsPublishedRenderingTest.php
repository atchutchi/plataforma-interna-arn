<?php
/** Published page rendering with HTML escaping and private assets.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.OutputNotEscaped, Universal.Files.SeparateFunctionsFromOO.Mixed
/** Assert the delivered reading surface rather than template source text. */
final class NewsPublishedRenderingTest extends TestCase {
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testEditorialPreviewUsesNeutralStatusForPublishedAndDraftVersions(): void {
		define( 'ABSPATH', dirname( __DIR__, 2 ) );
		function esc_html( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
		function esc_attr( string $text ): string {
			return esc_html( $text ); }
		function esc_url( string $text ): string {
			return esc_html( $text ); }
		function arn_intranet_portal_url( string $slug ): string {
			return '/' . $slug . '/'; }
		$file = dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/news-preview.php';
		foreach ( array( 'Publicada', 'Rascunho' ) as $state ) {
			$args = array(
				'news' => array(
					'state'       => $state,
					'version'     => 1,
					'title'       => 'Versão pública',
					'body'        => 'Corpo autorizado',
					'category'    => 'Circular',
					'featured'    => null,
					'gallery'     => array(),
					'attachments' => array(),
					'cms_url'     => '/wp-admin/admin.php?page=arn-newsroom',
					'reason'      => 'Motivo privado',
					'revision'    => 9,
				),
			);
			ob_start();
			include $file;
			$html = (string) ob_get_clean();
			self::assertStringContainsString( 'Pré-visualização: ' . $state, $html );
			self::assertStringContainsString( 'Versão 1. Vista editorial da versão indicada.', $html );
			self::assertStringContainsString( 'Corpo autorizado', $html );
			self::assertStringNotContainsString( 'Esta notícia ainda não foi publicada', $html );
			self::assertStringNotContainsString( 'Motivo privado', $html );
			self::assertStringNotContainsString( 'revision', $html );
		}
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function testDetailEscapesApprovedTextAndShowsBylineAudienceAndPrivateMedia(): void {
		define( 'ABSPATH', dirname( __DIR__, 2 ) );
		function esc_html( string $text ): string {
			return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
		function esc_attr( string $text ): string {
			return esc_html( $text ); }
		function esc_url( string $text ): string {
			return esc_html( $text ); }
		function arn_intranet_portal_url( string $slug ): string {
			return '/' . $slug . '/'; }
		function wp_date( string $format, int $at, ?DateTimeZone $zone = null ): string {
			return gmdate( $format, $at ); }
		$args = array(
			'news' => array(
				'id'           => 7,
				'title'        => '<script>alert(1)</script>',
				'body'         => "Primeiro <b>texto</b>\n\nSegundo parágrafo.",
				'category'     => 'circular',
				'author'       => 'Autor fictício',
				'published_at' => 1700000010,
				'audience'     => 'Pessoas seleccionadas',
				'featured'     => array(
					'url'         => '/?arn_news_media=aaaa',
					'description' => 'Destaque "aprovado"',
					'mime'        => 'image/png',
				),
				'gallery'      => array(
					array(
						'url'         => '/?arn_news_media=bbbb',
						'description' => 'Galeria aprovada',
						'mime'        => 'image/jpeg',
					),
				),
				'attachments'  => array(
					array(
						'url'         => '/?arn_news_media=cccc',
						'description' => 'Anexo aprovado',
						'mime'        => 'application/pdf',
					),
				),
			),
		);
		$file = dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/news-published.php';
		self::assertFileExists( $file );
		ob_start();
		include $file;
		$html = (string) ob_get_clean();
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );
		self::assertStringContainsString( 'Autor fictício', $html );
		self::assertStringContainsString( '14/11/2023', $html );
		self::assertStringContainsString( 'Pessoas seleccionadas', $html );
		self::assertStringContainsString( 'Primeiro &lt;b&gt;texto&lt;/b&gt;', $html );
		self::assertStringContainsString( 'Galeria aprovada', $html );
		self::assertStringContainsString( 'Anexo aprovado', $html );
		self::assertStringContainsString( 'arn_news_media=aaaa', $html );
		self::assertStringNotContainsString( 'wp-admin', $html );
	}
}
