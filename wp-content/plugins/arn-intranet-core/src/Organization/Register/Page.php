<?php
/** Private two-step fictional import and catalogue history.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Register;

use Arn\Intranet\F112\Controller;
use Arn\Intranet\Organization\ImportPreview\StrictCsv;
use RuntimeException;
use Throwable;
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Page {
	private static ?array $preview = null;
	private static string $csv     = '';
	private static string $message = '';
	/** Encode existing entities too, so the browser preserves literal CSV bytes. */
	public static function escapeLiteral( string $value ): string {
		return htmlspecialchars( $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true );
	}
	public static function request( string $method, array $get, array $post, array $files, callable $nonce ): ?array {
		if ( array( 'page' => 'arn-register' ) !== $get || array() !== $files ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		if ( 'GET' === $method && array() === $post ) {
			return null; }
		$keys = array_keys( $post );
		sort( $keys );
		$action   = $post['action'] ?? null;
		$expected = 'preview' === $action ? array( 'action', 'csv', 'nonce' ) : array( 'action', 'csv', 'nonce', 'proof' );
		if ( 'POST' !== $method || ! in_array( $action, array( 'preview', 'apply' ), true ) || $keys !== $expected || ! is_string( $post['nonce'] ) || true !== $nonce( $post['nonce'] ) || ! is_string( $post['csv'] ) || strlen( $post['csv'] ) > StrictCsv::MAX_BYTES || ( 'apply' === $action && ( ! is_string( $post['proof'] ) || strlen( $post['proof'] ) > 150 ) ) ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		return $post;
	}
	public static function register(): void {
		add_action( 'init', array( self::class, 'guard' ), -28 );
		add_action(
			'admin_menu',
			static function (): void {
				if ( LocalAccess::allowed() ) {
					add_menu_page( 'Cadastro', 'Cadastro', 'read', 'arn-register', array( self::class, 'render' ), 'dashicons-groups', 28 ); }
			}
		);
		if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
			\WP_CLI::add_command( 'arn register install', array( LocalAccess::class, 'install' ) ); }
	}
	public static function guard(): void {
		if ( ! is_admin() || 'arn-register' !== ( $_GET['page'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only.
			return; }
		Controller::privateHeaders();
		if ( is_network_admin() || is_user_admin() || ! LocalAccess::allowed() ) {
			Controller::refuse(); }
		try {
			$post = self::request( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), wp_unslash( $_GET ), wp_unslash( $_POST ), $_FILES, static fn( string $n ): bool => false !== wp_verify_nonce( $n, 'arn-register-' . get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Verified in the strict parser.
		} catch ( Throwable ) {
			Controller::refuse(); }
		if ( null !== $post ) {
			try {
				self::$csv = $post['csv'];
				$repo      = LocalAccess::repository();
				if ( 'preview' === $post['action'] ) {
					self::$preview = $repo->preview( self::$csv );
				} else {
					$r             = $repo->apply( self::$csv, $post['proof'] );
					self::$message = 'Lote confirmado na revisão ' . $r['revision'] . '. Criados: ' . $r['totals']['create'] . '. Alterados: ' . $r['totals']['update'] . '. Sem mudança: ' . $r['totals']['unchanged'] . '.';
					self::$csv     = '';
				}
			} catch ( Throwable ) {
				self::$message = 'Operação não confirmada. Consulta o histórico e valida novamente. Nenhuma repetição deve ser presumida como nova importação.'; }
		}
		if ( ! LocalAccess::allowed() ) {
			Controller::refuse(); }
	}
	private static function formStart( string $action ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=arn-register' ) ) . '"><input type="hidden" name="action" value="' . esc_attr( $action ) . '"><input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'arn-register-' . get_current_user_id() ) ) . '">';
	}
	public static function render(): void {
		if ( ! LocalAccess::allowed() ) {
			Controller::refuse(); }
		try {
			$state = LocalAccess::repository()->listing();
		} catch ( Throwable ) {
			echo '<div class="wrap"><h1>Cadastro</h1><p role="alert">Cadastro indisponível. Solicita diagnóstico ao responsável técnico.</p></div>';
			return; }
		echo '<div class="wrap arn-register"><style>.arn-register{max-width:1100px}.arn-register textarea{box-sizing:border-box;width:100%;max-width:100%;font-family:monospace}.arn-register button,.arn-register .button{min-height:44px;align-content:center}.arn-register td,.arn-register th{overflow-wrap:anywhere}.arn-register table{table-layout:fixed;width:100%}.arn-register .records{max-height:32rem;overflow:auto}.arn-register section{margin:24px 0;padding:20px;background:#fff;border:1px solid #c3c4c7}@media(max-width:600px){.arn-register section{padding:12px}.arn-register th,.arn-register td{padding:6px}}</style><h1>Cadastro</h1><p>Registos profissionais fictícios. Importar não cria contas, identidades Google ou permissões.</p>';
		if ( '' !== self::$message ) {
			echo '<p role="status">' . esc_html( self::$message ) . '</p>'; }
		if ( null !== self::$preview ) {
			$r = self::$preview['report'];
			echo '<section aria-labelledby="preview-heading"><h2 id="preview-heading">Resultado da validação</h2><p>' . esc_html( $r->valid ? 'Lote válido. Revê a proposta antes de confirmar.' : 'O lote tem erros. Nenhuma linha pode ser importada.' ) . '</p><p>' . esc_html( 'Criar: ' . $r->totals['create'] . '. Alterar: ' . $r->totals['update'] . '. Sem mudança: ' . $r->totals['unchanged'] . '. Inválidas: ' . $r->totals['invalid'] . '.' ) . '</p>';
			foreach ( $r->global_errors as $error ) {
				echo '<p role="alert">' . esc_html( $error ) . '</p>'; }
			echo '<table class="widefat"><caption>Propostas por linha</caption><thead><tr><th>Linha e registo</th><th>Resultado</th><th>Valores propostos</th><th>Detalhe</th></tr></thead><tbody>';
			$labels = array(
				'create'    => 'Criar',
				'update'    => 'Alterar',
				'unchanged' => 'Sem mudança',
				'invalid'   => 'Inválida',
			);
			foreach ( $r->rows as $row ) {
				$fields = array(
					'name'    => 'Nome',
					'email'   => 'Email',
					'unit_id' => 'Unidade',
				);
				$detail = array() !== $row['errors'] ? implode( ' ', $row['errors'] ) : implode( ', ', array_map( static fn( string $key ): string => $fields[ $key ], $row['changed_fields'] ) );
				echo '<tr><td>' . esc_html( self::escapeLiteral( $row['line'] . ': ' . ( $row['values'][0] ?? '' ) ) ) . '</td><td>' . esc_html( $labels[ $row['proposal'] ] ) . '</td><td>' . esc_html( self::escapeLiteral( implode( ' | ', array_slice( $row['values'], 1, 3 ) ) ) ) . '</td><td>' . esc_html( $detail ) . '</td></tr>'; }
			echo '</tbody></table>';
			if ( $r->valid ) {
				self::formStart( 'apply' );
				echo '<input type="hidden" name="csv" value="' . esc_attr( self::escapeLiteral( self::$csv ) ) . '"><input type="hidden" name="proof" value="' . esc_attr( self::$preview['proof'] ) . '"><p>A confirmação é válida durante dez minutos nesta sessão. Pessoas ausentes do lote permanecem no cadastro.</p><p><button type="submit" class="button button-primary">Confirmar importação</button></p></form>'; }
			echo '</section>';
		}
		echo '<section aria-labelledby="import-heading"><h2 id="import-heading">Importar ou actualizar registos</h2><p id="csv-help">Cola um CSV UTF-8 com o cabeçalho professional_id,name,email,unit_id. Máximo: 20 linhas e 16 KiB. Usa identificadores LAB-PER-001 a LAB-PER-999, nomes começados por Pessoa fictícia e emails example.invalid. As unidades têm de existir e estar activas.</p>';
		self::formStart( 'preview' );
		echo '<p><label for="register-csv">Conteúdo CSV</label></p><textarea id="register-csv" name="csv" rows="8" maxlength="16384" required aria-describedby="csv-help" spellcheck="false">' . esc_textarea( '' === self::$csv ? "professional_id,name,email,unit_id\n" : self::$csv ) . '</textarea><p><button type="submit" class="button button-primary">Validar lote</button></p></form></section><section aria-labelledby="catalog-heading"><h2 id="catalog-heading">Registos profissionais</h2><p>' . esc_html( 'Revisão ' . $state['catalog']->revision . '. Total: ' . count( $state['catalog']->people ) . '.' ) . '</p>';
		if ( array() === $state['catalog']->people ) {
			echo '<p>Ainda não há registos profissionais.</p>'; }
		echo '<div class="records" tabindex="0" role="region" aria-label="Registos profissionais"><table class="widefat"><thead><tr><th>Identificador</th><th>Nome</th><th>Email</th><th>Unidade</th></tr></thead><tbody>';
		foreach ( $state['catalog']->people as $id => $p ) {
			echo '<tr><td>' . esc_html( $id ) . '</td><td>' . esc_html( self::escapeLiteral( $p['name'] ) ) . '</td><td>' . esc_html( $p['email'] ) . '</td><td>' . esc_html( $p['unit_id'] ) . '</td></tr>'; }
		echo '</tbody></table></div></section><section aria-labelledby="history-heading"><h2 id="history-heading">Histórico de importações</h2><p>Últimos 20 lotes confirmados. Os recibos e os valores anteriores são conservados na auditoria privada.</p>';
		foreach ( $state['history'] as $r ) {
			echo '<p>' . esc_html( 'Revisão ' . $r['revision'] . ' | ' . gmdate( 'Y-m-d H:i:s', $r['at'] ) . ' UTC | Conta fictícia ' . $r['actor'] . ' | Criados ' . $r['totals']['create'] . ', alterados ' . $r['totals']['update'] . ', sem mudança ' . $r['totals']['unchanged'] . '.' ) . '</p>'; }
		echo '</section></div>';
	}
}
