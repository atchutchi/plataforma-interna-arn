<?php
/**
 * Fronteira de media com WordPress real e apenas fixtures temporárias.
 *
 * @package ArnIntranet
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
use Arn\Intranet\Documents\PrivateFiles\NativeMediaBoundary;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
global $wpdb;
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
$checks      = 0;
$assert      = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$fixture_tag = 'arn-native-fixture-' . bin2hex( random_bytes( 8 ) );
$source      = tempnam( sys_get_temp_dir(), 'arn-native-' );
$fixture_ids = array();
try {
	// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- PNG fictício de um pixel.
	file_put_contents( $source, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=', true ) );
	$source_hash = hash_file( 'sha256', $source );
	$assert( ! current_user_can( 'upload_files' ) && ! current_user_can( 'unfiltered_upload' ), 'administrador sem uploads nativos' );
	$assert( ! current_user_can( 'export' ), 'exportador nativo indisponível' );
	$assert( NativeMediaBoundary::MESSAGE === wp_upload_dir( null, false, true )['error'], 'directório nativo fechado' );
	$before = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" );
	$assert( is_wp_error( wp_insert_attachment( array( 'post_title' => $fixture_tag ), false, 0, true ) ), 'inserção de attachment recusada' );
	$assert( ! empty( wp_upload_bits( name: $fixture_tag . '.png', deprecated: null, bits: file_get_contents( $source ) )['error'] ), 'upload de bytes recusado' );
	foreach ( array( 'wp_handle_upload', 'wp_handle_sideload' ) as $handler ) {
		$file   = array(
			'name'     => $fixture_tag . '.png',
			'tmp_name' => $source,
			'size'     => filesize( $source ),
			'error'    => 0,
			'type'     => 'image/png',
		);
		$result = $handler( $file, array( 'test_form' => false ) );
		$assert( NativeMediaBoundary::MESSAGE === $result['error'], $handler . ' recusado' );
	}
	$assert( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts}" ) === $before && hash_file( 'sha256', $source ) === $source_hash, 'sem gravações nativas ou movimento da fonte' );
	$assert( is_wp_error( wp_get_image_editor( $source ) ), 'sem editor que possa gerar cópias públicas' );

	// Fixture de attachment anterior: não desactivar filtros nem importar um original.
	$assert(
		false !== $wpdb->insert(
			$wpdb->posts,
			array(
				'post_title'     => $fixture_tag,
				'post_name'      => $fixture_tag,
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image/png',
				'guid'           => 'https://example.invalid/fixture.png',
			)
		),
		'fixture nativa isolada'
	);
	$attachment    = (int) $wpdb->insert_id;
	$fixture_ids[] = $attachment;
	add_post_meta(
		$attachment,
		'_wp_attachment_metadata',
		array(
			'width'  => 1,
			'height' => 1,
			'file'   => 'fixture.png',
		)
	);
	foreach ( array( 'read_post', 'edit_post', 'delete_post' ) as $cap ) {
		$assert( ! current_user_can( $cap, $attachment ), $cap . ' attachment recusado' );
	}
	$assert( false === wp_get_attachment_url( $attachment ), 'URL nativa omitida' );
	$assert( array() === wp_get_attachment_metadata( $attachment ), 'metadados omitidos' );
	$assert( null === wp_prepare_attachment_for_js( $attachment ), 'projecção JS omitida' );
	$assert( '' === wp_get_attachment_image( $attachment ), 'miniatura omitida' );
	$assert( false === wp_delete_attachment( $attachment, true ), 'eliminação nativa recusada' );
	$assert(
		is_wp_error(
			wp_update_post(
				array(
					'ID'         => $attachment,
					'post_title' => 'mudança recusada',
				),
				true
			)
		),
		'actualização recusada'
	);
	$assert( $fixture_tag === $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID=%d", $attachment ) ), 'attachment anterior conservado' );
	$oembed = new WP_REST_Request( 'GET', '/oembed/1.0/embed' );
	$oembed->set_param( 'url', home_url( '/?attachment_id=' . $attachment ) );
	$assert( 404 === rest_do_request( $oembed )->get_status(), 'oEmbed não revela attachment' );
	$query = new WP_Query(
		array(
			'attachment_id'    => $attachment,
			'suppress_filters' => true,
		)
	);
	$assert( array() === $query->posts && 0 === (int) $query->found_posts, 'tipo attachment implícito não omite filtro' );
	foreach ( array( 'attachment', 'any', array( 'page', 'attachment' ) ) as $fixture_type ) {
		$query = new WP_Query(
			array(
				'post_type'        => $fixture_type,
				'post_status'      => 'any',
				'post__in'         => array( $attachment ),
				'suppress_filters' => true,
			)
		);
		$assert( array() === $query->posts && 0 === (int) $query->found_posts, 'consulta filtrada antes de contar' );
	}
	foreach ( array( 'GET', 'HEAD', 'POST', 'DELETE', 'OPTIONS' ) as $method ) {
		foreach ( array( '/wp/v2/media', '/wp/v2/media/' . $attachment, '/wp/v2/types/attachment' ) as $route ) {
			$response = rest_do_request( new WP_REST_Request( $method, $route ) );
			$assert( 404 === $response->get_status(), 'REST ' . $method . ' fechado' );
		}
	}
	$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/users/me' ) );
	$assert( 200 === $response->get_status(), 'REST autenticado normal conservado' );
	$fixture_page = wp_insert_post(
		array(
			'post_title'  => $fixture_tag,
			'post_type'   => 'page',
			'post_status' => 'draft',
		),
		true
	);
	$assert( is_int( $fixture_page ) && $fixture_page > 0, 'CMS de páginas conservado' );
	$fixture_ids[] = $fixture_page;
	foreach ( array( $attachment, $fixture_page ) as $comment_parent ) {
		$wpdb->insert(
			$wpdb->comments,
			array(
				'comment_post_ID'  => $comment_parent,
				'comment_content'  => $fixture_tag,
				'comment_approved' => '1',
			)
		);
		$comment_id = (int) $wpdb->insert_id;
		if ( $attachment === $comment_parent ) {
			$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/comments/' . $comment_id ) );
			$assert( 404 === $response->get_status(), 'comentário singular de attachment recusado' );
		}
	}
	$assert( ! comments_open( $attachment ), 'comentários de attachment fechados' );
	$assert(
		0 === (int) get_comments(
			array(
				'post_id' => $attachment,
				'count'   => true,
				'status'  => 'all',
			)
		),
		'contagem de comentários de attachment filtrada'
	);
	$assert(
		1 === (int) get_comments(
			array(
				'post_id' => $fixture_page,
				'count'   => true,
				'status'  => 'all',
			)
		),
		'comentário de página conservado'
	);
	$request = new WP_REST_Request( 'GET', '/wp/v2/comments' );
	$request->set_param( 'post', array( $attachment, $fixture_page ) );
	$request->set_param( 'status', 'all' );
	$comments_response = rest_do_request( $request );
	$assert( 200 === $comments_response->get_status() && 1 === count( $comments_response->get_data() ) && $fixture_page === $comments_response->get_data()[0]['post'], 'REST comentários mistos filtra attachment' );
	$assert(
		1 === count(
			get_posts(
				array(
					'post_type'   => 'page',
					'post_status' => 'draft',
					'post__in'    => array( $fixture_page ),
				)
			)
		),
		'consulta normal conservada'
	);
} finally {
	// Só os IDs criados pelo próprio ensaio, com marcador exacto, são removidos.
	foreach ( $fixture_ids as $fixture_id ) {
		if ( $fixture_tag === $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID=%d", $fixture_id ) ) ) {
			$wpdb->delete(
				$wpdb->comments,
				array(
					'comment_post_ID' => $fixture_id,
					'comment_content' => $fixture_tag,
				)
			);
			$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $fixture_id ) );
			$wpdb->delete( $wpdb->posts, array( 'ID' => $fixture_id ) );
			clean_post_cache( $fixture_id );
		}
	}
	if ( is_string( $source ) && is_file( $source ) ) {
		unlink( $source );
	}
}
WP_CLI::success( 'Media nativa: ' . $checks . ' verificações aprovadas.' );
