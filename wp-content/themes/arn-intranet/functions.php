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
 * Mantém os estilos do conteúdo disponíveis também no editor do CMS.
 */
function arn_intranet_setup(): void {
	add_editor_style( 'assets/css/theme.css' );
}

add_action( 'after_setup_theme', 'arn_intranet_setup' );

/**
 * Carrega o CSS e o comportamento do salto para o conteúdo.
 */
function arn_intranet_enqueue_assets(): void {
	$style_path  = get_theme_file_path( 'assets/css/theme.css' );
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

/**
 * Mostra o aviso local apenas na cópia de desenvolvimento do computador.
 *
 * A verificação ocorre na renderização, porque o editor pode guardar os blocos
 * expandidos do padrão no conteúdo ou num template personalizado.
 *
 * @param string $block_content HTML produzido pelo bloco.
 * @param array  $block         Bloco com os respetivos atributos.
 * @return string HTML original em ambiente local, ou vazio para o aviso fora dele.
 */
function arn_intranet_render_local_notice( string $block_content, array $block ): string {
	$class_name = $block['attrs']['className'] ?? '';

	if (
		is_string( $class_name ) &&
		1 === preg_match( '/(?:^|\s)arn-local-notice(?:\s|$)/', $class_name ) &&
		'local' !== wp_get_environment_type()
	) {
		return '';
	}

	return $block_content;
}

add_filter( 'render_block_core/group', 'arn_intranet_render_local_notice', 10, 2 );
