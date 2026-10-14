<?php
/** Lista privada e recibos locais com POST protegido.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Notifications;

use Arn\Intranet\Authorization\Records\CurrentLocalAccount;
use Closure;
use Throwable;
/** Toda a autoridade é obtida no servidor a cada operação. */
final class LocalNotificationsPage {
	/** Dependência interna fixada.
	 *
	 * @var Closure
	 */
	private readonly Closure $repository_factory;
	/** Recebe dependências internas e fixa a fronteira.
	 *
	 * @param CurrentLocalAccount $current Fronteira da conta corrente.
	 *
	 * @param Closure|null        $repository_factory Fábrica interna da persistência.
	 */
	public function __construct( private readonly CurrentLocalAccount $current = new CurrentLocalAccount(), ?Closure $repository_factory = null ) {
		$this->repository_factory = $repository_factory ?? LocalNotificationInstaller::repository( ... );
	}
	/** Liga callbacks sem instalar tabelas.
	 */
	public function register(): void {
		add_action( 'admin_post_arn-local-notifications-read', array( $this, 'handle' ) );
		if ( $this->current->allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ) );
		}
	}
	/** Revalida o gate antes de registar o menu.
	 */
	public function registerPage(): void {
		if ( $this->current->allowed() ) {
			$hook = add_management_page( 'Notificações locais', 'Notificações locais', 'read', 'arn-local-notifications', array( $this, 'render' ) );
			add_action( 'load-' . $hook, array( $this, 'prepare' ) );
		}
	}
	/** Recusa pedidos inválidos antes do cabeçalho.
	 */
	public function prepare(): void {
		$this->selection();
	}
	/** Lê uma página depois de validar a sessão corrente.
	 */
	private function selection(): array {
		nocache_headers();
	 // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Leitura privada sem efeitos.
		$before  = array_key_exists( 'before', $_GET ) ? Notification::positive( $_GET['before'] ) : null;
		$invalid = array_key_exists( 'before', $_GET ) && null === $before;
	 // phpcs:enable WordPress.Security.NonceVerification.Recommended
		$account = $this->current->read();
		if ( ! $invalid && null !== $account ) {
			try {
				return array( ( $this->repository_factory )()->page( $account[1], $account[0]->revision, $before ), $account[0]->revision );
			} catch ( Throwable ) {
				wp_die( 'Notificações indisponíveis.', '', array( 'response' => 404 ) );
			}
		}
		wp_die( 'Notificações indisponíveis.', '', array( 'response' => 404 ) );
	}
	/** Apresenta apenas campos públicos escapados.
	 */
	public function render(): void {
		[$page, $revision] = $this->selection();
		echo '<div class="wrap"><h1>Notificações locais</h1><p>Avisos internos do laboratório. Marcar como lida não equivale a aprovação formal.</p>';
	 // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Mensagem constante, sem efeito.
		if ( isset( $_GET['result'] ) && 'read' === $_GET['result'] ) {
			echo '<div class="notice notice-success inline"><p>Notificação marcada como lida.</p></div>';
		}
		if ( isset( $_GET['result'] ) && 'denied' === $_GET['result'] ) {
			echo '<div class="notice notice-error inline"><p>Não foi possível marcar a notificação.</p></div>';
		}
	 // phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( array() === $page['rows'] ) {
			echo '<p>Não tens notificações disponíveis.</p>';
		}
		foreach ( $page['rows'] as $row ) {
			echo '<section style="padding:16px 0;border-bottom:1px solid #c3c4c7"><h2>' . esc_html( $row['title'] ) . '</h2><p>Notificação ' . esc_html( (string) $row['id'] ) . ' · ' . esc_html( gmdate( 'd/m/Y H:i:s', $row['occurred_at'] ) ) . ' UTC · ' . esc_html( null === $row['read_at'] ? 'Não lida' : 'Lida' ) . '</p>';
			if ( null === $row['read_at'] ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="arn-local-notifications-read"><input type="hidden" name="event_id" value="' . esc_attr( (string) $row['id'] ) . '"><input type="hidden" name="revision" value="' . esc_attr( (string) $revision ) . '">';
				wp_nonce_field( 'arn-local-notifications-read' );
				echo '<button type="submit" class="button">Marcar como lida</button></form>';
			}
			echo '</section>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=arn-local-notifications' ) ) . '">Voltar ao início</a></p>';
		if ( null !== $page['next'] ) {
			echo '<p><a href="' . esc_url( admin_url( 'tools.php?page=arn-local-notifications&before=' . $page['next'] ) ) . '">Página seguinte</a></p>';
		}
		echo '</div>';
	}
	/** Valida método, nonce e sessão antes de guardar o recibo.
	 *
	 * @param array  $input Campos recebidos no POST.
	 *
	 * @param string $method Método HTTP efectivo.
	 */
	public function process( array $input, string $method ): bool {
		if ( 'POST' !== $method || ! $this->current->allowed() ) {
			return false;
		}
		$id       = Notification::positive( $input['event_id'] ?? null );
		$revision = Notification::positive( $input['revision'] ?? null );
		$nonce    = $input['_wpnonce'] ?? null;
		if ( null === $id || null === $revision || ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'arn-local-notifications-read' ) ) {
			return false;
		}
		$account = $this->current->read();
		if ( null === $account || $revision !== $account[0]->revision ) {
			return false;
		}
		try {
			( $this->repository_factory )()->markRead( $account[1], $revision, $id, time() );
			return true;
		} catch ( Throwable ) {
			return false;
		}
	}
	/** Redirecciona para destino fixo depois do POST.
	 */
	public function handle(): void {
		nocache_headers();
	 // phpcs:ignore WordPress.Security.NonceVerification.Missing -- process valida nonce, método e sessão.
		$success = $this->process( $_POST, $_SERVER['REQUEST_METHOD'] ?? '' );
		wp_safe_redirect( admin_url( 'tools.php?page=arn-local-notifications&result=' . ( $success ? 'read' : 'denied' ) ), 303 );
		exit;
	}
}
