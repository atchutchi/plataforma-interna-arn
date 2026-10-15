<?php
/** Publication projections never expose private editorial versions.
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
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Audience;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\CurrentNewsActor;
use Arn\Intranet\News\LocalNewsroom;
use Arn\Intranet\News\NewsPublishedController;
use Arn\Intranet\News\NewsPublishedPortal;
use Arn\Intranet\News\Version;
use PHPUnit\Framework\TestCase;

// phpcs:disable Squiz.Commenting.FunctionComment
/** Regression boundaries for publication, audience, filters and routing. */
final class NewsPublishedPortalTest extends TestCase {
	private function version( int $number, string $title, Audience $audience, int $at = 1700000000 ): Version {
		$asset = new Asset( 'featured', new BlobDescriptor( str_repeat( (string) $number, 32 ), str_repeat( 'a', 64 ), 10, 'image/png' ), 'Imagem aprovada' );
		return new Version( $number, new Content( $title, 'Corpo ' . $title, 'circular', $asset, array(), array(), $audience ), 11, $at );
	}

	private function article( int $id = 7, int $at = 1700000010 ): Article {
		return Article::create( $id, 11, Scope::queues( array( 'editorial-ficticia' ) ), $this->version( 1, 'Texto aprovado', Audience::units( array( 'LAB-A' ) ), $at - 3 ) )
			->submit( 1, $at - 2 )->approve( 2, 1, 12, $at, 'Aprovado' );
	}

	private function room( array $articles, int $id = 14, array $units = array( 'LAB-A' ), Role $role = Role::Employee, ?Scope $scope = null ): LocalNewsroom {
		$snapshot   = new AuthorizationSnapshot( $id, AccountState::Active, 4, array( new StoredGrant( 1, new Grant( $id, $role, $scope ?? Scope::own(), 0, null ) ) ) );
		$current    = new CurrentNewsActor(
			static fn() => new class( $snapshot ) {
				public function __construct( private AuthorizationSnapshot $snapshot ) {}
				public function find( int $id ): ?AuthorizationSnapshot {
					return $id === $this->snapshot->user_id ? $this->snapshot : null; }
			},
			static fn(): int => $id,
			static fn(): array => array(
				'arn_authz_local'    => true,
				'arn_authz_user_id'  => $id,
				'arn_authz_revision' => 4,
			),
			static fn(): array => array(),
			static fn(): array => array( $id => $units )
		);
		$repository = new class( $articles ) {
			public function __construct( private array $articles ) {}
			public function all(): array {
				return $this->articles; }
			public function find( int $id ): ?Article {
				foreach ( $this->articles as $article ) {
					if ( $id === $article->id ) {
						return $article; }
				}
				return null; }
		};
		return new LocalNewsroom( $current, static fn(): array => array( $repository, null ), null, static fn(): bool => true );
	}

	private function model( array $input, LocalNewsroom $room ): array {
		return NewsPublishedPortal::model( $input, $room, static fn( string $id ): string => '/private/' . $id, static fn(): string => 'Autor fictício', static fn( int $id ): string => '/noticias/?arn_news=' . $id );
	}

