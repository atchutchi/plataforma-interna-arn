<?php
/** Pure news model and editorial policy tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Audience;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\NewsPolicy;
use Arn\Intranet\News\Version;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Exercises the immutable aggregate and existing authorization matrix. */
final class NewsModelTest extends TestCase {
	/** Build a valid private blob.
	 *
	 * @param string $id ID character.
	 * @param string $mime Verified MIME.
	 * @return BlobDescriptor
	 */
	private function blob( string $id, string $mime = 'image/png' ): BlobDescriptor {
		return new BlobDescriptor( str_repeat( $id, 32 ), str_repeat( 'a', 64 ), 1, $mime );
	}

	/** Build minimal content.
	 *
	 * @param string     $body Plain body.
	 * @param Asset|null $featured Featured image.
	 * @param array      $gallery Gallery assets.
	 * @param array      $attachments Attachment assets.
	 * @return Content
	 */
	private function content( string $body = 'Corpo', ?Asset $featured = null, array $gallery = array(), array $attachments = array() ): Content {
		return new Content( 'Título', $body, 'circular', $featured, $gallery, $attachments, Audience::all() );
	}

	/** Build one version owned by the author.
	 *
	 * @param int          $number Version number.
	 * @param Content|null $content Optional content.
	 * @param int          $actor Actor ID.
	 * @return Version
	 */
	private function version( int $number, ?Content $content = null, int $actor = 2 ): Version {
		return new Version( $number, $content ?? $this->content(), $actor, 10 );
	}

	/** Build an authorization snapshot.
	 *
	 * @param int          $user User ID.
	 * @param Role         $role Business role.
	 * @param Scope        $scope Granted scope.
	 * @param bool         $revoked Revocation status.
	 * @param AccountState $state Account state.
	 * @param int|null     $expires Grant expiry.
	 * @return AuthorizationSnapshot
	 */
	private function snapshot( int $user, Role $role, Scope $scope, bool $revoked = false, AccountState $state = AccountState::Active, ?int $expires = null ): AuthorizationSnapshot {
		return new AuthorizationSnapshot( $user, $state, 4, array( new StoredGrant( 1, new Grant( $user, $role, $scope, 0, $expires, $revoked ) ) ) );
	}

