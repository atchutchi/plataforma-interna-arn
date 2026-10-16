<?php
/**
 * Public projection closes private state.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Projection contract tests without WordPress.
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\F112\{View, Audience};
use Arn\Intranet\Communications\{Notice, NoticeContent};
use PHPUnit\Framework\TestCase;

final class F112RenderingTest extends TestCase {
	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
	public function test_communication_rows_show_validity_and_unlimited_state(): void {
		require __DIR__ . '/fixtures/f112-render-functions.php';
		define( 'ABSPATH', '/' );
		$notice    = Notice::draft( 1, 7, new NoticeContent( 'Com validade', 'Texto', Audience::unit( 'LAB-A' ), 'team-direct', 1894791600 ), 10 )->publish( 7, 1, 11, 'Publicar' );
		$unlimited = Notice::draft( 2, 7, new NoticeContent( 'Sem limite', 'Texto', Audience::unit( 'LAB-A' ), 'team-direct', null ), 10 )->publish( 7, 1, 11, 'Publicar' );
		$args      = array(
			'f112' => array(
				'module' => 'communications',
				'view'   => 'list',
				'data'   => array(
					'items' => array( View::published( $notice, 20 ), View::published( $unlimited, 20 ) ),
					'total' => 2,
					'page'  => 1,
					'pages' => 1,
				),
				'query'  => \Arn\Intranet\F112\Input::query( 'communications', array() ),
			),
		);
		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/f112.php';
			$html = (string) ob_get_contents();
		} finally {
			ob_end_clean(); }
		self::assertStringContainsString( 'Disponível até', $html );
		self::assertStringContainsString( 'Sem limite de validade', $html );
	}

	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
	public function test_detail_returns_to_the_original_notices_page(): void {
		require __DIR__ . '/fixtures/f112-render-functions.php';
		define( 'ABSPATH', '/' );
		$row   = array(
			'id'           => 7,
			'title'        => 'Aviso',
			'body'         => 'Publicado',
			'audience'     => 'LAB-A',
			'version'      => 1,
			'published_at' => 11,
			'valid_until'  => null,
		);
		$query = array(
			'get'       => array(
				'entry'         => '7',
				'from'          => 'notices',
				'arn_f112_page' => '2',
			),
			'selection' => \Arn\Intranet\Communications\NoticeSelection::parse( array( 'page' => '2' ) ),
		);
		$args  = array(
			'f112' => array(
				'module' => 'communications',
				'view'   => 'detail',
				'data'   => $row,
				'query'  => $query,
			),
		);
		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/f112.php';
			$html = (string) ob_get_contents();
		} finally {
			ob_end_clean(); }
		self::assertSame( 1, preg_match( '/href="([^"]+)">Voltar aos resultados/', $html, $back ) );
		self::assertStringContainsString( 'notices=1', html_entity_decode( $back[1] ) );
		self::assertStringContainsString( 'arn_f112_page=2', html_entity_decode( $back[1] ) );
	}
	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
	public function test_reviewer_cms_renders_frozen_content_and_opaque_proof_without_private_cas(): void {
		require __DIR__ . '/fixtures/f112-render-functions.php';
		define( 'ABSPATH', '/' );
		$model = array(
			'module'   => 'communications',
			'query'    => \Arn\Intranet\F112\Input::query( 'communications', array( 'entry' => '1' ), true ),
			'row'      => array(
				'id'      => 1,
				'version' => 1,
				'title'   => '<script>Submissão</script>',
				'state'   => 'Pending',
				'fields'  => array(
					'title'       => '<script>Submissão</script>',
					'body'        => '<img src=x>',
					'audience'    => 'all',
					'circuit'     => 'institutional-review',
					'valid_until' => '',
				),
				'actions' => array( 'approve', 'reject', 'return' ),
				'history' => array(),
				'token'   => 'opaque-proof',
			),
			'errors'   => array(),
			'recovery' => array(),
			'options'  => array(),
		);
		ob_start();
		try {
			View::management( $model );
			$html = (string) ob_get_contents();
		} finally {
			ob_end_clean(); }
		self::assertStringContainsString( 'name="token" value="opaque-proof"', $html );
		self::assertStringContainsString( 'name="version" value="1"', $html );
		self::assertStringNotContainsString( 'name="revision"', $html );
		self::assertStringNotContainsString( 'Histórico privado', $html );
		self::assertStringNotContainsString( 'Guardar nova versão', $html );
		self::assertStringContainsString( '&lt;script&gt;Submissão&lt;/script&gt;', $html );
		self::assertStringNotContainsString( '<script>', $html );
	}
	public function test_actual_public_template_escapes_literal_html_and_contains_one_h1(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/' ); }
		$notice = Notice::draft( 1, 7, new NoticeContent( '<script>alert(1)</script>', '<img src=x onerror=alert(1)>', Audience::unit( 'LAB-A' ), 'team-direct', null ), 10 )->publish( 7, 1, 11, 'Publication test' );
		$args   = array(
			'f112' => array(
				'module' => 'communications',
				'view'   => 'detail',
				'data'   => View::published( $notice, 20 ),
				'query'  => \Arn\Intranet\F112\Input::query( 'communications', array( 'entry' => '1' ) ),
			),
		);
		ob_start();
		try {
			include dirname( __DIR__, 2 ) . '/wp-content/themes/arn-intranet/portal/f112.php';
			$html = (string) ob_get_contents();
		} finally {
			ob_end_clean(); }
		self::assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		self::assertStringContainsString( '&lt;img src=x onerror=alert(1)&gt;', $html );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringNotContainsString( 'name="revision"', $html );
		self::assertSame( 1, substr_count( $html, '<h1>' ) );
	}
	public function test_projection_excludes_unpublished_body_revision_and_audit_reason(): void {
		$notice = Notice::draft( 1, 7, new NoticeContent( '<b>Título</b>', 'Publicado', Audience::unit( 'LAB-A' ), 'team-direct', null ), 10, 'Motivo privado' )->publish( 7, 1, 11, 'Outro motivo' );
		$notice = $notice->revise( new NoticeContent( 'SEGREDO', 'Corpo privado', Audience::unit( 'LAB-A' ), 'team-direct', null ), 7, 2, 12, 'Motivo secreto' );
		$row    = View::published( $notice, 20 );
		self::assertSame( '<b>Título</b>', $row['title'] );
		self::assertSame( 'Publicado', $row['body'] );
		self::assertArrayNotHasKey( 'revision', $row );
		self::assertArrayNotHasKey( 'history', $row );
		self::assertStringNotContainsString( 'SEGREDO', json_encode( $row ) );
		self::assertStringNotContainsString( 'Motivo', json_encode( $row ) );
	}
}
