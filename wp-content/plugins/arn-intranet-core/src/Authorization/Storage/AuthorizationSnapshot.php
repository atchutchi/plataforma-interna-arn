<?php
/**
 * Leitura coerente de conta e concessões persistidas.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Authorization\Account;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use InvalidArgumentException;

/** A lista interna não retém referências PHP externas. */
final readonly class AuthorizationSnapshot {

	/**
	 * Lista validada e destacada.
	 *
	 * @var array<StoredGrant>
	 */
	public array $stored_grants;

	/**
	 * Recusa integralmente uma leitura malformada.
	 *
	 * @param int                $user_id       Conta positiva.
	 * @param AccountState       $state         Estado conhecido.
	 * @param int                $revision      Revisão positiva.
	 * @param array<StoredGrant> $stored_grants Concessões da mesma conta.
	 * @throws InvalidArgumentException Quando os valores são inválidos.
	 */
	public function __construct( public int $user_id, public AccountState $state, public int $revision, array $stored_grants ) {
		$seen = array();
		$copy = array();
		foreach ( $stored_grants as $stored ) {
			if ( ! $stored instanceof StoredGrant || $stored->grant->user_id !== $user_id || isset( $seen[ $stored->id ] ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
				throw new InvalidArgumentException( 'Snapshot inválido.' );
			}
			$seen[ $stored->id ] = true;
			$copy[]              = $stored;
		}
		if ( $user_id <= 0 || $revision <= 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Snapshot inválido.' );
		}
		$this->stored_grants = $copy;
	}

	/**
	 * A prova de sessão tem de ser obtida no servidor.
	 *
	 * @param bool $session_valid Prova de sessão, nunca input de cliente.
	 * @return Account
	 */
	public function toAccount( bool $session_valid ): Account {
		return new Account( $this->user_id, $this->state, $session_valid );
	}

	/**
	 * Preserva expiradas e revogadas para o motor decidir.
	 *
	 * @return array<Grant>
	 */
	public function policyGrants(): array {
		return array_map( static fn( StoredGrant $stored ): Grant => $stored->grant, $this->stored_grants );
	}
}
