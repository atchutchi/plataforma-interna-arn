<?php
/**
 * Fronteira administrativa de persistência.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Autoridade antecede todas as operações do repositório. */
final class AuthorizationStorageServiceTest extends TestCase {

	/** Todas as operações recusam actor ou conta sem encaminhamento. */
	public function testAuthorityGuardsEveryOperation(): void {
		foreach ( array( array( false, true ), array( true, false ) ) as $allowed ) {
			foreach ( array( 'createAccount', 'changeState', 'createGrant', 'revokeGrant', 'replaceGrant', 'find', 'history' ) as $method ) {
				$repository = $this->createMock( AuthorizationRepository::class );
				foreach ( array( 'installSchema', 'createAccount', 'changeState', 'createGrant', 'revokeGrant', 'replaceGrant', 'find', 'history' ) as $operation ) {
					$repository->expects( self::never() )->method( $operation );
				}
				$authority = $this->createMock( AuthorizationStorageAuthority::class );
				$authority->method( 'canManage' )->with( 7 )->willReturn( $allowed[0] );
				$authority->method( 'canUseAccount' )->with( 2 )->willReturn( $allowed[1] );
				$service = new AuthorizationStorageService( $repository, $authority );
				try {
					$service->$method( ...$this->arguments( $method ) );
					self::fail( 'Operação não autorizada.' );
				} catch ( DomainException $exception ) {
					self::assertSame( 'Operação não autorizada.', $exception->getMessage() );
				}
			}
		}
	}

	/** Delega apenas após validar e nunca pré-lê o estado. */
	public function testAuthorizedOperationsForwardExactInputs(): void {
		foreach ( array( 'installSchema', 'createAccount', 'changeState', 'createGrant', 'revokeGrant', 'replaceGrant', 'find', 'history' ) as $method ) {
			$repository = $this->createMock( AuthorizationRepository::class );
			$args       = $this->arguments( $method );
			$repo_args  = match ( $method ) {
				'installSchema' => array(),
				'find', 'history' => array_slice( $args, 1 ),
				default => $args,
			};
			$expectation = $repository->expects( self::once() )->method( $method )->with( ...$repo_args );
			if ( in_array( $method, array( 'createGrant', 'replaceGrant' ), true ) ) {
				$expectation->willReturn( 4 );
			} elseif ( 'history' === $method ) {
				$expectation->willReturn( array() );
			}
			if ( 'find' !== $method ) {
				$repository->expects( self::never() )->method( 'find' );
			}
			$service = new AuthorizationStorageService( $repository, $this->authority() );
			$result  = $service->$method( ...$args );
			if ( in_array( $method, array( 'createGrant', 'replaceGrant' ), true ) ) {
				self::assertSame( 4, $result );
			}
		}
	}

	/** Instalação tem actor explícito. */
	public function testSchemaInstallationRequiresAuthority(): void {
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::never() )->method( 'installSchema' );
		$authority = $this->createMock( AuthorizationStorageAuthority::class );
		$authority->method( 'canManage' )->willReturn( false );
		$this->expectException( DomainException::class );
		( new AuthorizationStorageService( $repository, $authority ) )->installSchema( $this->context() );
	}

	/** Parâmetros inválidos não chegam ao repositório. */
	public function testInvalidInputsNeverReachRepository(): void {
		$revoked = new Grant( 2, Role::Employee, Scope::own(), 0, null, true );
		foreach ( array( array( 'find', array( 0, 2 ) ), array( 'find', array( 7, 0 ) ), array( 'history', array( 7, 2, 0 ) ), array( 'history', array( 7, 2, 101 ) ), array( 'changeState', array( 2, AccountState::Active, 0, $this->context() ) ), array( 'revokeGrant', array( 2, 0, 1, $this->context() ) ), array( 'createGrant', array( $revoked, 1, $this->context() ) ), array( 'replaceGrant', array( 3, 1, $revoked, 1, $this->context() ) ) ) as $case ) {
			$repository = $this->createMock( AuthorizationRepository::class );
			$repository->expects( self::never() )->method( $case[0] );
			$service = new AuthorizationStorageService( $repository, $this->authority() );
			try {
				$service->{$case[0]}( ...$case[1] );
				self::fail( 'Parâmetro inválido aceite.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/** Recusa resultado de outra conta e preserva ausência. */
	public function testFindChecksExactAccountAndPreservesNull(): void {
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->method( 'find' )->willReturn( null );
		self::assertNull( ( new AuthorizationStorageService( $repository, $this->authority() ) )->find( 7, 2 ) );
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->method( 'find' )->willReturn( new AuthorizationSnapshot( 3, AccountState::Pending, 1, array() ) );
		$this->expectException( DomainException::class );
		( new AuthorizationStorageService( $repository, $this->authority() ) )->find( 7, 2 );
	}

	/** Uma concessão válida de outra conta também não pode substituir. */
	public function testReplacementCannotChangeRecipient(): void {
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::never() )->method( 'replaceGrant' );
		$service = new AuthorizationStorageService( $repository, $this->authority() );
		$this->expectException( InvalidArgumentException::class );
		$service->replaceGrant( 2, 1, new Grant( 3, Role::Employee, Scope::own(), 0, null ), 1, $this->context() );
	}

	/** Leitura válida e limites 1/50/100 são preservados. */
	public function testReadResultsAndHistoryLimits(): void {
		$snapshot   = new AuthorizationSnapshot( 2, AccountState::Suspended, 9, array() );
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::once() )->method( 'find' )->with( 2 )->willReturn( $snapshot );
		$repository->expects( self::exactly( 3 ) )->method( 'history' )->willReturnCallback(
			static function ( int $user_id, int $limit ): array {
				self::assertSame( 2, $user_id );
				self::assertContains( $limit, array( 1, 50, 100 ) );
				return array();
			}
		);
		$service = new AuthorizationStorageService( $repository, $this->authority() );
		self::assertSame( $snapshot, $service->find( 7, 2 ) );
		self::assertSame( array(), $service->history( 7, 2, 1 ) );
		self::assertSame( array(), $service->history( 7, 2 ) );
		self::assertSame( array(), $service->history( 7, 2, 100 ) );
	}

	/**
	 * Contexto interno válido.
	 *
	 * @return ChangeContext
	 */
	private function context(): ChangeContext {
		return new ChangeContext( 7, 'Ensaio local', 'LAB-1', 0 );
	}

	/**
	 * Autoridade sintética.
	 *
	 * @return AuthorizationStorageAuthority
	 */
	private function authority(): AuthorizationStorageAuthority {
		$authority = $this->createMock( AuthorizationStorageAuthority::class );
		$authority->method( 'canManage' )->willReturn( true );
		$authority->method( 'canUseAccount' )->willReturn( true );
		return $authority;
	}

	/**
	 * Argumentos válidos do contrato.
	 *
	 * @param string $method Operação.
	 * @return array
	 */
	private function arguments( string $method ): array {
		$grant = new Grant( 2, Role::Employee, Scope::own(), 0, null );
		return match ( $method ) {
			'installSchema' => array( $this->context() ),
			'createAccount' => array( 2, $this->context() ),
			'changeState' => array( 2, AccountState::Active, 1, $this->context() ),
			'createGrant' => array( $grant, 1, $this->context() ),
			'revokeGrant' => array( 2, 1, 1, $this->context() ),
			'replaceGrant' => array( 2, 1, $grant, 1, $this->context() ),
			'find' => array( 7, 2 ),
			'history' => array( 7, 2, 100 ),
		};
	}
}
