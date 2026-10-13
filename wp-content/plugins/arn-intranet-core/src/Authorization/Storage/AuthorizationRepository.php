<?php
/**
 * Fronteira interna da persistência transaccional.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;

/** O adaptador revalida invariantes e confirma auditoria na transacção. */
interface AuthorizationRepository {

	/** Instala explicitamente a versão suportada, sem hooks. */
	public function installSchema(): void;

	/**
	 * Cria conta Pending com revisão 1 e evento atómico.
	 *
	 * @param int           $user_id Conta.
	 * @param ChangeContext $context Decisão.
	 */
	public function createAccount( int $user_id, ChangeContext $context ): void;

	/**
	 * Valida transição e revisão sob bloqueio da conta.
	 *
	 * @param int           $user_id           Conta.
	 * @param AccountState  $state             Estado pretendido.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 */
	public function changeState( int $user_id, AccountState $state, int $expected_revision, ChangeContext $context ): void;

	/**
	 * Cria concessão em conta Active e audita na mesma transacção.
	 *
	 * @param Grant         $grant             Concessão nova.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 * @return int Identificador criado.
	 */
	public function createGrant( Grant $grant, int $expected_revision, ChangeContext $context ): int;

	/**
	 * Revoga sob bloqueio sem alterar dados históricos.
	 *
	 * @param int           $user_id           Conta.
	 * @param int           $grant_id          Concessão.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 */
	public function revokeGrant( int $user_id, int $grant_id, int $expected_revision, ChangeContext $context ): void;

	/**
	 * Revoga anterior e cria nova com um único evento e revisão.
	 *
	 * @param int           $user_id           Conta.
	 * @param int           $grant_id          Concessão anterior.
	 * @param Grant         $replacement       Concessão nova.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 * @return int Identificador criado.
	 */
	public function replaceGrant( int $user_id, int $grant_id, Grant $replacement, int $expected_revision, ChangeContext $context ): int;

	/**
	 * Lê conta e todas as concessões na mesma transacção.
	 *
	 * @param int $user_id Conta.
	 * @return AuthorizationSnapshot|null
	 */
	public function find( int $user_id ): ?AuthorizationSnapshot;

	/**
	 * Devolve eventos internos validados por id DESC.
	 *
	 * @param int $user_id Conta.
	 * @param int $limit   Limite de 1 a 100.
	 * @return array Envelopes e payloads JSON já validados pelo adaptador.
	 */
	public function history( int $user_id, int $limit = 50 ): array;
}
