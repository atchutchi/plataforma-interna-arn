<?php
/**
 * Página da pré-validação sintética.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\ImportPreview;

use Arn\Intranet\Organization\Storage\{LocalUnitInstaller, LocalUnitSnapshot, WpdbLocalUnitRepository};
use Closure;
use Throwable;

/** O relatório existe apenas durante a resposta actual. */
final class LocalImportPreviewPage {
	/**
	 * Árvore lida para esta resposta.
	 *
	 * @var ?LocalUnitSnapshot
	 */
	private ?LocalUnitSnapshot $snapshot = null;
	/**
	 * Análise em memória.
	 *
	 * @var ?PreviewReport
	 */
	private ?PreviewReport $report = null;
	/**
	 * Falha de leitura fechada.
	 *
	 * @var bool
	 */
	private bool $unavailable = false;

	/**
	 * Aceita um leitor substituível para testes.
	 *
	 * @param ?Closure $reader Leitor substituível para testes.
	 */
	public function __construct( private readonly ?Closure $reader = null ) {}

	/** Verifica ambiente, duas flags booleanas e capacidade. */
	public static function allowed(): bool {
		return LocalUnitInstaller::allowed() && defined( 'ARN_ALLOW_LOCAL_IMPORT_PREVIEW' ) && true === ARN_ALLOW_LOCAL_IMPORT_PREVIEW;
	}

	/** Liga os hooks apenas no contexto autorizado. */
	public function register(): void {
		if ( self::allowed() ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ) );
		}
	}

	/** Regista a página e a preparação prévia ao cabeçalho. */
	public function registerPage(): void {
		if ( self::allowed() ) {
			add_management_page( 'Validar importação sintética', 'Validar importação sintética', 'manage_options', 'arn-local-import-preview', array( $this, 'render' ) );
			add_action( 'load-tools_page_arn-local-import-preview', array( $this, 'prepare' ) );
		}
	}

	/** Guardas e nonce precedem toda a leitura. Corre antes dos headers. */
	public function prepare(): void {
		if ( ! self::allowed() ) {
			wp_die( 'Pré-validação indisponível.', '', array( 'response' => 403 ) );
		}
		nocache_headers();
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		if ( ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
			wp_die( 'Método recusado.', '', array( 'response' => 405 ) );
		}
		if ( 'POST' === $method ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verificação explícita anterior à leitura.
			$nonce = $_POST['_wpnonce'] ?? null;
			if ( ! is_string( $nonce ) || ! wp_verify_nonce( $nonce, 'arn-local-import-preview' ) ) {
				wp_die( 'Pedido sintético inválido.', '', array( 'response' => 403 ) );
			}
			if ( ! empty( $_FILES ) || array_diff( array_keys( $_POST ), array( '_wpnonce', '_wp_http_referer', 'scenario', 'revision' ) ) || ! is_string( $_POST['scenario'] ?? null ) || ! isset( SyntheticScenarios::labels()[ $_POST['scenario'] ] ) ) {
				wp_die( 'Pedido sintético inválido.', '', array( 'response' => 400 ) );
			}
			try {
				$revision = WpdbLocalUnitRepository::revision( $_POST['revision'] ?? null );
			} catch ( Throwable ) {
				wp_die( 'Pedido sintético inválido.', '', array( 'response' => 400 ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
		try {
			$this->snapshot = null === $this->reader ? LocalUnitInstaller::repository()->snapshot() : ( $this->reader )();
			if ( 'POST' === $method ) {
				if ( $revision !== $this->snapshot->revision ) {
					wp_die( 'Revisão desactualizada. Recarrega a página.', '', array( 'response' => 409 ) );
				}
				$this->report = PreviewValidator::validate( SyntheticScenarios::csv( $_POST['scenario'] ), $this->snapshot );
			}
		} catch ( Throwable ) {
			$this->unavailable = true;
		}
	}

	/** Apenas apresenta o estado preparado, sem recalcular. */
	public function render(): void {
		if ( ! self::allowed() ) {
			wp_die( 'Pré-validação indisponível.', '', array( 'response' => 403 ) );
		}
		nocache_headers();
		echo '<div class="wrap"><h1>Validar importação sintética</h1><p>Ensaio informativo sem importação de pessoas ou alteração de dados.</p>';
		if ( $this->unavailable || null === $this->snapshot ) {
			echo '<p>Pré-validação indisponível.</p></div>';
			return;
		}
		echo '<p>Revisão de unidades: ' . esc_html( (string) $this->snapshot->revision ) . '</p><form method="post"><input type="hidden" name="revision" value="' . esc_attr( (string) $this->snapshot->revision ) . '">';
		wp_nonce_field( 'arn-local-import-preview' );
		echo '<label for="arn-import-scenario">Cenário sintético</label> <select id="arn-import-scenario" name="scenario">';
		foreach ( SyntheticScenarios::labels() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select> ';
		echo '<button type="submit" class="button button-primary">Pré-validar</button>';
		echo '</form>';
		if ( null !== $this->report ) {
			$r = $this->report;
			echo '<h2>Relatório em memória</h2><p>Estado: ' . esc_html( $r->valid ? 'Válido apenas neste ensaio' : 'Inválido' ) . '. Criações: ' . esc_html( (string) $r->totals['create'] ) . '. Alterações: ' . esc_html( (string) $r->totals['update'] ) . '. Sem mudança: ' . esc_html( (string) $r->totals['unchanged'] ) . '. Inválidas: ' . esc_html( (string) $r->totals['invalid'] ) . '.</p>';
			echo '<p>Identificador: ' . esc_html( $r->batch_id ) . '. CSV SHA-256: ' . esc_html( $r->csv_hash ) . '. Catálogo SHA-256: ' . esc_html( $r->catalog_hash ) . ' (revisão ' . esc_html( (string) $r->catalog_revision ) . '). Unidades SHA-256: ' . esc_html( $r->units_hash ) . ' (revisão ' . esc_html( (string) $r->units_revision ) . '). Validador: ' . esc_html( $r->validator_version ) . '.</p>';
			echo '<p>Actor: ' . esc_html( (string) get_current_user_id() ) . '. Instante UTC: ' . esc_html( gmdate( 'Y-m-d H:i:s' ) ) . '.</p><ul>';
			foreach ( $r->global_errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			foreach ( $r->rows as $row ) {
				echo '<li>Linha ' . esc_html( (string) $row['line'] ) . ': ' . esc_html( implode( ' | ', $row['values'] ) ) . '. Proposta: ' . esc_html( $row['proposal'] ) . '. Campos diferentes: ' . esc_html( implode( ', ', $row['changed_fields'] ) ) . '. Erros: ' . esc_html( implode( ', ', array_map( static fn( string $key, string $value ): string => $key . ': ' . $value, array_keys( $row['errors'] ), $row['errors'] ) ) ) . '.</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
}
