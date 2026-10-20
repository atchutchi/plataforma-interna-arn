<?php
/**
 * Keep native automatic menus separate from guarded module destinations.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

use WP_Block;
use WP_Post;

// phpcs:disable Squiz.Commenting -- Native rendering is retained, including its accessibility markup.
final class NavigationPageList {
	public static function register(): void {
		add_filter( 'register_block_type_args', array( self::class, 'arguments' ), 10, 2 );
	}

	public static function arguments( array $arguments, string $name ): array {
		if ( 'core/page-list' !== $name || ! is_callable( $arguments['render_callback'] ?? null ) ) {
			return $arguments;
		}
		$original                     = $arguments['render_callback'];
		$arguments['render_callback'] = static function ( array $attributes, string $content, WP_Block $block ) use ( $original ) {
			if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ! array_key_exists( 'showSubmenuIcon', $block->context ) ) {
				return $original( $attributes, $content, $block );
			}
			// Module links are supplied once by their own navigation authorities.
			$filter = static fn( array $pages ): array => array_values(
				array_filter( $pages, static fn( $page ): bool => ! $page instanceof WP_Post || 0 !== (int) $page->post_parent || ! in_array( $page->post_name, array( 'funcionarios', 'meu-perfil', 'minha-equipa', 'comunicados', 'calendario', 'minhas-tarefas', 'tarefas-da-equipa' ), true ) )
			);
			add_filter( 'get_pages', $filter, PHP_INT_MAX );
			try {
				return $original( $attributes, $content, $block );
			} finally {
				remove_filter( 'get_pages', $filter, PHP_INT_MAX );
			}
		};
		return $arguments;
	}
}
