<?php
/**
 * Read-only projection of native saved navigation, including submenus.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

use WP_Block;
use WP_Block_List;
use WP_Post;

// phpcs:disable Squiz.Commenting -- Destinations keep their own server-side authority.
final class ModuleNavigation {
	public static function register(): void {
		add_filter( 'block_core_navigation_render_inner_blocks', array( self::class, 'links' ), 20 );
	}

	public static function links( WP_Block_List $blocks ): WP_Block_List {
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $blocks;
		}
		$page_ids = array();
		foreach ( NavigationTargets::SLUGS as $view => $slug ) {
			$post = get_page_by_path( $slug, OBJECT, 'page' );
			if ( $post instanceof WP_Post ) {
				$page_ids[ $view ] = (int) $post->ID;
			}
		}
		$allowed = array_fill_keys( array_column( ( new ServiceDirectory() )->items(), 'id' ), true );
		$seen    = array();
		return self::project( $blocks, new NavigationTargets( home_url( '/' ), $page_ids ), $allowed, $seen );
	}

	private static function project( WP_Block_List $blocks, NavigationTargets $targets, array $allowed, array &$seen ): WP_Block_List {
		$result = array();
		foreach ( $blocks as $block ) {
			$node = clone $block;
			if ( 'core/navigation' === $node->name ) {
				$result[] = $node;
				continue;
			}
			$attributes   = $node->attributes;
			$is_link      = in_array( $node->name, array( 'core/navigation-link', 'core/navigation-submenu' ), true );
			$native_guard = defined( 'IS_GUTENBERG_PLUGIN' ) && IS_GUTENBERG_PLUGIN ? 'gutenberg_block_core_shared_navigation_item_should_render' : 'block_core_shared_navigation_item_should_render';
			if ( $is_link && ( empty( $attributes['label'] ) || ( function_exists( $native_guard ) && ! $native_guard( $attributes, $node ) ) ) ) {
				continue;
			}
			$view    = $is_link ? $targets->identify( $attributes ) : null;
			$refused = null !== $view && ( ! isset( $allowed[ $view ] ) || isset( $seen[ $view ] ) );
			if ( null !== $view && ! $refused ) {
				$seen[ $view ] = true;
			}
			$had_children = count( $node->inner_blocks ) > 0;
			$children     = array();
			$content      = array();
			$index        = 0;
			foreach ( $node->inner_content as $chunk ) {
				if ( null !== $chunk ) {
					$content[] = $chunk;
					continue;
				}
				$child = $node->inner_blocks[ $index++ ];
				if ( ! $child instanceof WP_Block ) {
					continue;
				}
				$projected = self::project( new WP_Block_List( array( $child ) ), $targets, $allowed, $seen );
				foreach ( $projected as $kept ) {
					$children[] = $kept;
					$content[]  = null;
				}
			}
			if ( $refused && ! $children ) {
				continue;
			}
			if ( $refused ) {
				$attributes['url']            = '';
				$attributes['id']             = 0;
				$attributes['kind']           = 'custom';
				$attributes['type']           = '';
				$attributes['isTopLevelLink'] = false;
			}
			if ( 'core/navigation-submenu' === $node->name && $had_children && ! $children && empty( $attributes['url'] ) ) {
				continue;
			}
			$node->attributes                   = $attributes;
			$node->inner_blocks                 = new WP_Block_List( $children );
			$node->inner_content                = $content;
			$node->parsed_block['attrs']        = $attributes;
			$node->parsed_block['innerContent'] = $content;
			$node->parsed_block['innerBlocks']  = array_map( static fn( WP_Block $child ): array => $child->parsed_block, $children );
			$result[]                           = $node;
		}
		return new WP_Block_List( $result );
	}
}
