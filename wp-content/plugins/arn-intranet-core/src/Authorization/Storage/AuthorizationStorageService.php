<?php
/**
 * Guarda administrativa do laboratório de persistência.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use DomainException;
use InvalidArgumentException;

// Mensagens constantes de domínio, escapadas na interface.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Não consulta antecipadamente estado nem presume validade concorrente. */
final class AuthorizationStorageService {

	/**
	 * Recebe fronteiras explícitas, sem WordPress.
	 *
	 * @param AuthorizationRepository       $repository Persistência.
	 * @param AuthorizationStorageAuthority $authority  Autoridade local.
	 */
	public function __construct( private readonly AuthorizationRepository $repository, private readonly AuthorizationStorageAuthority $authority ) {}

	/**
	 * Instalação explícita exige actor, sem conta alvo.
	 *
	 * @param ChangeContext $context Decisão.
	 */
	public function installSchema( ChangeContext $context ): void {
		$this->requireActor( $context->actor_id );
		$this->repository->installSchema();
	}

	/**
	 * Cria apenas conta permitida.
	 *
	 * @param int           $user_id Conta.
	 * @param ChangeContext $context Decisão.
	 */
	public function createAccount( int $user_id, ChangeContext $context ): void {
		$this->requireAuthority( $context->actor_id, $user_id );
		$this->repository->createAccount( $user_id, $context );
	}

	/**
	 * A transição é validada dentro da transacção pelo adaptador.
	 *
	 * @param int           $user_id           Conta.
	 * @param AccountState  $state             Estado pretendido.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 */
	public function changeState( int $user_id, AccountState $state, int $expected_revision, ChangeContext $context ): void {
		$this->positive( $expected_revision );
		$this->requireAuthority( $context->actor_id, $user_id );
		$this->repository->changeState( $user_id, $state, $expected_revision, $context );
	}

	/**
	 * Recusa concessão nova já revogada.
	 *
	 * @param Grant         $grant             Concessão.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 * @return int
	 */
	public function createGrant( Grant $grant, int $expected_revision, ChangeContext $context ): int {
		$this->positive( $expected_revision );
		$this->requireNewGrant( $grant );
		$this->requireAuthority( $context->actor_id, $grant->user_id );
		return $this->repository->createGrant( $grant, $expected_revision, $context );
	}

	/**
	 * Encaminha revogação após validar autoridade e parâmetros.
	 *
	 * @param int           $user_id           Conta.
	 * @param int           $grant_id          Concessão.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 */
	public function revokeGrant( int $user_id, int $grant_id, int $expected_revision, ChangeContext $context ): void {
		$this->positive( $grant_id );
		$this->positive( $expected_revision );
		$this->requireAuthority( $context->actor_id, $user_id );
		$this->repository->revokeGrant( $user_id, $grant_id, $expected_revision, $context );
	}

	/**
	 * Exige substituição da mesma conta alvo.
	 *
	 * @param int           $user_id           Conta.
	 * @param int           $grant_id          Concessão anterior.
	 * @param Grant         $replacement       Nova concessão.
	 * @param int           $expected_revision Revisão esperada.
	 * @param ChangeContext $context           Decisão.
	 * @return int
	 * @throws InvalidArgumentException Quando a validação recusa a operação.
	 */
	public function replaceGrant( int $user_id, int $grant_id, Grant $replacement, int $expected_revision, ChangeContext $context ): int {
		$this->positive( $grant_id );
		$this->positive( $expected_revision );
		$this->requireNewGrant( $replacement );
		if ( $replacement->user_id !== $user_id ) {
			throw new InvalidArgumentException( 'Concessão nova inválida.' );
		}
		$this->requireAuthority( $context->actor_id, $user_id );
		return $this->repository->replaceGrant( $user_id, $grant_id, $replacement, $expected_revision, $context );
	}

	/**
	 * Consulta administrativa exige actor e conta permitidos.
	 *
	 * @param int $actor_id Actor.
	 * @param int $user_id  Conta.
	 * @return AuthorizationSnapshot|null
	 * @throws DomainException Quando a validação recusa a operação.
	 */
	public function find( int $actor_id, int $user_id ): ?AuthorizationSnapshot {
		$this->requireAuthority( $actor_id, $user_id );
		$snapshot = $this->repository->find( $user_id );
		if ( null !== $snapshot && $snapshot->user_id !== $user_id ) {
			throw new DomainException( 'Snapshot persistente inválido.' );
		}
		return $snapshot;
	}

	/**
	 * O adaptador valida o envelope e payloads antes de devolver eventos.
	 *
	 * @param int $actor_id Actor.
	 * @param int $user_id  Conta.
	 * @param int $limit    Limite de 1 a 100.
	 * @return array
	 * @throws InvalidArgumentException Quando a validação recusa a operação.
	 */
	public function history( int $actor_id, int $user_id, int $limit = 50 ): array {
		if ( $limit < 1 || $limit > 100 ) {
			throw new InvalidArgumentException( 'Limite inválido.' );
		}
		$this->requireAuthority( $actor_id, $user_id );
		return $this->repository->history( $user_id, $limit );
	}

	/**
	 * Recusa identificadores e revisões não positivos.
	 *
	 * @param int $value Valor interno.
	 * @throws InvalidArgumentException Quando a validação recusa a operação.
	 */
	private function positive( int $value ): void {
		if ( $value <= 0 ) {
			throw new InvalidArgumentException( 'Identificador ou revisão inválidos.' );
		}
	}

	/**
	 * Exige actor autorizado antes de aceder ao adaptador.
	 *
	 * @param int $actor_id Actor.
	 * @throws DomainException Quando a validação recusa a operação.
	 */
	private function requireActor( int $actor_id ): void {
		$this->positive( $actor_id );
		if ( ! $this->authority->canManage( $actor_id ) ) {
			throw new DomainException( 'Operação não autorizada.' );
		}
	}

	/**
	 * Confirma a autoridade do actor e a conta fictícia alvo.
	 *
	 * @param int $actor_id Actor.
	 * @param int $user_id  Conta.
	 * @throws DomainException Quando a validação recusa a operação.
	 */
	private function requireAuthority( int $actor_id, int $user_id ): void {
		$this->positive( $user_id );
		$this->requireActor( $actor_id );
		if ( ! $this->authority->canUseAccount( $user_id ) ) {
			throw new DomainException( 'Operação não autorizada.' );
		}
	}

	/**
	 * As restantes invariantes já pertencem ao valor Grant.
	 *
	 * @param Grant $grant Concessão nova.
	 * @throws InvalidArgumentException Quando a validação recusa a operação.
	 */
	private function requireNewGrant( Grant $grant ): void {
		if ( $grant->revoked ) {
			throw new InvalidArgumentException( 'Concessão nova inválida.' );
		}
	}
}
