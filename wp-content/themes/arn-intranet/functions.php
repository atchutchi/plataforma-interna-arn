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
	// O tema fornece um salto próprio com destino e foco explícitos.
	remove_action( 'wp_enqueue_scripts', 'wp_enqueue_block_template_skip_link' );
	remove_action( 'wp_footer', 'the_block_template_skip_link' );
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

/**
 * Usa a marca fornecida enquanto o CMS não tiver um ícone próprio.
 *
 * @param string $url URL de ícone já resolvido pelo WordPress.
 * @param int    $size Dimensão solicitada.
 * @return string URL do recurso de apresentação.
 */
function arn_intranet_default_site_icon( string $url, int $size ): string {
	if ( '' !== $url ) {
		return $url;
	}

	return get_theme_file_uri( $size <= 32 ? 'assets/images/favicon.svg' : 'assets/images/arn-logo.png' );
}

add_filter( 'get_site_icon_url', 'arn_intranet_default_site_icon', 10, 2 );

/**
 * Aplica a identidade visual à entrada nativa, sem alterar autenticação.
 */
function arn_intranet_login_assets(): void {
	wp_enqueue_style(
		'arn-intranet-login',
		get_theme_file_uri( 'assets/css/login.css' ),
		array(),
		(string) filemtime( get_theme_file_path( 'assets/css/login.css' ) )
	);
}

add_action( 'login_enqueue_scripts', 'arn_intranet_login_assets' );

/**
 * A marca da entrada aponta para a plataforma.
 *
 * @return string Endereço do portal.
 */
function arn_intranet_login_logo_url(): string {
	return home_url( '/' );
}

add_filter( 'login_headerurl', 'arn_intranet_login_logo_url' );

/**
 * Nome acessível da marca no ecrã de entrada.
 *
 * @return string Nome institucional.
 */
function arn_intranet_login_logo_text(): string {
	return __( 'ARN, Autoridade Reguladora Nacional', 'arn-intranet' );
}

add_filter( 'login_headertext', 'arn_intranet_login_logo_text' );

/**
 * Reserva a barra editorial a quem pode editar conteúdos.
 *
 * @param bool $show Preferência nativa do WordPress.
 * @return bool Estado visual, sem alterar competências.
 */
function arn_intranet_editor_toolbar( bool $show ): bool {
	return current_user_can( 'edit_posts' ) ? $show : false;
}

add_filter( 'show_admin_bar', 'arn_intranet_editor_toolbar' );
