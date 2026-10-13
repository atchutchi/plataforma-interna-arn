<?php
/**
 * Adaptador WordPress do laboratório local de leitura.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization;

/** Mantém o laboratório fechado fora do administrador local autorizado. */
final class AuthorizationProbe {
	/** Regista o menu apenas com os três gates efectivos satisfeitos. */
	public function register(): void {
		if ( $this->allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ) );
		}
	}
	/** Revalida os gates antes de criar a entrada em Ferramentas. */
	public function registerPage(): void {
		if ( ! $this->allowed() ) {
			return;
		}
		add_management_page( 'Ensaios de acesso local', 'Ensaios de acesso local', 'manage_options', 'arn-authorization-probe', array( $this, 'render' ) );
	}
	/** Apresenta snapshots fictícios depois de revalidar o estado efectivo. */
	public function render(): void {
		if ( ! $this->allowed() ) {
			return;
		}
		echo '<div class="wrap"><h1>Ensaios de acesso local</h1><p>Estes ensaios usam dados fictícios e não alteram acessos, contas ou sessões. Não aprovam atribuições institucionais.</p>';
		echo '<p>Permitido e Recusado identificam a decisão. Coincide indica que a decisão obtida corresponde à esperada. Diverge indica uma diferença a investigar.</p>';
		echo '<table class="widefat striped"><caption>Resultados dos cenários fictícios de autorização</caption><thead><tr><th scope="col">Cenário</th><th scope="col">Descrição</th><th scope="col">Esperada</th><th scope="col">Obtida</th><th scope="col">Comparação</th></tr></thead><tbody>';
		foreach ( AuthorizationFixtures::results( time() ) as $result ) {
			echo '<tr><th scope="row">' . esc_html( $result['id'] ) . '</th><td>' . esc_html( $result['text'] ) . '</td><td>' . esc_html( $result['expected'] ? 'Permitido' : 'Recusado' ) . '</td><td>' . esc_html( $result['actual'] ? 'Permitido' : 'Recusado' ) . '</td><td>' . esc_html( $result['expected'] === $result['actual'] ? 'Coincide' : 'Diverge' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
	/** Usa valores efectivos do WordPress, sem overrides ou provas do browser. */
	private function allowed(): bool {
		return 'local' === wp_get_environment_type() && defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE' ) && true === ARN_ALLOW_LOCAL_AUTHORIZATION_PROBE && current_user_can( 'manage_options' );
	}
}
