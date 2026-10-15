<?php
/** Closed legacy installer gate exercise.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\News\LocalNewsInstaller;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
if ( 'local' !== wp_get_environment_type() ) {
	$gate_prefix  = 'arn_ni_gate_' . bin2hex( random_bytes( 5 ) ) . '_';
	$identities   = static function (): array {
		$found = array();
		foreach ( array( 'autor.noticias.teste', 'editor.noticias.teste' ) as $login ) {
			$user    = get_user_by( 'login', $login );
			$found[] = $user instanceof WP_User ? $user->ID : null;
		}
		return $found;
	};
	$before_users = $identities();
	$rejected     = false;
	try {
		( new LocalNewsInstaller( $gate_prefix ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$after_users = $identities();
	global $wpdb;
	$tables = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $gate_prefix . 'arn_news' ) );
	if ( ! $rejected || $before_users !== $after_users || 0 !== $tables ) {
		throw new RuntimeException( 'Falhou: instalador não recusou gate antes de escritas.' );
	}
	echo "Instalador de notícias: recusa fora de local verificada.\n";
	return;
}
$prefix   = 'arn_ed_' . bin2hex( random_bytes( 5 ) ) . '_';
$rejected = false;
try {
	( new LocalNewsInstaller( $prefix ) )->install();
} catch ( RuntimeException ) {
	$rejected = true;
}
global $wpdb;
$tables = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $prefix . 'arn_news' ) );
if ( ! $rejected || 0 !== $tables ) {
	throw new RuntimeException( 'Falhou: entrada F1-10 criou esquema depois da desactivação.' );
}
echo "Instalador F1-10: entrada legada recusada antes de SQL.\n";