	public function testOnlyApprovedVersionReachesDetailDuringPendingRevision(): void {
		$article = $this->article()->revise( $this->version( 2, 'Segredo posterior', Audience::people( array( 15 ) ), 1700000020 ), 3 )->submit( 4, 1700000030 );
		$model   = NewsPublishedPortal::project( $article, static fn( string $id ): string => '/private/' . $id, static fn(): string => 'Autor fictício', static fn( int $id ): string => '/noticias/?arn_news=' . $id );
		self::assertSame( 'Texto aprovado', $model['title'] );
		self::assertSame( 'Corpo Texto aprovado', $model['body'] );
		self::assertSame( 'Autor fictício', $model['author'] );
		self::assertSame( 1700000010, $model['published_at'] );
		self::assertSame( 'Unidades seleccionadas', $model['audience'] );
		self::assertSame( '/private/' . str_repeat( '1', 32 ), $model['featured']['url'] );
		self::assertSame( array( 'id', 'title', 'body', 'category', 'author', 'published_at', 'audience', 'featured', 'gallery', 'attachments', 'detail_url' ), array_keys( $model ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Pure test without WordPress.
		self::assertStringNotContainsString( 'Segredo posterior', json_encode( $model, JSON_THROW_ON_ERROR ) );
		self::assertNull( NewsPublishedPortal::project( $article->archive( 5, 1, 12, 1700000040, 'Retirar' ), static fn(): string => '', static fn(): string => '', static fn(): string => '' ) );
	}

	public function testAudienceAndApprovedProjectionPrecedeSearchCountsAndPagination(): void {
		$draft   = Article::create( 8, 11, Scope::queues( array( 'editorial-ficticia' ) ), $this->version( 1, 'Rascunho reservado', Audience::all() ) );
		$outside = Article::create( 9, 11, Scope::queues( array( 'editorial-ficticia' ) ), $this->version( 1, 'Alheio', Audience::units( array( 'LAB-B' ) ) ) )->submit( 1, 1700000001 )->approve( 2, 1, 12, 1700000002, 'Aprovado' );
		$pending = $this->article()->revise( $this->version( 2, 'Futuro secreto', Audience::all(), 1700000020 ), 3 )->submit( 4, 1700000030 );
		$room    = $this->room( array( $draft, $outside, $pending ) );
		$model   = $this->model( array(), $room );
		self::assertTrue( $model['available'] );
		self::assertSame( 1, $model['total'] );
		self::assertSame( 1, $model['counts']['circular'] );
		self::assertSame( 'Texto aprovado', $model['items'][0]['title'] );
		foreach ( array( 'Rascunho', 'Alheio', 'Futuro' ) as $query ) {
			self::assertSame( 0, $this->model( array( 'q' => $query ), $room )['total'] );
		}
		self::assertSame( 0, $this->model( array(), $this->room( array( $pending ), 15, array( 'LAB-B' ) ) )['total'] );
		self::assertSame(
			1,
			$this->model(
				array(
					'arn_from' => '2023-11-14',
					'arn_to'   => '2023-11-14',
				),
				$room
			)['total']
		);
		self::assertSame( 0, $this->model( array( 'arn_from' => '2023-11-15' ), $room )['total'] );
	}

	public function testAuthorAndEditorHaveNoPublishedAudienceException(): void {
		$article = $this->article();
		self::assertSame( 0, $this->model( array(), $this->room( array( $article ), 11, array( 'LAB-B' ) ) )['total'] );
		self::assertSame( 0, $this->model( array(), $this->room( array( $article ), 12, array(), Role::Editorial, Scope::queues( array( 'editorial-ficticia' ) ) ) )['total'] );
	}

	public function testOrderIsPublicationDateThenIdBeforePagination(): void {
		$articles = array();
		for ( $id = 1; $id <= 12; ++$id ) {
			$articles[] = $this->article( $id );
		}
		$model = $this->model( array( 'arn_page' => '2' ), $this->room( $articles ) );
		self::assertSame( 12, $model['total'] );
		self::assertSame( 2, $model['pages'] );
		self::assertSame( array( 2, 1 ), array_column( $model['items'], 'id' ) );
		$latest = $this->model( array(), $this->room( array( $this->article( 2, 1700000060 ), $this->article( 3 ), $this->article( 4 ) ) ) );
		self::assertSame( array( 2, 4, 3 ), array_column( $latest['items'], 'id' ) );
	}

	public function testMalformedFiltersCloseBeforeReadingAnySource(): void {
		$reads = 0;
		$room  = new LocalNewsroom(
			gate: static function () use ( &$reads ): bool {
				++$reads;
				return false;
			}
		);
		foreach ( array(
			array( 'q' => array() ),
			array( 'q' => null ),
			array( 'q' => str_repeat( 'a', 161 ) ),
			array( 'q' => "bad\0query" ),
			array( 'category' => 'regulamento' ),
			array( 'category' => array() ),
			array( 'arn_page' => '01' ),
			array( 'arn_from' => '2026-02-30' ),
			array( 'arn_from' => "2026-01-01\0" ),
			array( 'arn_to' => array() ),
			array(
				'arn_from' => '2026-02-02',
				'arn_to'   => '2026-02-01',
			),
		) as $input ) {
			self::assertFalse( $this->model( $input, $room )['available'] );
			self::assertSame( 0, $reads );
		}
	}

	public function testDetailRouteRejectsMethodWrongPageArraysAndPreviewCollision(): void {
		self::assertSame( 7, NewsPublishedController::requestId( array( 'arn_news' => '7' ), 'GET', true ) );
		foreach ( array(
			array(),
			array( 'arn_news' => array( '7' ) ),
			array( 'arn_news' => '0' ),
			array( 'arn_news' => '07' ),
			array(
				'arn_news'         => '7',
				'arn_news_preview' => '7',
			),
			array(
				'arn_news' => '7',
				'arn_from' => '2026-02-30',
			),
		) as $input ) {
			self::assertNull( NewsPublishedController::requestId( $input, 'GET', true ) );
		}
		self::assertNull( NewsPublishedController::requestId( array( 'arn_news' => '7' ), 'POST', true ) );
		self::assertNull( NewsPublishedController::requestId( array( 'arn_news' => '7' ), 'GET', false ) );
	}
}
