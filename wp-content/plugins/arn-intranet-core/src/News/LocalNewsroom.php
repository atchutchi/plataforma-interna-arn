<?php
/** Local-only runtime for drafting and reading private news.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose

/** Filters repository primitives before exposing any article, notice or binary. */
final class LocalNewsroom {
	private const ERROR = 'Operação de notícias indisponível.';
	private readonly Closure $repository_factory;
	private readonly Closure $store_factory;
	private readonly Closure $gate;

	public function __construct( private readonly CurrentNewsActor $current = new CurrentNewsActor(), ?Closure $repository_factory = null, ?Closure $store_factory = null, ?Closure $gate = null ) {
		$this->repository_factory = $repository_factory ?? function (): array {
			global $wpdb;
			$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$db->set_prefix( $wpdb->prefix );
			return array( new WpdbNewsRepository( $db, new WpdbNewsAuthority( $db, $this->current, null, fn(): array => $this->current->participantUnits() ) ), new WpdbFileRegistry( $db ) );
		};
		$this->store_factory      = $store_factory ?? static fn(): PrivateBlobStore => LocalPrivateFiles::store();
		$this->gate               = $gate ?? static fn(): bool => NewsConfiguration::configured();
	}

	public static function configured(): bool {
		return NewsConfiguration::configured();
	}

	private function actor(): array {
		if ( true !== ( $this->gate )() ) {
			throw new RuntimeException( self::ERROR );
		}
		$actor = $this->current->read();
		if ( null === $actor ) {
			throw new RuntimeException( self::ERROR );
		}
		return $actor;
	}

