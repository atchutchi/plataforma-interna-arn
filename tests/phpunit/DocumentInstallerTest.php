<?php
/** Local documentary installer gate checks.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Documents\Catalog\LocalDocumentInstaller;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use PHPUnit\Framework\TestCase;


/** Guard exact installer eligibility. */
final class DocumentInstallerTest extends TestCase {
	/** Every gate must have its exact value. */
	public function testRequiresEveryExactLocalGate(): void {
		self::assertTrue( LocalDocumentInstaller::eligible( 'local', true, true, true, true, true, true ) );
		self::assertFalse( LocalDocumentInstaller::eligible( 'development', true, true, true, true, true, true ) );
		self::assertFalse( LocalDocumentInstaller::eligible( 'local', 1, true, true, true, true, true ) );
		self::assertFalse( LocalDocumentInstaller::eligible( 'local', true, true, false, true, true, true ) );
		self::assertFalse( LocalDocumentInstaller::eligible( 'local', true, true, true, true, false, true ) );
	}

	/** A revoked historical RH grant is a decision, not a missing bootstrap step. */
	public function testRevokedGrantCannotBeRecreatedByInstaller(): void {
		$grant    = new StoredGrant( 7, new Grant( 42, Role::HumanResources, Scope::units( array( 'unidade-ficticia-a' ) ), 0, null, true ) );
		$snapshot = new AuthorizationSnapshot( 42, AccountState::Active, 4, array( $grant ) );
		$this->expectException( RuntimeException::class );
		LocalDocumentInstaller::existingGrantId( $snapshot );
	}
}
