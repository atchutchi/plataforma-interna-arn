<?php
/**
 * Conta sem dados pessoais ou credenciais.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

use InvalidArgumentException;

/** Snapshot imutável, calculado pelo servidor. */
final readonly class Account {

	/**
	 * Valida o identificador da conta.
	 *
	 * @param int          $user_id       Identificador positivo.
	 * @param AccountState $state         Estado local corrente.
	 * @param bool         $session_valid Prova de sessão obtida no servidor.
	 * @throws InvalidArgumentException Quando a conta é inválida.
	 */
	public function __construct( public int $user_id, public AccountState $state, public bool $session_valid ) {
		if ( $user_id <= 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio, sem saída HTML.
			throw new InvalidArgumentException( 'Conta inválida.' );
		}
	}
}
