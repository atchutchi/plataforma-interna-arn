<?php
/** Registers private dynamic support surfaces, never installs resources.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

// phpcs:disable Squiz.Commenting -- Installation is an explicit CLI command only.
final class Bootstrap {
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && true === WP_CLI && class_exists( '\WP_CLI' ) ) {
			\WP_CLI::add_command(
				'arn tickets install',
				static function (): void {
					( new LocalInstaller() )->install();
					\WP_CLI::success( 'Tickets fictícios preparados. Credenciais conservadas na pasta privada.' );
				}
			);
		}
		Controller::register();
		AttachmentController::register();
		Navigation::register();
		add_action( 'init', array( self::class, 'block' ) );
		add_action( 'wp_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function block(): void {
		$base = dirname( __DIR__, 2 );
		wp_register_script( 'arn-tickets-view', plugins_url( 'assets/js/tickets-view.js', $base . '/arn-intranet-core.php' ), array( 'wp-blocks', 'wp-element' ), (string) filemtime( $base . '/assets/js/tickets-view.js' ), true );
		register_block_type(
			'arn/tickets-view',
			array(
				'api_version'     => 3,
				'editor_script'   => 'arn-tickets-view',
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
		wp_enqueue_style( 'arn-tickets', get_stylesheet_directory_uri() . '/assets/css/tickets.css', array( 'arn-intranet' ), (string) filemtime( $theme . '/assets/css/tickets.css' ) );
		$base = dirname( __DIR__, 2 );
		wp_enqueue_script( 'arn-tickets', plugins_url( 'assets/js/tickets.js', $base . '/arn-intranet-core.php' ), array(), (string) filemtime( $base . '/assets/js/tickets.js' ), true );
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
		get_template_part( 'portal/tickets', null, array( 'tickets' => $model ) );
		return (string) ob_get_clean();
	}
}
