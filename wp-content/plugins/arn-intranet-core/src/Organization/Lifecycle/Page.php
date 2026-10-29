<?php
/** Private HR interface with explicit binding and professional status.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Lifecycle;

use Arn\Intranet\F112\Controller;
use RuntimeException;
use Throwable;
// phpcs:disable WordPress.PHP.YodaConditions -- Structured record comparisons.
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Page {
	private static string $message = '';
	/** Preserve literal entities before the final WordPress HTML escape. */
	public static function escapeLiteral( string $value ): string {
		return \Arn\Intranet\Organization\Register\Page::escapeLiteral( $value );
	}
	public static function request( string $method, array $get, array $post, array $files, callable $nonce ): ?Command {
		if ( array( 'page' => 'arn-register-lifecycle' ) !== $get || array() !== $files ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		if ( 'GET' === $method && array() === $post ) {
			return null; }
		$keys = array_keys( $post );
		sort( $keys );
		if ( 'POST' !== $method || $keys !== array( 'account', 'account_revision', 'action', 'catalog_revision', 'nonce', 'person', 'reason', 'request_id', 'revision', 'state' ) ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		foreach ( $post as $value ) {
			if ( ! is_string( $value ) || strlen( $value ) > 500 ) {
				throw new RuntimeException( 'Pedido inválido.' ); }
		}
		if ( true !== $nonce( $post['nonce'] ) ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		foreach ( array( 'account', 'account_revision', 'catalog_revision', 'revision' ) as $key ) {
			if ( 1 !== preg_match( '/^(0|[1-9][0-9]{0,8})$/D', $post[ $key ] ) ) {
				throw new RuntimeException( 'Pedido inválido.' ); }
		}
		return new Command( $post['action'], $post['person'], $post['state'], (int) $post['account'], (int) $post['revision'], (int) $post['catalog_revision'], (int) $post['account_revision'], $post['reason'], $post['request_id'] );
	}
	public static function register(): void {
		add_action( 'init', array( self::class, 'guard' ), -28 );
		add_action(
			'admin_menu',
			static function (): void {
				if ( LocalAccess::allowed() ) {
					add_submenu_page( 'arn-register', 'Estado profissional e acesso', 'Estado e acesso', 'read', 'arn-register-lifecycle', array( self::class, 'render' ) ); }
			}
		);
		if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
			\WP_CLI::add_command( 'arn register lifecycle-install', array( LocalAccess::class, 'install' ) ); }
	}
	public static function guard(): void {
		if ( ! is_admin() || 'arn-register-lifecycle' !== ( $_GET['page'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing.
			return; }
		Controller::privateHeaders();
		if ( is_network_admin() || is_user_admin() || ! LocalAccess::allowed() ) {
			Controller::refuse(); }
		try {
			$command = self::request( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), wp_unslash( $_GET ), wp_unslash( $_POST ), $_FILES, static fn( string $n ): bool => false !== wp_verify_nonce( $n, 'arn-lifecycle-' . get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Strict parser.
		} catch ( Throwable ) {
			Controller::refuse(); }
		if ( null !== $command ) {
			try {
				$r             = LocalAccess::repository()->apply( $command );
				self::$message = 'Decisão confirmada na revisão ' . $r['revision'] . '. O histórico foi conservado. Reactivar o registo não reactiva o acesso.';
			} catch ( Throwable ) {
				self::$message = 'Operação não confirmada. Consulta o histórico e recarrega o formulário antes de repetir.'; }
		}
		if ( ! LocalAccess::allowed() ) {
			Controller::refuse(); }
	}
	private static function form( string $action, string $id, array $data, int $account, int $account_revision ): void {
		$fields = array(
			'action'           => $action,
			'person'           => $id,
			'account'          => $account,
			'account_revision' => $account_revision,
			'revision'         => $data['state']->revision,
			'catalog_revision' => $data['catalog']->revision,
			'request_id'       => bin2hex( random_bytes( 32 ) ),
			'nonce'            => wp_create_nonce( 'arn-lifecycle-' . get_current_user_id() ),
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=arn-register-lifecycle' ) ) . '" aria-label="' . esc_attr( $action . ' ' . $id ) . '">';
		foreach ( $fields as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">'; }
		if ( 'state' === $action ) {
			echo '<p><label>Novo estado <select name="state" required><option value="">Escolhe o estado</option>';
			foreach ( array( 'Active', 'Suspended', 'Closed' ) as $state ) {
				echo '<option value="' . esc_attr( $state ) . '">' . esc_html( State::LABELS[ $state ] ) . '</option>'; }
			echo '</select></label></p>';
		} else {
			echo '<input type="hidden" name="state" value=""><p>' . esc_html( 'Associar explicitamente à conta fictícia ' . LocalAccess::TARGET . ' (ID ' . $account . '). Esta conta só pode estar ligada a um registo.' ) . '</p>'; }
		echo '<p><label>Justificação <input type="text" name="reason" maxlength="250" required></label></p><p><button class="button button-primary" type="submit">' . esc_html( 'link' === $action ? 'Confirmar associação' : 'Confirmar estado' ) . '</button></p></form>';
	}
	public static function render(): void {
		if ( ! LocalAccess::allowed() ) {
			Controller::refuse(); }
		try {
			$data = LocalAccess::repository()->listing();
		} catch ( Throwable ) {
			echo '<div class="wrap"><h1>Estado profissional e acesso</h1><p role="alert">Circuito indisponível. Solicita diagnóstico ao responsável técnico.</p></div>';
			return; }
		echo '<div class="wrap arn-lifecycle"><style>.arn-lifecycle{max-width:1000px}.arn-lifecycle section{margin:24px 0;padding:20px;border:1px solid #c3c4c7;background:#fff}.arn-lifecycle input[type=text]{width:min(100%,36rem)}.arn-lifecycle input,.arn-lifecycle select,.arn-lifecycle button{min-height:44px;max-width:100%}.arn-lifecycle form{padding:12px 0}.arn-lifecycle p{overflow-wrap:anywhere}</style><h1>Estado profissional e acesso</h1><p>Laboratório fictício. Suspender ou encerrar o registo suspende a conta associada e invalida as sessões. Reactivar o registo exige uma decisão de acesso separada. Não são criadas permissões.</p><p><a href="' . esc_url( admin_url( 'admin.php?page=arn-register' ) ) . '">Voltar ao cadastro</a></p>';
		if ( '' !== self::$message ) {
			echo '<p role="status">' . esc_html( self::$message ) . '</p>'; }
		foreach ( $data['catalog']->people as $id => $person ) {
			$p = $data['state']->person( $id );
			echo '<section aria-label="' . esc_attr( $id ) . '"><h2>' . esc_html( self::escapeLiteral( $id . ' · ' . $person['name'] ) ) . '</h2><p>' . esc_html( 'Estado profissional: ' . State::LABELS[ $p['state'] ] . '. Conta associada: ' . ( $p['account'] > 0 ? $p['account'] : 'nenhuma' ) . '.' ) . '</p>';
			$linked       = $p['account'] > 0;
			$account_data = $linked ? ( $data['accounts'][ $p['account'] ] ?? null ) : null;
			if ( ! $linked || null !== $account_data ) {
				self::form( 'state', $id, $data, $p['account'], $account_data['revision'] ?? 0 );
			} else {
				echo '<p role="alert">Conta associada indisponível. Nenhuma alteração pode ser confirmada.</p>'; }
			if ( $linked && null !== $account_data ) {
				echo '<p>' . esc_html( 'Estado da conta: ' . $account_data['state'] . '. Revisão de acesso: ' . $account_data['revision'] . '.' ) . '</p>'; }
			if ( ! $linked ) {
				foreach ( $data['accounts'] as $account => $details ) {
					$used = array_filter( $data['state']->people, static fn( array $entry ): bool => $entry['account'] === $account );
					if ( array() === $used ) {
						self::form( 'link', $id, $data, $account, $details['revision'] ); }
				}
			}
			echo '</section>';
		}
		echo '<section><h2>Histórico de decisões</h2><p>Últimas 20 decisões. Valores anteriores e recibos conservados na auditoria privada.</p>';
		foreach ( $data['history'] as $r ) {
			echo '<p>' . esc_html( self::escapeLiteral( 'Revisão ' . $r['revision'] . ' | ' . gmdate( 'Y-m-d H:i:s', $r['at'] ) . ' UTC | ' . $r['person'] . ' | ' . State::LABELS[ $r['state'] ] . ' | Conta ' . $r['account'] . ' | Decisor ' . $r['actor'] . ' | ' . $r['reason'] ) ) . '</p>'; }
		echo '</section></div>';
	}
}
