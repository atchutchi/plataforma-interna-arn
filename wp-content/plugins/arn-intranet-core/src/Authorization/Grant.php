<?php
/**
 * Concessão explícita com vigência e revogação.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

use InvalidArgumentException;

/** Snapshot imutável sem persistência ou efeitos WordPress. */
final readonly class Grant {

	/**
	 * Valida o destinatário e o intervalo temporal.
	 *
	 * @param int      $user_id    Conta destinatária.
	 * @param Role     $role       Pacote explícito.
	 * @param Scope    $scope      Âmbito concedido.
	 * @param int      $starts_at  Início Unix inclusivo.
	 * @param int|null $expires_at Fim Unix exclusivo ou null.
	 * @param bool     $revoked    Concessão revogada.
	 * @throws InvalidArgumentException Quando os dados são inválidos.
	 */
	public function __construct(
		public int $user_id,
		public Role $role,
		public Scope $scope,
		public int $starts_at,
		public ?int $expires_at,
		public bool $revoked = false,
	) {
		if ( $user_id <= 0 || $starts_at < 0 || ( null !== $expires_at && $expires_at <= $starts_at ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Concessão inválida.' );
		}
	}

	/**
	 * Não concede acesso fora do intervalo ou após revogação.
	 *
	 * @param int $now Instante Unix obtido no servidor.
	 * @return bool
	 */
	public function validAt( int $now ): bool {
		return ! $this->revoked && $now >= $this->starts_at && ( null === $this->expires_at || $now < $this->expires_at );
	}
}
