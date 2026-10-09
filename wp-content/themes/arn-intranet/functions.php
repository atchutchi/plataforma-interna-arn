<?php
/**
 * Recursos do tema de blocos.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Carrega o CSS e o comportamento do salto para o conteúdo.
 */
function arn_intranet_enqueue_assets(): void {
	$style_path = get_theme_file_path( 'assets/css/theme.css' );
	$script_path = get_theme_file_path( 'assets/js/navigation.js' );

	wp_enqueue_style(
		'arn-intranet',
		get_theme_file_uri( 'assets/css/theme.css' ),
		array(),
		(string) filemtime( $style_path )
	);

	wp_enqueue_script(
		'arn-intranet-navigation',
		get_theme_file_uri( 'assets/js/navigation.js' ),
		array(),
		(string) filemtime( $script_path ),
		array(
			'strategy'  => 'defer',
			'in_footer' => true,
		)
	);
}

add_action( 'wp_enqueue_scripts', 'arn_intranet_enqueue_assets' );
