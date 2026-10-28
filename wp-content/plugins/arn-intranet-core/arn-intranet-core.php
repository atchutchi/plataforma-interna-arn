<?php
/**
 * Plugin institucional da intranet ARN.
 *
 * @package ArnIntranetCore
 */

/**
 * Plugin Name: Núcleo da intranet ARN
 * Description: Regras institucionais da plataforma interna. Na fundação, o acesso fictício só pode funcionar no ambiente local.
 * Version: 0.9.0
 * Requires at least: 6.7
 * Requires PHP: 8.4
 * Author: ARN
 * Text Domain: arn-intranet-core
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

use Arn\Intranet\Notifications\LocalNotificationsPage;
use Arn\Intranet\Access\LocalAccessPolicy;
use Arn\Intranet\Access\LocalFixtureAccess;
use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Access\PrivatePortal;
use Arn\Intranet\Autoloader;
use Arn\Intranet\Administration\AdminDashboard;
use Arn\Intranet\Authorization\AuthorizationProbe;
use Arn\Intranet\Authorization\Records\LocalRecordsPage;
use Arn\Intranet\Authorization\Session\WordPressLocalAccountSessions;
use Arn\Intranet\Authorization\Session\LocalAccountStatePage;
use Arn\Intranet\Authorization\Session\LocalGrantManagementPage;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageProbe;
use Arn\Intranet\Identity\GoogleLoginController;
use Arn\Intranet\Identity\WordPressFederatedAccess;
use Arn\Intranet\EditorialPreview\Portal;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFilesPage;
use Arn\Intranet\Documents\Catalog\DocumentCatalogPage;
use Arn\Intranet\Documents\Catalog\DocumentFileController;
use Arn\Intranet\News\NewsroomPage;
use Arn\Intranet\News\NewsFileController;
use Arn\Intranet\News\NewsPreviewController;
use Arn\Intranet\News\NewsPublishedController;
use Arn\Intranet\People\PeoplePortal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/src/Autoloader.php';

Autoloader::register( __DIR__ . '/src' );

\Arn\Intranet\Documents\PrivateFiles\NativeMediaBoundary::register();
\Arn\Intranet\Documents\PrivateFiles\ReconciliationPage::register();
\Arn\Intranet\Media\QueuePage::register();
\Arn\Intranet\Organization\Register\Page::register();
\Arn\Intranet\Media\ConsumerPage::register();

$arn_vendor = defined( 'ARN_OIDC_VENDOR_AUTOLOAD' ) ? ARN_OIDC_VENDOR_AUTOLOAD : dirname( __DIR__, 3 ) . '/vendor/autoload.php';
if ( is_string( $arn_vendor ) && is_readable( $arn_vendor ) ) {
	require_once $arn_vendor;
}
unset( $arn_vendor );

// As guardas persistentes não dependem da activação do cliente Google.
( new \Arn\Intranet\Identity\PreRegistration\WordPressPreRegistrationAccess() )->register();
\Arn\Intranet\Identity\PreRegistration\PreRegistrationPortal::register();
if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
	$arn_workspace_command = new \Arn\Intranet\Identity\PreRegistration\WorkspacePreRegistrationCommand();
	foreach ( array(
		'install-schema' => 'installSchema',
		'preflight'      => 'preflight',
		'provision'      => 'provision',
		'disable'        => 'disable',
		'enable'         => 'enable',
		'ready'          => 'ready',
		'renew'          => 'renew',
	) as $arn_workspace_action => $arn_workspace_method ) {
		\WP_CLI::add_command( 'arn workspace ' . $arn_workspace_action, array( $arn_workspace_command, $arn_workspace_method ) );
	}
	unset( $arn_workspace_command, $arn_workspace_action, $arn_workspace_method );
}

if ( class_exists( \GuzzleHttp\Client::class ) ) {
	( new GoogleLoginController() )->registerHooks();
}

( new WordPressFederatedAccess(
	static fn( int $id ) => GoogleLoginController::linkedUser( $id ),
	static fn(): bool => class_exists( \GuzzleHttp\Client::class ) && null !== GoogleLoginController::configuration()
) )->register();

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

( new LocalPrivateFiles() )->register();
PrivatePortal::register();
PeoplePortal::register();
\Arn\Intranet\F112\PostTypes::register();
\Arn\Intranet\F112\Portal::register();
\Arn\Intranet\Tasks\Bootstrap::register();
\Arn\Intranet\Requests\Bootstrap::register();
\Arn\Intranet\Support\Bootstrap::register();
Portal::register();
\Arn\Intranet\EditorialPreview\NavigationPageList::register();
\Arn\Intranet\EditorialPreview\ModuleNavigation::register();
DocumentCatalogPage::register();
DocumentFileController::register();
NewsroomPage::register();
NewsFileController::register();
NewsPreviewController::register();
NewsPublishedController::register();

( new LocalFixtureAccess(
	arn_intranet_core_environment_type(),
	arn_intranet_core_fictional_flag()
) )->register();

( new WordPressLocalAccountSessions() )->register();

add_action( 'plugins_loaded', array( new AuthorizationProbe(), 'register' ) );

add_action( 'wp_loaded', array( new AuthorizationStorageProbe(), 'register' ) );

add_action( 'wp_loaded', array( new LocalAccountStatePage(), 'register' ) );

add_action( 'wp_loaded', array( new LocalGrantManagementPage(), 'register' ) );

add_action( 'wp_loaded', array( new LocalRecordsPage(), 'register' ) );

( new AdminDashboard( __FILE__ ) )->register();
\Arn\Intranet\Cms\SettingsPage::register();

add_action( 'wp_loaded', array( new LocalNotificationsPage(), 'register' ) );

add_action( 'wp_loaded', array( new \Arn\Intranet\Organization\LocalUnitsPage(), 'register' ) );
add_action( 'wp_loaded', array( new \Arn\Intranet\Organization\ImportPreview\LocalImportPreviewPage(), 'register' ) );
add_action( 'wp_loaded', array( new LocalPrivateFilesPage(), 'register' ) );

\Arn\Intranet\Leave\Bootstrap::register();
