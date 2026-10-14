<?php
/** Ferramenta de leitura de ficheiros fictícios.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

/** Mostra só ficheiros correntes autorizados à Ana. */
final class LocalPrivateFilesPage {
	/**
	 * Recebe o serviço de leitura.
	 *
	 * @param LocalPrivateFiles $files Serviço de leitura.
	 */
	public function __construct( private readonly LocalPrivateFiles $files = new LocalPrivateFiles() ) {}

	/** Rótulos fechados, sem input de browser. */
	public static function labels(): array {
		return array(
			'original'  => 'Original (PDF)',
			'thumbnail' => 'Miniatura (PNG)',
			'preview'   => 'Pré-visualização (JPEG)',
		);
	}

	/** Regista apenas para uma sessão própria com leitura possível. */
	public function register(): void {
		$rows = $this->files->listVisible();
		if ( null !== $rows && array() !== $rows ) {
			add_action( 'admin_menu', array( $this, 'registerPage' ) );
		}
	}

	/** Capability WordPress é só a porta de menu. */
	public function registerPage(): void {
		$rows = $this->files->listVisible();
		if ( null !== $rows && array() !== $rows ) {
			$hook = add_management_page( 'Ficheiros privados fictícios', 'Ficheiros privados fictícios', 'read', 'arn-local-private-files', array( $this, 'render' ) );
			add_action( 'load-' . $hook, array( $this, 'prepare' ) );
		}
	}

	/** Revalida antes do cabeçalho administrativo. */
	public function prepare(): void {
		$this->rows();
	}

	/** A lista não revela unidades ou versões antigas. */
	public function render(): void {
		$rows  = $this->rows();
		$count = array_sum( array_map( 'count', $rows ) );
		echo '<div class="wrap"><h1>Ficheiros privados fictícios</h1><p>Conteúdo sintético para ensaiar a entrega privada.</p><p>Ficheiros disponíveis: ' . esc_html( (string) $count ) . '</p>';
		foreach ( array(
			'own'    => 'Documento próprio',
			'unit-a' => 'Unidade fictícia A',
			'unit-b' => 'Unidade fictícia B',
		) as $key => $title ) {
			if ( ! isset( $rows[ $key ] ) ) {
				continue;
			}
			echo '<h2>' . esc_html( $title ) . '</h2><ul>';
			foreach ( self::labels() as $variant => $label ) {
				if ( ! isset( $rows[ $key ][ $variant ] ) ) {
					continue;
				}
				echo '<li><a href="' . esc_url( home_url( '/?arn_private_file=' . $rows[ $key ][ $variant ]->blob->id ) ) . '">' . esc_html( $label ) . '</a></li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/** Resposta uniforme para sessão inválida ou sem objecto. */
	private function rows(): array {
		nocache_headers();
		$rows = $this->files->listVisible();
		if ( null === $rows || array() === $rows ) {
			wp_die( '', '', array( 'response' => 404 ) );
		}
		return $rows;
	}
}
