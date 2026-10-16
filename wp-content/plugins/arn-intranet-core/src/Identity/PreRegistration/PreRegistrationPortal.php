<?php
/**
 * Superfície mínima antes de qualquer autorização de negócio.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Throwable;

// phpcs:disable Squiz.Commenting -- Guarda central anterior aos módulos e às suas fontes.
final class PreRegistrationPortal {
	public static function register(): void {
		add_action( 'init', array( self::class, 'enforce' ), -1000 );}
	public static function route( string $method, string $path, array $query, string $root ): string {
		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return 'deny';}
		if ( $path === $root && array() === $query ) {
			return 'home';}
		if ( rtrim( $root, '/' ) . '/wp-login.php' === $path && 'logout' === ( $query['action'] ?? null ) && ! array_diff( array_keys( $query ), array( 'action', '_wpnonce', 'redirect_to' ) ) ) {
			foreach ( $query as$value ) {
				if ( ! is_string( $value ) ) {
					return 'deny';
				}
			}return 'logout';}
		return 'deny';
	}
	public static function enforce(): void {
		if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
			return;}
		$id = get_current_user_id();
		if ( $id < 1 ) {
			return;}
		try {
			$registration = CurrentPreRegistration::lookup( $id );
			if ( null === $registration ) {
				return;}
			nocache_headers();
			header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
			header( 'Vary: Cookie', false );
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
			if ( PreRegistrationState::Linked !== $registration->state || ! CurrentPreRegistration::enabled() ) {
				self::refuse();}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Só decide a recusa de superfícies, sem tratar ou persistir dados do pedido.
			$query  = wp_unslash( $_GET );
			$method = $_SERVER['REQUEST_METHOD'] ?? '';
			$uri    = $_SERVER['REQUEST_URI'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Dados usados só na comparação fechada de rotas, nunca na saída.
			$root   = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			$path   = wp_parse_url( $uri, PHP_URL_PATH );
			$route  = is_string( $method ) && is_string( $root ) && is_string( $path ) ? self::route( $method, $path, $query, $root ) : 'deny';
			if ( 'logout' === $route ) {
				return;}
			if ( 'home' !== $route ) {
				self::refuse();}
			$template = get_theme_file_path( 'portal/pre-registration.php' );
			if ( 'arn-intranet' !== get_stylesheet() || ! is_readable( $template ) ) {
				self::refuse();}
			status_header( 200 );
			header( 'Content-Type: text/html; charset=UTF-8' );
			if ( 'GET' === $method ) {
				require $template;} // $registration é o único conteúdo pessoal disponível à vista.
			exit;
		} catch ( Throwable ) {
			self::refuse();}
	}
	private static function refuse(): never {
		status_header( 403 );
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		echo esc_html__( 'Este acesso ainda não está disponível.', 'arn-intranet-core' );
		exit;}
}