	/** Create a draft for the current fixture. The queue is fixed on the server. */
	public function create( DraftInput $input, string $reason ): Article {
		$actor = $this->actor();
		if ( ! NewsPolicy::canCreate( $actor[0], $actor[2], time() ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$this->validateText( $input );
		if ( $this->hasExisting( $input ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$content      = $this->content( $input, null );
		[$repository] = ( $this->repository_factory )();
		return $repository->create( $actor[1], Scope::queues( array( 'editorial-ficticia' ) ), new Version( 1, $content, $actor[1], time() ), $reason );
	}

	/** Append only from the latest authorised draft version. */
	public function revise( int $id, DraftInput $input, int $expected, string $reason ): Article {
		$actor        = $this->actor();
		[$repository] = ( $this->repository_factory )();
		$before       = $repository->find( $id );
		if ( null === $before || ! NewsPolicy::canEdit( $before, $actor[0], $actor[2], time() ) || $expected !== $before->revision ) {
			throw new RuntimeException( self::ERROR );
		}
		$this->validateText( $input );
		$content = $this->content( $input, $before );
		return $repository->revise( $id, new Version( count( $before->versions ) + 1, $content, $actor[1], time() ), $expected, $reason );
	}

	public function submit( int $id, int $expected, string $reason ): Article {
		$actor        = $this->actor();
		[$repository] = ( $this->repository_factory )();
		$before       = $repository->find( $id );
		if ( null === $before || $expected !== $before->revision || ! NewsPolicy::canSubmit( $before, $actor[0], $actor[2], time() ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return $repository->submit( $id, $expected, $reason );
	}

	/** Decision input is rechecked under ordered SQL locks by the repository authority. */
	public function decide( int $id, string $operation, int $version, int $expected, string $reason ): Article {
		$actor        = $this->actor();
		[$repository] = ( $this->repository_factory )();
		$before       = $repository->find( $id );
		if ( null === $before || $before->revision !== $expected || ! NewsPolicy::canDecide( $before, $operation, $version, $actor[0], $actor[2], time() ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return $repository->decide( $id, $operation, $version, $expected, $reason );
	}

	/** Internal envelopes only. Consumers project precisely published_version. */
	public function published(): ?array {
		try {
			$actor = $this->actor();
			$units = $this->current->unitsFor( $actor[1] );
			if ( null === $units ) {
				return null;
			}
			[$repository] = ( $this->repository_factory )();
			$visible      = array();
			foreach ( $repository->all() as $article ) {
				if ( NewsPolicy::canReadPublished( $article, $actor[0], $actor[2], time(), $units ) ) {
					$visible[] = $article;
				}
			}
			return $visible;
		} catch ( Throwable ) {
			return null;
		}
	}

	public function findPublished( int $id ): ?Article {
		try {
			$actor = $this->actor();
			$units = $this->current->unitsFor( $actor[1] );
			if ( null === $units ) {
				return null;
			}
			[$repository] = ( $this->repository_factory )();
			$article      = $repository->find( $id );
			return null !== $article && NewsPolicy::canReadPublished( $article, $actor[0], $actor[2], time(), $units ) ? $article : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	public function visible(): ?array {
		try {
			$actor        = $this->actor();
			[$repository] = ( $this->repository_factory )();
			$visible      = array();
			foreach ( $repository->all() as $article ) {
				$number = $this->visibleVersion( $article, $actor );
				if ( null !== $number ) {
					$visible[] = $article;
				}
			}
			return $visible;
		} catch ( Throwable ) {
			return null;
		}
	}

	public function findVisible( int $id ): ?Article {
		try {
			$actor        = $this->actor();
			[$repository] = ( $this->repository_factory )();
			$article      = $repository->find( $id );
			return null !== $article && null !== $this->visibleVersion( $article, $actor ) ? $article : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	public function historyVisible( int $id ): ?array {
		try {
			$actor        = $this->actor();
			[$repository] = ( $this->repository_factory )();
			$article      = $repository->find( $id );
			if ( null === $article ) {
				return null;
			}
			$own = $article->author_id === $actor[1];
			if ( ! $own && ! NewsPolicy::canReviewScope( $article->review_scope, $actor[0], $actor[2], time() ) ) {
				return null;
			}
			$number = $own ? count( $article->versions ) : $article->submitted_version;
			return null !== $number && NewsPolicy::canRead( $article, $number, $actor[0], $actor[2], time() )
				? NewsroomProjection::history( $repository->history( $id ), $article, $own ) : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	public function notifications(): ?array {
		try {
			$actor        = $this->actor();
			[$repository] = ( $this->repository_factory )();
			$visible      = array();
			foreach ( $repository->notificationRows( $actor[1] ) as $notice ) {
				$article = $repository->find( (int) $notice->article_id );
				$event   = null === $article ? null : ( $article->transitions[ (int) $notice->revision - 1 ] ?? null );
				if ( null !== $event && $event->version === (int) $notice->version && $event->occurred_at === (int) $notice->created_at
					&& $this->noticeVisible( $article, $notice, $event, $actor ) ) {
					$visible[] = $notice;
				}
			}
			return $visible;
		} catch ( Throwable ) {
			return null;
		}
	}

	public function markRead( int $id ): void {
		$this->actor();
		$notices = $this->notifications();
		$allowed = false;
		foreach ( null === $notices ? array() : $notices as $notice ) {
			if ( (int) $notice->id === $id ) {
				$allowed = true;
				break;
			}
		}
		if ( ! $allowed ) {
			throw new RuntimeException( self::ERROR );
		}
		[$repository] = ( $this->repository_factory )();
		$repository->markRead( $id, time() );
	}

	/** Validate registry tuple and exact version before opening the private handle. */
	public function file( string $blob_id ): ?array {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $blob_id ) ) {
			return null;
		}
		try {
			$actor                   = $this->actor();
			[$repository, $registry] = ( $this->repository_factory )();
			$record                  = $registry->find( $blob_id );
			if ( null === $record || 'news_asset' !== $record->object_type || 1 !== $record->version || 'original' !== $record->variant ) {
				return null;
			}
			$row = $repository->asset( $blob_id );
			if ( null === $row || $row['asset_id'] !== $record->object_id || $row['asset']->blob->id !== $record->blob->id ) {
				return null;
			}
			$article = $repository->find( $row['article_id'] );
			$units   = $this->current->unitsFor( $actor[1] );
			if ( null === $article || null === $units || ! NewsPolicy::canRead( $article, $row['version'], $actor[0], $actor[2], time(), $units ) ) {
				return null;
			}
			$version = $article->version( $row['version'] );
			$asset   = $row['asset'];
			$slot    = 'featured' === $asset->kind ? $version->content->featured : ( 'gallery' === $asset->kind ? ( $version->content->gallery[ $row['position'] ] ?? null ) : ( $version->content->attachments[ $row['position'] ] ?? null ) );
			$tuple   = $registry->findForObject( 'news_asset', $row['asset_id'], 1, 'original' );
			if ( $slot?->blob->id !== $blob_id || $tuple?->blob->id !== $blob_id ) {
				return null;
			}
			return array(
				'handle'  => ( $this->store_factory )()->open( $asset->blob ),
				'blob'    => $asset->blob,
				'asset'   => $asset,
				'article' => $article,
				'version' => $version,
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	private function visibleVersion( Article $article, array $actor ): ?int {
		$number = $article->author_id === $actor[1] ? count( $article->versions ) : $article->submitted_version;
		if ( $article->author_id !== $actor[1] && ( null === $number || ! $article->wasSubmitted( $number ) || ! NewsPolicy::canReviewScope( $article->review_scope, $actor[0], $actor[2], time() ) ) ) {
			return null;
		}
		return null !== $number && NewsPolicy::canRead( $article, $number, $actor[0], $actor[2], time() ) ? $number : null;
	}

	/** A notice has its own historical visibility rule, never the current draft rule. */
	private function noticeVisible( Article $article, object $notice, EditorialTransition $event, array $actor ): bool {
		if ( (int) $notice->recipient_id !== $actor[1] ) {
			return false;
		}
		if ( 'publication' === $notice->kind ) {
			$units = $this->current->unitsFor( $actor[1] );
			return 'approved' === $event->event && $article->published_version === (int) $notice->version && null !== $units
				&& NewsPolicy::canReadPublished( $article, $actor[0], $actor[2], time(), $units );
		}
		if ( $notice->kind !== $event->event ) {
			return false;
		}
		if ( 'submitted' === $notice->kind ) {
			return $article->wasSubmitted( (int) $notice->version ) && NewsPolicy::canReviewScope( $article->review_scope, $actor[0], $actor[2], time() );
		}
		return in_array( $notice->kind, array( 'approved', 'rejected', 'returned', 'archived' ), true ) && $article->author_id === $actor[1]
			&& NewsPolicy::canRead( $article, (int) $notice->version, $actor[0], $actor[2], time() );
	}

	private function validateText( DraftInput $input ): void {
		new Content( $input->title, $input->body, $input->category, null, array(), array(), $input->audience );
	}

	private function hasExisting( DraftInput $input ): bool {
		foreach ( array_merge( null === $input->featured ? array() : array( $input->featured ), $input->gallery, $input->attachments ) as $media ) {
			if ( null !== $media->existing_id ) {
				return true;
			}
		}
		return false;
	}

	private function content( DraftInput $input, ?Article $before ): Content {
		$latest  = null === $before ? array() : array_merge(
			null === $before->version( count( $before->versions ) )->content->featured ? array() : array( $before->version( count( $before->versions ) )->content->featured ),
			$before->version( count( $before->versions ) )->content->gallery,
			$before->version( count( $before->versions ) )->content->attachments
		);
		$allowed = array();
		foreach ( $latest as $asset ) {
			$allowed[ $asset->blob->id ] = $asset;
		}
		$used   = array();
		$inputs = array_merge( null === $input->featured ? array() : array( array( 'featured', $input->featured ) ), array_map( static fn( MediaInput $item ): array => array( 'gallery', $item ), $input->gallery ), array_map( static fn( MediaInput $item ): array => array( 'attachment', $item ), $input->attachments ) );
		foreach ( $inputs as [$kind, $media] ) {
			if ( ! Content::plainText( $media->description, 1, 'attachment' === $kind ? 160 : 300 ) ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( null !== $media->existing_id ) {
				$existing = $allowed[ $media->existing_id ] ?? null;
				if ( null === $existing || $existing->kind !== $kind || isset( $used[ $media->existing_id ] ) ) {
					throw new RuntimeException( self::ERROR );
				}
				$used[ $media->existing_id ] = true;
			}
		}
		$store       = ( $this->store_factory )();
		$make        = function ( string $kind, MediaInput $media ) use ( $store, $allowed ): Asset {
			$blob = null !== $media->existing_id ? $store->duplicate( $allowed[ $media->existing_id ]->blob ) : $store->importFile( $media->source );
			if ( 'attachment' !== $kind ) {
				$this->assertImage( $store, $blob );
			}
			return new Asset( $kind, $blob, $media->description );
		};
		$featured    = null === $input->featured ? null : $make( 'featured', $input->featured );
		$gallery     = array_map( static fn( MediaInput $media ): Asset => $make( 'gallery', $media ), $input->gallery );
		$attachments = array_map( static fn( MediaInput $media ): Asset => $make( 'attachment', $media ), $input->attachments );
		return new Content( $input->title, $input->body, $input->category, $featured, $gallery, $attachments, $input->audience );
	}

	/** Inspect the copied bytes, never browser MIME or name. */
	private function assertImage( PrivateBlobStore $store, $blob ): void {
		$handle = $store->open( $blob );
		try {
			$bytes = stream_get_contents( $handle );
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Binários inválidos não devem emitir avisos no pedido HTTP.
			$size = false === $bytes ? false : @getimagesizefromstring( $bytes );
			if ( false === $size || $size[0] < 1 || $size[1] < 1 || $size[0] > 4096 || $size[1] > 4096 || $size[0] * $size[1] > 16000000 ) {
				throw new RuntimeException( self::ERROR );
			}
		} finally {
			fclose( $handle );
		}
	}
}
