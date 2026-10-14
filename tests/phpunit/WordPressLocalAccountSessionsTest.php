<?php
/** Sessões locais fictícias e geração persistente.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use ArnWordPressDoubles;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_User;

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Doubles do namespace isolam os hooks.
/** Token do pedido simulado. */
function wp_get_session_token(): string {
	return WordPressLocalAccountSessionsTest::$token;
}
/**
 * Regista a acção usando o mesmo armazenamento dos filtros.
 *
 * @param string   $hook Nome.
 * @param callable $callback Callback.
 * @param int      $priority Prioridade.
 * @param int      $accepted_args Argumentos.
 */
function add_action( string $hook, callable $callback, int $priority, int $accepted_args ): void {
	add_filter( $hook, $callback, $priority, $accepted_args );
}
/** Verifica decisões sem WordPress nem SQL. */
final class WordPressLocalAccountSessionsTest extends TestCase {
	/** Token fictício do pedido.
	 *
	 * @var string
	 */
	public static string $token = 'old';
	/** Leitura simulada.
	 *
	 * @var mixed
	 */
	private mixed $snapshot;
	/** Gate interno.
	 *
	 * @var bool
	 */
	private bool $enabled = true;
	/** Número de consultas.
	 *
	 * @var int
	 */
	private int $reads = 0;
	/** Adaptador.
	 *
	 * @var WordPressLocalAccountSessions
	 */
	private WordPressLocalAccountSessions $guard;
	/** Prepara fixtures isoladas. */
	protected function setUp(): void {
		ArnWordPressDoubles::reset();
		self::$token                    = 'old';
		ArnWordPressDoubles::$users[10] = new WP_User( 10, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		ArnWordPressDoubles::$users[20] = new WP_User( 20, 'lab_admin', 'admin@example.test' );
		ArnWordPressDoubles::$meta[10]  = array( LocalFixtureUser::META_KEY => '1' );
		$this->snapshot                 = new AuthorizationSnapshot( 10, AccountState::Active, 2, array() );
		$this->guard                    = new WordPressLocalAccountSessions(
			function (): mixed {
				++$this->reads;
				if ( $this->snapshot instanceof \Throwable ) {
					throw $this->snapshot;
				}
				return $this->snapshot;
			},
			fn(): bool => $this->enabled
		);
	}
	/** Estado e tipos nativos são obrigatórios. */
	public function testPurePolicyRequiresActiveMatchingAccountAndExactGeneration(): void {
		$session = array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 10,
			'arn_authz_revision' => 2,
		);
		self::assertTrue( LocalAccountSessionPolicy::active( $this->snapshot, 10 ) );
		self::assertTrue( LocalAccountSessionPolicy::valid( $this->snapshot, 10, $session, true ) );
		self::assertFalse( LocalAccountSessionPolicy::valid( $this->snapshot, 10, $session, false ) );
		self::assertFalse( LocalAccountSessionPolicy::active( $this->snapshot, 20 ) );
		self::assertFalse( LocalAccountSessionPolicy::active( null, 10 ) );
		foreach ( AccountState::cases() as $state ) {
			self::assertSame( AccountState::Active === $state, LocalAccountSessionPolicy::active( new AuthorizationSnapshot( 10, $state, 2, array() ), 10 ) );
		}
		foreach ( array( array(), array( 'arn_authz_local' => 1 ), array( 'arn_authz_user_id' => '10' ), array( 'arn_authz_revision' => '2' ), array( 'arn_authz_revision' => 1 ) ) as $change ) {
			$invalid = array() === $change ? array() : array_replace( $session, $change );
			self::assertFalse( LocalAccountSessionPolicy::valid( $this->snapshot, 10, $invalid, true ) );
		}
	}
	/** Cada fase reconsulta a revisão efectiva. */
	public function testIssueAndResolveRevalidateWithoutCaching(): void {
		$user = ArnWordPressDoubles::$users[10];
		self::assertSame( $user, $this->guard->filterAuthentication( $user ) );
		$session = $this->guard->attachSessionInformation( array( 'expiration' => 123 ), 10 );
		self::assertSame(
			array(
				'expiration'         => 123,
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => 10,
				'arn_authz_revision' => 2,
			),
			$session
		);
		ArnWordPressDoubles::$sessions[10]['old'] = $session;
		self::assertTrue( $this->guard->filterSendAuthCookies( true, 0, 0, 10, '', 'old' ) );
		self::assertSame( 10, $this->guard->filterCurrentUser( 10 ) );
		self::assertSame( 4, $this->reads );
		$this->snapshot = new AuthorizationSnapshot( 10, AccountState::Suspended, 3, array() );
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		self::assertSame( array(), ArnWordPressDoubles::$sessions[10] );
	}
	/** Reactivação e concessões mudam a geração, preservando novos tokens. */
	public function testOldTokenCannotResurrectAndNewTokenSurvives(): void {
		ArnWordPressDoubles::$sessions[10]['old'] = $this->guard->attachSessionInformation( array(), 10 );
		$this->snapshot                           = new AuthorizationSnapshot( 10, AccountState::Active, 4, array() );
		ArnWordPressDoubles::$sessions[10]['new'] = $this->guard->attachSessionInformation( array(), 10 );
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		self::assertArrayNotHasKey( 'old', ArnWordPressDoubles::$sessions[10] );
		self::$token = 'new';
		self::assertSame( 10, $this->guard->filterCurrentUser( 10 ) );
	}
	/** Falhas e leituras inválidas fecham todas as vias. */
	public function testInvalidSnapshotsDenyAuthenticationResetAndCookies(): void {
		foreach ( array( null, array(), new \RuntimeException( 'internal' ), new AuthorizationSnapshot( 20, AccountState::Active, 2, array() ), new AuthorizationSnapshot( 10, AccountState::Pending, 2, array() ), new AuthorizationSnapshot( 10, AccountState::Suspended, 2, array() ) ) as $snapshot ) {
			$this->snapshot = $snapshot;
			self::assertInstanceOf( WP_Error::class, $this->guard->filterAuthentication( ArnWordPressDoubles::$users[10] ) );
			self::assertFalse( $this->guard->filterPasswordReset( true, 10 ) );
			$errors = new WP_Error( '' );
			$this->guard->validatePasswordReset( $errors, ArnWordPressDoubles::$users[10] );
			self::assertSame( 'arn_local_account_unavailable', $errors->get_error_code() );
			ArnWordPressDoubles::$sessions[10]['old'] = $this->guard->attachSessionInformation( array(), 10 );
			self::assertFalse( $this->guard->filterSendAuthCookies( true, 0, 0, 10, '', 'old' ) );
			self::assertSame( array(), ArnWordPressDoubles::$sessions[10] );
		}
	}
	/** Falha entre criação e envio não deixa token utilizável. */
	public function testRepositoryFailureAtCookieEmissionRevokesToken(): void {
		ArnWordPressDoubles::$sessions[10]['old'] = $this->guard->attachSessionInformation( array(), 10 );
		$this->snapshot                           = new \RuntimeException( 'internal' );
		self::assertFalse( $this->guard->filterSendAuthCookies( true, 0, 0, 10, '', 'old' ) );
		self::assertSame( array(), ArnWordPressDoubles::$sessions[10] );
	}
	/** Uma revisão alterada antes do envio recusa só a geração antiga. */
	public function testRevisionChangedBeforeSendingPreservesNewSession(): void {
		ArnWordPressDoubles::$sessions[10]['old'] = $this->guard->attachSessionInformation( array(), 10 );
		$this->snapshot                           = new AuthorizationSnapshot( 10, AccountState::Active, 3, array() );
		ArnWordPressDoubles::$sessions[10]['new'] = $this->guard->attachSessionInformation( array(), 10 );
		self::assertFalse( $this->guard->filterSendAuthCookies( true, 0, 0, 10, '', 'old' ) );
		self::assertArrayNotHasKey( 'old', ArnWordPressDoubles::$sessions[10] );
		self::assertArrayHasKey( 'new', ArnWordPressDoubles::$sessions[10] );
	}
	/** Conta removida mantém identificação através do token emitido. */
	public function testDeletedAccountCannotKeepItsMarkedSession(): void {
		ArnWordPressDoubles::$sessions[10]['old'] = $this->guard->attachSessionInformation( array(), 10 );
		unset( ArnWordPressDoubles::$users[10] );
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		self::assertSame( array(), ArnWordPressDoubles::$sessions[10] );
		self::assertSame( 1, $this->reads );
	}
	/** Recuperação activa mantém erros prévios e valida o estado novamente. */
	public function testActiveResetAndEarlierValidationErrorArePreserved(): void {
		self::assertTrue( $this->guard->filterPasswordReset( true, 10 ) );
		$errors = new WP_Error( 'earlier' );
		$this->guard->validatePasswordReset( $errors, ArnWordPressDoubles::$users[10] );
		self::assertSame( 'earlier', $errors->get_error_code() );
		$this->enabled = false;
		$this->guard->validatePasswordReset( $errors, ArnWordPressDoubles::$users[10] );
		self::assertSame( 'earlier', $errors->get_error_code() );
	}
	/** Marca removida continua identificada pela sessão. */
	public function testRemovedMarkerAndDisabledGateDenyWithoutLookup(): void {
		ArnWordPressDoubles::$sessions[10]['old'] = $this->guard->attachSessionInformation( array(), 10 );
		ArnWordPressDoubles::$users[10]           = new WP_User( 10, 'renamed', 'other@example.test' );
		ArnWordPressDoubles::$meta                = array();
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		self::assertSame( 1, $this->reads );
		ArnWordPressDoubles::$meta[10] = array( LocalFixtureUser::META_KEY => '1' );
		$this->enabled                 = false;
		self::assertInstanceOf( WP_Error::class, $this->guard->filterAuthentication( ArnWordPressDoubles::$users[10] ) );
		self::assertSame( 1, $this->reads );
	}
	/** Sessões antigas sem geração e fixtures legadas exigem novo login marcado. */
	public function testLegacyFixturesAndUnmarkedCookiesAreRejected(): void {
		ArnWordPressDoubles::$sessions[10]['old'] = array( 'expiration' => 123 );
		self::assertFalse( $this->guard->filterCurrentUser( 10 ) );
		ArnWordPressDoubles::$meta = array();
		self::assertInstanceOf( WP_Error::class, $this->guard->filterAuthentication( ArnWordPressDoubles::$users[10] ) );
	}
	/** Contas legítimas e recusas anteriores são preservadas. */
	public function testLegitimateAccountsAndEarlierFailuresAreUntouched(): void {
		$user  = ArnWordPressDoubles::$users[20];
		$error = new WP_Error( 'earlier' );
		self::assertSame( $user, $this->guard->filterAuthentication( $user ) );
		self::assertSame( 20, $this->guard->filterCurrentUser( 20 ) );
		self::assertSame( array( 'x' => 1 ), $this->guard->attachSessionInformation( array( 'x' => 1 ), 20 ) );
		self::assertTrue( $this->guard->filterSendAuthCookies( true, 0, 0, 20, '', 'old' ) );
		self::assertTrue( $this->guard->filterPasswordReset( true, 20 ) );
		self::assertTrue( $this->guard->filterApplicationPasswords( true, $user ) );
		self::assertFalse( $this->guard->filterApplicationPasswords( true, ArnWordPressDoubles::$users[10] ) );
		self::assertSame( $error, $this->guard->filterAuthentication( $error ) );
		self::assertNull( $this->guard->filterAuthentication( null ) );
		self::assertSame( $error, $this->guard->filterPasswordReset( $error, 10 ) );
		self::assertFalse( $this->guard->filterPasswordReset( false, 10 ) );
		self::assertFalse( $this->guard->filterCurrentUser( false ) );
		self::assertTrue( $this->guard->filterSendAuthCookies( true, 0, 0, 0, '', '' ) );
		self::assertFalse( $this->guard->filterSendAuthCookies( false, 0, 0, 0, '', '' ) );
		self::assertSame( 0, $this->reads );
	}
	/** Defaults recusam sem flags e nunca precisam do DAO para conta legítima. */
	public function testDefaultsAreClosedAndLazy(): void {
		$guard = new WordPressLocalAccountSessions();
		self::assertInstanceOf( WP_Error::class, $guard->filterAuthentication( ArnWordPressDoubles::$users[10] ) );
		self::assertSame( ArnWordPressDoubles::$users[20], $guard->filterAuthentication( ArnWordPressDoubles::$users[20] ) );
	}
	/** Hooks após core e guardas anteriores. */
	public function testHookSignatures(): void {
		$this->guard->register();
		foreach ( array(
			'authenticate'               => array( 'filterAuthentication', 1 ),
			'determine_current_user'     => array( 'filterCurrentUser', 1 ),
			'attach_session_information' => array( 'attachSessionInformation', 2 ),
			'send_auth_cookies'          => array( 'filterSendAuthCookies', 6 ),
			'allow_password_reset'       => array( 'filterPasswordReset', 2 ),
			'validate_password_reset'    => array( 'validatePasswordReset', 2 ),
			'wp_is_application_passwords_available_for_user' => array( 'filterApplicationPasswords', 2 ),
		) as $hook => $expected ) {
			self::assertSame( array( $this->guard, $expected[0] ), ArnWordPressDoubles::$filters[ $hook ]['callback'] );
			self::assertSame( PHP_INT_MAX, ArnWordPressDoubles::$filters[ $hook ]['priority'] );
			self::assertSame( $expected[1], ArnWordPressDoubles::$filters[ $hook ]['accepted_args'] );
		}
	}
}
