<?php
/** Ensaios da fonte corrente, sem simular garantias transaccionais.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\{IdentityLinkRepository, LocalIdentityLink};
use Arn\Intranet\Identity\Institutional\PrincipalSource;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\{AuthorizationRepository, AuthorizationSnapshot};
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting.FunctionComment
/** Erros de transporte e mudanças durante a leitura não concedem acesso. */
final class InstitutionalSourceTest extends TestCase {
	public function testRevalidatesAndFailsClosedWithoutCaching(): void {
		$link     = new LocalIdentityLink( 12, 'accounts.google.com', 'synthetic', true, true, 3 );
		$snapshot = new AuthorizationSnapshot( 12, AccountState::Active, 2, array() );
		$links    = $this->createMock( IdentityLinkRepository::class );
		$auth     = $this->createMock( AuthorizationRepository::class );
		$links->method( 'findForUser' )->willReturnCallback(
			static function () use ( &$link ) {
				return $link;
			}
		);
		$auth->method( 'find' )->willReturnCallback(
			static function () use ( &$snapshot ) {
				return $snapshot;
			}
		);
		$session = array(
			'arn_oidc'           => true,
			'arn_oidc_issued_at' => 100,
			'arn_oidc_last_seen' => 100,
			'expiration'         => 1000,
			'arn_institutional'  => array(
				'version'                => 1,
				'user_id'                => 12,
				'issuer'                 => 'https://accounts.google.com',
				'subject'                => 'synthetic',
				'link_generation'        => 3,
				'authorization_revision' => 2,
			),
		);
		$enabled = true;
		$reader  = static function () use ( &$session ) {
			return $session;
		};
		$source  = new PrincipalSource(
			$links,
			$auth,
			static function () use ( &$enabled ): bool {
				return $enabled;
			},
			static fn(): int => 200,
			$reader
		);
		self::assertNotNull( $source->read( 12, 'synthetic-token' ) );
		$snapshot = new AuthorizationSnapshot( 12, AccountState::Suspended, 3, array() );
		self::assertNull( $source->read( 12, 'synthetic-token' ) );
		$snapshot = new AuthorizationSnapshot( 12, AccountState::Active, 4, array() );
		self::assertNull( $source->read( 12, 'synthetic-token' ) );
		$snapshot = new AuthorizationSnapshot( 12, AccountState::Active, 2, array() );
		$link     = new LocalIdentityLink( 12, 'accounts.google.com', 'synthetic', true, true, 4 );
		self::assertNull( $source->read( 12, 'synthetic-token' ) );
		$link    = new LocalIdentityLink( 12, 'accounts.google.com', 'synthetic', true, true, 3 );
		$session = null;
		self::assertNull( $source->read( 12, 'synthetic-token' ) );
		$enabled = false;
		self::assertNull( $source->state( 12 ) );
	}
	public function testSqlFailureIsNotAnAnonymousFallback(): void {
		$links = $this->createMock( IdentityLinkRepository::class );
		$auth  = $this->createMock( AuthorizationRepository::class );
		$links->method( 'findForUser' )->willThrowException( new \RuntimeException( 'private-sql' ) );
		$auth->expects( self::never() )->method( 'find' );
		$source = new PrincipalSource( $links, $auth, static fn(): bool=>true, static fn(): int=>200, static fn()=>array() );
		self::assertNull( $source->read( 12, 'synthetic-token' ) );
	}
}
