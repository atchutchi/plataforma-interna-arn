<?php
/** Gestão auditada do estado da conta fictícia local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Authorization\AccountState;
use Closure;
use Throwable;

/** O browser nunca escolhe o destinatário ou o actor. */
final class LocalAccountStatePage {
	/** Guarda comum com ligação diferida.
	 *
	 * @var LocalAccountAdministration
	 */
	private readonly LocalAccountAdministration $administration;
	/** Mantém a composição interna sem abrir persistência.
	 *
	 * @param Closure|null $repository_factory Fábrica de testes.
	 */
	public function __construct( ?Closure $repository_factory = null ) {
		$this->administration = new LocalAccountAdministration( $repository_factory );
	}
	/** Liga antes do menu e do despacho admin_post. */
	public function register(): void {
		if ( $this->administration->allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ), 10, 0 );
			add_action( 'admin_post_arn-local-account-state', array( $this, 'handlePost' ), 10, 0 );
		}
	}
	/** Revalida a capacidade antes de acrescentar o destino. */
	public function registerPage(): void {
		if ( $this->administration->allowed() ) {
			add_management_page( 'Estado de contas local', 'Estado de contas local', 'manage_options', 'arn-local-account-state', array( $this, 'render' ) );
		}
	}
	/** O GET consulta exclusivamente a fixture fixa e não escreve. */
	public function render(): void {
		if ( ! $this->administration->allowed() ) {
			return;
		}
		echo '<div class="wrap"><h1>Estado de contas local</h1><p>Laboratório de Ana Teste. Esta ferramenta não gere acessos institucionais. Uma alteração de estado invalida as sessões anteriores. Reactivar exige novo início de sessão.</p>';
		$fixture = $this->administration->fixture();
		try {
			$snapshot = null === $fixture ? null : $this->administration->service()->find( get_current_user_id(), $fixture->ID );
		} catch ( Throwable ) {
			$snapshot = null;
		}
		if ( null === $snapshot ) {
			echo '<p>A conta fictícia ou a persistência local não está preparada.</p></div>';
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Código fixo de apresentação sem escrita.
		if ( 'updated' === ( $_GET['message'] ?? null ) ) {
			echo '<div class="notice notice-success" role="status"><p>Estado local guardado.</p></div>';
		}
		echo '<p>Ana Teste. Estado actual: ' . esc_html(
			match ( $snapshot->state ) {
			AccountState::Active => 'Activa',
			AccountState::Suspended => 'Suspensa',
			default => 'Pendente' }
		) . '. Revisão: ' . esc_html( (string) $snapshot->revision ) . '.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="arn-local-account-state"><input type="hidden" name="revision" value="' . esc_attr( (string) $snapshot->revision ) . '">';
		wp_nonce_field( 'arn-local-account-state' );
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="arn-state">Estado pretendido</label></th><td><select id="arn-state" name="state" required><option value="">Seleccionar estado</option><option value="Active">Activa</option><option value="Suspended">Suspensa</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="arn-state-reason">Justificação</label></th><td><input type="text" class="regular-text" id="arn-state-reason" name="reason" required maxlength="500" aria-describedby="arn-state-help"><p id="arn-state-help" class="description">Justifica a alteração numa frase curta. O motivo fica no histórico.</p></td></tr></tbody></table>';
		submit_button( 'Guardar estado local' );
		echo '</form></div>';
	}
	/** Valida POST, nonce e inputs antes de abrir a ligação. */
	public function handlePost(): void {
		$this->administration->requirePost( 'arn-local-account-state' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- requirePost verifica o nonce próprio antes de ler qualquer campo.
		$revision = $this->administration->positive( $_POST['revision'] ?? null );
		$state    = $_POST['state'] ?? null;
		if ( ! in_array( $state, array( 'Active', 'Suspended' ), true ) ) {
			wp_die( 'Dados do pedido local inválidos.', '', array( 'response' => 400 ) );
		}
		$context = $this->administration->context( $_POST['reason'] ?? null, 'LAB-ACCOUNT-STATE' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$fixture = $this->administration->fixture();
		if ( null === $fixture ) {
			wp_die( 'A conta fictícia não está preparada.', '', array( 'response' => 403 ) );
		}
		try {
			$this->administration->service()->changeState( $fixture->ID, 'Active' === $state ? AccountState::Active : AccountState::Suspended, (int) $revision, $context );
		} catch ( Throwable ) {
			wp_die( 'Não foi possível guardar o estado local. Recarrega a página para consultar a revisão actual antes de repetir.', '', array( 'response' => 409 ) );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=arn-local-account-state&message=updated' ), 303 );
		exit;
	}
}
