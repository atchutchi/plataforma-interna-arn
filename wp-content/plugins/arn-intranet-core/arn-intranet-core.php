<?php
/**
 * Plugin institucional da intranet ARN.
 *
 * @package ArnIntranetCore
 */

/**
 * Plugin Name: Núcleo da intranet ARN
 * Description: Regras institucionais da plataforma interna. Na fundação, o acesso fictício só pode funcionar no ambiente local.
 * Version: 0.1.0
 * Requires at least: 6.7
 * Requires PHP: 8.4
 * Author: ARN
 * Text Domain: arn-intranet-core
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Arn\Intranet\Access\LocalAccessPolicy;
use Arn\Intranet\Access\LocalFixtureAccess;
use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Access\PrivatePortal;
use Arn\Intranet\Autoloader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Autoloader.php';

Autoloader::register( __DIR__ . '/src' );

/**
 * Traduções do plugin. As strings de origem já estão em português de Portugal.
 */
function arn_intranet_core_load_textdomain(): void {
	load_plugin_textdomain( 'arn-intranet-core', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/**
 * Ambiente efetivo. Sem WordPress, a decisão fica fechada.
 */
function arn_intranet_core_environment_type(): string {
	if ( function_exists( 'wp_get_environment_type' ) ) {
		return (string) wp_get_environment_type();
	}

	return 'production';
}

/**
 * A flag tem de ser o booleano verdadeiro. Texto ou ausência não chegam.
 */
function arn_intranet_core_fictional_flag(): bool {
	return defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) && true === ARN_ALLOW_FICTIONAL_LOCAL_ACCESS;
}

/**
 * Cria a conta fictícia apenas quando a política o permite.
 */
function arn_intranet_core_maybe_prepare_local_fixture(): void {
	if ( ! LocalAccessPolicy::fictionalAccessAllowed( arn_intranet_core_environment_type(), arn_intranet_core_fictional_flag() ) ) {
		return;
	}

	if ( ! function_exists( 'get_user_by' ) || ! function_exists( 'email_exists' ) || ! function_exists( 'wp_insert_user' ) || ! function_exists( 'update_user_meta' ) ) {
		return;
	}

	$existing = get_user_by( 'login', LocalFixtureUser::LOGIN );
	if ( $existing instanceof WP_User && LocalFixtureAccess::isFixture( $existing ) ) {
		update_user_meta( $existing->ID, LocalFixtureUser::META_KEY, '1' );
		return;
	}

	if ( $existing || email_exists( LocalFixtureUser::EMAIL ) ) {
		return;
	}

	wp_insert_user(
		array(
			'user_login'   => LocalFixtureUser::LOGIN,
			'user_email'   => LocalFixtureUser::EMAIL,
			'user_pass'    => LocalFixtureUser::PASSWORD,
			'display_name' => LocalFixtureUser::DISPLAY_NAME,
			'role'         => LocalFixtureUser::ROLE,
			'locale'       => 'pt_PT',
			'meta_input'   => array( LocalFixtureUser::META_KEY => '1' ),
		)
	);
}

/**
 * Explica o modo fictício a quem administra o ambiente local.
 */
function arn_intranet_core_admin_notices(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$allowed = LocalAccessPolicy::fictionalAccessAllowed(
		arn_intranet_core_environment_type(),
		arn_intranet_core_fictional_flag()
	);

	if ( $allowed ) {
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Acesso fictício local ativo. Este modo fica limitado a este computador e não funciona em homologação ou produção.', 'arn-intranet-core' );
		echo '</p></div>';
		return;
	}

	if ( arn_intranet_core_fictional_flag() ) {
		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'O acesso fictício foi recusado porque o ambiente não é local.', 'arn-intranet-core' );
		echo '</p></div>';
	}
}

add_action( 'init', 'arn_intranet_core_load_textdomain' );
add_action( 'init', 'arn_intranet_core_maybe_prepare_local_fixture' );
add_action( 'admin_notices', 'arn_intranet_core_admin_notices' );

PrivatePortal::register();

( new LocalFixtureAccess(
	arn_intranet_core_environment_type(),
	arn_intranet_core_fictional_flag()
) )->register();
