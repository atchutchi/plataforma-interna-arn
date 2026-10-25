<?php
/** Registers private dynamic task surfaces, never installs resources.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

// phpcs:disable Squiz.Commenting -- Installation is an explicit CLI command only.
final class Bootstrap {
	public static function register(): void {
		Controller::register();
		AttachmentController::register();
		Navigation::register();
		add_action( 'init', array( self::class, 'block' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function block(): void {
		$base = dirname( __DIR__, 2 );
		wp_register_script( 'arn-tasks-view', plugins_url( 'assets/js/tasks-view.js', $base . '/arn-intranet-core.php' ), array( 'wp-blocks', 'wp-element' ), (string) filemtime( $base . '/assets/js/tasks-view.js' ), true );
		register_block_type(
			'arn/tasks-view',
			array(
				'api_version'     => 3,
				'editor_script'   => 'arn-tasks-view',
				'attributes'      => array(
					'view' => array(
						'type' => 'string',
						'enum' => array( 'own', 'team' ),
					),
				),
				'supports'        => array(
					'html'     => false,
					'inserter' => false,
					'multiple' => false,
				),
				'render_callback' => array( self::class, 'render' ),
			)
		);
	}

	public static function assets(): void {
		if ( null === Controller::model() ) {
			return;
		}
		$theme = get_stylesheet_directory();
		wp_enqueue_style( 'arn-tasks', get_stylesheet_directory_uri() . '/assets/css/tasks.css', array( 'arn-intranet' ), (string) filemtime( $theme . '/assets/css/tasks.css' ) );
		$base = dirname( __DIR__, 2 );
		wp_enqueue_script( 'arn-tasks', plugins_url( 'assets/js/tasks.js', $base . '/arn-intranet-core.php' ), array(), (string) filemtime( $base . '/assets/js/tasks.js' ), true );
	}

	public static function render( array $attributes ): string {
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || is_admin() || ! is_singular( 'page' ) || ! Configuration::current() ) {
			return '';
		}
		$view  = $attributes['view'] ?? null;
		$model = Controller::model();
		if ( ! is_string( $view ) || null === $model || $model['view'] !== $view || ( new Pages() )->viewFor( get_post() ) !== $view ) {
			return '';
		}
		ob_start();
		get_template_part( 'portal/tasks', null, array( 'tasks' => $model ) );
		return (string) ob_get_clean();
	}
}
