<?php
/** Regressão de falha inicial com wpdb real, sem configuração nem dados do site.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// Ensaio standalone: mede e restaura handlers/reporting para provar ausência de saída.
// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_error_reporting, WordPress.DB.RestrictedFunctions
// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Session\WordPressLocalAccountSessions;
use Arn\Intranet\Autoloader;
use Arn\Intranet\Identity\IdentityWpdb;

require dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src/Autoloader.php';
Autoloader::register( dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/src' );
require dirname( __DIR__ ) . '/phpunit/WordPressDoubles.php';

$core = $argv[1] ?? '';
if ( ! is_file( $core ) ) {
	throw new RuntimeException( 'Indica o ficheiro class-wpdb.php do core local.' );
}
require $core;

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_DISPLAY', true );
define( 'WP_CONTENT_DIR', __DIR__ );
define( 'DB_USER', 'fictional-connection-regression' );
define( 'DB_PASSWORD', 'fictional-not-a-secret' );
define( 'DB_NAME', 'fictional-not-a-database' );
define( 'DB_HOST', '127.0.0.1:1' );
define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', true );
define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );

/** Detecta o percurso de bail sem terminar o ensaio.
 *
 * @param string $message Mensagem que nunca pode chegar ao público.
 * @throws RuntimeException Quando o core tenta terminar o pedido.
 */
function wp_die( string $message ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Double fiel à assinatura de wp_die.
	throw new RuntimeException( 'wp_die foi chamado.' );
}
/** Stub do caminho de falha nativo. */
function wp_load_translations_early(): void {}
/** Token fictício, guardado apenas no double. */
function wp_get_session_token(): string {
	return 'fictional-token';
}
/** Ambiente fictício explícito. */
function wp_get_environment_type(): string {
	return 'local';
}

$warnings = 0;
$handler  = static function () use ( &$warnings ): bool {
	++$warnings;
	return true;
};
set_error_handler( $handler );
$original_reporting = error_reporting();
$original_mysqli    = ( new mysqli_driver() )->report_mode;
mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
ob_start();
$failure = '';
try {
	new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
} catch ( Throwable $error ) {
	$failure = $error->getMessage();
}
$output        = ob_get_clean();
$after_handler = set_error_handler( static fn(): bool => true );
restore_error_handler();
$restored = $handler === $after_handler && error_reporting() === $original_reporting && ( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT ) === ( new mysqli_driver() )->report_mode;
$wpdb     = (object) array( 'prefix' => 'fictional_' );
ArnWordPressDoubles::reset();
ArnWordPressDoubles::$users[10] = new WP_User( 10, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
ArnWordPressDoubles::$meta[10]  = array( LocalFixtureUser::META_KEY => '1' );
$session                        = array(
	'arn_authz_local'    => true,
	'arn_authz_user_id'  => 10,
	'arn_authz_revision' => 1,
);
ArnWordPressDoubles::$sessions[10]['fictional-token'] = $session;
$guard = new WordPressLocalAccountSessions();
ob_start();
$login   = $guard->filterAuthentication( ArnWordPressDoubles::$users[10] );
$current = $guard->filterCurrentUser( 10 );
$revoked = array() === ArnWordPressDoubles::$sessions[10];
ArnWordPressDoubles::$sessions[10]['fictional-token'] = $session;
$sent    = $guard->filterSendAuthCookies( true, 0, 0, 10, '', 'fictional-token' );
$output .= ob_get_clean();
restore_error_handler();
mysqli_report( $original_mysqli );
if ( 'Ligação dedicada indisponível.' !== $failure || '' !== $output || 0 !== $warnings || ! $restored || ! $login instanceof WP_Error || false !== $current || ! $revoked || false !== $sent || array() !== ArnWordPressDoubles::$sessions[10] ) {
	fwrite( STDERR, 'FAIL: abertura silenciosa, restauração ou revogação não confirmadas.' . PHP_EOL );
	exit( 1 );
}
echo 'PASS: wpdb real com WP_DEBUG e WP_DEBUG_DISPLAY, erro estático, zero saída/avisos, estado restaurado e sessões revogadas.' . PHP_EOL;
