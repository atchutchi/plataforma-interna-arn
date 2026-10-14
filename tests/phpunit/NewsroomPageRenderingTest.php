<?php
/** Focused CMS presentation regressions without WordPress bootstrap.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;

// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.EscapeOutput.OutputNotEscaped, Universal.Files.SeparateFunctionsFromOO.Mixed -- Tiny WordPress rendering doubles used only here.
function esc_html( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( string $value ): string {
	return esc_html( $value ); }
function esc_url( string $value ): string {
	return esc_html( $value ); }
function esc_textarea( string $value ): string {
	return esc_html( $value ); }
function admin_url( string $path ): string {
	return 'https://example.test/wp-admin/' . $path; }
function add_query_arg( array $args, string $url ): string {
	return $url . '?' . http_build_query( $args ); }
function wp_nonce_field( string $action ): void {
	echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '">'; }
function selected( string $actual, string $expected ): void {
	if ( $actual === $expected ) {
		echo 'selected="selected"'; } }

/** The recovered POST must win over the stored version. */
final class NewsroomPageRenderingTest extends TestCase {
	/** A temporary array is returned only for rendering assertions. */
	private function article(): Article {
		$featured     = new Asset( 'featured', new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( '1', 64 ), 10, 'image/png' ), 'Destaque guardado' );
		$gallery      = new Asset( 'gallery', new BlobDescriptor( str_repeat( 'b', 32 ), str_repeat( '2', 64 ), 10, 'image/jpeg' ), 'Galeria guardada' );
		$attachment   = new Asset( 'attachment', new BlobDescriptor( str_repeat( 'c', 32 ), str_repeat( '3', 64 ), 10, 'application/pdf' ), 'Anexo guardado' );
		$content      = new Content( 'Título', 'Corpo', 'circular', $featured, array( $gallery ), array( $attachment ) );
		$initial      = new Version( 1, $content, 7, 10 );
		$next_content = new Content(
			'Título',
			'Corpo',
			'circular',
			new Asset( 'featured', new BlobDescriptor( str_repeat( 'd', 32 ), str_repeat( '4', 64 ), 10, 'image/png' ), 'Destaque guardado' ),
			array( new Asset( 'gallery', new BlobDescriptor( str_repeat( 'e', 32 ), str_repeat( '5', 64 ), 10, 'image/jpeg' ), 'Galeria guardada' ) ),
			array( new Asset( 'attachment', new BlobDescriptor( str_repeat( 'f', 32 ), str_repeat( '6', 64 ), 10, 'application/pdf' ), 'Anexo guardado' ) )
		);
		$next         = new Version( 2, $next_content, 7, 20 );
		return new Article( 33, 7, Scope::queues( array( 'editorial-ficticia' ) ), 2, array( $initial, $next ), null, null );
	}

	/** Render the same form method used by WordPress, with request-local recovery. */
	private function formHtml( ?array $recovery ): string {
		$state = new ReflectionProperty( NewsroomPage::class, 'recovery' );
		$state->setValue( null, $recovery );
		ob_start();
		try {
			( new ReflectionMethod( NewsroomPage::class, 'form' ) )->invoke( null, 'revise', $this->article() );
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
			$state->setValue( null, null );
		}
	}

	/** Removing every persisted media item survives a reason error and retains the old CAS. */
	public function testRecoveredPostKeepsMediaRemovalAndOldRevision(): void {
		$initial = $this->formHtml( null );
		self::assertStringContainsString( 'Destaque guardado', $initial );
		self::assertStringContainsString( 'Galeria guardada', $initial );
		self::assertStringContainsString( 'Anexo guardado', $initial );
		$post   = array(
			'article_id'        => '33',
			'expected_revision' => '1',
			'title'             => 'Título alterado',
			'body'              => 'Corpo',
			'category'          => 'circular',
			'reason'            => '',
		);
		$errors = NewsFormInput::validate( $post, array() )['errors'];
		self::assertArrayHasKey( 'reason', $errors );
		$html = $this->formHtml(
			array(
				'operation' => 'revise',
				'post'      => $post,
				'errors'    => $errors,
			)
		);
		self::assertStringContainsString( 'name="expected_revision" value="1"', $html );
		self::assertStringContainsString( 'Título alterado', $html );
		self::assertStringNotContainsString( 'Destaque guardado', $html );
		self::assertStringNotContainsString( 'Galeria guardada', $html );
		self::assertStringNotContainsString( 'Anexo guardado', $html );
		self::assertStringNotContainsString( 'name="featured[id]"', $html );
		self::assertStringNotContainsString( 'name="gallery[0][id]"', $html );
		self::assertStringNotContainsString( 'name="attachments[0][id]"', $html );
	}

	/** CSS and JavaScript are resolved from the plugin root. */
	public function testAssetsUsePluginRoot(): void {
		$root = NewsroomPage::assetRoot();
		self::assertFileExists( $root . '/assets/css/newsroom.css' );
		self::assertFileExists( $root . '/assets/js/newsroom.js' );
		self::assertFileExists( $root . '/arn-intranet-core.php' );
	}
}
