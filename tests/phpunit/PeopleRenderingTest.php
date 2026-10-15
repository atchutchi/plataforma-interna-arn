<?php
/** People template and REST rendering tests.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.WP.AlternativeFunctions -- Small WordPress stubs for isolated rendering.
declare(strict_types=1);

use Arn\Intranet\People\PeoplePortal;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

function esc_html( string $text ): string {
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( string $text ): string {
	return esc_html( $text ); }
function esc_url( string $text ): string {
	return esc_html( $text ); }
function esc_textarea( string $text ): string {
	return esc_html( $text ); }
function home_url( string $path ): string {
	return 'https://example.test' . $path; }
function get_permalink(): string {
	return 'https://example.test/meu-perfil/'; }
function get_page_by_path(): null {
	return null; }
function wp_parse_url( string $url, int $part ): string|false|null {
	return parse_url( $url, $part ); }
function absint( mixed $value ): int {
	return abs( (int) $value ); }
function selected( string $actual, string $expected ): void {
	if ( $actual === $expected ) {
		echo ' selected="selected"'; } }
function _n( string $single, string $plural, int $number ): string {
	return 1 === $number ? $single : $plural; }
function number_format_i18n( int $number ): string {
	return (string) $number; }
function add_query_arg( string|array $key, string $value, string $url = '' ): string {
	return $url . '?' . ( is_array( $key ) ? http_build_query( $key ) : rawurlencode( $key ) . '=' . rawurlencode( $value ) ); }
function wp_date( string $format, int $timestamp ): string {
	return gmdate( $format, $timestamp ); }

final class PeopleRenderingTest extends TestCase {
	private function render( array $model ): string {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/' ); }
		if ( ! defined( 'OBJECT' ) ) {
			define( 'OBJECT', 'OBJECT' ); }
		$args = array( 'people' => $model );
		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/people.php';
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
		}
	}

	public function test_contact_is_escaped_and_has_no_private_revision_or_form(): void {
		$item = array(
			'id'     => 7,
			'name'   => '<script>Alfa</script>',
			'email'  => 'alfa@example.invalid',
			'job'    => 'Teste',
			'unit'   => 'LAB-A',
			'phone'  => '',
			'office' => '',
			'bio'    => '<b>Texto</b>',
		);
		$html = $this->render(
			array(
				'view'    => 'contact',
				'contact' => $item,
			)
		);
		self::assertStringContainsString( '&lt;script&gt;Alfa&lt;/script&gt;', $html );
		self::assertStringContainsString( '&lt;b&gt;Texto&lt;/b&gt;', $html );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringNotContainsString( 'expected_revision', $html );
		self::assertStringNotContainsString( 'Guardar contactos', $html );
	}

	public function test_own_form_keeps_only_three_fields_and_history_labels(): void {
		$item = array(
			'name'     => 'Alfa',
			'email'    => 'alfa@example.invalid',
			'job'      => 'Teste',
			'unit'     => 'LAB-A',
			'phone'    => '',
			'office'   => '',
			'bio'      => '',
			'revision' => 2,
		);
		$html = $this->render(
			array(
				'view'         => 'profile',
				'own'          => $item,
				'fields'       => array(
					'phone'  => '<x>',
					'office' => 'Sala',
					'bio'    => '<script>',
				),
				'history'      => array(
					array(
						'occurred_at'    => 1730000000,
						'changed_fields' => array( 'phone' ),
					),
				),
				'error'        => 'Rever',
				'confirmation' => false,
				'nonce'        => 'token',
			)
		);
		self::assertStringContainsString( 'value="&lt;x&gt;"', $html );
		self::assertStringContainsString( '&lt;script&gt;', $html );
		self::assertStringContainsString( 'name="expected_revision" value="2"', $html );
		self::assertStringNotContainsString( 'name="email"', $html );
		self::assertStringNotContainsString( 'name="role"', $html );
		self::assertStringNotContainsString( 'changed_fields', $html );
	}

	#[RunInSeparateProcess]
	public function test_rest_block_renderer_has_no_people_content(): void {
		define( 'REST_REQUEST', true );
		self::assertSame( '', PeoplePortal::render( array( 'view' => 'profile' ) ) );
	}
}
