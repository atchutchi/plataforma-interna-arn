<?php
/**
 * Testes da fronteira de identidade institucional.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\Account;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Identity\Institutional\Principal;
use Arn\Intranet\Identity\Institutional\SessionPrincipalPolicy;
use Arn\Intranet\Identity\LocalIdentityLink;
use PHPUnit\Framework\TestCase;

/** Fronteira pura, sem provar persistência ou emissão de cookies. */
final class InstitutionalPrincipalTest extends TestCase {

	/** Metadados fictícios distintos dos marcadores locais. */
	private function session(): array {
		return array(
			'arn_institutional'  => array(
				'version'                => 1,
				'user_id'                => 12,
				'issuer'                 => 'https://accounts.google.com',
				'subject'                => 'invented-sub',
				'link_generation'        => 7,
				'authorization_revision' => 9,
			),
			'arn_oidc'           => true,
			'arn_oidc_issued_at' => 9000,
			'arn_oidc_last_seen' => 9900,
			'expiration'         => 12600,
		);
	}

	/**
	 * Simula leituras correntes já obtidas no servidor.
	 *
	 * @param array        $session Metadados fictícios.
	 * @param int          $generation Geração corrente.
	 * @param int          $revision Revisão corrente.
	 * @param AccountState $state Estado corrente.
	 * @param bool         $enabled Activação explícita.
	 */
	private function resolve( array $session, int $generation = 7, int $revision = 9, AccountState $state = AccountState::Active, bool $enabled = true ): ?Principal {
		return SessionPrincipalPolicy::resolve( new Account( 12, $state, true ), new LocalIdentityLink( 12, 'accounts.google.com', 'invented-sub', true, true, 7 ), $generation, $revision, $session, 10000, $enabled );
	}

	/** Uma sessão válida não transporta concessões ou dados pessoais. */
	public function testValidSessionYieldsCanonicalPrincipal(): void {
		$principal = $this->resolve( $this->session() );
		self::assertNotNull( $principal );
		self::assertSame( 12, $principal->user_id );
		self::assertSame( 'https://accounts.google.com', $principal->issuer );
		self::assertSame( 7, $principal->link_generation );
		self::assertSame( 9, $principal->authorization_revision );
	}

	/** Nenhuma coerção ou metadado incompleto pode restaurar acesso. */
	public function testInvalidMetadataIsRejected(): void {
		$valid = $this->session();
		foreach ( $valid['arn_institutional'] as $key => $value ) {
			foreach ( array( null, false, array(), '', is_int( $value ) ? (string) $value : 123, 'other' ) as $invalid ) {
				$session                              = $valid;
				$session['arn_institutional'][ $key ] = $invalid;
				self::assertNull( $this->resolve( $session ), $key );
			}
			$session = $valid;
			unset( $session['arn_institutional'][ $key ] );
			self::assertNull( $this->resolve( $session ), $key );
		}
		foreach ( array( null, false, '1', 1 ) as $metadata ) {
			$session                      = $valid;
			$session['arn_institutional'] = $metadata;
			self::assertNull( $this->resolve( $session ) );
		}
	}

	/** Sessões antigas não voltam após nova geração ou revisão. */
	public function testRevocationRecreationAndAuthorizationChangesRejectOldSession(): void {
		foreach ( array( array( 8, 9 ), array( 7, 10 ), array( 0, 9 ), array( 7, 0 ) ) as [ $generation, $revision ] ) {
			self::assertNull( $this->resolve( $this->session(), $generation, $revision ) );
		}
		self::assertNull( $this->resolve( $this->session(), state: AccountState::Suspended ) );
		self::assertNull( $this->resolve( $this->session(), state: AccountState::Pending ) );
		self::assertNull( $this->resolve( $this->session(), enabled: false ) );
	}

	/** Mantém os limites temporais e recusa a sessão local legada. */
	public function testLegacyAndExpiredSessionsAreRejected(): void {
		$session = $this->session();
		unset( $session['arn_institutional'] );
		$session['arn_authz_local'] = true;
		self::assertNull( $this->resolve( $session ) );
		foreach ( array(
			'expiration'         => 10000,
			'arn_oidc_issued_at' => 6400,
			'arn_oidc_last_seen' => 8200,
			'arn_oidc'           => false,
		) as $key => $value ) {
			$session         = $this->session();
			$session[ $key ] = $value;
			if ( 'arn_oidc_last_seen' === $key ) {
				$session['arn_oidc_issued_at'] = 8000;
			}
			self::assertNull( $this->resolve( $session ) );
		}
	}

	/** Titular, sessão nativa e vínculo são requisitos independentes. */
	public function testMissingAndMismatchedSourcesFailClosed(): void {
		foreach ( array( null, new Account( 13, AccountState::Active, true ), new Account( 12, AccountState::Active, false ) ) as $account ) {
			self::assertNull( SessionPrincipalPolicy::resolve( $account, new LocalIdentityLink( 12, 'accounts.google.com', 'invented-sub', true, true, 7 ), 7, 9, $this->session(), 10000, true ) );
		}
		foreach ( array( null, new LocalIdentityLink( 13, 'accounts.google.com', 'invented-sub', true, true, 7 ), new LocalIdentityLink( 12, 'accounts.google.com', 'invented-sub', false, false ) ) as $link ) {
			self::assertNull( SessionPrincipalPolicy::resolve( new Account( 12, AccountState::Active, true ), $link, 7, 9, $this->session(), 10000, true ) );
		}
	}
	/** O valor imutável também recusa construções inválidas directas. */
	public function testPrincipalRejectsInvalidConstruction(): void {
		foreach ( array(
			array( 0, 'https://accounts.google.com', 'subject', 1, 1 ),
			array( 1, 'accounts.google.com', 'subject', 1, 1 ),
			array( 1, 'https://accounts.google.com', '', 1, 1 ),
			array( 1, 'https://accounts.google.com', 'subject', 0, 1 ),
			array( 1, 'https://accounts.google.com', 'subject', 1, 0 ),
		) as $values ) {
			try {
				new Principal( ...$values );
				self::fail( 'Construcao invalida aceite.' );
			} catch ( \InvalidArgumentException $exception ) {
				self::assertSame( 'Principal institucional inválido.', $exception->getMessage() );
			}
		}
	}
}
