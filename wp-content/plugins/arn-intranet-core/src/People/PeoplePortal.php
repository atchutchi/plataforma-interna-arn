<?php
/** Dynamic People block registration.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are covered by the F1-07 plan and tests.
declare(strict_types=1);
namespace Arn\Intranet\People;

/** Dynamic People view with a fixed page-to-view contract. */
final class PeoplePortal {
	public static function register(): void {
		add_action( 'init', array( self::class, 'registerBlock' ) );
		PeopleController::register();
		PeopleNavigation::register();
	}

	public static function registerBlock(): void {
		$script = dirname( __DIR__, 2 ) . '/assets/js/people-view.js';
		wp_register_script( 'arn-people-view', plugins_url( 'assets/js/people-view.js', dirname( __DIR__, 2 ) . '/arn-intranet-core.php' ), array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-i18n' ), (string) filemtime( $script ), true );
		register_block_type(
			'arn/people-view',
			array(
				'api_version'     => 3,
				'title'           => __( 'Vista de pessoas ARN', 'arn-intranet-core' ),
				'attributes'      => array(
					'view' => array(
						'type'    => 'string',
						'default' => 'directory',
						'enum'    => array( 'directory', 'profile', 'team' ),
					),
				),
				'editor_script'   => 'arn-people-view',
				'render_callback' => array( self::class, 'render' ),
			)
		);
	}

	public static function render( array $attributes ): string {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() || ! is_singular( 'page' ) ) {
			return '';
		}
		$view = $attributes['view'] ?? null;
		if ( ! is_string( $view ) || ! in_array( $view, array( 'directory', 'profile', 'team' ), true ) ) {
			return '';
		}
		$model = PeopleController::model( $view );
		if ( null === $model ) {
			return '';
		}
		ob_start();
		get_template_part( 'portal/people', null, array( 'people' => $model ) );
		return (string) ob_get_clean();
	}
}
