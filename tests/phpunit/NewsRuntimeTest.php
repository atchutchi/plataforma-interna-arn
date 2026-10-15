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
use Arn\Intranet\News\LocalEditorialInstaller;
use Arn\Intranet\News\LocalNewsRoster;
use Arn\Intranet\News\LocalNewsroom;
use Arn\Intranet\News\MediaInput;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\Audience;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\Version;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
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

	public function testDraftDetachesExternalElementReferences(): void {
		$gallery_item    = new MediaInput( null, 'gallery-a', 'A' );
		$attachment_item = new MediaInput( null, 'attachment-a', 'A' );
		$gallery         = array( &$gallery_item );
		$attachments     = array( &$attachment_item );
		$draft           = new DraftInput( 'Título', '', 'nota-interna', null, $gallery, $attachments );
		$gallery_item    = new MediaInput( null, 'gallery-b', 'B' );
		$attachment_item = new MediaInput( null, 'attachment-b', 'B' );
		self::assertSame( 'gallery-a', $draft->gallery[0]->source );
		self::assertSame( 'attachment-a', $draft->attachments[0]->source );
		$gallery_item    = 'invalid';
		$attachment_item = 'invalid';
		self::assertInstanceOf( MediaInput::class, $draft->gallery[0] );
		self::assertInstanceOf( MediaInput::class, $draft->attachments[0] );
	}

	public function testInstallerLocationRejectsWorkingTreeAndPreservesMetadataRoot(): void {
		$project = 'C:/workspace/arn';
		self::assertFalse( LocalNewsInstaller::allowedLocation( $project . '/tests/private', $project ) );
		self::assertFalse( LocalNewsInstaller::allowedLocation( $project, $project ) );
		self::assertTrue( LocalNewsInstaller::allowedLocation( $project . '/.git/tools/local-private-files', $project ) );
		self::assertTrue( LocalNewsInstaller::allowedLocation( 'C:/workspace/arn-sibling/private', $project ) );
	}

	public function testInstallerRequiresExactGatesAndRejectsRevokedGrant(): void {
		self::assertTrue( LocalNewsInstaller::eligible( 'local', true, true, true, true, true, true, true ) );
		self::assertFalse( LocalNewsInstaller::eligible( 'local', true, true, true, true, false, true, true ) );
		self::assertFalse( LocalNewsInstaller::eligible( 'local', 1, true, true, true, true, true, true ) );
		self::assertFalse( LocalNewsInstaller::eligible( 'local', true, true, true, true, true, false, true ) );
		$stored   = new StoredGrant( 2, new Grant( 42, Role::Employee, Scope::own(), 0, null, true ) );
		$snapshot = new AuthorizationSnapshot( 42, AccountState::Active, 4, array( $stored ) );
		$this->expectException( RuntimeException::class );
		LocalNewsInstaller::existingGrantId( $snapshot, Role::Employee, Scope::own() );
	}

	public function testRosterClosesIfAnyExpectedFixtureIsMissingOrUnmarked(): void {
		$ids    = array(
			'autor.noticias.teste'         => 11,
			'editor.noticias.teste'        => 12,
			'editor.suplente.teste'        => 13,
			'colaborador.noticias.a.teste' => 14,
			'colaborador.noticias.b.teste' => 15,
		);
		$lookup = static fn( string $login ): ?array => isset( $ids[ $login ] ) ? array(
			'id'     => $ids[ $login ],
			'login'  => $login,
			'marked' => true,
		) : null;
		$roster = new LocalNewsRoster( $lookup );
		self::assertSame(
			array(
				11 => array( 'LAB-A' ),
				12 => array(),
				13 => array(),
				14 => array( 'LAB-A' ),
				15 => array( 'LAB-B' ),
			),
			$roster->participantUnits()
		);
		self::assertSame( array( 12, 13 ), $roster->reviewerIds() );
		self::assertSame( array( 'LAB-B' ), $roster->unitsFor( 15 ) );
		self::assertNull( $roster->unitsFor( 1 ) );
		$missing = new LocalNewsRoster( static fn( string $login ): ?array => 'editor.suplente.teste' === $login ? null : $lookup( $login ) );
		self::assertSame( array(), $missing->participantUnits() );
		self::assertSame( array(), $missing->reviewerIds() );
		$unmarked = new LocalNewsRoster(
			static fn( string $login ): ?array => 'colaborador.noticias.b.teste' === $login ? array(
				'id'     => 15,
				'login'  => $login,
				'marked' => false,
			) : $lookup( $login )
		);
		self::assertSame( array(), $unmarked->participantUnits() );
	}

	public function testEditorialInstallerRequiresExactFiveGatesAndExclusivePrefix(): void {
		self::assertTrue( LocalEditorialInstaller::eligible( 'local', true, true, true, true, true, true, true ) );
		self::assertFalse( LocalEditorialInstaller::eligible( 'local', true, true, true, true, 'true', true, true ) );
		self::assertFalse( LocalEditorialInstaller::eligible( 'development', true, true, true, true, true, true, true ) );
		self::assertFalse( LocalEditorialInstaller::eligible( 'local', true, true, true, true, true, false, true ) );
		self::assertTrue( LocalEditorialInstaller::validPrefix( 'arn_ed_012345abcd_' ) );
		self::assertFalse( LocalEditorialInstaller::validPrefix( 'arn_ed_012345abcd' ) );
		self::assertFalse( LocalEditorialInstaller::validPrefix( 'arn_news_' ) );
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

	public function testPublishedReaderCannotOpenManagementOrPrivateRevision(): void {
		$asset      = new Asset( 'featured', new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'image/png' ), 'Imagem' );
		$first      = new Version( 1, new Content( 'Publicado', 'Corpo', 'circular', $asset, array(), array(), Audience::units( array( 'LAB-A' ) ) ), 11, 10 );
		$news       = Article::create( 7, 11, Scope::queues( array( 'editorial-ficticia' ) ), $first )->submit( 1, 11 )->approve( 2, 1, 12, 12, 'Aprovado' );
		$next       = new Version( 2, new Content( 'Privado', 'Rascunho', 'circular', null, array(), array(), Audience::units( array( 'LAB-B' ) ) ), 11, 13 );
		$news       = $news->revise( $next, 3 );
		$repository = new class( $news ) {
			public function __construct( private Article $article ) {}
			public function all(): array {
				return array( $this->article ); }
			public function find( int $id ): ?Article {
				return $id === $this->article->id ? $this->article : null; }
		};
		$make       = static function ( int $id, array $units ) use ( $repository ): LocalNewsroom {
			$grant    = new Grant( $id, Role::Employee, Scope::own(), 0, null );
			$snapshot = new AuthorizationSnapshot( $id, AccountState::Active, 4, array( new StoredGrant( 1, $grant ) ) );
			$session  = array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => 4,
			);
			$actor    = new CurrentNewsActor(
				static fn() => new class( $snapshot ) {
				public function __construct( private AuthorizationSnapshot $snapshot ) {}
				public function find( int $id ): ?AuthorizationSnapshot {
				return $id === $this->snapshot->user_id ? $this->snapshot : null; }
				},
				static fn(): int => $id,
				static fn(): array => $session,
				static fn(): array => array(),
				static fn(): array => array( $id => $units )
			);
			return new LocalNewsroom( $actor, static fn(): array => array( $repository, null ), null, static fn(): bool => true );
		};
		$inside     = $make( 14, array( 'LAB-A' ) );
		self::assertNull( $inside->findVisible( 7 ) );
		self::assertSame( array(), $inside->visible() );
		self::assertSame( 1, $inside->findPublished( 7 )?->published_version );
		self::assertCount( 1, $inside->published() );
		$outside = $make( 15, array( 'LAB-B' ) );
		self::assertNull( $outside->findPublished( 7 ) );
		self::assertSame( array(), $outside->published() );
	}

	/** A published reader has content access but no editorial audit history. */
	public function testPublishedReaderCannotReadEditorialHistoryWithoutReviewQueue(): void {
		$asset      = new Asset( 'featured', new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'image/png' ), 'Imagem' );
		$version    = new Version( 1, new Content( 'Publicado', 'Corpo', 'circular', $asset, array(), array(), Audience::all() ), 11, 10 );
		$article    = Article::create( 7, 11, Scope::queues( array( 'editorial-ficticia' ) ), $version )->submit( 1, 11 )->approve( 2, 1, 12, 12, 'Aprovado' );
		$repository = new class( $article ) {
			public function __construct( private Article $article ) {}
			public function find( int $id ): ?Article {
				return 7 === $id ? $this->article : null; }
			public function history( int $id ): array {
				return array(
					(object) array(
						'revision'    => 2,
						'version'     => 1,
						'event'       => 'submitted',
						'actor_id'    => 11,
						'occurred_at' => 11,
						'reason'      => 'Envio',
					),
				); }
		};
		$room       = static function ( int $id, Role $role, Scope $scope ) use ( $repository ): LocalNewsroom {
			$snapshot = new AuthorizationSnapshot( $id, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( $id, $role, $scope, 0, null ) ) ) );
			$session  = array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => 4,
			);
			$actor    = new CurrentNewsActor(
				static fn() => new class( $snapshot ) {
					public function __construct( private AuthorizationSnapshot $snapshot ) {}
					public function find( int $requested ): ?AuthorizationSnapshot {
				return $requested === $this->snapshot->user_id ? $this->snapshot : null; }
				},
				static fn(): int => $id,
				static fn(): array => $session,
				static fn(): array => array(),
				static fn(): array => array( $id => array( 'LAB-A' ) )
			);
			return new LocalNewsroom( $actor, static fn(): array => array( $repository, null ), null, static fn(): bool => true );
		};
		$reader     = $room( 14, Role::Employee, Scope::own() );
		self::assertSame( 1, $reader->findPublished( 7 )?->published_version );
		self::assertNull( $reader->historyVisible( 7 ) );
		$author = $room( 11, Role::Employee, Scope::own() );
		self::assertCount( 1, $author->historyVisible( 7 ) );
		$editor = $room( 12, Role::Editorial, Scope::queues( array( 'editorial-ficticia' ) ) );
		self::assertCount( 1, $editor->historyVisible( 7 ) );
	}

	public function testDecisionNoticeProjectsMatchingReasonOnlyForAuthorAndKeepsReceipts(): void {
		$first          = new Version( 1, new Content( 'Publicado', 'Corpo', 'circular', new Asset( 'featured', new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'image/png' ), 'Imagem' ), array(), array(), Audience::units( array( 'LAB-A' ) ) ), 11, 10 );
		$second         = new Version( 2, new Content( 'Privado', 'Corpo privado', 'circular', new Asset( 'featured', new BlobDescriptor( str_repeat( 'c', 32 ), str_repeat( 'd', 64 ), 1, 'image/png' ), 'Outra imagem' ), array(), array(), Audience::units( array( 'LAB-B' ) ) ), 11, 13 );
		$article        = Article::create( 7, 11, Scope::queues( array( 'editorial-ficticia' ) ), $first )->submit( 1, 11 )->approve( 2, 1, 12, 12, 'Aprovação reservada' )->revise( $second, 3 )->submit( 4, 14 )->reject( 5, 2, 12, 15, 'Corrigir o texto' );
		$rows           = array(
			(object) array(
				'id'           => 1,
				'recipient_id' => 11,
				'article_id'   => 7,
				'revision'     => 3,
				'version'      => 1,
				'kind'         => 'approved',
				'created_at'   => 12,
				'read_at'      => null,
			),
			(object) array(
				'id'           => 2,
				'recipient_id' => 11,
				'article_id'   => 7,
				'revision'     => 6,
				'version'      => 2,
				'kind'         => 'rejected',
				'created_at'   => 15,
				'read_at'      => null,
			),
			(object) array(
				'id'           => 3,
				'recipient_id' => 14,
				'article_id'   => 7,
				'revision'     => 3,
				'version'      => 1,
				'kind'         => 'publication',
				'created_at'   => 12,
				'read_at'      => null,
			),
			(object) array(
				'id'           => 4,
				'recipient_id' => 14,
				'article_id'   => 7,
				'revision'     => 3,
				'version'      => 1,
				'kind'         => 'approved',
				'created_at'   => 12,
				'read_at'      => null,
			),
		);
		$repo           = new class( $article, $rows ) {
			/** Receipt IDs recorded by the test repository.
			 *
			 * @var array<int>
			 */
			public array $receipts = array();
			public function __construct( private Article $article, private array $rows ) {}
			public function notificationRows( int $recipient ): array {
				return array_values( array_filter( $this->rows, static fn( object $row ): bool => $row->recipient_id === $recipient ) ); }
			public function find( int $id ): ?Article {
				return 7 === $id ? $this->article : null; }
			public function markRead( int $id, int $at ): void {
				$this->receipts[] = $id; }
		};
		$room           = static function ( int $id ) use ( $repo ): LocalNewsroom {
			$snapshot = new AuthorizationSnapshot( $id, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( $id, Role::Employee, Scope::own(), 0, null ) ) ) );
			$session  = array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => 4,
			);
			$actor    = new CurrentNewsActor(
				static fn() => new class( $snapshot ) {
					public function __construct( private AuthorizationSnapshot $snapshot ) {}
					public function find( int $requested ): ?AuthorizationSnapshot {
						return $requested === $this->snapshot->user_id ? $this->snapshot : null; }
				},
				static fn(): int => $id,
				static fn(): array => $session,
				static fn(): array => array(),
				static fn(): array => array(
					11 => array( 'LAB-A' ),
					14 => array( 'LAB-A' ),
				)
			);
			return new LocalNewsroom( $actor, static fn(): array => array( $repo, null ), null, static fn(): bool => true );
		};
		$author_notices = $room( 11 )->notifications();
		self::assertSame( array( 'Aprovação reservada', 'Corrigir o texto' ), array_map( static fn( object $notice ): string => $notice->reason, $author_notices ) );
		$reader = $room( 14 );
		self::assertCount( 1, $reader->notifications() );
		self::assertSame( 'publication', $reader->notifications()[0]->kind );
		self::assertObjectNotHasProperty( 'reason', $reader->notifications()[0] );
		$reader->markRead( 3 );
		$room( 11 )->markRead( 2 );
		self::assertSame( array( 3, 2 ), $repo->receipts );
	}
}
