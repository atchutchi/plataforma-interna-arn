<?php
/** Ensaios do formulário local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageProbeTest;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use ArnWordPressDoubles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/AuthorizationStorageProbeTest.php';
require_once __DIR__ . '/LocalAccountSessionDefaultsTest.php';

require_once __DIR__ . '/LocalAccountStatePageTest.php';

/** Cada teste tem flags isoladas. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class LocalGrantManagementPageTest extends TestCase {
	/** Prepara exclusivamente Ana fictícia e actor administrativo. */
	protected function setUp(): void {
		ArnWordPressDoubles::reset();
		AuthorizationStorageProbeTest::$environment                 = 'local';
		AuthorizationStorageProbeTest::$capability                  = true;
		AuthorizationStorageProbeTest::$actor                       = 1;
		AuthorizationStorageProbeTest::$fixture                     = new \WP_User( 2, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		ArnWordPressDoubles::$users[2]                              = AuthorizationStorageProbeTest::$fixture;
		ArnWordPressDoubles::$meta[2][ LocalFixtureUser::META_KEY ] = '1';
		$_SERVER['REQUEST_METHOD']                                  = 'POST';
		$_POST = array(
			'action'       => 'arn-local-grants',
			'_wpnonce'     => 'valid-grants',
			'revision'     => '3',
			'operation'    => 'create',
			'preset'       => 'unit-manager-a',
			'reason'       => 'Ensaio autorizado',
			'user_id'      => '1',
			'actor_id'     => '999',
			'role'         => 'SystemAdministrator',
			'scope'        => 'Institutional',
			'starts_at'    => '0',
			'expires_at'   => '1',
			'decision_ref' => 'BROWSER',
			'revoked'      => 'true',
		);
	}
	/** Activa flags explícitas. */
	private function enable(): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
		define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', true );
	}
	/** O alvo, actor e referência são sempre internos e o serviço recebe CAS. */
	public function testValidPostUsesFixedFixtureAndRedirects303(): void {
		$this->enable();
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::once() )->method( 'createGrant' )->with( self::callback( static fn( $grant ): bool => 2 === $grant->user_id && \Arn\Intranet\Authorization\Role::UnitManager === $grant->role && array( 'unidade-ficticia-a' ) === $grant->scope->targets && abs( time() - $grant->starts_at ) < 3 && null === $grant->expires_at && ! $grant->revoked ), 3, self::callback( static fn( ChangeContext $context ): bool => 1 === $context->actor_id && 'LAB-GRANT-MANAGEMENT' === $context->decision_ref && 'Ensaio autorizado' === $context->reason && abs( time() - $context->occurred_at ) < 3 ) );
		$page = new LocalGrantManagementPage( static fn() => $repository );
		$this->expectExceptionCode( 303 );
		$this->expectExceptionMessage( 'tools.php?page=arn-local-grants&message=updated' );
		$page->handlePost();
	}
	/** Verifica entradas e condições efectivas.
	 *
	 * @param string $field Campo.
	 * @param mixed  $value Valor inválido.
	 */
	#[DataProvider( 'invalidInputs' )]
	public function testInvalidInputNeverOpensStorage( string $field, mixed $value ): void {
		$this->enable();
		if ( 'method' === $field ) {
			$_SERVER['REQUEST_METHOD'] = $value;
		} else {
			$_POST[ $field ] = $value;
		}
		$page = new LocalGrantManagementPage(
			static function () {
				self::fail( 'Não deve abrir persistência.' );
			}
		);
		$this->expectException( \RuntimeException::class );
		$page->handlePost();
	}
	/** Entradas não canónicas nunca chegam ao serviço. */
	public static function invalidInputs(): array {
		return array( array( 'method', 'GET' ), array( '_wpnonce', 'bad' ), array( '_wpnonce', 'valid' ), array( '_wpnonce', array() ), array( 'action', 'other' ), array( 'revision', '03' ), array( 'revision', '+3' ), array( 'revision', '0' ), array( 'revision', '3.0' ), array( 'revision', '9223372036854775808' ), array( 'revision', array() ), array( 'operation', 'other' ), array( 'operation', array() ), array( 'preset', 'administrator' ), array( 'preset', array() ), array( 'reason', '' ), array( 'reason', ' motivo' ), array( 'reason', "a\nb" ), array( 'reason', str_repeat( 'a', 501 ) ), array( 'reason', array() ) );
	}
	/** Verifica entradas e condições efectivas.
	 *
	 * @param string $gate Gate recusado.
	 */
	#[DataProvider( 'deniedGates' )]
	public function testGatesAndFixtureRejectBeforeStorage( string $gate ): void {
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'string-flag' === $gate ? 'true' : 'storage' !== $gate );
		define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'fictional' !== $gate );
		if ( 'environment' === $gate ) {
			AuthorizationStorageProbeTest::$environment = 'development';
		}
		if ( 'capability' === $gate ) {
			AuthorizationStorageProbeTest::$capability = false;
		}
		if ( 'mark' === $gate ) {
			ArnWordPressDoubles::$meta = array();
		}
		if ( 'missing' === $gate ) {
			AuthorizationStorageProbeTest::$fixture = false;
		}
		if ( 'wrong-login' === $gate ) {
			AuthorizationStorageProbeTest::$fixture->user_login = 'lab_admin';
		}
		if ( 'wrong-mark' === $gate ) {
			ArnWordPressDoubles::$meta[2][ LocalFixtureUser::META_KEY ] = 'yes';
		}
		$page = new LocalGrantManagementPage(
			static function () {
				self::fail( 'Não deve abrir persistência.' );
			}
		);
		$this->expectException( \RuntimeException::class );
		$page->handlePost();
	}
	/** Gates reais, sem overrides no formulário. */
	public static function deniedGates(): array {
		return array_map( static fn( $gate ) => array( $gate ), array( 'storage', 'fictional', 'environment', 'capability', 'mark', 'missing', 'wrong-login', 'wrong-mark', 'string-flag' ) );
	}
	/** A revisão recusada não mostra detalhes internos. */
	public function testConflictRequiresReloadWithStaticError(): void {
		$this->enable();
		$repository = $this->createMock( AuthorizationRepository::class );
		$repository->method( 'createGrant' )->willThrowException( new \RuntimeException( 'SQL private' ) );
		$page = new LocalGrantManagementPage( static fn() => $repository );
		$this->expectExceptionCode( 409 );
		$this->expectExceptionMessage( 'Recarrega' );
		$page->handlePost();
	}
	/** GET só lê e o formulário nativo liga rótulos e nonce. */
	public function testRenderReadsOnlyAndEscapesNoArbitraryMessage(): void {
		$this->enable();
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_GET['message']           = '<script>bad</script>';
		$repository                = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::never() )->method( 'createGrant' );
		$repository->expects( self::once() )->method( 'find' )->with( 2 )->willReturn( new AuthorizationSnapshot( 2, AccountState::Active, 3, array() ) );
		$page = new LocalGrantManagementPage( static fn() => $repository );
		ob_start();
		$page->render();
		$html = ob_get_clean();
		self::assertStringContainsString( 'method="post"', $html );
		self::assertStringContainsString( 'name="revision" value="3"', $html );
		self::assertStringContainsString( 'name="_wpnonce"', $html );
		self::assertStringContainsString( 'for="arn-grant-reason"', $html );
		self::assertStringContainsString( 'id="arn-grant-reason"', $html );
		self::assertStringNotContainsString( 'name="user_id"', $html );
		self::assertStringNotContainsString( '<script>', $html );
		$page->register();
		$page->registerPage();
		self::assertArrayHasKey( 'admin_post_arn-local-grants', ArnWordPressDoubles::$filters );
		self::assertArrayHasKey( 'arn-local-grants', AuthorizationStorageProbeTest::$pages );
	}
	/** Substituir e revogar encaminham identidade e CAS pelo serviço.
	 *
	 * @param string $operation Operação.
	 */
	#[DataProvider( 'existingOperations' )]
	public function testExistingGrantUsesServerTarget( string $operation ): void {
		$this->enable();
		$_POST['operation'] = $operation;
		$_POST['grant_id']  = '7';
		$repository         = $this->createMock( AuthorizationRepository::class );
		$repository->expects( self::never() )->method( 'createGrant' );
		$context = self::callback( static fn( ChangeContext $context ): bool => 1 === $context->actor_id && 'LAB-GRANT-MANAGEMENT' === $context->decision_ref );
		if ( 'replace' === $operation ) {
			$repository->expects( self::once() )->method( 'replaceGrant' )->with( 2, 7, self::callback( static fn( $grant ): bool => 2 === $grant->user_id && ! $grant->revoked ), 3, $context );
		} else {
			unset( $_POST['preset'] );
			$repository->expects( self::once() )->method( 'revokeGrant' )->with( 2, 7, 3, $context );
		}
		$this->expectExceptionCode( 303 );
		( new LocalGrantManagementPage( static fn() => $repository ) )->handlePost();
	}
	/** Operações sobre concessão anterior. */
	public static function existingOperations(): array {
		return array( array( 'replace' ), array( 'revoke' ) );
	}
	/** IDs malformados são recusados antes da ligação.
	 *
	 * @param string $operation Operação.
	 * @param mixed  $value ID recebido.
	 */
	#[DataProvider( 'invalidGrantIds' )]
	public function testInvalidGrantIdNeverOpensStorage( string $operation, mixed $value ): void {
		$this->enable();
		$_POST['operation'] = $operation;
		$_POST['grant_id']  = $value;
		$this->expectExceptionCode( 400 );
		( new LocalGrantManagementPage(
			static function () {
				self::fail( 'Não deve abrir persistência.' );
			}
		) )->handlePost();
	}
	/** Ambos os fluxos exigem o mesmo inteiro canónico. */
	public static function invalidGrantIds(): array {
		$cases = array();
		foreach ( array( 'replace', 'revoke' ) as $operation ) {
			foreach ( array( null, '', '0', '-1', '+7', '07', '7.0', '7e0', ' 7', '7 ', "7\n", '9223372036854775808', array(), 7 ) as $value ) {
				$cases[] = array( $operation, $value );
			}
		}
		return $cases;
	}
	/** Histórico revogado continua visível, fora das opções de escrita. */
	public function testRevokedHistoryIsVisibleAndCannotBeSelected(): void {
		$this->enable();
		$repository = $this->createMock( AuthorizationRepository::class );
		$grant      = new \Arn\Intranet\Authorization\Grant( 2, \Arn\Intranet\Authorization\Role::Employee, \Arn\Intranet\Authorization\Scope::own(), 0, null, true );
		$repository->method( 'find' )->willReturn( new AuthorizationSnapshot( 2, AccountState::Suspended, 3, array( new \Arn\Intranet\Authorization\Storage\StoredGrant( 7, $grant ) ) ) );
		ob_start();
		( new LocalGrantManagementPage( static fn() => $repository ) )->render();
		$html = ob_get_clean();
		self::assertStringContainsString( 'ID 7:', $html );
		self::assertStringContainsString( 'Revogada', $html );
		self::assertStringContainsString( 'Suspensa', $html );
		self::assertStringNotContainsString( '<option value="7">', $html );
		self::assertStringContainsString( 'tools.php?page=arn-authorization-storage', $html );
	}
	/** Sem flags, nem hooks nem GET abrem uma ligação. */
	public function testMissingFlagsCloseRegistrationAndRender(): void {
		$page = new LocalGrantManagementPage(
			static function () {
				self::fail( 'Não deve abrir persistência.' );
			}
		);
		$page->register();
		$page->registerPage();
		ob_start();
		$page->render();
		self::assertSame( '', ob_get_clean() );
		self::assertArrayNotHasKey( 'admin_post_arn-local-grants', ArnWordPressDoubles::$filters );
	}
}
