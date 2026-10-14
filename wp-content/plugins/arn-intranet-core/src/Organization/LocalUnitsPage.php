<?php
/** Administração fechada de unidades fictícias.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization;

use Arn\Intranet\Organization\Storage\{LocalUnitInstaller, LocalUnitScenarios, LocalUnitContext, WpdbLocalUnitRepository};
use Closure;
use Throwable;
/** Ferramenta administrativa das unidades fictícias. */
final class LocalUnitsPage {
	/**
	 * Inicializa e valida o contexto local.
	 *
	 * @param ?Closure $repository_factory Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function __construct( private readonly ?Closure $repository_factory = null ) {}
	/**
	 * Abre o repositório dedicado após a guarda local.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	private function repository(): WpdbLocalUnitRepository {
		return null === $this->repository_factory ? LocalUnitInstaller::repository() : ( $this->repository_factory )(); }
	/**
	 * Regista a página e o endpoint de submissão.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function register(): void {
		if ( LocalUnitInstaller::allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ) );
			add_action( 'admin_post_arn-local-units', array( $this, 'handlePost' ) );
		}
	}
	/**
	 * Operação register page.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function registerPage(): void {
		if ( LocalUnitInstaller::allowed() ) {
			add_management_page( 'Unidades locais', 'Unidades locais', 'manage_options', 'arn-local-units', array( $this, 'render' ) ); }
	}
	/**
	 * Apresenta a árvore e o histórico auditado.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function render(): void {
		if ( ! LocalUnitInstaller::allowed() ) {
			return; }
		nocache_headers();
		echo '<div class="wrap"><h1>Unidades locais</h1><p>Unidades fictícias de laboratório. Não representam o organograma aprovado e não alteram permissões, pessoas ou sessões.</p>';
		try {
			$repository = $this->repository();
			$snapshot   = $repository->snapshot();
			$history    = $repository->history(); } catch ( Throwable ) {
			echo '<p>A persistência local não está preparada ou é incompatível.</p></div>';
			return; }
			echo '<p>Revisão global: ' . esc_html( (string) $snapshot->revision ) . '.</p><h2>Árvore fictícia</h2><ul>';
			foreach ( $snapshot->units as $unit ) {
				echo '<li>' . esc_html( $unit->id . ': ' . $unit->name . '. Superior: ' . ( $unit->parent_id ?? 'Raiz' ) . '. Estado: ' . ( $unit->active ? 'Activa' : 'Inactiva' ) ) . '</li>'; }
			echo '</ul><h2>Aplicar cenário</h2><p>Cada cenário aplica apenas a alteração descrita. Inicializar exige árvore vazia. Não são aceites nomes ou superiores livres. Um conflito exige recarregar a página.</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="arn-local-units"><input type="hidden" name="revision" value="' . esc_attr( (string) $snapshot->revision ) . '">';
			wp_nonce_field( 'arn-local-units' );
			echo '<p><label for="arn-unit-scenario">Cenário fictício</label><br><select id="arn-unit-scenario" name="scenario" required style="max-width:100%">';
			foreach ( LocalUnitScenarios::labels() as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; }
			echo '</select></p><p><label for="arn-unit-reason">Justificação obrigatória</label><br><input class="regular-text" style="max-width:100%" id="arn-unit-reason" name="reason" required maxlength="500"></p>';
			submit_button( 'Guardar alteração local' );
			echo '</form><h2>Últimos eventos</h2><ul>';
			foreach ( $history as $event ) {
				echo '<li>' . esc_html( 'Revisão ' . $event->revision . '. ' . LocalUnitScenarios::labels()[ $event->event ] . '. Actor ' . $event->actor_id . '. ' . $event->reason . '. Instante ' . $event->occurred_at ) . '</li>'; }
			echo '</ul></div>';
	}
	/**
	 * Operação handle post.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function handlePost(): void {
		if ( ! LocalUnitInstaller::allowed() ) {
			wp_die( 'Unidades locais indisponíveis.', '', array( 'response' => 403 ) ); }
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? null ) ) {
			wp_die( 'Método recusado.', '', array( 'response' => 405 ) ); }
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce próprio validado abaixo antes de abrir persistência.
		$nonce = $_POST['_wpnonce'] ?? null;
		if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'arn-local-units' ) ) {
			wp_die( 'Pedido local inválido.', '', array( 'response' => 403 ) ); }
		try {
			if ( 'arn-local-units' !== ( $_POST['action'] ?? null ) || ! is_string( $_POST['scenario'] ?? null ) || ! isset( LocalUnitScenarios::labels()[ $_POST['scenario'] ] ) || ! is_string( $_POST['reason'] ?? null ) ) {
				throw new \RuntimeException(); }
			$scenario = $_POST['scenario'];
			$revision = WpdbLocalUnitRepository::revision( $_POST['revision'] ?? null );
			$context  = new LocalUnitContext( get_current_user_id(), wp_unslash( $_POST['reason'] ), time() );
		} catch ( Throwable ) {
			wp_die( 'Dados do pedido local inválidos.', '', array( 'response' => 400 ) ); }
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		try {
			$this->repository()->apply( $scenario, $revision, $context ); } catch ( Throwable ) {
			wp_die( 'Alteração local recusada. Recarrega a página para consultar a revisão actual.', '', array( 'response' => 409 ) ); }
			wp_safe_redirect( admin_url( 'tools.php?page=arn-local-units' ), 303 );
			exit;
	}
}
