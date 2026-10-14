<?php
/** Pure tests for the local document catalogue.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use Arn\Intranet\Documents\Catalog\Document;
use Arn\Intranet\Documents\Catalog\DocumentPolicy;
use Arn\Intranet\Documents\Catalog\Metadata;
use Arn\Intranet\Documents\Catalog\Revision;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Guards aggregate invariants and documentary access. */
final class DocumentCatalogTest extends TestCase {
	/** Build valid metadata.
	 *
	 * @param array<int> $recipients Explicit IDs.
	 * @return Metadata
	 */
	private function metadata( array $recipients = array( 2 ) ): Metadata {
		return new Metadata( 'Título', 'Resumo', 'circular', 'ORIG-1', 'RH', '2026-10-14', null, null, $recipients );
	}

	/** Build one private version.
	 *
	 * @param int        $number     Version number.
	 * @param array<int> $recipients Explicit IDs.
	 * @return Revision
	 */
	private function version( int $number, array $recipients = array( 2 ) ): Revision {
		return new Revision( $number, $this->metadata( $recipients ), new BlobDescriptor( str_repeat( (string) $number, 32 ), str_repeat( 'a', 64 ), 1, 'application/pdf' ), 3, 10 );
	}

	/** Build a current authorization snapshot.
	 *
	 * @param int          $user    Account ID.
	 * @param Role         $role    Business role.
	 * @param Scope        $scope   Explicit scope.
	 * @param bool         $revoked Grant status.
	 * @param AccountState $state   Account state.
	 * @return AuthorizationSnapshot
	 */
	private function snapshot( int $user, Role $role, Scope $scope, bool $revoked = false, AccountState $state = AccountState::Active ): AuthorizationSnapshot {
		return new AuthorizationSnapshot( $user, $state, 4, array( new StoredGrant( 1, new Grant( $user, $role, $scope, 0, null, $revoked ) ) ) );
	}

