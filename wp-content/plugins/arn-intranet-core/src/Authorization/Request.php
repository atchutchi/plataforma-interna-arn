<?php
/**
 * Pedido calculado pelo servidor para um registo específico.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization;

use InvalidArgumentException;

/** As provas não podem ser aceites directamente de clientes. */
final readonly class Request {

	/**
	 * Valida o contexto do registo, preservando zero para desconhecido.
	 *
	 * @param Action $action           Operação pedida.
	 * @param Scope  $scope            Âmbito completo do registo.
	 * @param int    $owner_id         Proprietário ou alvo, zero se desconhecido.
	 * @param int    $author_id        Autor, zero se desconhecido.
	 * @param bool   $module_ready     Módulo disponível.
	 * @param bool   $state_allowed    Estado admissível.
	 * @param bool   $audience_allowed Audiência permitida.
	 * @param bool   $version_current  Versão corrente.
	 * @throws InvalidArgumentException Quando os IDs de contexto são inválidos.
	 */
	public function __construct(
		public Action $action,
		public Scope $scope,
		public int $owner_id,
		public int $author_id,
		public bool $module_ready,
		public bool $state_allowed,
		public bool $audience_allowed,
		public bool $version_current,
	) {
		if ( $owner_id < 0 || $author_id < 0 ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante de domínio.
			throw new InvalidArgumentException( 'Pedido inválido.' );
		}
	}
}
