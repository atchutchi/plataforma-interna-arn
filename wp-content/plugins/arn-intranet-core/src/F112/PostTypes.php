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
			'map_meta_cap'        => true,
			'capabilities'        => array(
				'create_posts' => 'do_not_allow',
				'read_post'    => 'do_not_allow',
				'edit_post'    => 'do_not_allow',
				'delete_post'  => 'do_not_allow',
			),
		);
	}

	public static function register(): void {
		add_action(
			'init',
			static function (): void {
				foreach ( array( 'arn_comunicado', 'arn_evento' ) as $type ) {
					register_post_type( $type, self::arguments( $type ) );
				}
				register_block_type(
					'arn/f112-view',
					array(
						'api_version'     => 3,
						'attributes'      => array( 'module' => array( 'type' => 'string' ) ),
						'render_callback' => static fn(): string => '',
					)
				);
			}
		);
	}
}
