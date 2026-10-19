<?php
/** Native People navigation links.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are covered by the F1-07 plan and tests.
declare(strict_types=1);
namespace Arn\Intranet\People;

use WP_Block_List;
use WP_Post;

/** Appends native links at render time without changing the saved menu. */
final class PeopleNavigation {
	public static function register(): void {
		add_filter( 'block_core_navigation_render_inner_blocks', array( self::class, 'links' ) );
	}

	public static function links( WP_Block_List $blocks ): WP_Block_List {
		foreach ( self::destinations() as $destination ) {
			$exists = false;
			foreach ( $blocks as $block ) {
				if ( 'core/navigation-link' === $block->name && ( $block->attributes['url'] ?? null ) === $destination['url'] ) {
					$exists = true;
					break;
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
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() || ! PeopleConfiguration::current() ) {
			return array();
		}
		$source = new LocalPeopleDirectory();
		$own    = $source->own();
		if ( null === $own ) {
			return array();
		}
		$views = array(
			'directory' => 'Funcionários',
			'profile'   => 'Meu perfil',
		);
		$query = PeopleQuery::parse( array() );
		if ( null !== $query && null !== $source->team( $query ) ) {
			$views['team'] = 'A minha equipa';
		}
		$pages  = new PeoplePages();
		$result = array();
		foreach ( $views as $view => $label ) {
			$post = get_page_by_path( $pages->slug( $view ), OBJECT, 'page' );
			if ( ! $post instanceof WP_Post || $pages->viewFor( $post ) !== $view ) {
				continue;
			}
			$result[ $view ] = array(
				'label' => $label,
				'url'   => get_permalink( $post ),
				'id'    => $post->ID,
			);
		}
		return $result;
	}
}
