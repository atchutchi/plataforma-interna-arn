<?php
/**
 * Limites explícitos da atribuição de concessões.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

use InvalidArgumentException;

/** Uma delegação não concede por si só ManageGrants. */
final readonly class Delegation {

	/**
	 * Destinatários sem referências externas.
	 *
	 * @var array<int>
	 */
	public array $manageable_user_ids;

	/**
	 * Pacotes sem referências externas.
	 *
	 * @var array<Role>
	 */
	public array $allowed_roles;

	/**
	 * Valida as listas e a vigência sem normalizações permissivas.
	 *
	 * @param int         $actor_id            Actor autorizado a delegar.
	 * @param array<int>  $manageable_user_ids  Destinatários explícitos.
	 * @param array<Role> $allowed_roles        Pacotes explícitos.
	 * @param Scope       $allowed_scope        Limite do âmbito atribuído.
	 * @param int         $starts_at            Início Unix inclusivo.
	 * @param int|null    $expires_at           Fim Unix exclusivo ou null.
	 * @param bool        $revoked              Delegação revogada.
	 * @throws InvalidArgumentException Quando os dados são inválidos.
	 */
	public function __construct(
		public int $actor_id,
		array $manageable_user_ids,
		array $allowed_roles,
		public Scope $allowed_scope,
		public int $starts_at,
		public ?int $expires_at,
		public bool $revoked = false,
	) {
		if ( $actor_id <= 0 || $starts_at < 0 || ( null !== $expires_at && $expires_at <= $starts_at ) || array() === $manageable_user_ids || array() === $allowed_roles ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Delegação inválida.' );
		}
		$seen = array();
		foreach ( $manageable_user_ids as $user_id ) {
			if ( ! is_int( $user_id ) || $user_id <= 0 || in_array( $user_id, $seen, true ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
				throw new InvalidArgumentException( 'Delegação inválida.' );
			}
			$seen[] = $user_id;
		}
		$this->manageable_user_ids = $seen;
		$seen                      = array();
		foreach ( $allowed_roles as $role ) {
			if ( ! $role instanceof Role || in_array( $role, $seen, true ) ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
				throw new InvalidArgumentException( 'Delegação inválida.' );
			}
			$seen[] = $role;
		}
		$this->allowed_roles = $seen;
	}

	/**
	 * Exige o intervalo vigente e ausência de revogação.
	 *
	 * @param int $now Instante Unix do servidor.
	 * @return bool
	 */
	public function validAt( int $now ): bool {
		return ! $this->revoked && $now >= $this->starts_at && ( null === $this->expires_at || $now < $this->expires_at );
	}
}