	/** Build server session metadata.
	 *
	 * @param int $user User ID.
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

	/** Assert domain rejection without relying on message wording.
	 *
	 * @param callable $operation Domain operation.
	 * @return void
	 */
	private function invalid( callable $operation ): void {
		try {
			$operation();
			self::fail( 'Valor inválido aceite.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertNotSame( '', $exception->getMessage() );
		}
	}

	/** Asset kind, MIME, text and boundaries are validated. */
	public function testAssetKindsMimeAndDescriptions(): void {
		self::assertSame( 'featured', ( new Asset( 'featured', $this->blob( 'a' ), 'Imagem' ) )->kind );
		self::assertSame( 'attachment', ( new Asset( 'attachment', $this->blob( 'b', 'application/pdf' ), 'Documento' ) )->kind );
		foreach ( array( 'featured', 'gallery' ) as $kind ) {
			$this->invalid( fn() => new Asset( $kind, $this->blob( 'c', 'application/pdf' ), 'Alternativa' ) );
			$this->invalid( fn() => new Asset( $kind, $this->blob( 'c' ), str_repeat( 'a', 301 ) ) );
		}
		$this->invalid( fn() => new Asset( 'attachment', $this->blob( 'c' ), str_repeat( 'a', 161 ) ) );
		foreach ( array( '', ' ', "x\0", "\xFF", '<b>imagem</b>' ) as $description ) {
			$this->invalid( fn() => new Asset( 'gallery', $this->blob( 'c' ), $description ) );
		}
		$this->invalid( fn() => new Asset( 'other', $this->blob( 'c' ), 'Designação' ) );
	}

	/** Content allows plain paragraphs and closed categories. */
	public function testContentTextCategoriesAndParagraphs(): void {
		foreach ( array( 'circular', 'nota-interna', 'despacho', 'comunicacao-sindical' ) as $category ) {
			self::assertSame( $category, ( new Content( 'T', '', $category, null, array(), array() ) )->category );
		}
		self::assertSame( "Um\n\nDois", ( new Content( 'T', "Um\r\n\r\nDois", 'circular', null, array(), array() ) )->body );
		self::assertSame( 200, mb_strlen( ( new Content( str_repeat( 'á', 200 ), '', 'circular', null, array(), array() ) )->title ) );
		self::assertSame( 20000, mb_strlen( ( new Content( 'T', str_repeat( 'á', 20000 ), 'circular', null, array(), array() ) )->body ) );
		foreach ( array( '', ' ', "x\n", "\xFF", '<b>T</b>', str_repeat( 'a', 201 ) ) as $title ) {
			$this->invalid( fn() => new Content( $title, '', 'circular', null, array(), array() ) );
		}
		foreach ( array( "x\rY", "x\0Y", "\xFF", '<script>x</script>', str_repeat( 'a', 20001 ) ) as $body ) {
			$this->invalid( fn() => new Content( 'T', $body, 'circular', null, array(), array() ) );
		}
		$this->invalid( fn() => new Content( 'T', '', 'outro', null, array(), array() ) );
	}

	/** Lists retain order and detach references from the caller. */
	public function testContentOrderedListsAndExternalReferences(): void {
		$first     = new Asset( 'gallery', $this->blob( 'a' ), 'Primeira' );
		$last      = new Asset( 'gallery', $this->blob( 'b' ), 'Última' );
		$external  = $first;
		$gallery   = array( &$external, $last );
		$content   = $this->content( '', null, $gallery );
		$external  = $last;
		$gallery[] = $first;
		self::assertSame( array( $first, $last ), $content->gallery );
		self::assertSame( array(), $content->attachments );
		$this->invalid( fn() => new Content( 'T', '', 'circular', null, array( $first, 'bad' ), array() ) );
		$this->invalid( fn() => new Content( 'T', '', 'circular', null, array( 1 => $first ), array() ) );
		$this->invalid( fn() => new Content( 'T', '', 'circular', $first, array(), array() ) );
		$this->invalid( fn() => new Content( 'T', '', 'circular', null, array_fill( 0, 9, $first ), array() ) );
		$attachment = new Asset( 'attachment', $this->blob( 'c', 'application/pdf' ), 'Ficheiro' );
		$this->invalid( fn() => new Content( 'T', '', 'circular', null, array(), array_fill( 0, 6, $attachment ) ) );
		$this->invalid( fn() => new Content( 'T', '', 'circular', null, array(), array( $first ) ) );
	}

	/** Revisions are immutable and require a current CAS value. */
	public function testArticleTransitionsAndCas(): void {
		$initial = $this->version( 1 );
		$draft   = Article::create( 7, 2, Scope::queues( array( 'editorial-ficticia' ) ), $initial );
		self::assertSame( 1, $draft->revision );
		self::assertFalse( $draft->pending() );
		self::assertSame( $initial, $draft->version( 1 ) );
		$next    = $this->version( 2, $this->content( 'Revisto' ) );
		$revised = $draft->revise( $next, 1 );
		self::assertSame( 2, $revised->revision );
		self::assertSame( $initial, $draft->version( 1 ) );
		self::assertSame( $next, $revised->version( 2 ) );
		self::assertNull( $revised->version( 3 ) );
		$this->invalid( fn() => $draft->revise( $next, 0 ) );
		$this->invalid( fn() => $revised->revise( $this->version( 3 ), 1 ) );
		$this->invalid( fn() => $revised->revise( $this->version( 2 ), 2 ) );
	}

	/** Submission needs complete content and closes the draft. */
	public function testSubmissionRequiresCompleteContentAndClosesDraft(): void {
		$scope    = Scope::queues( array( 'editorial-ficticia' ) );
		$featured = new Asset( 'featured', $this->blob( 'a' ), 'Alternativa' );
		$empty    = Article::create( 7, 2, $scope, $this->version( 1, $this->content( '', $featured ) ) );
		$this->invalid( fn() => $empty->submit( 1, 11 ) );
		$missing = Article::create( 7, 2, $scope, $this->version( 1 ) );
		$this->invalid( fn() => $missing->submit( 1, 11 ) );
		$draft   = Article::create( 7, 2, $scope, $this->version( 1, $this->content( 'Completo', $featured ) ) );
		$pending = $draft->submit( 1, 11 );
		self::assertTrue( $pending->pending() );
		self::assertSame( 2, $pending->revision );
		self::assertSame( 1, $pending->submitted_version );
		self::assertSame( 11, $pending->submitted_at );
		self::assertFalse( $draft->pending() );
		$this->invalid( fn() => $pending->submit( 2, 12 ) );
		$this->invalid( fn() => $pending->revise( $this->version( 2 ), 2 ) );
		$this->invalid( fn() => $draft->submit( 0, 11 ) );
		$space = Article::create( 8, 2, $scope, $this->version( 1, $this->content( "\u{00A0}", $featured ) ) );
		$this->invalid( fn() => $space->submit( 1, 11 ) );
	}

	/** Hydration rejects contradictory state and reused private IDs. */
	public function testHydrationRejectsContradictionsAndDuplicateBlobIds(): void {
		$scope  = Scope::queues( array( 'editorial-ficticia' ) );
		$first  = $this->version( 1 );
		$second = $this->version( 2 );
		foreach ( array( array(), array( $second ), array( $first, $first ), array( 1 => $first ), array( $second, $first ) ) as $versions ) {
			$this->invalid( fn() => new Article( 7, 2, $scope, 1, $versions, null, null ) );
		}
		foreach ( array( Scope::own(), Scope::queues( array( 'a', 'b' ) ) ) as $bad_scope ) {
			$this->invalid( fn() => new Article( 7, 2, $bad_scope, 1, array( $first ), null, null ) );
		}
		$this->invalid( fn() => new Article( 0, 2, $scope, 1, array( $first ), null, null ) );
		$this->invalid( fn() => new Article( 7, 2, $scope, 2, array( $first ), null, null ) );
		$this->invalid( fn() => new Article( 7, 2, $scope, 2, array( $first ), 1, null ) );
		$this->invalid( fn() => new Article( 7, 2, $scope, 2, array( $first ), 2, 11 ) );
		$this->invalid( fn() => new Article( 7, 2, $scope, 2, array( $first ), 1, 11 ) );
		$this->invalid( fn() => new Article( 7, 2, $scope, 1, array( $this->version( 1, null, 3 ) ), null, null ) );
		$shared     = new Asset( 'featured', $this->blob( 'a' ), 'Alternativa' );
		$with_asset = $this->version( 1, $this->content( 'T', $shared ) );
		$again      = $this->version( 2, $this->content( 'T', $shared ) );
		$this->invalid( fn() => Article::create( 7, 2, $scope, $with_asset )->revise( $again, 1 ) );
		$duplicate = $this->content( 'T', $shared, array( new Asset( 'gallery', $this->blob( 'a' ), 'Outra' ) ) );
		$this->invalid( fn() => Article::create( 7, 2, $scope, $this->version( 1, $duplicate ) ) );
	}

	/** Existing matrix gates all news actions and reads. */
	public function testPolicyRequiresCorrectRoleScopeAndCurrentSession(): void {
		$scope    = Scope::queues( array( 'editorial-ficticia' ) );
		$featured = new Asset( 'featured', $this->blob( 'a' ), 'Imagem' );
		$draft    = Article::create( 7, 2, $scope, $this->version( 1, $this->content( 'Corpo', $featured ) ) );
		$pending  = $draft->submit( 1, 11 );
		$author   = $this->snapshot( 2, Role::Employee, Scope::own() );
		$editor   = $this->snapshot( 3, Role::Editorial, $scope );
		self::assertTrue( NewsPolicy::canCreate( $author, $this->session( 2 ), 10 ) );
		self::assertTrue( NewsPolicy::canAuthorAction( Action::CreateNewsDraft, 2, $author, $this->session( 2 ), 10 ) );
		self::assertTrue( NewsPolicy::canAuthorAction( Action::SubmitNews, 2, $author, $this->session( 2 ), 10 ) );
		self::assertFalse( NewsPolicy::canAuthorAction( Action::CreateNewsDraft, 3, $author, $this->session( 2 ), 10 ) );
		self::assertFalse( NewsPolicy::canAuthorAction( Action::ReviewNews, 2, $author, $this->session( 2 ), 10 ) );
		self::assertTrue( NewsPolicy::canEdit( $draft, $author, $this->session( 2 ), 10 ) );
		self::assertTrue( NewsPolicy::canSubmit( $draft, $author, $this->session( 2 ), 10 ) );
		self::assertFalse( NewsPolicy::canEdit( $pending, $author, $this->session( 2 ), 10 ) );
		self::assertFalse( NewsPolicy::canSubmit( $pending, $author, $this->session( 2 ), 10 ) );
		self::assertTrue( NewsPolicy::canRead( $draft, 1, $author, $this->session( 2 ), 10 ) );
		self::assertTrue( NewsPolicy::canRead( $pending, 1, $author, $this->session( 2 ), 10 ) );
		self::assertFalse( NewsPolicy::canRead( $draft, 1, $editor, $this->session( 3 ), 10 ) );
		self::assertTrue( NewsPolicy::canRead( $pending, 1, $editor, $this->session( 3 ), 10 ) );
		self::assertTrue( NewsPolicy::canReviewScope( $scope, $editor, $this->session( 3 ), 10 ) );
		self::assertFalse( NewsPolicy::canReviewScope( Scope::queues( array( 'outra' ) ), $editor, $this->session( 3 ), 10 ) );
		self::assertFalse( NewsPolicy::canRead( $pending, 1, $this->snapshot( 4, Role::TechnicalAdministrator, Scope::institutional() ), $this->session( 4 ), 10 ) );
		self::assertFalse( NewsPolicy::canRead( $draft, 1, $this->snapshot( 5, Role::Employee, Scope::own() ), $this->session( 5 ), 10 ) );
		foreach ( array( $this->snapshot( 2, Role::Employee, Scope::own(), true ), $this->snapshot( 2, Role::Employee, Scope::own(), false, AccountState::Suspended ), $this->snapshot( 2, Role::Employee, Scope::own(), false, AccountState::Active, 10 ) ) as $blocked ) {
			self::assertFalse( NewsPolicy::canCreate( $blocked, $this->session( 2 ), 10 ) );
			self::assertFalse( NewsPolicy::canRead( $pending, 1, $blocked, $this->session( 2 ), 10 ) );
		}
		self::assertFalse( NewsPolicy::canRead( $pending, 1, $author, $this->session( 2, 3 ), 10 ) );
		self::assertFalse( NewsPolicy::canCreate( $author, $this->session( 2, 3 ), 10 ) );
	}
}
