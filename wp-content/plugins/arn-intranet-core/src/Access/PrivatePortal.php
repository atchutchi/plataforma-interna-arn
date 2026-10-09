<?php
/**
 * Ligação da regra de portal privado aos hooks do WordPress.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Access;

use WP_Error;

/**
 * Fecha páginas, REST, AJAX, feeds, sitemaps, oEmbed e XML-RPC a quem não tem sessão.
 */
final class PrivatePortal {

	/**
	 * Regista os hooks.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'requireSessionForFrontend' ), 0 );
		add_filter( 'rest_authentication_errors', array( self::class, 'requireSessionForRest' ), 99 );
		add_action( 'admin_init', array( self::class, 'requireSessionForAjax' ), 0 );

		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'wp_sitemaps_enabled', '__return_false' );
		add_filter( 'embed_oembed_discover', '__return_false' );

		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );

		foreach ( array( 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments' ) as $feed ) {
			add_action( $feed, array( self::class, 'refuseFeed' ), 1 );
		}
	}

	/**
	 * Sem sessão, qualquer página pública redireciona para a entrada.
	 */
	public static function requireSessionForFrontend(): void {
		if ( is_user_logged_in() ) {
			return;
		}

		$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ) : '/';

		if ( PrivatePortalPolicy::anonymousRequestAllowed( $path, self::isLoginPage(), false ) ) {
			return;
		}

		if ( is_feed() || is_author() ) {
			self::refuseFeed();
		}

		nocache_headers();
		wp_safe_redirect( wp_login_url( home_url( $path ) ), 302 );
		exit;
	}

	/**
	 * A REST só responde a pedidos autenticados.
	 *
	 * @param WP_Error|null|true $result Resultado anterior.
	 * @return WP_Error|null|true
	 */
	public static function requireSessionForRest( $result ) {
		if ( ! empty( $result ) ) {
			return $result;
		}

		if ( is_user_logged_in() ) {
			return $result;
		}

		$route = isset( $GLOBALS['wp']->query_vars['rest_route'] ) ? (string) $GLOBALS['wp']->query_vars['rest_route'] : '';

		if ( '' === $route && isset( $_SERVER['REQUEST_URI'] ) ) {
			$request_uri = sanitize_text_field( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
			$prefix      = '/' . rest_get_url_prefix();
			$position    = strpos( $request_uri, $prefix );
			$route       = false === $position ? '' : substr( $request_uri, $position + strlen( $prefix ) );
		}

		if ( PrivatePortalPolicy::anonymousRestRouteAllowed( $route ) ) {
			return $result;
		}

		return new WP_Error(
			'arn_rest_login_required',
			__( 'Esta API exige sessão iniciada.', 'arn-intranet-core' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * O admin-ajax.php sem sessão só aceita o heartbeat.
	 */
	public static function requireSessionForAjax(): void {
		if ( ! wp_doing_ajax() || is_user_logged_in() ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Só se lê o nome da ação para decidir a recusa.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( PrivatePortalPolicy::anonymousAjaxActionAllowed( $action ) ) {
			return;
		}

		wp_die( esc_html__( 'Esta ação exige sessão iniciada.', 'arn-intranet-core' ), '', array( 'response' => 401 ) );
	}

	/**
	 * Feeds e arquivos de autor não existem no portal.
	 */
	public static function refuseFeed(): void {
		wp_die( esc_html__( 'Este conteúdo não está disponível sem sessão.', 'arn-intranet-core' ), '', array( 'response' => 404 ) );
	}

	/**
	 * Identifica a página de login do WordPress.
	 */
	private static function isLoginPage(): bool {
		$script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : '';

		return 'wp-login.php' === $script;
	}
}
