<?php
/**
 * Ligação WordPress dedicada que preserva a fronteira transaccional.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

/**
 * Instanciar explicitamente com configuração protegida e prefixo do site.
 * Não reutilizar a ligação global nem efectuar queries de outros módulos.
 */
final class IdentityWpdb extends \wpdb {

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
