<?php
/** Gestão auditada do estado da conta fictícia local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;
use WP_User;

/** O browser nunca escolhe o destinatário ou o actor. */
final class LocalAccountStatePage {
	/** Fábrica diferida da persistência.
	 *
	 * @var Closure():AuthorizationRepository
	 */
	private readonly Closure $repository_factory;
	/**
	 * Não abre ligações antes dos gates efectivos.
	 *
	 * @param Closure|null $repository_factory Composição interna de testes.
	 */
	public function __construct( ?Closure $repository_factory = null ) {
		global $wpdb;
		$prefix                   = $wpdb->prefix ?? '';
		$this->repository_factory = $repository_factory ?? static function () use ( $prefix ): AuthorizationRepository {
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $prefix );
			return new WpdbAuthorizationRepository( $database );
		};
	}
	/** Liga antes do menu e do despacho admin_post. */
	public function register(): void {
		if ( $this->allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ), 10, 0 );
			add_action( 'admin_post_arn-local-account-state', array( $this, 'handlePost' ), 10, 0 );
		}
	}
	/** Revalida a capacidade antes de acrescentar o destino. */
	public function registerPage(): void {
		if ( $this->allowed() ) {
			add_management_page( 'Estado de contas local', 'Estado de contas local', 'manage_options', 'arn-local-account-state', array( $this, 'render' ) );
		}
	}
	/** O GET consulta exclusivamente a fixture fixa e não escreve. */
	public function render(): void {
		if ( ! $this->allowed() ) {
			return;
		}
		echo '<div class="wrap"><h1>Estado de contas local</h1><p>Laboratório de Ana Teste. Esta ferramenta não gere acessos institucionais. Uma alteração de estado invalida as sessões anteriores. Reactivar exige novo início de sessão.</p>';
		$fixture = $this->fixture();
		try {
			$snapshot = null === $fixture ? null : $this->service()->find( get_current_user_id(), $fixture->ID );
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
		if ( ! $this->allowed() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'Operação local não autorizada.', '', array( 'response' => 403 ) );
		}
		if ( 'arn-local-account-state' !== ( $_POST['action'] ?? null ) || ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'arn-local-account-state' ) ) {
			wp_die( 'Pedido local inválido.', '', array( 'response' => 403 ) );
		}
		$revision = $_POST['revision'] ?? null;
		$state    = $_POST['state'] ?? null;
		$reason   = $_POST['reason'] ?? null;
		if ( ! is_string( $revision ) || 1 !== preg_match( '/\A[1-9][0-9]*\z/', $revision ) || (string) (int) $revision !== $revision || ! in_array( $state, array( 'Active', 'Suspended' ), true ) || ! is_string( $reason ) ) {
			wp_die( 'Dados do pedido local inválidos.', '', array( 'response' => 400 ) );
		}
		try {
			$context = new ChangeContext( get_current_user_id(), wp_unslash( $reason ), 'LAB-ACCOUNT-STATE', time() );
		} catch ( Throwable ) {
			wp_die( 'Justificação inválida. Usa uma frase curta numa só linha, sem espaços no início ou no fim.', '', array( 'response' => 400 ) );
		}
		$fixture = $this->fixture();
		if ( null === $fixture ) {
			wp_die( 'A conta fictícia não está preparada.', '', array( 'response' => 403 ) );
		}
		try {
			$this->service()->changeState( $fixture->ID, 'Active' === $state ? AccountState::Active : AccountState::Suspended, (int) $revision, $context );
		} catch ( Throwable ) {
			wp_die( 'Não foi possível guardar o estado local. Recarrega a página para consultar a revisão actual antes de repetir.', '', array( 'response' => 409 ) );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=arn-local-account-state&message=updated' ), 303 );
		exit;
	}
	/** Exige ambas as flags e a autoridade administrativa efectiva. */
	private function allowed(): bool {
		return defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) && true === ARN_ALLOW_FICTIONAL_LOCAL_ACCESS
			&& ( new LocalAuthorizationStorageAuthority() )->canManage( get_current_user_id() );
	}
	/** Resolve exclusivamente Ana Teste existente e marcada. */
	private function fixture(): ?WP_User {
		$fixture = get_user_by( 'login', LocalFixtureUser::LOGIN );
		return $fixture instanceof WP_User && LocalFixtureUser::LOGIN === $fixture->user_login && ( new LocalAuthorizationStorageAuthority() )->canUseAccount( $fixture->ID ) ? $fixture : null;
	}
	/** Cria o serviço auditado depois da autorização. */
	private function service(): AuthorizationStorageService {
		return new AuthorizationStorageService( ( $this->repository_factory )(), new LocalAuthorizationStorageAuthority() );
	}
}
