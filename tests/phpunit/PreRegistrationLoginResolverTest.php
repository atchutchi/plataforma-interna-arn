<?php
/**
 * Resolução após prova OIDC, sem usar email para reassociar identidades.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\{LocalIdentityLink, OidcConfiguration};
use Arn\Intranet\Identity\PreRegistration\{PreRegistration, PreRegistrationLoginResolver};
use PHPUnit\Framework\TestCase;
use RuntimeException;

// phpcs:disable Squiz.Commenting -- Contrato de decisão puro, sem WordPress ou identidade real.
final class PreRegistrationLoginResolverTest extends TestCase {
	private function config(): OidcConfiguration {
		$values = array(
			'ARN_OIDC_ENABLED'          => '1',
			'ARN_OIDC_CLIENT_ID'        => 'fixture',
			'ARN_OIDC_CLIENT_SECRET'    => 'fixture-secret',
			'ARN_OIDC_REDIRECT_URI'     => 'https://fixture.example.invalid/callback',
			'ARN_OIDC_WORKSPACE_DOMAIN' => 'example.invalid',
		);
		return OidcConfiguration::fromEnvironment( static fn( string $key )=>$values[ $key ] ?? false );
	}
	private function claims(): array {
		return array(
			'iss'            => 'https://accounts.google.com',
			'sub'            => 'fixture-subject',
			'email'          => 'fixture@example.invalid',
			'hd'             => 'example.invalid',
			'email_verified' => true,
		);}
	private function record( string $state = 'Linked' ): PreRegistration {
		return PreRegistration::fromRow(
			array(
				'id'               => str_repeat( 'a', 32 ),
				'user_id'          => 10,
				'display_name'     => 'Pessoa Fictícia',
				'primary_email'    => 'fixture@example.invalid',
				'state'            => $state,
				'revision'         => 2,
				'authorized_by'    => 1,
				'source_sha256'    => str_repeat( 'b', 64 ),
				'inventory_sha256' => str_repeat( 'c', 64 ),
				'observed_at'      => 1894701600,
				'expires_at'       => 1894788000,
				'disabled_from'    => 'Disabled' === $state ? 'Linked' : null,
			)
		);}
	public function testExistingPairWinsOverPendingEmailAndNeverCallsClaim(): void {
		$link      = new LocalIdentityLink( 20, 'https://accounts.google.com', 'fixture-subject', true, true );
		$conflicts = 0;
		$resolver  = new PreRegistrationLoginResolver(
			static fn()=>$link,
			static fn()=>null,
			static function (): never {
				throw new RuntimeException( 'claim must not run' );
			},
			static fn(): bool=>false,
			static function () use ( &$conflicts ): void {
				++$conflicts;
			}
		);
		self::assertSame( $link, $resolver->resolve( $this->claims(), $this->config(), 1894705200 ) );
		self::assertSame( 1, $conflicts );
	}
	public function testRevokedPairNeverFallsThroughToEmailClaim(): void {
		$resolver = new PreRegistrationLoginResolver(
			static fn()=>new LocalIdentityLink( 20, 'https://accounts.google.com', 'fixture-subject', false, false ),
			static fn()=>null,
			static function (): never {
				self::fail( 'reassociação proibida' );
			},
			static fn(): bool=>true
		);
		$this->expectException( RuntimeException::class );
		$resolver->resolve( $this->claims(), $this->config(), 1894705200 );
	}
	public function testRegisteredLinkedPairNeedsCurrentEnabledState(): void {
		foreach ( array( 'Linked', 'Disabled', 'AwaitingGoogle' )as$state ) {
			$resolver = new PreRegistrationLoginResolver(
				static fn()=>new LocalIdentityLink( 10, 'https://accounts.google.com', 'fixture-subject', true, true ),
				fn()=>$this->record( $state ),
				static function (): never {
					self::fail( 'não deve criar vínculo' );
				},
				static fn(): bool=>true
			);
			if ( 'Linked' === $state ) {
				self::assertSame( 10, $resolver->resolve( $this->claims(), $this->config(), 1894705200 )->user_id );
			} else {
				try {
					$resolver->resolve( $this->claims(), $this->config(), 1894705200 );
					self::fail( 'estado devia recusar' );
				} catch ( RuntimeException ) {
					self::assertTrue( true );}
			}
		}
	}
	public function testUnknownPairClaimsOnlyWithStrictGate(): void {
		$calls    = 0;
		$link     = new LocalIdentityLink( 10, 'https://accounts.google.com', 'fixture-subject', true, true );
		$resolver = new PreRegistrationLoginResolver(
			static fn()=>null,
			fn()=>$this->record(),
			static function () use ( &$calls, $link ) {
				++$calls;
				return $link;
			},
			static fn(): bool=>true
		);
		self::assertSame( $link, $resolver->resolve( $this->claims(), $this->config(), 1894705200 ) );
		self::assertSame( 1, $calls );
		$resolver = new PreRegistrationLoginResolver(
			static fn()=>null,
			static fn()=>null,
			static function (): never {
				self::fail( 'gate fechado' );
			},
			static fn(): bool=>false
		);
		$this->expectException( RuntimeException::class );
		$resolver->resolve( $this->claims(), $this->config(), 1894705200 );
	}
	public function testUnverifiedClaimsNeverReachStorage(): void {
		$resolver                 = new PreRegistrationLoginResolver(
			static function (): never {
				self::fail( 'claims inválidas' );
			},
			static fn()=>null,
			static fn()=>null,
			static fn(): bool=>true
		);
		$claims                   = $this->claims();
		$claims['email_verified'] = 'true';
		$this->expectException( RuntimeException::class );
		$resolver->resolve( $claims, $this->config(), 1894705200 );
	}
}
