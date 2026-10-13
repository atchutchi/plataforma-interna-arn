<?php
/** Leitura administrativa da persistência fictícia local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;
use WP_User;
/** Não aceita alvos do browser nem instala ou altera dados. */
final class AuthorizationStorageProbe {
	/**
	 * Fábrica interna diferida.
	 *
	 * @var Closure():AuthorizationRepository
	 */
	private Closure $repository_factory;
	/**
	 * Prepara uma fábrica sem abrir uma ligação.
	 *
	 * @param Closure|null $repository_factory Fronteira interna para testes sem SQL.
	 */
	public function __construct( ?Closure $repository_factory = null ) {
		$this->repository_factory = $repository_factory ?? static function (): AuthorizationRepository {
			global $wpdb;
			$prefix   = $wpdb->prefix;
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $prefix );
			return new WpdbAuthorizationRepository( $database );
		};
	}
	/** Regista o hook somente depois da autorização efectiva. */
	public function register(): void {
		if ( $this->allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ) );
		}
	}
	/** Revalida antes de acrescentar o menu. */
	public function registerPage(): void {
		if ( ! $this->allowed() ) {
			return;
		}
		add_management_page( 'Persistência de acessos local', 'Persistência de acessos local', 'manage_options', 'arn-authorization-storage', array( $this, 'render' ) );
	}
	/** Consulta exclusivamente Ana Teste já marcada. */
	public function render(): void {
		if ( ! $this->allowed() ) {
			return;
		}
		echo '<div class="wrap"><h1>Persistência de acessos local</h1><p>Dados fictícios do laboratório. Active indica estado funcional e não comprova uma sessão real. Não aprova atribuições institucionais.</p>';
		$authority = new LocalAuthorizationStorageAuthority();
		$fixture   = get_user_by( 'login', LocalFixtureUser::LOGIN );
		if ( ! $fixture instanceof WP_User || ! $authority->canUseAccount( $fixture->ID ) ) {
			echo '<p>A conta fictícia não está preparada.</p></div>';
			return;
		}
		try {
			$service  = new AuthorizationStorageService( ( $this->repository_factory )(), $authority );
			$actor    = get_current_user_id();
			$snapshot = $service->find( $actor, $fixture->ID );
			$history  = $service->history( $actor, $fixture->ID );
		} catch ( Throwable ) {
			echo '<p>A persistência local não está disponível ou não está preparada.</p></div>';
			return;
		}
		if ( null === $snapshot ) {
			echo '<p>A conta funcional fictícia não está preparada.</p></div>';
			return;
		}
		echo '<p>Conta fictícia ID: ' . esc_html( (string) $snapshot->user_id ) . '. Estado: ' . esc_html( $snapshot->state->name ) . '. Revisão: ' . esc_html( (string) $snapshot->revision ) . '.</p>';
		$this->table(
			'Concessões persistidas, incluindo histórico',
			array( 'ID', 'Função', 'Âmbito', 'Alvos', 'Início UTC', 'Fim UTC', 'Revogada' ),
			array_map(
				static function ( StoredGrant $stored ): array {
					$grant = $stored->grant;
					return array( (string) $stored->id, $grant->role->name, $grant->scope->type->name, implode( ', ', $grant->scope->targets ), gmdate( 'Y-m-d H:i:s', $grant->starts_at ), null === $grant->expires_at ? 'Sem fim' : gmdate( 'Y-m-d H:i:s', $grant->expires_at ), $grant->revoked ? 'Sim' : 'Não' );
				},
				$snapshot->stored_grants
			)
		);
		$rows = array();
		foreach ( $history as $event ) {
			$rows[] = array( (string) $event['id'], (string) $event['revision'], $event['event'], (string) $event['actor_id'], $event['reason'], $event['decision_ref'], gmdate( 'Y-m-d H:i:s', $event['occurred_at'] ) );
		}
		$this->table( 'Histórico de alterações da conta fictícia', array( 'ID', 'Revisão', 'Evento', 'Actor ID', 'Justificação', 'Referência', 'Data UTC' ), $rows );
		echo '</div>';
	}
	/** Verifica o estado efectivo sem overrides. */
	private function allowed(): bool {
		return ( new LocalAuthorizationStorageAuthority() )->canManage( get_current_user_id() );
	}
	/**
	 * Apresenta valores escapados numa tabela acessível.
	 *
	 * @param string $caption Legenda.
	 * @param array  $headers Colunas.
	 * @param array  $rows Linhas.
	 */
	private function table( string $caption, array $headers, array $rows ): void {
		echo '<table class="widefat striped"><caption>' . esc_html( $caption ) . '</caption><thead><tr>';
		foreach ( $headers as $header ) {
			echo '<th scope="col">' . esc_html( $header ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			echo '<tr>';
			foreach ( $row as $index => $value ) {
				if ( 0 === $index ) {
					echo '<th scope="row">' . esc_html( $value ) . '</th>';
				} else {
						echo '<td>' . esc_html( $value ) . '</td>';
				}
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}
}
