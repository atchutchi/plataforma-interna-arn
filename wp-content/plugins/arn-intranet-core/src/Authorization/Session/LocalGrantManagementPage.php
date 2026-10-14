<?php
/** Gestão auditada das concessões da conta fictícia local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\LocalGrantPresets;
use Closure;
use Throwable;

/** Só a chave do cenário é recebida do browser. */
final class LocalGrantManagementPage {
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
			add_action( 'admin_post_arn-local-grants', array( $this, 'handlePost' ), 10, 0 );
		}
	}
	/** Revalida a capacidade no destino. */
	public function registerPage(): void {
		if ( $this->administration->allowed() ) {
			add_management_page( 'Concessões locais', 'Concessões locais', 'manage_options', 'arn-local-grants', array( $this, 'render' ) );
		}
	}
	/** GET apenas lê a fixture fixa e preserva o histórico. */
	public function render(): void {
		if ( ! $this->administration->allowed() ) {
			return;
		}
		echo '<div class="wrap"><h1>Concessões locais</h1><p>Laboratório de Ana Teste. Estas concessões são fictícias e não atribuem papéis WordPress. Cada alteração invalida as sessões anteriores e exige novo início de sessão.</p>';
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
			echo '<div class="notice notice-success" role="status"><p>Concessão local guardada.</p></div>';
		}
		$state = match ( $snapshot->state ) {
			AccountState::Active => 'Activa',
			AccountState::Suspended => 'Suspensa',
			default => 'Pendente',
		};
		echo '<p>Ana Teste. Estado actual: ' . esc_html( $state ) . '. Revisão: ' . esc_html( (string) $snapshot->revision ) . '.</p>';
		echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=arn-authorization-storage' ) ) . '">Consultar auditoria local, apenas leitura</a></p>';
		echo '<h2>Histórico de concessões</h2><ul>';
		$now = time();
		foreach ( $snapshot->stored_grants as $stored ) {
			$status = $stored->grant->revoked ? 'Revogada' : ( $stored->grant->validAt( $now ) ? 'Vigente' : 'Fora de vigência' );
			echo '<li>ID ' . esc_html( (string) $stored->id ) . ': ' . esc_html( LocalGrantPresets::describe( $stored->grant ) ) . '. ' . esc_html( $status ) . '.</li>';
		}
		echo '</ul>';
		if ( array() === $snapshot->stored_grants ) {
			echo '<p>Sem concessões registadas.</p>';
		}
		echo '<h2>Alterar concessão</h2><p>Criar e substituir exigem conta activa. Revogar também é permitido com a conta suspensa. O registo anterior permanece no histórico.</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="arn-local-grants"><input type="hidden" name="revision" value="' . esc_attr( (string) $snapshot->revision ) . '">';
		wp_nonce_field( 'arn-local-grants' );
		echo '<table class="form-table" role="presentation"><tbody><tr><th scope="row"><label for="arn-grant-operation">Operação</label></th><td><select id="arn-grant-operation" name="operation" required><option value="create">Criar</option><option value="replace">Substituir</option><option value="revoke">Revogar</option></select></td></tr>';
		echo '<tr><th scope="row"><label for="arn-grant-id">Concessão existente</label></th><td><select id="arn-grant-id" name="grant_id" aria-describedby="arn-grant-id-help"><option value="">Seleccionar concessão</option>';
		foreach ( $snapshot->stored_grants as $stored ) {
			if ( ! $stored->grant->revoked ) {
				echo '<option value="' . esc_attr( (string) $stored->id ) . '">ID ' . esc_html( (string) $stored->id ) . ': ' . esc_html( LocalGrantPresets::describe( $stored->grant ) ) . '</option>';
			}
		}
		echo '</select><p id="arn-grant-id-help" class="description">Selecciona apenas para substituir ou revogar.</p></td></tr>';
		echo '<tr><th scope="row"><label for="arn-grant-preset">Cenário fictício</label></th><td><select id="arn-grant-preset" name="preset" aria-describedby="arn-grant-preset-help">';
		foreach ( LocalGrantPresets::labels() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select><p id="arn-grant-preset-help" class="description">Usado ao criar ou substituir. Tem início imediato e não tem prazo definido.</p></td></tr>';
		echo '<tr><th scope="row"><label for="arn-grant-reason">Justificação</label></th><td><input type="text" class="regular-text" id="arn-grant-reason" name="reason" required maxlength="500" aria-describedby="arn-grant-help"><p id="arn-grant-help" class="description">Justifica a alteração numa frase curta. O motivo fica na auditoria.</p></td></tr></tbody></table>';
		submit_button( 'Guardar concessão local' );
		echo '</form></div>';
	}
	/** Valida entradas antes da ligação e deixa a transacção decidir o CAS. */
	public function handlePost(): void {
		$this->administration->requirePost( 'arn-local-grants' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- requirePost verifica o nonce próprio antes de ler qualquer campo.
		$revision  = $this->administration->positive( $_POST['revision'] ?? null );
		$operation = $_POST['operation'] ?? null;
		$preset    = $_POST['preset'] ?? null;
		if ( ! in_array( $operation, array( 'create', 'replace', 'revoke' ), true ) || ( 'revoke' !== $operation && ( ! is_string( $preset ) || ! array_key_exists( $preset, LocalGrantPresets::labels() ) ) ) ) {
			wp_die( 'Dados do pedido local inválidos.', '', array( 'response' => 400 ) );
		}
		$grant_id = 'create' === $operation ? null : $this->administration->positive( $_POST['grant_id'] ?? null );
		$context  = $this->administration->context( $_POST['reason'] ?? null, 'LAB-GRANT-MANAGEMENT' );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$fixture = $this->administration->fixture();
		if ( null === $fixture ) {
			wp_die( 'A conta fictícia não está preparada.', '', array( 'response' => 403 ) );
		}
		try {
			$service = $this->administration->service();
			if ( 'revoke' === $operation ) {
				$service->revokeGrant( $fixture->ID, $grant_id, $revision, $context );
			} else {
				$grant = LocalGrantPresets::grant( $preset, $fixture->ID, $context->occurred_at );
				if ( 'create' === $operation ) {
					$service->createGrant( $grant, $revision, $context );
				} else {
					$service->replaceGrant( $fixture->ID, $grant_id, $grant, $revision, $context );
				}
			}
		} catch ( Throwable ) {
			wp_die( 'Não foi possível guardar a concessão local. Recarrega a página para consultar a revisão actual antes de repetir.', '', array( 'response' => 409 ) );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=arn-local-grants&message=updated' ), 303 );
		exit;
	}
}
