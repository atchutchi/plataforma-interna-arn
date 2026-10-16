<?php
/**
 * Preparação final explícita antes de criar contas ou aceitar o primeiro vínculo.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\{GoogleLoginController, IdentityWpdb, WpdbIdentityLinkRepository};
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Comando local com erro constante, sem migrações automáticas.
final class LocalWorkspaceReadiness {
	public function __construct( private readonly WorkspaceBatch $batch ) {}
	public function complete( int $actor_id ): void {
		if ( ! WorkspacePreRegistrationCommand::permitted() || get_current_user_id() !== $actor_id || 73 !== count( $this->batch->entries ) || time() < $this->batch->observed_at || time() >= $this->batch->expires_at || ! class_exists( \GuzzleHttp\Client::class ) || null === GoogleLoginController::configuration() || 'arn-intranet' !== get_stylesheet() || ! is_readable( get_theme_file_path( 'portal/pre-registration.php' ) ) || ! defined( 'ARN_WORKSPACE_PRIVATE_DIR' ) || ! is_string( ARN_WORKSPACE_PRIVATE_DIR ) ) {
			throw new RuntimeException( 'Preparação Workspace indisponível.' );}
		WorkspacePreRegistrationCommand::assertRuntime();
		( new PrivateWorkspaceSource( ARN_WORKSPACE_PRIVATE_DIR, ABSPATH, array( dirname( __DIR__, 6 ) ) ) )->protectedPath( ARN_WORKSPACE_PRIVATE_DIR, true );
		global $wpdb;
		$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		try {
			$database->set_prefix( $wpdb->prefix );
			if ( ! ( new WpdbPreRegistrationRepository( $database ) )->hasSchema() || ! ( new WpdbIdentityLinkRepository( $database ) )->hasSchema() ) {
				throw new RuntimeException( 'Preparação Workspace indisponível.' );}
		} finally {
			$database->close();}
		self::assertGuards();
		if ( '1' !== get_option( 'arn_workspace_prereg_ready' ) ) {
			update_option( 'arn_workspace_prereg_ready', '1', false );}
		if ( '1' !== get_option( 'arn_workspace_prereg_ready' ) ) {
			throw new RuntimeException( 'Preparação Workspace indisponível.' );}
	}
	public static function assertGuards(): void {
		global $wp_filter;
		foreach ( array(
			'authenticate'            => 'filterAuthentication',
			'determine_current_user'  => 'filterCurrentUser',
			'allow_password_reset'    => 'filterPasswordReset',
			'validate_password_reset' => 'validatePasswordReset',
			'wp_is_application_passwords_available_for_user' => 'filterApplicationPasswords',
			'send_auth_cookies'       => 'filterSendAuthCookies',
		)as$hook => $method ) {
			$found = false;
			foreach ( $wp_filter[ $hook ]->callbacks[ PHP_INT_MAX ] ?? array()as$callback ) {
				$function = $callback['function'] ?? null;
				if ( is_array( $function ) && ( $function[0] ?? null ) instanceof WordPressPreRegistrationAccess && ( $function[1] ?? null ) === $method ) {
					$found = true;}
			}
			if ( ! $found ) {
				throw new RuntimeException( 'Guardas Workspace indisponíveis.' );}
		}
		if ( -1000 !== has_action( 'init', array( PreRegistrationPortal::class, 'enforce' ) ) ) {
			throw new RuntimeException( 'Guardas Workspace indisponíveis.' );}
	}
}
