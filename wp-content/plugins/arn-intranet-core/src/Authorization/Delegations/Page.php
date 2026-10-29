<?php
/** Audited delegation management, with distinct issuer and delegate actions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Delegations;

use Arn\Intranet\F112\Controller;
use Arn\Intranet\Organization\Register\Page as RegisterPage;
use RuntimeException;
use Throwable;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class Page {
	private static string $message = '';
	public static function utc( string $value ): int {
		if ( 1 !== preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}(:[0-9]{2})?$/D', $value ) ) {
			throw new RuntimeException( 'Data UTC inválida.' ); }
		$normalized = strlen( $value ) === 16 ? $value . ':00' : $value;
		$date       = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i:s', $normalized, new \DateTimeZone( 'UTC' ) );
		if ( false === $date || $date->format( 'Y-m-d\TH:i:s' ) !== $normalized || $date->getTimestamp() < 0 ) {
			throw new RuntimeException( 'Data UTC inválida.' ); }
		return $date->getTimestamp();
	}
	public static function request( string $method, array $get, array $post, array $files, callable $nonce ): ?Command {
		if ( array( 'page' => 'arn-access-delegations' ) !== $get || array() !== $files ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		if ( 'GET' === $method && array() === $post ) {
			return null; }
		$keys = array_keys( $post );
		sort( $keys );
		$expected = array_merge( Command::KEYS, array( 'nonce' ) );
		sort( $expected );
		if ( 'POST' !== $method || $keys !== $expected ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		foreach ( $post as $v ) {
			if ( ! is_string( $v ) || strlen( $v ) > 500 ) {
				throw new RuntimeException( 'Pedido inválido.' ); }
		}
		if ( true !== $nonce( $post['nonce'] ) ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		foreach ( array( 'delegate', 'target', 'revision', 'target_revision' ) as $key ) {
			if ( 1 !== preg_match( '/^(0|[1-9][0-9]{0,8})$/D', $post[ $key ] ) ) {
				throw new RuntimeException( 'Pedido inválido.' ); }
			$post[ $key ] = (int) $post[ $key ];
		}
		$post['starts']  = self::utc( $post['starts'] );
		$post['expires'] = self::utc( $post['expires'] );
		$values          = array();
		foreach ( Command::KEYS as $key ) {
			$values[ $key ] = $post[ $key ]; }
		return Command::from( $values );
	}
	public static function register(): void {
		add_action( 'init', array( self::class, 'guard' ), -28 );
		add_action(
			'admin_menu',
			static function (): void {
				if ( LocalAccess::allowed() ) {
					add_menu_page( 'Delegações de acesso', 'Delegações de acesso', 'read', 'arn-access-delegations', array( self::class, 'render' ), 'dashicons-shield', 31 ); }
			}
		);
		if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
			\WP_CLI::add_command( 'arn delegations install', array( LocalAccess::class, 'install' ) ); }
	}
	public static function guard(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Routing only. POST nonce is required by request().
		if ( ! is_admin() || 'arn-access-delegations' !== ( $_GET['page'] ?? null ) ) {
			return; }
		Controller::privateHeaders();
		if ( is_network_admin() || is_user_admin() || ! LocalAccess::allowed() ) {
			Controller::refuse(); }
		try {
			$c = self::request( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), wp_unslash( $_GET ), wp_unslash( $_POST ), $_FILES, static fn( string $n ): bool => false !== wp_verify_nonce( $n, 'arn-delegations-' . get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Strict parser.
		} catch ( Throwable ) {
			Controller::refuse(); }
		if ( null !== $c ) {
			try {
				$r             = LocalAccess::repository()->apply( $c );
				self::$message = 'Decisão confirmada na revisão ' . $r['revision'] . '. As concessões anteriores conservam a sua validade própria.';
			} catch ( Throwable ) {
				self::$message = 'Operação não confirmada. Consulta o histórico e recarrega o formulário antes de repetir.'; }
		}
		if ( ! LocalAccess::allowed() ) {
			Controller::refuse(); }
	}
	private static function form( string $action, string $id, array $data, ?array $r = null ): void {
		$now    = $data['now'];
		$values = array(
			'action'          => $action,
			'delegation'      => $id,
			'delegate'        => $data['roster']['delegate'],
			'target'          => $data['roster']['target'],
			'role'            => $r['role'] ?? 'Employee',
			'scope'           => $r['scope'] ?? 'own',
			'starts'          => gmdate( 'Y-m-d\TH:i:s', 'revoke' === $action ? $r['starts'] : $now ),
			'expires'         => gmdate( 'Y-m-d\TH:i:s', 'revoke' === $action ? $r['expires'] : $now + ( 'grant' === $action ? 86400 : 3600 ) ),
			'revision'        => $data['state']->revision,
			'target_revision' => 'grant' === $action ? $data['target']->revision : 0,
			'request_id'      => bin2hex( random_bytes( 32 ) ),
			'nonce'           => wp_create_nonce( 'arn-delegations-' . get_current_user_id() ),
		);
		echo '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=arn-access-delegations' ) ) . '" aria-label="' . esc_attr( $action . ' ' . $id ) . '">';
		foreach ( $values as $key => $value ) {
			if ( ( 'issue' === $action && in_array( $key, array( 'role', 'scope' ), true ) ) || ( 'revoke' !== $action && in_array( $key, array( 'starts', 'expires' ), true ) ) ) {
				continue; }
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		if ( 'issue' === $action ) {
			echo '<p><label>Função <select name="role"><option value="Employee">Funcionário</option><option value="UnitManager">Responsável de unidade</option></select></label></p><p><label>Âmbito <select name="scope"><option value="own">Próprio</option><option value="LAB-A">LAB-A</option><option value="LAB-B">LAB-B</option></select></label></p><p>Usa âmbito próprio para Funcionário e uma unidade para Responsável de unidade. O destinatário desta simulação é a conta fictícia ' . esc_html( (string) $data['roster']['target'] ) . '.</p>';
		}
		if ( 'revoke' !== $action ) {
			echo '<p><label>Início UTC <input type="datetime-local" step="1" name="starts" required value="' . esc_attr( $values['starts'] ) . '"></label></p><p><label>Fim UTC <input type="datetime-local" step="1" name="expires" required value="' . esc_attr( $values['expires'] ) . '"></label></p>';
			if ( 'grant' === $action ) {
				echo '<p>Estas datas pertencem à nova concessão. O fim da delegação não as altera.</p>'; }
		}
		$labels = array(
			'issue'  => 'Confirmar delegação',
			'revoke' => 'Revogar delegação',
			'grant'  => 'Atribuir função autorizada',
		);
		echo '<p><label>Justificação <input type="text" name="reason" maxlength="250" required></label></p><p><button type="submit" class="button button-primary">' . esc_html( $labels[ $action ] ) . '</button></p></form>';
	}
	public static function render(): void {
		if ( ! LocalAccess::allowed() ) {
			Controller::refuse(); }
		try {
			$data = LocalAccess::repository()->listing(); } catch ( Throwable ) {
			echo '<div class="wrap"><h1>Delegações de acesso</h1><p role="alert">Circuito indisponível. Solicita diagnóstico ao responsável técnico.</p></div>';
			return;
			}
			$issuer = $data['actor'] === $data['roster']['issuer'];
			echo '<div class="wrap arn-delegations"><style>.arn-delegations{max-width:1000px}.arn-delegations section{margin:24px 0;padding:20px;background:#fff;border:1px solid #c3c4c7}.arn-delegations input,.arn-delegations select,.arn-delegations button{min-height:44px;max-width:100%;box-sizing:border-box}.arn-delegations input[type=text]{width:min(100%,36rem)}.arn-delegations p{overflow-wrap:anywhere}</style><h1>Delegações de acesso</h1><p>Laboratório fictício. A delegação limita quem pode atribuir uma função, a quem, em que âmbito e até quando. Não cria a competência de gerir acessos. Revogar ou deixar expirar impede novas atribuições e conserva as concessões anteriores.</p><p>' . esc_html( $issuer ? 'Actuas como gestor de acessos designado.' : 'Actuas como gestor delegado.' ) . '</p>';
			if ( '' !== self::$message ) {
				echo '<p role="status">' . esc_html( self::$message ) . '</p>'; }
			if ( $issuer ) {
				echo '<section><h2>Nova delegação</h2><p>' . esc_html( 'Gestor delegado: conta fictícia ' . $data['roster']['delegate'] . '.' ) . '</p>';
				self::form( 'issue', '', $data );
				echo '</section>';
			}
			echo '<h2>Delegações registadas</h2>';
			if ( array() === $data['state']->records ) {
				echo '<p>Não existem delegações registadas.</p>'; }
			foreach ( $data['state']->records as $id => $r ) {
				$d      = State::delegation( $r );
				$live   = $d->validAt( $data['now'] );
				$status = $r['revoked'] ? 'Revogada' : ( $live ? 'Vigente' : ( $data['now'] < $r['starts'] ? 'Ainda não iniciada' : 'Expirada' ) );
				echo '<section aria-label="' . esc_attr( $id ) . '"><h3>' . esc_html( $id ) . '</h3><p>' . esc_html( 'Estado: ' . $status . '. Função: ' . ( 'Employee' === $r['role'] ? 'Funcionário' : 'Responsável de unidade' ) . '. Âmbito: ' . ( 'own' === $r['scope'] ? 'Próprio' : $r['scope'] ) . '. Destinatário: ' . $r['target'] . '.' ) . '</p><p>' . esc_html( gmdate( 'Y-m-d H:i:s', $r['starts'] ) . ' a ' . gmdate( 'Y-m-d H:i:s', $r['expires'] ) . ' UTC. Concessões emitidas: ' . count( $r['grants'] ) . '.' ) . '</p>';
				if ( $issuer && ! $r['revoked'] ) {
					self::form( 'revoke', $id, $data, $r ); }
				if ( ! $issuer && $live && null !== $data['target'] && \Arn\Intranet\Authorization\AccountState::Active === $data['target']->state ) {
					self::form( 'grant', $id, $data, $r ); }
				echo '</section>';
			}
			echo '<section><h2>Histórico de decisões</h2><p>Últimas 20 decisões. Valores anteriores e recibos conservados na auditoria.</p>';
			foreach ( $data['history'] as $r ) {
				$labels = array(
					'issue'  => 'Delegação emitida',
					'revoke' => 'Delegação revogada',
					'grant'  => 'Função atribuída',
				);
				echo '<p>' . esc_html( RegisterPage::escapeLiteral( 'Revisão ' . $r['revision'] . ' | ' . $labels[ $r['command']['action'] ] . ' | Decisor ' . $r['actor'] . ' | Concessão ' . $r['grant_id'] . ' | ' . $r['command']['reason'] ) ) . '</p>';
			}
			echo '</section></div>';
	}
}
