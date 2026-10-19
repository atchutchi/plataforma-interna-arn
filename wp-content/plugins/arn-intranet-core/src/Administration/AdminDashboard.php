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
		if ( current_user_can( 'read' ) && ! is_network_admin() && ! is_user_admin() ) {
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

	/** Calcula os grupos depois dos menus autorizados de cada módulo. */
	public function sections(): array {
		if ( ! current_user_can( 'read' ) || is_network_admin() || is_user_admin() ) {
			return array();
		}
		$capabilities = array();
		foreach ( array( 'read', 'edit_pages', 'edit_posts', 'upload_files', 'manage_options' ) as $capability ) {
			$capabilities[ $capability ] = current_user_can( $capability );
		}
		$links = DashboardLinks::visible( $capabilities, wp_get_environment_type(), $this->toolRegistered( 'arn-google-access' ), $this->toolRegistered( 'arn-authorization-probe' ) );
		global $menu, $submenu;
		return DashboardSections::visible( $links, is_array( $menu ) ? $menu : array(), is_array( $submenu ) ? $submenu : array(), $capabilities, wp_get_environment_type() );
	}

	/** Apresenta marca e destinos nativos conforme a conta actual. */
	public function render(): void {
		$sections = $this->sections();
		if ( array() === $sections ) {
			return;
		}

		echo '<section class="arn-admin" aria-label="Administração ARN"><div class="arn-admin-header">';
		$logo_path = get_theme_root( 'arn-intranet' ) . '/arn-intranet/assets/images/arn-logo.png';
		if ( is_file( $logo_path ) ) {
			echo '<img class="arn-admin-logo" src="' . esc_url( get_theme_root_uri( 'arn-intranet' ) . '/arn-intranet/assets/images/arn-logo.png' ) . '" width="1491" height="684" alt="ARN, Autoridade Reguladora Nacional">';
		}
		echo '<div><p class="arn-admin-label">Plataforma interna</p><h3>Administração da plataforma</h3><p class="arn-admin-intro">Acede aos conteúdos e às ferramentas disponíveis para a tua conta.</p></div></div>';
		foreach ( $sections as $id => $section ) {
			echo '<nav class="arn-admin-section" aria-labelledby="arn-admin-section-' . esc_attr( $id ) . '"><h4 id="arn-admin-section-' . esc_attr( $id ) . '">' . esc_html( $section['title'] ) . '</h4><ul class="arn-admin-links">';
			foreach ( $section['links'] as $link ) {
				echo '<li><a href="' . esc_url( admin_url( $link['path'] ) ) . '"><span class="dashicons dashicons-' . esc_attr( $link['icon'] ) . '" aria-hidden="true"></span><span><span class="arn-admin-link-title">' . esc_html( $link['title'] ) . '</span><span class="arn-admin-link-description">' . esc_html( $link['description'] ) . '</span></span></a></li>';
			}
			echo '</ul></nav>';
		}
		echo '<p class="arn-admin-footnote">Os destinos voltam a verificar a tua sessão e as permissões actuais.</p></section>';
	}

	/**
	 * Confirma a existência do destino no menu já calculado pelo WordPress.
	 *
	 * @param string $slug Slug estático da ferramenta.
	 */
	private function toolRegistered( string $slug ): bool {
		global $submenu;
		if ( ! is_array( $submenu ) || ! is_array( $submenu['tools.php'] ?? null ) ) {
			return false;
		}
		foreach ( $submenu['tools.php'] as $item ) {
			if ( is_array( $item ) && 'manage_options' === ( $item[1] ?? null ) && ( $item[2] ?? null ) === $slug && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Compara uma variável a um valor calculado.
				return true;
			}
		}
		return false;
	}
}
