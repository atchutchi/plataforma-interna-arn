<?php
/**
 * Apresentação institucional do painel nativo do WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Administration;

/** Acrescenta orientação e atalhos sem alterar funções ou preferências. */
final class AdminDashboard {

	/**
	 * Guarda o ficheiro principal para resolver apenas recursos do plugin.
	 *
	 * @param string $plugin_file Ficheiro principal.
	 */
	public function __construct( private readonly string $plugin_file ) {}

	/** Liga a apresentação ao ciclo administrativo, depois da autenticação. */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'registerColors' ) );
		add_action( 'wp_dashboard_setup', array( $this, 'setup' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	/** Disponibiliza um esquema nativo que cada conta pode escolher no perfil. */
	public function registerColors(): void {
		wp_admin_css_color(
			'arn',
			'ARN institucional',
			plugins_url( 'assets/css/arn-colors.css', $this->plugin_file ),
			array( '#082d46', '#0b3a5b', '#1f6b4a', '#ffffff' ),
			array(
				'base'    => '#ffffff',
				'focus'   => '#ffffff',
				'current' => '#ffffff',
			)
		);
	}

	/** Regista a caixa do painel com capacidade de leitura actual. */
	public function setup(): void {
		if ( current_user_can( 'read' ) ) {
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
			wp_add_dashboard_widget( 'arn-dashboard', 'Painel ARN', array( $this, 'render' ), null, null, 'normal', 'high' );
		}
	}

	/**
	 * Carrega CSS apenas no painel, preservando os restantes editores.
	 *
	 * @param string $hook_suffix Ecrã administrativo.
	 */
	public function assets( string $hook_suffix ): void {
		if ( 'index.php' !== $hook_suffix || ! current_user_can( 'read' ) || is_network_admin() || is_user_admin() ) {
			return;
		}
		$path = dirname( $this->plugin_file ) . '/assets/css/dashboard.css';
		wp_enqueue_style( 'arn-dashboard', plugins_url( 'assets/css/dashboard.css', $this->plugin_file ), array(), (string) filemtime( $path ) );
		$font_root = get_theme_root( 'arn-intranet' ) . '/arn-intranet/assets/fonts/';
		$font_url  = get_theme_root_uri( 'arn-intranet' ) . '/arn-intranet/assets/fonts/';
		if ( is_file( $font_root . 'SourceSans3.ttf' ) && is_file( $font_root . 'Manrope.ttf' ) ) {
			wp_add_inline_style(
				'arn-dashboard',
				'@font-face{font-family:"ARN Admin Body";font-style:normal;font-weight:200 900;font-display:swap;src:url("' . esc_url( $font_url . 'SourceSans3.ttf' ) . '")}@font-face{font-family:"ARN Admin Heading";font-style:normal;font-weight:200 800;font-display:swap;src:url("' . esc_url( $font_url . 'Manrope.ttf' ) . '")}'
			);
		}
	}

	/** Apresenta marca e destinos nativos conforme a conta actual. */
	public function render(): void {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}
		$capabilities = array();
		foreach ( array( 'read', 'edit_pages', 'edit_posts', 'upload_files', 'manage_options' ) as $capability ) {
			$capabilities[ $capability ] = current_user_can( $capability );
		}
		$links = DashboardLinks::visible( $capabilities, wp_get_environment_type(), $this->toolRegistered( 'arn-google-access' ), $this->toolRegistered( 'arn-authorization-probe' ) );

		echo '<section class="arn-admin" aria-label="Administração ARN"><div class="arn-admin-header">';
		$logo_path = get_theme_root( 'arn-intranet' ) . '/arn-intranet/assets/images/arn-logo.png';
		if ( is_file( $logo_path ) ) {
			echo '<img class="arn-admin-logo" src="' . esc_url( get_theme_root_uri( 'arn-intranet' ) . '/arn-intranet/assets/images/arn-logo.png' ) . '" width="1491" height="684" alt="ARN, Autoridade Reguladora Nacional">';
		}
		echo '<div><p class="arn-admin-label">Plataforma interna</p><h3>Administração da plataforma</h3><p class="arn-admin-intro">Acede aos conteúdos e às ferramentas disponíveis para a tua conta.</p></div></div>';
		echo '<nav aria-label="Atalhos do painel ARN"><ul class="arn-admin-links">';
		foreach ( $links as $link ) {
			echo '<li><a href="' . esc_url( admin_url( $link['path'] ) ) . '"><span class="dashicons dashicons-' . esc_attr( $link['icon'] ) . '" aria-hidden="true"></span><span><span class="arn-admin-link-title">' . esc_html( $link['title'] ) . '</span><span class="arn-admin-link-description">' . esc_html( $link['description'] ) . '</span></span></a></li>';
		}
		echo '</ul></nav><p class="arn-admin-footnote">Os atalhos disponíveis dependem das permissões actuais da tua conta.</p></section>';
	}

	/**
	 * Confirma a existência do destino no menu já calculado pelo WordPress.
	 *
	 * @param string $slug Slug estático da ferramenta.
	 */
	private function toolRegistered( string $slug ): bool {
		global $submenu;
		foreach ( $submenu['tools.php'] ?? array() as $item ) {
			if ( isset( $item[1] ) && ( $item[2] ?? null ) === $slug && current_user_can( $item[1] ) ) { // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Compara uma variável a um valor calculado.
				return true;
			}
		}
		return false;
	}
}
