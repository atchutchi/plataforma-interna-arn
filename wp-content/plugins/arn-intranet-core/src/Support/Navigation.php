<?php
/** Support links consume current module authority without changing saved menus.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use Throwable;
use WP_Block_List;
use WP_Post;

// phpcs:disable Squiz.Commenting -- Navigation never grants business permission.
final class Navigation {
	public static function register(): void {
		add_filter( 'block_core_navigation_render_inner_blocks', array( self::class, 'links' ) );
	}

	public static function destinations(): array {
		if ( ! Configuration::current() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() ) {
			return array();
		}
		$result = array();
		$portal = new Portal();
		$pages  = new Pages();
		try {
			$access = $portal->navigation();
		} catch ( Throwable ) {
			return array();
		}
		foreach ( array(
			'own'  => 'Meus tickets',
			'team' => 'Suporte TI',
		) as $view => $label ) {
			try {
				if ( null === ( $access[ $view ] ?? null ) ) {
					continue;
				}
				$post = get_page_by_path( $pages->slug( $view ), OBJECT, 'page' );
				if ( ! $post instanceof WP_Post || $pages->viewFor( $post ) !== $view ) {
					continue;
				}
				$result[ 'tickets_' . $view ] = array(
					'label' => $label,
					'url'   => get_permalink( $post ),
					'id'    => $post->ID,
				);
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Fail closed per surface.
				// No technical data is projected into navigation.
			}
		}
		return $result;
	}

	public static function links( WP_Block_List $blocks ): WP_Block_List {
		foreach ( self::destinations() as $item ) {
			$exists = false;
			foreach ( $blocks as $block ) {
				if ( 'core/navigation-link' === $block->name && ( $block->attributes['url'] ?? null ) === $item['url'] ) {
					$exists = true;
				}
			}
			if ( ! $exists ) {
				$blocks[] = array(
					'blockName'    => 'core/navigation-link',
					'attrs'        => $item + array(
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
}
