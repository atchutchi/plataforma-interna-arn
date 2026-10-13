<?php
/**
 * Contrato de armazenamento explícito e auditado de vínculos.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

/**
 * Fronteira interna de persistência. Chamadores usam IdentityLinkService.
 */
interface IdentityLinkRepository {

	/**
	 * Instala ou verifica o schema, apenas por chamada explícita.
	 */
	public function installSchema(): void;

	/**
	 * Grava vínculo inactivo e auditoria na mesma transacção.
	 *
	 * @param LocalIdentityLink $link     Vínculo novo inactivo.
	 * @param int               $actor_id Actor já autorizado pelo serviço.
	 */
	public function create( LocalIdentityLink $link, int $actor_id ): void;

	/**
	 * Grava estado e auditoria atomicamente, conservando a identidade.
	 *
	 * @param int  $user_id    Conta local.
	 * @param bool $authorized Autorização explícita.
	 * @param bool $active     Estado activo.
	 * @param int  $actor_id   Actor já autorizado pelo serviço.
	 */
	public function changeState( int $user_id, bool $authorized, bool $active, int $actor_id ): void;

	/**
	 * Consulta exacta, sem cache de autorização nem criação de tabelas.
	 *
	 * @param string $issuer  Emissor canónico.
	 * @param string $subject Identificador opaco.
	 */
	public function find( string $issuer, string $subject ): ?LocalIdentityLink;

	/**
	 * Consulta por conta exacta, conservando vínculos revogados e sem cache.
	 *
	 * @param int $user_id Identificador local.
	 */
	public function findForUser( int $user_id ): ?LocalIdentityLink;
}
