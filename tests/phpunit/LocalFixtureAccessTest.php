<?php
/**
 * Comportamento dos callbacks perante contas guardadas e sessões anteriores.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Access\LocalFixtureAccess;
use Arn\Intranet\Access\LocalFixtureUser;
use ArnWordPressDoubles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_User;

/**
 * Exercita a revogação da fixture, preservando utilizadores e erros reais.
 */
final class LocalFixtureAccessTest extends TestCase {

	/**
	 * Prepara duas contas com sessões já emitidas.
	 */
	protected function setUp(): void {
		ArnWordPressDoubles::reset();
		ArnWordPressDoubles::$users    = array(
			10 => new WP_User( 10, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL ),
			20 => new WP_User( 20, 'utilizador.real', 'pessoa@arn.gw' ),
		);
		ArnWordPressDoubles::$sessions = array(
			10 => array( 'sessao-fixture-1', 'sessao-fixture-2' ),
			20 => array( 'sessao-real' ),
		);
	}

	/**
	 * Uma conta legada existente deixa de entrar sem a política local.
	 *
	 * @param string $environment Ambiente do novo pedido.
	 * @param bool   $flag        Flag explícita.
	 */
	#[DataProvider( 'disabledEnvironments' )]
	public function testExistingFixtureCannotAuthenticateOutsideTheAllowedEnvironment( string $environment, bool $flag ): void {
		$guard  = new LocalFixtureAccess( $environment, $flag );
		$result = $guard->filterAuthentication( ArnWordPressDoubles::$users[10] );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'arn_fictional_access_disabled', $result->get_error_code() );
	}

	/**
	 * Um cookie antigo deixa de identificar a fixture e perde todos os tokens.
	 *
	 * @param string $environment Ambiente do novo pedido.
	 * @param bool   $flag        Flag explícita.
	 */
	#[DataProvider( 'disabledEnvironments' )]
	public function testExistingSessionsAreRejectedAndRevokedWithoutDeletingTheAccount( string $environment, bool $flag ): void {
		$guard = new LocalFixtureAccess( $environment, $flag );

		self::assertFalse( $guard->filterCurrentUser( 10 ) );
		self::assertSame( array(), ArnWordPressDoubles::$sessions[10] );
		self::assertSame( array( 'sessao-real' ), ArnWordPressDoubles::$sessions[20] );
		self::assertSame( 1, ArnWordPressDoubles::$cookies_cleared );
		self::assertSame( LocalFixtureUser::LOGIN, ArnWordPressDoubles::$users[10]->user_login );
	}

	/**
	 * Ambientes recusados mesmo que a base de dados contenha a fixture.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function disabledEnvironments(): array {
		return array(
			'flag retirada'       => array( 'local', false ),
			'desenvolvimento'     => array( 'development', true ),
			'base em homologacao' => array( 'staging', true ),
			'base em producao'    => array( 'production', true ),
			'producao sem flag'   => array( 'production', false ),
		);
	}

	/**
	 * O uso local autorizado continua operacional.
	 */
	public function testAuthorizedLocalFixtureKeepsAuthenticationAndSessions(): void {
		$guard = new LocalFixtureAccess( 'local', true );
		$user  = ArnWordPressDoubles::$users[10];

		self::assertSame( $user, $guard->filterAuthentication( $user ) );
		self::assertSame( 10, $guard->filterCurrentUser( 10 ) );
		self::assertCount( 2, ArnWordPressDoubles::$sessions[10] );
		self::assertSame( 0, ArnWordPressDoubles::$cookies_cleared );
	}

	/**
	 * Metadados mantêm a restrição mesmo após mudar o login ou o email local.
	 */
	public function testMarkedFixtureCannotEscapeByChangingItsProfile(): void {
		ArnWordPressDoubles::$users[10] = new WP_User( 10, 'outro.login', 'outro@example.test' );
		ArnWordPressDoubles::$meta[10]  = array( LocalFixtureUser::META_KEY => '1' );
		$guard                          = new LocalFixtureAccess( 'production', true );

		self::assertInstanceOf( WP_Error::class, $guard->filterAuthentication( ArnWordPressDoubles::$users[10] ) );
		self::assertFalse( $guard->filterCurrentUser( 10 ) );
	}

	/**
	 * O mesmo login não transforma uma conta real numa fixture.
	 */
	public function testMatchingOnlyOneIdentifierDoesNotRestrictARealAccount(): void {
		$guard = new LocalFixtureAccess( 'production', false );
		$users = array(
			new WP_User( 20, LocalFixtureUser::LOGIN, 'pessoa@arn.gw' ),
			new WP_User( 20, 'outra.conta', LocalFixtureUser::EMAIL ),
		);

		foreach ( $users as $user ) {
			ArnWordPressDoubles::$users[20] = $user;
			self::assertSame( $user, $guard->filterAuthentication( $user ) );
			self::assertSame( 20, $guard->filterCurrentUser( 20 ) );
			self::assertTrue( $guard->filterPasswordReset( true, 20 ) );
			self::assertTrue( $guard->filterApplicationPasswords( true, $user ) );
		}

		self::assertSame( array( 'sessao-real' ), ArnWordPressDoubles::$sessions[20] );
		self::assertSame( 0, ArnWordPressDoubles::$cookies_cleared );
	}

	/**
	 * Nem um reset nem credenciais de API reativam a fixture desautorizada.
	 */
	public function testDisabledFixtureCannotUseAlternativeCredentials(): void {
		$guard = new LocalFixtureAccess( 'local', false );

		self::assertFalse( $guard->filterPasswordReset( true, 10 ) );
		self::assertFalse( $guard->filterApplicationPasswords( true, ArnWordPressDoubles::$users[10] ) );
	}

	/**
	 * Os resultados anteriores não são promovidos nem substituídos.
	 */
	public function testAnonymousRequestsAndEarlierFailuresArePreserved(): void {
		$guard = new LocalFixtureAccess( 'local', true );
		$error = new WP_Error( 'falha-anterior' );

		self::assertSame( $error, $guard->filterAuthentication( $error ) );
		self::assertNull( $guard->filterAuthentication( null ) );
		self::assertFalse( $guard->filterCurrentUser( false ) );
		self::assertSame( $error, $guard->filterPasswordReset( $error, 10 ) );
		self::assertFalse( $guard->filterPasswordReset( false, 10 ) );
		self::assertFalse( $guard->filterApplicationPasswords( false, ArnWordPressDoubles::$users[10] ) );
	}

	/**
	 * Os filtros ficam depois da validação nativa de passwords e cookies.
	 */
	public function testCallbacksAreRegisteredWithTheRequiredArguments(): void {
		$guard = new LocalFixtureAccess( 'production', false );
		$guard->register();
		$expected = array(
			'authenticate'           => array( 'filterAuthentication', 1 ),
			'determine_current_user' => array( 'filterCurrentUser', 1 ),
			'allow_password_reset'   => array( 'filterPasswordReset', 2 ),
			'wp_is_application_passwords_available_for_user' => array( 'filterApplicationPasswords', 2 ),
		);

		foreach ( $expected as $hook => $details ) {
			$registered = ArnWordPressDoubles::$filters[ $hook ];
			self::assertSame( array( $guard, $details[0] ), $registered['callback'] );
			self::assertGreaterThan( 99, $registered['priority'] );
			self::assertSame( $details[1], $registered['accepted_args'] );
		}
	}
}
