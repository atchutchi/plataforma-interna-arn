<?php
/** Minimal HTTP boundary for the real published controller, with a closed runtime.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting.FunctionComment, Universal.Files.SeparateFunctionsFromOO.Mixed, WordPress.Security.NonceVerification.Recommended, WordPress.WP.AlternativeFunctions -- This test harness deliberately does not load WordPress.
if ( 'cli-server' !== PHP_SAPI ) {
	exit;
}
require dirname( __DIR__, 3 ) . '/vendor/autoload.php';
/** Minimal page object for the real route type check. */
final class WP_Post {
	/** Closed test page identifier.
	 *
	 * @var int
	 */
	public int $ID = 1;
}
function get_page_by_path( string $slug ): ?WP_Post {
	return 'noticias' === $slug ? new WP_Post() : null;
}
function is_page(): bool {
	return '/noticias/' === parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
}
function get_queried_object_id(): int {
	return is_page() ? 1 : 0;
}
function is_front_page(): bool {
	return '/' === parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
}
function wp_unslash( array $input ): array {
	return $input;
}
function wp_get_environment_type(): string {
	return 'local';
}
function status_header( int $status ): void {
	http_response_code( $status );
}
function nocache_headers(): void {
	header( 'Expires: Wed, 11 Jan 1984 05:00:00 GMT' );
}
\Arn\Intranet\News\NewsPublishedController::protect();
// A denied publication must exit before this simulated later login redirect.
header( 'Location: /wp-login.php', true, 302 );
echo 'later-login-redirect';
