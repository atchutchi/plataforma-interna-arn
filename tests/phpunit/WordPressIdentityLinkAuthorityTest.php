<?php
/**
 * Actor WordPress actual e competência local são obrigatórios.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\WordPressIdentityLinkAuthority;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/doubles/IdentityAuthorityState.php';
require_once __DIR__ . '/doubles/IdentityAuthorityFunctions.php';

/**
 * Não concede capabilities nem assume competências institucionais.
 */
final class WordPressIdentityLinkAuthorityTest extends TestCase {

	/**
	 * Valores fictícios reiniciados antes de cada cenário.
	 */
	protected function setUp(): void {
		IdentityAuthorityState::$actor     = 7;
		IdentityAuthorityState::$allowed   = true;
		IdentityAuthorityState::$multisite = false;
		IdentityAuthorityState::$member    = true;
		IdentityAuthorityState::$users     = array( 7, 12 );
	}

	/**
	 * A competência provisória é consultada no actor autenticado.
	 */
	public function testCurrentAuthorizedActorCanManage(): void {
		self::assertTrue( ( new WordPressIdentityLinkAuthority() )->canManage( 7 ) );
	}

	/**
	 * Um chamador não pode passar o ID de outro administrador.
	 */
	public function testActorIdCannotImpersonateCurrentUser(): void {
		IdentityAuthorityState::$actor = 12;
		self::assertFalse( ( new WordPressIdentityLinkAuthority() )->canManage( 7 ) );
	}

	/**
	 * Sem competência local a operação é recusada.
	 */
	public function testActorWithoutCapabilityIsRejected(): void {
		IdentityAuthorityState::$allowed = false;
		self::assertFalse( ( new WordPressIdentityLinkAuthority() )->canManage( 7 ) );
	}

	/**
	 * IDs inválidos nunca correspondem a contas autorizadas.
	 */
	public function testInvalidAndMissingAccountsAreRejected(): void {
		$authority = new WordPressIdentityLinkAuthority();
		self::assertFalse( $authority->canManage( 0 ) );
		self::assertFalse( $authority->accountExists( -1 ) );
		self::assertFalse( $authority->accountExists( 99 ) );
		self::assertTrue( $authority->accountExists( 12 ) );
	}

	/**
	 * Uma conta da rede não equivale a conta deste site.
	 */
	public function testMultisiteNonMemberIsRejected(): void {
		IdentityAuthorityState::$multisite = true;
		IdentityAuthorityState::$member    = false;
		$authority                         = new WordPressIdentityLinkAuthority();
		self::assertFalse( $authority->accountExists( 12 ) );
		self::assertFalse( $authority->canManage( 7 ) );
	}
}