	/** Build local server session metadata.
	 *
	 * @param int $user     Account ID.
	 * @param int $revision Session generation.
	 * @return array
	 */
	private function session( int $user, int $revision = 4 ): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $user,
			'arn_authz_revision' => $revision,
		);
	}

	/** Categories, dates and audience are strictly validated. */
	public function testMetadataRejectsMalformedValues(): void {
		foreach ( array( 'circular', 'nota-interna', 'despacho', 'regulamento', 'comunicacao-sindical' ) as $category ) {
			self::assertSame( $category, ( new Metadata( 'T', '', $category, 'R', 'O', '2024-02-29', null, null, array( 1 ) ) )->category );
		}
		$valid = array( 'T', '', 'circular', 'R', 'O', '2026-10-14', null, null, array( 2 ) );
		foreach ( array( array( 0, '' ), array( 0, "\u{00A0}" ), array( 0, '<b>T</b>' ), array( 0, "T\n" ), array( 0, str_repeat( 'a', 201 ) ), array( 1, str_repeat( 'a', 4001 ) ), array( 2, 'desconhecido' ), array( 3, '' ), array( 4, '' ), array( 5, '2026-02-30' ), array( 6, '2024-02-30' ), array( 7, '2026-13-01' ), array( 8, array() ), array( 8, array( 2, 2 ) ), array( 8, array( '2' ) ), array( 8, array( 0 ) ), array( 8, range( 1, 101 ) ) ) as $case ) {
			$bad             = $valid;
			$bad[ $case[0] ] = $case[1];
			try {
				new Metadata( ...$bad );
				self::fail( 'Metadados inválidos aceites.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/** A hydrated aggregate must reject holes, swaps and impossible publication. */
	public function testHydrationAndTransitions(): void {
		$one = $this->version( 1 );
		$two = $this->version( 2 );
		foreach ( array( array( $one, $one ), array( $two ), array( $two, $one ) ) as $versions ) {
			$this->assertInvalid( static fn() => new Document( 1, Scope::units( array( 'LAB-A' ) ), 2, $versions, null, false ) );
		}
		$this->assertInvalid( static fn() => new Document( 1, Scope::units( array( 'LAB-A' ) ), 1, array( $one ), 2, false ) );
		$this->assertInvalid( static fn() => new Document( 1, Scope::units( array( 'LAB-A' ) ), 1, array( $one, $two ), null, false ) );
		$this->assertInvalid( static fn() => new Document( 1, Scope::units( array( 'LAB-A' ) ), 2, array( $one ), null, false ) );
		$draft = Document::create( 1, Scope::units( array( 'LAB-A' ) ), $one );
		self::assertSame( 1, $draft->revision );
		self::assertNull( $draft->published_version );
		$published = $draft->publish( 1, 1 );
		$revised   = $published->revise( $two, 2 );
		self::assertSame( 1, $revised->published_version );
		self::assertSame( 1, $published->published_version );
		self::assertSame( $one, $revised->version( 1 ) );
		self::assertNull( $revised->version( 3 ) );
		$this->assertInvalid( static fn() => $revised->publish( 1, 3 ) );
		$this->assertInvalid( static fn() => $revised->revise( $two, 3 ) );
		$this->assertInvalid( static fn() => $revised->publish( 2, 2 ) );
		$archived = $revised->publish( 2, 3 )->archive( 4 );
		self::assertTrue( $archived->archived );
		$this->assertInvalid( static fn() => $archived->revise( new Revision( 3, $two->metadata, $two->blob, 3, 11 ), 5 ) );
		$this->assertInvalid( static fn() => $archived->publish( 2, 5 ) );
		$this->assertInvalid( static fn() => $archived->archive( 5 ) );
	}

	/** Each version requires a distinct private ID even for identical bytes. */
	public function testVersionsCannotSharePrivateBlobId(): void {
		$one      = $this->version( 1 );
		$same_id  = new Revision( 2, $one->metadata, $one->blob, 3, 11 );
		$new_blob = new BlobDescriptor( str_repeat( 'b', 32 ), $one->blob->sha256, $one->blob->size, $one->blob->mime );
		$distinct = new Revision( 2, $one->metadata, $new_blob, 3, 11 );
		$scope    = Scope::units( array( 'LAB-A' ) );
		$this->assertInvalid( static fn() => new Document( 1, $scope, 2, array( $one, $same_id ), null, false ) );
		$draft = Document::create( 1, $scope, $one );
		$this->assertInvalid( static fn() => $draft->revise( $same_id, 1 ) );
		self::assertSame( $distinct, $draft->revise( $distinct, 1 )->version( 2 ) );
		self::assertSame( $one->blob->sha256, $distinct->blob->sha256 );
	}

	/** Session generation, revoked grants, strict recipients and manager scope. */
	public function testDocumentPolicy(): void {
		$doc      = Document::create( 1, Scope::units( array( 'LAB-A' ) ), $this->version( 1, array( 2 ) ) )->publish( 1, 1 );
		$employee = $this->snapshot( 2, Role::Employee, Scope::own() );
		$manager  = $this->snapshot( 3, Role::HumanResources, Scope::units( array( 'LAB-A' ) ) );
		self::assertTrue( DocumentPolicy::canRead( $doc, 1, $employee, $this->session( 2 ), 10 ) );
		self::assertFalse( DocumentPolicy::canManage( $doc, $employee, $this->session( 2 ), 10 ) );
		self::assertTrue( DocumentPolicy::canManage( $doc, $manager, $this->session( 3 ), 10 ) );
		self::assertTrue( DocumentPolicy::canManageScope( Scope::units( array( 'LAB-A' ) ), $manager, $this->session( 3 ), 10 ) );
		self::assertFalse( DocumentPolicy::canManageScope( Scope::own(), $manager, $this->session( 3 ), 10 ) );
		self::assertFalse( DocumentPolicy::canRead( $doc, 1, $employee, $this->session( 2, 3 ), 10 ) );
		self::assertFalse( DocumentPolicy::canRead( $doc, 1, $this->snapshot( 2, Role::Employee, Scope::own(), true ), $this->session( 2 ), 10 ) );
		self::assertFalse( DocumentPolicy::canRead( $doc, 1, $this->snapshot( 2, Role::Employee, Scope::own(), false, AccountState::Suspended ), $this->session( 2 ), 10 ) );
		self::assertFalse( DocumentPolicy::canRead( $doc, 1, $this->snapshot( 4, Role::Employee, Scope::own() ), $this->session( 4 ), 10 ) );
		self::assertFalse( DocumentPolicy::canManage( $doc, $this->snapshot( 3, Role::HumanResources, Scope::units( array( 'LAB-B' ) ) ), $this->session( 3 ), 10 ) );
		self::assertFalse( DocumentPolicy::canManage( $doc, $this->snapshot( 3, Role::TechnicalAdministrator, Scope::institutional() ), $this->session( 3 ), 10 ) );
		$revised = $doc->revise( $this->version( 2, array( 4 ) ), 2 );
		self::assertTrue( DocumentPolicy::canRead( $revised, 1, $employee, $this->session( 2 ), 10 ) );
		self::assertFalse( DocumentPolicy::canRead( $revised, 2, $employee, $this->session( 2 ), 10 ) );
		self::assertTrue( DocumentPolicy::canRead( $revised, 2, $manager, $this->session( 3 ), 10 ) );
		self::assertFalse( DocumentPolicy::canRead( $revised->archive( 3 ), 1, $employee, $this->session( 2 ), 10 ) );
	}

	/** Assert a domain operation rejects inconsistent values.
	 *
	 * @param callable $operation Domain operation.
	 * @return void
	 */
	private function assertInvalid( callable $operation ): void {
		try {
			$operation();
			self::fail( 'Operação inválida aceite.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertNotSame( '', $exception->getMessage() );
		}
	}
}
