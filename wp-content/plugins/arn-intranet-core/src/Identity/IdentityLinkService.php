<?php
/**
 * Gestão controlada de associações a contas WordPress já existentes.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use DomainException;

// Mensagens estáticas de domínio. Qualquer interface futura escapa na saída.
// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

/**
 * Não autentica, não cria contas, não atribui funções nem regista hooks.
 */
final class IdentityLinkService {

	/**
	 * A integração fornece adaptadores explicitamente.
	 *
	 * @param IdentityLinkRepository $repository Persistência transaccional.
	 * @param IdentityLinkAuthority  $authority  Autorização local.
	 */
	public function __construct(
		private readonly IdentityLinkRepository $repository,
		private readonly IdentityLinkAuthority $authority,
	) {}

	/**
	 * Instalação técnica explícita, sem hooks de activação automáticos.
	 *
	 * @param int $actor_id Actor local actual.
	 */
	public function installSchema( int $actor_id ): void {
		$this->requireActor( $actor_id );
		$this->repository->installSchema();
	}

	/**
	 * Reserva a associação, inicialmente sem autorização de acesso.
	 *
	 * @param int    $actor_id Actor local actual.
	 * @param int    $user_id  Conta previamente criada e confirmada.
	 * @param string $issuer   Emissor Google.
	 * @param string $subject  Claim sub confirmada por procedimento controlado.
	 */
	public function create( int $actor_id, int $user_id, string $issuer, string $subject ): void {
		$this->requireActor( $actor_id );
		$this->requireAccount( $user_id );
		$this->repository->create( new LocalIdentityLink( $user_id, $issuer, $subject, false, false ), $actor_id );
	}

	/**
	 * Autoriza e activa uma associação já existente.
	 *
	 * @param int $actor_id Actor local actual.
	 * @param int $user_id  Conta previamente confirmada.
	 */
	public function activate( int $actor_id, int $user_id ): void {
		$this->requireActor( $actor_id );
		$this->requireAccount( $user_id );
		$this->repository->changeState( $user_id, true, true, $actor_id );
	}

	/**
	 * Revoga a associação mesmo que a conta tenha sido removida.
	 * Revogação das sessões WordPress exige integração futura separada.
	 *
	 * @param int $actor_id Actor local actual.
	 * @param int $user_id  Conta associada.
	 * @throws DomainException Quando a conta é inválida.
	 */
	public function revoke( int $actor_id, int $user_id ): void {
		$this->requireActor( $actor_id );
		if ( $user_id < 1 ) {
			throw new DomainException( 'Conta local inválida.' );
		}
		$this->repository->changeState( $user_id, false, false, $actor_id );
	}

	/**
	 * Consulta para futura política, apenas após validação OIDC completa.
	 * Não autoriza login e conserva os estados para GoogleAccountPolicy.
	 *
	 * @param string $issuer  Emissor Google já validado.
	 * @param string $subject Claim sub já validada.
	 */
	public function find( string $issuer, string $subject ): ?LocalIdentityLink {
		$canonical = LocalIdentityLink::canonicalIssuer( $issuer );
		if ( null === $canonical || ! LocalIdentityLink::validSubject( $subject ) ) {
			return null;
		}
		$link = $this->repository->find( $canonical, $subject );
		if ( null === $link || $link->issuer !== $canonical || $link->subject !== $subject || ! $this->authority->accountExists( $link->user_id ) ) {
			return null;
		}
		return $link;
	}

	/**
	 * Não aceita autorização por email ou identidade externa.
	 *
	 * @param int $actor_id Actor local actual.
	 * @throws DomainException Quando o actor não está autorizado.
	 */
	private function requireActor( int $actor_id ): void {
		if ( $actor_id < 1 || ! $this->authority->canManage( $actor_id ) ) {
			throw new DomainException( 'Operação de identidade não autorizada.' );
		}
	}

	/**
	 * O cadastro prévio é uma dependência, nunca um efeito da associação.
	 *
	 * @param int $user_id Conta local.
	 * @throws DomainException Quando não existe conta prévia.
	 */
	private function requireAccount( int $user_id ): void {
		if ( $user_id < 1 || ! $this->authority->accountExists( $user_id ) ) {
			throw new DomainException( 'Conta local prévia não encontrada.' );
		}
	}
}
