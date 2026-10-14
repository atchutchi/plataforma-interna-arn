<?php
/** Local newsroom input and installer contracts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use Arn\Intranet\News\DraftInput;
use Arn\Intranet\News\CurrentNewsActor;
use Arn\Intranet\News\LocalNewsInstaller;
use Arn\Intranet\News\LocalNewsroom;
use Arn\Intranet\News\MediaInput;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment

/** Pure boundary checks before the SQL integration run. */
final class NewsRuntimeTest extends TestCase {
	public function testInputsRejectAmbiguousMediaAndDetachLists(): void {
		$this->expectException( InvalidArgumentException::class );
		new MediaInput( 'a', 'b', 'Alternativa' );
	}

	public function testDraftCopiesStrictLists(): void {
		$gallery   = array( new MediaInput( null, 'server-file', 'Alternativa' ) );
		$draft     = new DraftInput( 'Título', '', 'nota-interna', null, $gallery, array() );
		$gallery[] = new MediaInput( null, 'other-file', 'Outra' );
		self::assertCount( 1, $draft->gallery );
		$this->expectException( InvalidArgumentException::class );
		new DraftInput( 'Título', '', 'nota-interna', null, array( 'invalid' ), array() );
	}

	public function testInstallerRequiresExactGatesAndRejectsRevokedGrant(): void {
		self::assertTrue( LocalNewsInstaller::eligible( 'local', true, true, true, true, true, true ) );
		self::assertFalse( LocalNewsInstaller::eligible( 'local', 1, true, true, true, true, true ) );
		self::assertFalse( LocalNewsInstaller::eligible( 'local', true, true, true, true, false, true ) );
		$stored   = new StoredGrant( 2, new Grant( 42, Role::Employee, Scope::own(), 0, null, true ) );
		$snapshot = new AuthorizationSnapshot( 42, AccountState::Active, 4, array( $stored ) );
		$this->expectException( RuntimeException::class );
		LocalNewsInstaller::existingGrantId( $snapshot, Role::Employee, Scope::own() );
	}

	public function testClosedRuntimeDoesNotReadProviders(): void {
		$reads = 0;
		$actor = new CurrentNewsActor(
			static function () use ( &$reads ) {
				++$reads;
				throw new RuntimeException( 'Leitura inesperada.' );
			},
			static fn(): int => 1,
			static fn(): array => array(),
			static fn(): array => array()
		);
		$room  = new LocalNewsroom(
			$actor,
			static function () use ( &$reads ) {
				++$reads;
				throw new RuntimeException( 'SQL inesperado.' );
			},
			static function () use ( &$reads ) {
				++$reads;
				throw new RuntimeException( 'Binário inesperado.' );
			},
			static fn(): bool => false
		);
		self::assertNull( $room->visible() );
		self::assertNull( $room->file( str_repeat( 'a', 32 ) ) );
		self::assertSame( 0, $reads );
	}
}
