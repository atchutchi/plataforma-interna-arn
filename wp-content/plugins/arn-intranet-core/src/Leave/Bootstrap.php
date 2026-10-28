<?php
/** Registers private dynamic task surfaces, never installs resources.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

// phpcs:disable Squiz.Commenting -- Installation is an explicit CLI command only.
final class Bootstrap {
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && true === WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command(
				'arn leave install',
				static function (): void {
					( new LocalInstaller() )->install();
					\WP_CLI::success( 'Férias fictícias preparadas sem alterar contas ou concessões.' );
				}
			);
		}
		Controller::register();
		Navigation::register();
		add_action( 'init', array( self::class, 'block' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function block(): void {
		$base = dirname( __DIR__, 2 );
		wp_register_script( 'arn-leave-view', plugins_url( 'assets/js/leave-view.js', $base . '/arn-intranet-core.php' ), array( 'wp-blocks', 'wp-element' ), (string) filemtime( $base . '/assets/js/leave-view.js' ), true );
		register_block_type(
			'arn/leave-view',
			array(
				'api_version'     => 3,
				'editor_script'   => 'arn-leave-view',
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
		wp_enqueue_style( 'arn-requests', get_stylesheet_directory_uri() . '/assets/css/requests.css', array( 'arn-intranet' ), (string) filemtime( $theme . '/assets/css/requests.css' ) );
		$base = dirname( __DIR__, 2 );
		wp_enqueue_script( 'arn-requests', plugins_url( 'assets/js/requests.js', $base . '/arn-intranet-core.php' ), array(), (string) filemtime( $base . '/assets/js/requests.js' ), true );
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
		View::render( $model );
		return (string) ob_get_clean();
	}
}
