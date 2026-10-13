<?php
/**
 * Decisões explícitas sobre contas já existentes, sem autenticação.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\IdentityLinkAuthority;
use Arn\Intranet\Identity\IdentityLinkRepository;
use Arn\Intranet\Identity\IdentityLinkService;
use Arn\Intranet\Identity\LocalIdentityLink;
use DomainException;
use PHPUnit\Framework\TestCase;

/**
 * As regras não dependem de WordPress nem de emails.
 */
final class IdentityLinkServiceTest extends TestCase {

	/**
	 * Uma criação explícita ainda não concede acesso.
	 */
	public function testCreationStartsInactiveAndUnauthorized(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::once() )->method( 'create' )->with(
			self::callback(
				static fn( LocalIdentityLink $link ): bool =>
				12 === $link->user_id && 'https://accounts.google.com' === $link->issuer &&
				'opaque-sub' === $link->subject && ! $link->active && ! $link->authorized
			),
			7,
		);
		$service = new IdentityLinkService( $repository, $this->authority() );
		$service->create( 7, 12, 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * A autorização é verificada antes de qualquer escrita.
	 */
	public function testUnauthorizedActorCannotCreateLink(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'create' );
		$service = new IdentityLinkService( $repository, $this->authority( false ) );
		$this->expectException( DomainException::class );
		$service->create( 7, 12, 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Nunca cria automaticamente a conta local ausente.
	 */
	public function testMissingLocalAccountCannotBeLinked(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'create' );
		$service = new IdentityLinkService( $repository, $this->authority( true, false ) );
		$this->expectException( DomainException::class );
		$service->create( 7, 12, 'accounts.google.com', 'opaque-sub' );
	}

	/**
	 * Activar é uma decisão explícita do actor autorizado.
	 */
	public function testActivationIsAnExplicitStateChange(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::once() )->method( 'changeState' )->with( 12, true, true, 7 );
		$service = new IdentityLinkService( $repository, $this->authority() );
		$service->activate( 7, 12 );
	}

