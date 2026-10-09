<?php
/**
 * Regra de portal privado.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Access;

/**
 * Decide o que um pedido sem sessão pode alcançar. Tudo o resto exige autenticação.
 */
final class PrivatePortalPolicy {

	/**
	 * Rotas REST abertas sem sessão. Vazio até o cliente OIDC de F1-02 exigir alguma.
	 *
	 * @var string[]
	 */
	private const ANONYMOUS_REST_PREFIXES = array();

	/**
	 * Um pedido anónimo só pode chegar à entrada e aos recursos dessa entrada.
	 *
	 * @param string $path           Caminho do pedido, sem query string.
	 * @param bool   $is_login_page  O WordPress identificou a página de login.
	 * @param bool   $is_admin_ajax  O pedido é admin-ajax.php.
	 * @param string $ajax_action    Ação AJAX pedida, quando aplicável.
	 */
	public static function anonymousRequestAllowed( string $path, bool $is_login_page, bool $is_admin_ajax, string $ajax_action = '' ): bool {
		if ( $is_login_page ) {
			return true;
		}

		if ( $is_admin_ajax ) {
			return self::anonymousAjaxActionAllowed( $ajax_action );
		}

		return '/wp-login.php' === self::normalizePath( $path );
	}

	/**
	 * Só o heartbeat da página de login fica disponível sem sessão.
	 *
	 * @param string $action Ação AJAX.
	 */
	public static function anonymousAjaxActionAllowed( string $action ): bool {
		return 'heartbeat' === $action;
	}

	/**
	 * A REST sem sessão fica fechada, exceto os prefixos expressamente listados.
	 *
	 * @param string $route Rota REST, por exemplo /wp/v2/users.
	 */
	public static function anonymousRestRouteAllowed( string $route ): bool {
		$normalized = self::normalizePath( $route );

		foreach ( self::ANONYMOUS_REST_PREFIXES as $prefix ) {
			if ( 0 === strncmp( $normalized, $prefix, strlen( $prefix ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Funcionalidades públicas do WordPress que o portal desliga.
	 *
	 * @return string[]
	 */
	public static function disabledPublicSurfaces(): array {
		return array( 'feeds', 'sitemaps', 'oembed', 'xmlrpc', 'rsd', 'author-archives' );
	}

	/**
	 * Normaliza barras e remove a query string.
	 *
	 * @param string $path Caminho bruto.
	 */
	private static function normalizePath( string $path ): string {
		$without_query = (string) strtok( $path, '?' );
		$trimmed       = '/' . ltrim( $without_query, '/' );

		return 1 === strlen( $trimmed ) ? $trimmed : rtrim( $trimmed, '/' );
	}
}
