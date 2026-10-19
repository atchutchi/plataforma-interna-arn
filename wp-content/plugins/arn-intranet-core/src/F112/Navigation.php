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
		foreach ( self::destinations() as $destination ) {
			$exists = false;
			foreach ( $blocks as $block ) {
				if ( 'core/navigation-link' === $block->name && ( $block->attributes['url'] ?? null ) === $destination['url'] ) {
					$exists = true;
				}
			}
			if ( ! $exists ) {
				$blocks[] = array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => $destination + array(
						'kind' => 'post-type',
						'type' => 'page',
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				);
			}
		}
		return $blocks;
	}

	/** Same guarded destinations for native navigation and the services page. */
	public static function destinations(): array {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() ) {
			return array();
		}
		$pages  = new Pages();
		$result = array();
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
			$result[ $module ] = array(
				'label' => $label,
				'url'   => get_permalink( $post ),
				'id'    => $post->ID,
			);
		}
		return $result;
	}
}
