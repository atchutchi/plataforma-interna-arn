<?php
/** Native functional dashboard guards inside the exclusive Services fixture.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Administration\AdminDashboard;
use Arn\Intranet\F112\ManagementPage;

// phpcs:disable Squiz.Commenting, WordPress.WP.GlobalVariablesOverride.Prohibited -- Temporary native menu/screen context, restored in finally.
function arn_dashboard_management_fixture( array $ids, int $admin, Closure $login, Closure $assert, Closure $snapshot ): void {
	if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'local' !== wp_get_environment_type() ) {
		throw new RuntimeException( 'Painel fictício apenas em CLI local.' );
	}
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
	require_once ABSPATH . 'wp-admin/includes/screen.php';
	$names  = array( 'menu', 'submenu', 'admin_page_hooks', '_registered_pages', '_parent_pages', 'current_screen' );
	$saved  = array();
	$before = array();
	foreach ( $names as $name ) {
		$saved[ $name ] = array( array_key_exists( $name, $GLOBALS ), $GLOBALS[ $name ] ?? null );
	}
	foreach ( array( 'arn_authz_accounts', 'arn_authz_grants', 'arn_authz_audit', 'arn_comm_objects', 'arn_comm_versions', 'arn_comm_audit', 'arn_comm_notifications', 'arn_calendar_objects', 'arn_calendar_versions', 'arn_calendar_audit', 'arn_calendar_notifications' ) as $table ) {
		$before[ $table ] = $snapshot( $table );
	}
	$dashboard = new AdminDashboard( dirname( __DIR__, 2 ) . '/wp-content/plugins/arn-intranet-core/arn-intranet-core.php' );
	try {
		$GLOBALS['current_screen'] = WP_Screen::get( 'dashboard' );
		$sections                  = static function (): array {
			$GLOBALS['menu']    = array();
			$GLOBALS['submenu'] = array();
			ManagementPage::menu();
			return ( new AdminDashboard( '' ) )->sections();
		};
		foreach ( array(
			'a'        => array(),
			'author'   => array( 'communications' ),
			'reviewer' => array( 'communications' ),
			'events'   => array( 'calendar' ),
			'manager'  => array( 'communications', 'calendar' ),
		) as $actor => $expected ) {
			$login( $ids[ $actor ] );
			$groups = $sections();
			$assert( $expected === array_column( $groups['management']['links'] ?? array(), 'id' ), 'painel acompanha menu F112 do perfil ' . $actor ); // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- Expected and actual are both calculated.
			$assert( ! isset( $groups['cms'] ) && ! isset( $groups['laboratory'] ) && array( 'profile' ) === array_column( $groups['account']['links'], 'id' ), 'competências funcionais não dão gestão técnica ' . $actor );
		}
		$login( $ids['manager'], false );
		$assert( ! isset( $sections()['management'] ), 'painel retira gestão com geração de sessão inválida' );
		wp_set_current_user( $admin );
		$assert( ! isset( $sections()['management'] ), 'administrador técnico não recebe gestão funcional' );
		$GLOBALS['submenu'] = array( 'tools.php' => 'invalid' );
		$assert( ! isset( $dashboard->sections()['laboratory'] ), 'pai de ferramentas malformado não interrompe o painel' );
		$GLOBALS['menu']    = false;
		$GLOBALS['submenu'] = new stdClass();
		$malformed          = $dashboard->sections();
		$assert( ! isset( $malformed['management'] ) && ! isset( $malformed['laboratory'] ), 'raízes nativas malformadas fecham gestão e ferramentas' );
		foreach ( array( 'dashboard-network', 'dashboard-user' ) as $screen ) {
			$GLOBALS['current_screen'] = WP_Screen::get( $screen );
			$assert( array() === $dashboard->sections(), 'painel não aparece no contexto ' . $screen );
		}
		$GLOBALS['current_screen'] = WP_Screen::get( 'dashboard' );
		wp_set_current_user( 0 );
		$assert( array() === $dashboard->sections(), 'painel anónimo sem destinos' );
		foreach ( $before as $table => $rows ) {
			$assert( $rows === $snapshot( $table ), 'consulta do painel conserva ' . $table );
		}
	} finally {
		foreach ( $saved as $name => [$exists, $value] ) {
			if ( $exists ) {
				$GLOBALS[ $name ] = $value;
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}
		$login( $ids['a'] );
	}
}
