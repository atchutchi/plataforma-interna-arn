<?php
/** Gates efectivos das sessões fictícias.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use ArnWordPressDoubles;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_User;

require_once __DIR__ . '/WordPressLocalAccountSessionsTest.php';

// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Doubles do gate WordPress.
/**
 * Simula disponibilidade da função WordPress sem carregar o core.
 *
 * @param string $name Nome da função.
 */
function function_exists( string $name ): bool {
	return 'wp_get_environment_type' === $name || \function_exists( $name );
}
/** Ambiente efectivo simulado. */
function wp_get_environment_type(): string {
	return LocalAccountSessionDefaultsTest::$environment;
}
/** Cada conjunto tem constantes próprias num processo isolado. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState( false )]
final class LocalAccountSessionDefaultsTest extends TestCase {
	/** Ambiente do cenário.
	 *
	 * @var string
	 */
	public static string $environment = 'production';
	/**
	 * Verifica tipos estritos e revalidação do ambiente por chamada.
	 *
	 * @param string $environment Ambiente.
	 * @param mixed  $fictional Flag fictícia.
	 * @param mixed  $storage Flag persistência.
	 * @param bool   $expected Decisão esperada.
	 */
	#[DataProvider( 'gates' )]
	public function testEffectiveGate( string $environment, mixed $fictional, mixed $storage, bool $expected ): void {
		self::$environment = $environment;
		define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', $fictional );
		define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', $storage );
		ArnWordPressDoubles::reset();
		$user                           = new WP_User( 10, LocalFixtureUser::LOGIN, LocalFixtureUser::EMAIL );
		ArnWordPressDoubles::$users[10] = $user;
		ArnWordPressDoubles::$meta[10]  = array( LocalFixtureUser::META_KEY => '1' );
		$reads                          = 0;
		$guard                          = new WordPressLocalAccountSessions(
			static function () use ( &$reads ): AuthorizationSnapshot {
				++$reads;
				return new AuthorizationSnapshot( 10, AccountState::Active, 2, array() );
			}
		);
		$result                         = $guard->filterAuthentication( $user );
		self::assertSame( $expected, $result === $user );
		self::assertSame( $expected ? 1 : 0, $reads );
		self::$environment = 'production';
		self::assertInstanceOf( WP_Error::class, $guard->filterAuthentication( $user ) );
		self::assertSame( $expected ? 1 : 0, $reads );
	}
	/**
	 * Texto e números nunca equivalem ao booleano explícito.
	 *
	 * @return array
	 */
	public static function gates(): array {
		return array(
			array( 'local', true, true, true ),
			array( 'local', false, true, false ),
			array( 'local', true, false, false ),
			array( 'local', 'true', true, false ),
			array( 'local', true, 'true', false ),
			array( 'local', 1, true, false ),
			array( 'local', true, 1, false ),
			array( 'staging', true, true, false ),
			array( 'development', true, true, false ),
			array( 'production', true, true, false ),
		);
	}
}
