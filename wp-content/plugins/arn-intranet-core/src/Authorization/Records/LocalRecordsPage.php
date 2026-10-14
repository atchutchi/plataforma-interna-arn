<?php
/** Ferramenta nativa de leitura de registos fictícios.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Records;

/** Não regista handlers de escrita, pesquisa ou exportação. */
final class LocalRecordsPage {
	/** Fronteira interna injectável para ensaios.
	 *
	 * @param LocalRecordAccess $access Leitura protegida.
	 */
	public function __construct( private readonly LocalRecordAccess $access = new LocalRecordAccess() ) {}
	/** Liga o menu antes da administração. */
	public function register(): void {
		if ( $this->access->allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ) );
		}
	}
	/** Capability WordPress não substitui a autorização por objecto. */
	public function registerPage(): void {
		if ( $this->access->allowed() ) {
			$hook = add_management_page( 'Registos fictícios', 'Registos fictícios', 'read', 'arn-local-records', array( $this, 'render' ) );
			add_action( 'load-' . $hook, array( $this, 'prepare' ) );
		}
	}
	/** Define privacidade e recusa antes do cabeçalho administrativo. */
	public function prepare(): void {
		$this->selection();
	}
	/** Revalida também na renderização, sem decisões guardadas. */
	public function render(): void {
		[$rows, $detail] = $this->selection();
		echo '<div class="wrap"><h1>Registos fictícios</h1><div class="notice notice-warning inline"><p>Laboratório local. Estes registos são sintéticos e servem apenas para ensaiar o acesso por âmbito.</p></div>';
		if ( null !== $detail ) {
			echo '<h2>' . esc_html( $detail['title'] ) . '</h2><p>' . esc_html( $detail['content'] ) . '</p><p><a href="' . esc_url( admin_url( 'tools.php?page=arn-local-records' ) ) . '">Voltar à lista</a></p>';
		} else {
			echo '<p>Registos disponíveis: ' . esc_html( (string) count( $rows ) ) . '</p><ul>';
			foreach ( $rows as $key => $record ) {
				echo '<li><a href="' . esc_url( admin_url( 'tools.php?page=arn-local-records&record=' . $key ) ) . '">' . esc_html( $record['title'] ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
	/** A mesma resposta pública cobre alvo desconhecido e alvo recusado. */
	private function selection(): array {
		nocache_headers();
		$rows = $this->access->read();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Leitura sem efeitos, chave exacta e autorização calculada no servidor.
		$has_detail = array_key_exists( 'record', $_GET );
		$detail     = null === $rows || ! $has_detail ? null : LocalRecordSelection::detail( $rows, $_GET['record'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( null === $rows || ( $has_detail && null === $detail ) ) {
			wp_die( 'Registo não encontrado.', '', array( 'response' => 404 ) );
		}
		return array( $rows, $detail );
	}
}
