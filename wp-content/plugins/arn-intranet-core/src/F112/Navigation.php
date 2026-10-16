<?php
/**
 * Authorised native navigation additions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use WP_Block_List;
use WP_Post;

// phpcs:disable Squiz.Commenting -- Does not mutate the saved navigation.
final class Navigation {
	public static function register(): void {
		add_filter( 'block_core_navigation_render_inner_blocks', array( self::class, 'links' ) );
	}

	public static function links( WP_Block_List $blocks ): WP_Block_List {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() ) {
			return $blocks;
		}
		$pages = new Pages();
		foreach ( array(
			'communications' => 'Comunicados',
			'calendar'       => 'Calendário',
		) as $module => $label ) {
			if ( ! Configuration::current( $module ) || ! ( new Portal( $module ) )->readable() ) {
				continue;
			}
			$post = get_page_by_path( $pages->slug( $module ), OBJECT, 'page' );
			if ( ! $post instanceof WP_Post || $pages->viewFor( $post ) !== $module ) {
				continue;
			}
			$url    = get_permalink( $post );
			$exists = false;
			foreach ( $blocks as $block ) {
				if ( 'core/navigation-link' === $block->name && ( $block->attributes['url'] ?? null ) === $url ) {
					$exists = true;
				}
			}
			if ( ! $exists ) {
				$blocks[] = array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => array(
						'label' => $label,
						'url'   => $url,
						'kind'  => 'post-type',
						'type'  => 'page',
						'id'    => $post->ID,
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
			}
		}
		return $blocks;
	}
}
