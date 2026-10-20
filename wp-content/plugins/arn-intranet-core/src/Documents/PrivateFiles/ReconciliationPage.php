<?php
/** Local technical diagnostics, deliberately without business-content access.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Arn\Intranet\F112\Controller;
use Arn\Intranet\Identity\IdentityWpdb;
use Throwable;

// phpcs:disable Squiz.Commenting
/** Explicit opt-in and named fictional administrator, no public or REST report. */
final class ReconciliationPage {
	private static ?array $report = null;
	private static bool $prepared = false;

	public static function register(): void {
		add_action( 'init', array( self::class, 'guard' ), -30 );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
	}

	public static function enabled( string $environment, mixed $flag, mixed $configured_id, int $id, string $email, bool $capable, bool $session ): bool {
		return 'local' === $environment && true === $flag && is_int( $configured_id ) && $configured_id > 0 && $id === $configured_id && 1 === preg_match( '/@example\.(invalid|test)$/D', $email ) && $capable && $session;
	}

	public static function allowed(): bool {
		$user  = wp_get_current_user();
		$token = wp_get_session_token();
		return self::enabled(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_FILE_RECONCILIATION' ) ? ARN_ALLOW_LOCAL_FILE_RECONCILIATION : null,
			defined( 'ARN_LOCAL_FILE_RECONCILIATION_USER_ID' ) ? ARN_LOCAL_FILE_RECONCILIATION_USER_ID : null,
			(int) $user->ID,
			(string) $user->user_email,
			current_user_can( 'manage_options' ),
			'' !== $token && \WP_Session_Tokens::get_instance( (int) $user->ID )->verify( $token )
		);
	}

	public static function menu(): void {
		if ( self::allowed() ) {
			add_management_page( 'Reconciliação de ficheiros', 'Reconciliação de ficheiros', 'manage_options', 'arn-file-reconciliation', array( self::class, 'render' ) );
		}
	}

	public static function requestValid( string $method, array $get, array $post, callable $verify ): bool {
		return array( 'page' => 'arn-file-reconciliation' ) === $get && ( ( 'GET' === $method && array() === $post ) || ( 'POST' === $method && array( 'nonce', 'action' ) === array_keys( $post ) && is_string( $post['nonce'] ) && 'diagnose' === $post['action'] && $verify( $post['nonce'] ) ) );
	}

	public static function guard(): void {
		if ( ! is_admin() || 'arn-file-reconciliation' !== ( $_GET['page'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route selection only.
			return;
		}
		Controller::privateHeaders();
		if ( ! self::allowed() || is_network_admin() || is_user_admin() || ! self::requestValid(
			(string) ( $_SERVER['REQUEST_METHOD'] ?? '' ),
			wp_unslash( $_GET ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Strict shape in requestValid.
			wp_unslash( $_POST ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce checked by requestValid.
			static fn( string $nonce ): bool => false !== wp_verify_nonce( $nonce, 'arn-file-reconciliation-' . get_current_user_id() )
		) ) {
			Controller::refuse();
		}
		self::$prepared = true;
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$db = null;
			try {
				global $wpdb;
				$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
				$db->set_prefix( $wpdb->prefix );
				$source       = new ReconciliationSnapshot( $db );
				self::$report = ( new FileReconciliation( $source->read( ... ), LocalPrivateFiles::store() ) )->run();
			} catch ( Throwable ) {
				self::$report = array(
					'complete'       => false,
					'findings'       => array(),
					'total_findings' => 0,
					'checked'        => 0,
				);
			} finally {
				$db?->close();
			}
			if ( ! self::allowed() ) {
				Controller::refuse();
			}
		}
	}

	public static function render(): void {
		if ( ! self::$prepared || ! self::allowed() ) {
			Controller::refuse();
		}
		echo '<div class="wrap"><h1>Reconciliação de ficheiros</h1><p>Diagnóstico técnico do laboratório. Não altera ficheiros, referências, auditoria ou permissões.</p><p>Compara o registo, a auditoria, notícias, documentos e binários privados. As observações não autorizam apagar nem recuperar ficheiros automaticamente.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'tools.php?page=arn-file-reconciliation' ) ) . '"><input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'arn-file-reconciliation-' . get_current_user_id() ) ) . '"><input type="hidden" name="action" value="diagnose">';
		echo '<p class="submit"><button type="submit" class="button button-primary">Analisar sem alterar ficheiros</button></p>';
		echo '</form>';
		$r = self::$report;
		if ( null !== $r ) {
			echo '<section aria-label="Resultado do diagnóstico"><h2>' . esc_html( $r['complete'] ? 'Análise terminada' : 'Análise inconclusiva' ) . '</h2>';
			if ( isset( $r['id'] ) ) {
				echo '<p>Identificador do diagnóstico: <code>' . esc_html( $r['id'] ) . '</code></p><p>Instante UTC: ' . esc_html( gmdate( 'Y-m-d H:i:s', $r['started_at'] ) ) . '</p>';
			}
			echo '<p>Binários verificados: ' . esc_html( (string) $r['checked'] ) . '. Observações: ' . esc_html( (string) $r['total_findings'] ) . '.</p>';
			foreach ( array(
				'documents' => 'Documentos',
				'news'      => 'Notícias',
			) as $key => $label ) {
				echo '<p>' . esc_html( $label ) . ': ' . esc_html( ( $r['coverage'][ $key ] ?? false ) ? 'incluído na comparação' : 'não analisado' ) . '.</p>';
			}
			if ( $r['complete'] && 0 === $r['total_findings'] ) {
				echo '<p>Não foram detectadas divergências no âmbito analisado.</p>';
			}
			echo '<ul>';
			foreach ( $r['findings'] as $finding ) {
				echo '<li>' . esc_html( FileReconciliation::LABELS[ $finding['code'] ] ) . ( null === $finding['id'] ? '' : ': <code>' . esc_html( $finding['id'] ) . '</code>' ) . '</li>';
			}
			echo '</ul><p>São apresentadas até 200 observações. Uma análise inconclusiva não comprova integridade. Preserva os originais, regista o diagnóstico num local protegido e investiga antes de qualquer recuperação.</p></section>';
		}
		echo '<p>Limites deste ensaio: 1000 entradas por fonte, 1 MiB por binário e 64 MiB por execução. Alterações concorrentes exigem nova análise numa janela sem escrita. Não é uma análise antimalware.</p></div>';
	}
}
