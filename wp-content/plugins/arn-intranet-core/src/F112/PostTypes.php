<?php
/**
 * F1-12 local fictional composition.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use InvalidArgumentException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Private transport anchors only.
final class PostTypes {
	public static function arguments( string $type ): array {
		if ( ! in_array( $type, array( 'arn_comunicado', 'arn_evento' ), true ) ) {
			throw new InvalidArgumentException( 'Âncora F112 inválida.' );
		}
		$denied = array_fill_keys( array( 'create_posts', 'read_post', 'edit_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts' ), 'do_not_allow' );
		return array(
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'rewrite'             => false,
			'query_var'           => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'supports'            => false,
			'can_export'          => false,
			'show_in_nav_menus'   => false,
			'capability_type'     => $type,
			'map_meta_cap'        => false,
			'capabilities'        => $denied,
		);
	}

	public static function register(): void {
		add_action( 'init', array( self::class, 'nativeBoundary' ), -10 );
		add_action(
			'init',
			static function (): void {
				foreach ( array( 'arn_comunicado', 'arn_evento' ) as $type ) {
					register_post_type( $type, self::arguments( $type ) );
				}
				$base = dirname( __DIR__, 2 );
				wp_register_script( 'arn-f112-view', plugins_url( 'assets/js/f112-view.js', $base . '/arn-intranet-core.php' ), array( 'wp-blocks', 'wp-element' ), (string) filemtime( $base . '/assets/js/f112-view.js' ), true );
				register_block_type(
					'arn/f112-view',
					array(
						'api_version'     => 3,
						'editor_script'   => 'arn-f112-view',
						'supports'        => array(
							'html'     => false,
							'inserter' => false,
							'multiple' => false,
						),
						'attributes'      => array( 'module' => array( 'type' => 'string' ) ),
						'render_callback' => array( Portal::class, 'render' ),
					)
				);
			}
		);
	}

	public static function nativeBoundary(): void {
		if ( ( defined( 'WP_CLI' ) && WP_CLI ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		$types = array( 'arn_comunicado', 'arn_evento' );
		$type  = $_GET['post_type'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Transport refusal only.
		if ( in_array( $type, $types, true ) ) {
			Controller::refuse();
		}
		foreach ( array( 'p', 'post', 'attachment_id', 'preview_id' ) as $key ) {
			$id = Input::id( $_GET[ $key ] ?? null ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Never mutates an object.
			if ( null !== $id ) {
				$post = get_post( $id );
				if ( $post instanceof \WP_Post && in_array( $post->post_type, $types, true ) ) {
					Controller::refuse();
				}
			}
		}
	}
}
