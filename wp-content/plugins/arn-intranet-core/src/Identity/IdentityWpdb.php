<?php
/**
 * Ligação WordPress dedicada que preserva a fronteira transaccional.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use RuntimeException;
use Throwable;

/**
 * Instanciar explicitamente com configuração protegida e prefixo do site.
 * Não reutilizar a ligação global nem efectuar queries de outros módulos.
 */
final class IdentityWpdb extends \wpdb {

	/**
	 * Converte falhas iniciais em erro estático, sem saída nem wp_die.
	 * O construtor nativo chama este método mesmo quando WP_DEBUG está activo.
	 *
	 * @param bool $allow_bail Argumento nativo, deliberadamente ignorado.
	 * @return true Ligação dedicada pronta.
	 * @throws RuntimeException Quando a ligação inicial não está disponível.
	 */
	public function db_connect( $allow_bail = true ) {
		$show_errors      = $this->hide_errors();
		$suppress_errors  = $this->suppress_errors( true );
		$mysqli_reporting = ( new \mysqli_driver() )->report_mode; // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_driver -- Preserva o modo global que o core altera durante db_connect.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Impede saída de warnings com credenciais durante a ligação dedicada.
		set_error_handler(
			static function (): never {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante, sem dados da ligação.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
		);
		try {
			if ( ! parent::db_connect( false ) || ! $this->ready ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante.
				throw new RuntimeException( 'Ligação dedicada indisponível.' );
			}
			return true;
		} catch ( Throwable ) {
			try {
				$this->close();
			} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Uma falha de limpeza também recebe apenas a mensagem estática abaixo.
				// Não propagar detalhes internos de uma ligação parcialmente aberta.
			}
			$this->error      = null;
			$this->last_error = '';
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- A causa original não é propagada porque pode conter dados protegidos.
			throw new RuntimeException( 'Ligação dedicada indisponível.' );
		} finally {
			restore_error_handler();
			mysqli_report( $mysqli_reporting ); // phpcs:ignore WordPress.DB.RestrictedFunctions.mysql_mysqli_report -- Restaura o modo anterior, sem afectar a ligação WordPress principal.
			$this->show_errors( $show_errors );
			$this->suppress_errors( $suppress_errors );
		}
	}

	/**
	 * WordPress chama este método antes de repetir queries numa nova ligação.
	 * Recusar evita que uma escrita seja repetida fora da transacção perdida.
	 *
	 * @param bool $allow_bail Argumento WordPress, deliberadamente ignorado.
	 * @return false A operação actual deve falhar, sem reconnect ou wp_die.
	 */
	public function check_connection( $allow_bail = true ) {
		return false;
	}
}
