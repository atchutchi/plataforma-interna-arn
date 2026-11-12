<?php
/** Resolução institucional sem criação por email.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\{IdentityLinkRepository, LocalIdentityLink, MaintainedGoogleClient, OidcConfiguration};
use Arn\Intranet\Identity\Institutional\{CallbackLogin, PrincipalSource};
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting.FunctionComment
/** Vinculos desconhecidos ou inactivos nunca entram pelo email. */
final class InstitutionalLoginTest extends TestCase {
	#[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
	#[\PHPUnit\Framework\Attributes\PreserveGlobalState( false )]
	public function testUnavailableInfrastructureDoesNotCrashTheComposition(): void {
		define( 'ARN_INSTITUTIONAL_IDENTITY_ENABLED', true );
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Processo PHPUnit isolado sem carregar WordPress.
		$GLOBALS['wpdb'] = (object) array( 'prefix' => 'synthetic_' );
		self::assertNull( \Arn\Intranet\Identity\GoogleLoginController::institutionalSource() );
		self::assertTrue( \Arn\Intranet\Identity\GoogleLoginController::institutionalMode() );
	}

	public function testOnlyExistingStableIdentityCanResolve(): void {
		$values = array(
			'ARN_OIDC_ENABLED'          => '1',
			'ARN_OIDC_CLIENT_ID'        => 'synthetic',
			'ARN_OIDC_CLIENT_SECRET'    => 'synthetic',
			'ARN_OIDC_REDIRECT_URI'     => 'https://intranet.example.test/wp-login.php?action=arn_google_callback',
			'ARN_OIDC_WORKSPACE_DOMAIN' => 'example.test',
		);
		$config = OidcConfiguration::fromEnvironment( static fn( $name )=>$values[ $name ] ?? false );
		$link   = new LocalIdentityLink( 12, 'accounts.google.com', 'synthetic', true, true, 4 );
		$links  = $this->createMock( IdentityLinkRepository::class );
		$links->method( 'find' )->with( 'https://accounts.google.com', 'synthetic' )->willReturnCallback(
			static function () use ( &$link ) {
				return $link;
			}
		);
		$links->expects( self::never() )->method( 'create' );
		$links->expects( self::never() )->method( 'changeState' );
		$source = new PrincipalSource( $links, $this->createMock( AuthorizationRepository::class ), static fn()=>true, static fn()=>time() );
		$login  = new CallbackLogin( new MaintainedGoogleClient(), $links, $source );
		$claims = array(
			'iss'            => 'accounts.google.com',
			'sub'            => 'synthetic',
			'hd'             => 'example.test',
			'email_verified' => true,
			'email'          => 'changed@example.test',
		);
		self::assertSame( $link, $login->resolveValidated( $claims, $config ) );
		foreach ( array( null, new LocalIdentityLink( 12, 'accounts.google.com', 'synthetic', false, false, 5 ), new LocalIdentityLink( 12, 'accounts.google.com', 'synthetic', true, true ) ) as $candidate ) {
			$link = $candidate;
			try {
				$login->resolveValidated( $claims, $config );
				self::fail( 'Identidade sem prova aceite.' );
			} catch ( \RuntimeException $error ) {
				self::assertSame( 'Entrada institucional recusada.', $error->getMessage() ); }
		}
	}
}