	/**
	 * Revogar retira estado activo e autorização.
	 */
	public function testRevocationRemovesBothAccessConditions(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::once() )->method( 'changeState' )->with( 12, false, false, 7 );
		$service = new IdentityLinkService( $repository, $this->authority() );
		$service->revoke( 7, 12 );
	}

	/**
	 * A autorização é necessária também para activar, sem escrita parcial.
	 */
	public function testUnauthorizedActorCannotActivate(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'changeState' );
		$service = new IdentityLinkService( $repository, $this->authority( false ) );
		$this->expectException( DomainException::class );
		$service->activate( 7, 12 );
	}

	/**
	 * Um actor sem competência também não pode retirar acesso alheio.
	 */
	public function testUnauthorizedActorCannotRevoke(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'changeState' );
		$service = new IdentityLinkService( $repository, $this->authority( false ) );
		$this->expectException( DomainException::class );
		$service->revoke( 7, 12 );
	}

	/**
	 * Activar não pode restaurar automaticamente uma conta removida.
	 */
	public function testMissingAccountCannotBeActivated(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'changeState' );
		$service = new IdentityLinkService( $repository, $this->authority( true, false ) );
		$this->expectException( DomainException::class );
		$service->activate( 7, 12 );
	}

	/**
	 * Um vínculo pode ser revogado depois de a conta local desaparecer.
	 */
	public function testMissingAccountLinkCanStillBeRevoked(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::once() )->method( 'changeState' )->with( 12, false, false, 7 );
		$service = new IdentityLinkService( $repository, $this->authority( true, false ) );
		$service->revoke( 7, 12 );
	}

	/**
	 * A instalação também exige decisão administrativa explícita.
	 */
	public function testUnauthorizedActorCannotInstallSchema(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'installSchema' );
		$service = new IdentityLinkService( $repository, $this->authority( false ) );
		$this->expectException( DomainException::class );
		$service->installSchema( 7 );
	}

	/**
	 * Uma conta removida não pode voltar a ser resolvida.
	 */
	public function testLookupFailsClosedWhenAccountDisappears(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->method( 'find' )->willReturn( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', true, true ) );
		$service = new IdentityLinkService( $repository, $this->authority( true, false ) );
		self::assertNull( $service->find( 'accounts.google.com', 'opaque-sub' ) );
	}

	/**
	 * As identidades fornecidas pelo repositório são reconferidas.
	 */
	public function testLookupRejectsMismatchedIdentity(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->method( 'find' )->willReturn( new LocalIdentityLink( 12, 'accounts.google.com', 'other-sub', true, true ) );
		$service = new IdentityLinkService( $repository, $this->authority() );
		self::assertNull( $service->find( 'accounts.google.com', 'opaque-sub' ) );
	}

	/**
	 * A consulta conserva o estado revogado para a política negar acesso.
	 */
	public function testLookupPreservesRevokedState(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->method( 'find' )->with( 'https://accounts.google.com', 'opaque-sub' )->willReturn(
			new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false )
		);
		$service = new IdentityLinkService( $repository, $this->authority() );
		self::assertFalse( $service->find( 'accounts.google.com', 'opaque-sub' )->authorized );
	}

	/**
	 * Um emissor inválido não chega ao armazenamento.
	 */
	public function testInvalidLookupDoesNotAccessRepository(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'find' );
		$service = new IdentityLinkService( $repository, $this->authority() );
		self::assertNull( $service->find( 'https://example.test', 'opaque-sub' ) );
	}

	/**
	 * A consulta pelo ID exige conta local existente e conserva revogação.
	 */
	public function testUserLookupPreservesRevokedState(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$link       = new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', false, false );
		$repository->method( 'findForUser' )->with( 12 )->willReturn( $link );
		$authority = $this->createMock( IdentityLinkAuthority::class );
		$authority->expects( self::once() )->method( 'accountExists' )->with( 12 )->willReturn( true );
		$service = new IdentityLinkService( $repository, $authority );
		self::assertSame( $link, $service->findForUser( 12 ) );
	}

	/**
	 * A remoção da conta nega também a resolução pelo ID.
	 */
	public function testUserLookupRejectsRemovedAccount(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->method( 'findForUser' )->willReturn( new LocalIdentityLink( 12, 'accounts.google.com', 'opaque-sub', true, true ) );
		$service = new IdentityLinkService( $repository, $this->authority( true, false ) );
		self::assertNull( $service->findForUser( 12 ) );
	}

	/**
	 * Um adaptador não pode trocar a conta solicitada.
	 */
	public function testUserLookupRejectsMismatchedAccount(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->method( 'findForUser' )->willReturn( new LocalIdentityLink( 13, 'accounts.google.com', 'opaque-sub', true, true ) );
		$service = new IdentityLinkService( $repository, $this->authority() );
		self::assertNull( $service->findForUser( 12 ) );
	}

	/**
	 * Identificadores inválidos nunca chegam ao armazenamento.
	 */
	public function testInvalidUserLookupDoesNotAccessRepository(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$repository->expects( self::never() )->method( 'findForUser' );
		$service = new IdentityLinkService( $repository, $this->authority() );
		foreach ( array( 0, -1 ) as $user_id ) {
			self::assertNull( $service->findForUser( $user_id ) );
		}
	}

	/**
	 * Uma conta cadastrada sem vínculo permanece sem associação.
	 */
	public function testUserLookupReturnsNullForMissingLink(): void {
		$repository = $this->createMock( IdentityLinkRepository::class );
		$service    = new IdentityLinkService( $repository, $this->authority() );
		self::assertNull( $service->findForUser( 12 ) );
	}

	/**
	 * Confirma os dois requisitos de administração sem WordPress.
	 *
	 * @param bool $allowed Actor pode gerir vínculos.
	 * @param bool $exists  Conta já existe.
	 */
	private function authority( bool $allowed = true, bool $exists = true ): IdentityLinkAuthority {
		$authority = $this->createMock( IdentityLinkAuthority::class );
		$authority->method( 'canManage' )->willReturn( $allowed );
		$authority->method( 'accountExists' )->willReturn( $exists );
		return $authority;
	}
}
