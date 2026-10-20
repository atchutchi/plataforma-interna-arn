<?php
/** Atomic storage of private news, assets, audit and notices.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Cms\UploadAdmission;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\Documents\PrivateFiles\FileRecord;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment

/** Internal reads do not authorize a caller and must be filtered by NewsPolicy. */
final class WpdbNewsRepository {
	private const ERROR = 'Persistência de notícias indisponível.';
	private readonly NewsSchema $schema;
	private readonly FileSchema $file_schema;
	private readonly AuthorizationSchema $authorization_schema;
	private readonly WpdbFileRegistry $registry;

	public function __construct( private readonly IdentityWpdb $database, private readonly NewsAuthority $authority ) {
		$database->suppress_errors( true );
		$this->schema               = new NewsSchema( $database );
		$this->file_schema          = new FileSchema( $database );
		$this->authorization_schema = new AuthorizationSchema( $database );
		$this->registry             = new WpdbFileRegistry( $database );
	}

	public function create( int $author_id, Scope $review_scope, Version $initial, string $reason ): Article {
		$this->reason( $reason );
		if ( 1 !== $initial->number || $author_id < 1 || $initial->actor_id !== $author_id ) {
			throw new RuntimeException( self::ERROR );
		}
		return $this->transaction(
			function () use ( $author_id, $review_scope, $initial, $reason ): Article {
				$decision = $this->authority->lockAndAssert( $author_id, $review_scope, 'create' );
				if ( $author_id !== $decision['actor_id'] ) {
						throw new RuntimeException( self::ERROR );
				}
				$this->admit( $initial, null, array() );
				$now = gmdate( 'Y-m-d H:i:s', $initial->created_at );
				$this->execute( $this->database->prepare( "INSERT INTO %i (post_author,post_date,post_date_gmt,post_content,post_title,post_status,post_type,post_name,post_modified,post_modified_gmt) VALUES (%d,%s,%s,'','Notícia privada','private','arn_noticia','',%s,%s)", $this->schema->table( 'posts' ), $author_id, $now, $now, $now, $now ), 1 );
				$id      = (int) $this->database->insert_id;
				$article = Article::create( $id, $author_id, $review_scope, $initial, $reason );
				$this->execute( $this->database->prepare( 'INSERT INTO %i (id,author_id,review_queue,revision,submitted_version,submitted_at,created_at) VALUES (%d,%d,%s,1,NULL,NULL,%d)', $this->schema->table( 'news' ), $id, $author_id, $review_scope->targets[0], $initial->created_at ), 1 );
				$this->insertVersion( $id, $initial );
				$this->audit( $id, 1, 'created', $author_id, $reason, $initial->created_at, 1 );
				$this->authority->assertUploadCurrent();
				return $article;
			}
		);
	}

	public function revise( int $id, Version $next, int $expected, string $reason, array $retained = array() ): Article {
		$this->reason( $reason );
		$preflight = $this->preflight( $id );
		return $this->transaction(
			function () use ( $id, $next, $expected, $reason, $preflight, $retained ): Article {
				$decision = $this->authority->lockAndAssert( $preflight['author_id'], $preflight['scope'], 'revise' );
				if ( $decision['actor_id'] !== $next->actor_id ) {
						throw new RuntimeException( self::ERROR );
				}
				$before = $this->lockedArticle( $id, $preflight );
				$after  = $before->revise( $next, $expected, $reason );
				$this->admit( $next, $before->version( count( $before->versions ) ), $retained );
				$this->insertVersion( $id, $next );
				$this->execute( $this->database->prepare( 'UPDATE %i SET revision=%d WHERE id=%d AND revision=%d', $this->schema->table( 'news' ), $after->revision, $id, $expected ), 1 );
				$this->audit( $id, $after->revision, 'revised', $decision['actor_id'], $reason, $next->created_at, $next->number );
				$this->authority->assertUploadCurrent();
				return $after;
			}
		);
	}

	public function submit( int $id, int $expected, string $reason ): Article {
		$this->reason( $reason );
		$preflight = $this->preflight( $id );
		return $this->transaction(
			function () use ( $id, $expected, $reason, $preflight ): Article {
				$decision = $this->authority->lockAndAssert( $preflight['author_id'], $preflight['scope'], 'submit' );
				$before   = $this->lockedArticle( $id, $preflight );
				$now      = time();
				$after    = $before->submit( $expected, $now, $reason );
				if ( array() === $decision['reviewer_ids'] ) {
						throw new RuntimeException( self::ERROR );
				}
				$this->execute( $this->database->prepare( 'UPDATE %i SET revision=%d,submitted_version=%d,submitted_at=%d WHERE id=%d AND revision=%d', $this->schema->table( 'news' ), $after->revision, $after->submitted_version, $now, $id, $expected ), 1 );
				$this->audit( $id, $after->revision, 'submitted', $decision['actor_id'], $reason, $now, $after->submitted_version );
				foreach ( $decision['reviewer_ids'] as $recipient_id ) {
					$this->notice( $after, $after->submitted_version, $recipient_id, 'submitted', $now );
				}
				return $after;
			}
		);
	}

	public function decide( int $id, string $operation, int $version, int $expected, string $reason ): Article {
		$this->reason( $reason );
		if ( ! $this->authority instanceof EditorialAuthority || ! in_array( $operation, array( 'approve', 'reject', 'return', 'archive' ), true ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$preflight = $this->preflight( $id );
		return $this->transaction(
			function () use ( $id, $operation, $version, $expected, $reason, $preflight ): Article {
				$locked = $this->authority->lockAndAssert( $preflight['author_id'], $preflight['scope'], $operation );
				$before = $this->lockedArticle( $id, $preflight );
				if ( $before->revision !== $expected ) {
					throw new RuntimeException( self::ERROR );
				}
				$decision = $this->authority->authorizeDecision( $before, $version, $operation, $locked );
				$now      = time();
				$method   = 'return' === $operation ? 'returnForEditing' : $operation;
				$after    = $before->$method( $expected, $version, $decision['actor_id'], $now, $reason );
				$this->execute( $this->database->prepare( 'UPDATE %i SET revision=%d WHERE id=%d AND revision=%d', $this->schema->table( 'news' ), $after->revision, $id, $expected ), 1 );
				$event = $after->transitions[ count( $after->transitions ) - 1 ];
				$this->audit( $id, $after->revision, $event->event, $decision['actor_id'], $reason, $now, $version );
				foreach ( $decision['recipient_kinds'] as $recipient_id => $kind ) {
					$this->notice( $after, $version, $recipient_id, $kind, $now );
				}
				return $after;
			}
		);
	}

	private function notice( Article $article, int $version, int $recipient_id, string $kind, int $at ): void {
		$this->execute( $this->database->prepare( 'INSERT INTO %i (article_id,version,revision,kind,recipient_id,created_at,read_at) VALUES (%d,%d,%d,%s,%d,%d,NULL)', $this->schema->table( 'notifications' ), $article->id, $version, $article->revision, $kind, $recipient_id, $at ), 1 );
	}

	public function find( int $id ): ?Article {
		$this->schema->assertReady();
		if ( $id < 1 ) {
			return null;
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d', $this->schema->table( 'news' ), $id ) );
		return array() === $rows ? null : $this->hydrate( $rows[0] );
	}

	public function all(): array {
		$this->schema->assertReady();
		$articles = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i ORDER BY id', $this->schema->table( 'news' ) ) ) as $row ) {
			$articles[] = $this->hydrate( $row );
		}
		return $articles;
	}

	public function history( int $id ): array {
		$this->schema->assertReady();
		return $id < 1 ? array() : $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE article_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $id ) );
	}

	/** Returns own asset identity, article/version/position and immutable metadata. */
	public function asset( string $blob_id ): ?array {
		$this->schema->assertReady();
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $blob_id ) ) {
			return null;
		}
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE blob_id=%s', $this->schema->table( 'assets' ), $blob_id ) );
		if ( array() === $rows ) {
			return null;
		}
		$row = $rows[0];
		$this->assertFile( $row );
		$article = $this->find( (int) $row->article_id );
		if ( null === $article || null === $article->version( (int) $row->version ) ) {
			throw new RuntimeException( self::ERROR );
		}
		return array(
			'asset_id'   => (int) $row->id,
			'article_id' => (int) $row->article_id,
			'version'    => (int) $row->version,
			'position'   => (int) $row->position,
			'asset'      => $this->assetValue( $row ),
		);
	}

	/** Envelope only; consumers still apply current actor and policy. */
	public function notificationRows( int $recipient_id ): array {
		$this->schema->assertReady();
		return $recipient_id < 1 ? array() : $this->rows( $this->database->prepare( 'SELECT id,article_id,version,revision,kind,recipient_id,created_at,read_at FROM %i WHERE recipient_id=%d ORDER BY id DESC', $this->schema->table( 'notifications' ), $recipient_id ) );
	}

	public function markRead( int $notification_id, int $at ): void {
		if ( $notification_id < 1 || $at < 0 ) {
			throw new RuntimeException( self::ERROR );
		}
		if ( ! $this->authority instanceof EditorialAuthority ) {
			throw new RuntimeException( self::ERROR );
		}
		$this->schema->assertReady();
		$notice = $this->one( $this->database->prepare( 'SELECT article_id,version,revision,kind,recipient_id FROM %i WHERE id=%d', $this->schema->table( 'notifications' ), $notification_id ) );
		if ( null === $notice ) {
			throw new RuntimeException( self::ERROR );
		}
		$preflight = $this->preflight( (int) $notice->article_id );
		$this->transaction(
			function () use ( $notification_id, $at, $notice, $preflight ): void {
				$decision = $this->authority->lockAndAssert( $preflight['author_id'], $preflight['scope'], 'read_notification' );
				if ( $decision['actor_id'] !== (int) $notice->recipient_id ) {
						throw new RuntimeException( self::ERROR );
				}
				$article       = $this->lockedArticle( (int) $notice->article_id, $preflight );
				$locked_notice = $this->one( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', $this->schema->table( 'notifications' ), $notification_id ) );
				if ( null === $locked_notice || (int) $locked_notice->article_id !== $article->id || (int) $notice->version !== (int) $locked_notice->version || (int) $notice->revision !== (int) $locked_notice->revision || $notice->kind !== $locked_notice->kind || ! $this->authority->authorizeNotice( $article, $locked_notice, $decision ) ) {
					throw new RuntimeException( self::ERROR );
				}
				if ( null === $locked_notice->read_at ) {
					$this->execute( $this->database->prepare( 'UPDATE %i SET read_at=%d WHERE id=%d AND read_at IS NULL', $this->schema->table( 'notifications' ), $at, $notification_id ), 1 );
				}
			}
		);
	}

	private function preflight( int $id ): array {
		$this->schema->assertReady();
		if ( $id < 1 ) {
			throw new RuntimeException( self::ERROR );
		}
		$row = $this->one( $this->database->prepare( 'SELECT author_id,review_queue FROM %i WHERE id=%d', $this->schema->table( 'news' ), $id ) );
		if ( null === $row ) {
			throw new RuntimeException( self::ERROR );
		}
		return array(
			'author_id' => (int) $row->author_id,
			'scope'     => Scope::queues( array( $row->review_queue ) ),
		);
	}

	private function lockedArticle( int $id, array $preflight ): Article {
		$row = $this->one( $this->database->prepare( 'SELECT * FROM %i WHERE id=%d FOR UPDATE', $this->schema->table( 'news' ), $id ) );
		if ( null === $row || (int) $row->author_id !== $preflight['author_id'] || array( $row->review_queue ) !== $preflight['scope']->targets ) {
			throw new RuntimeException( self::ERROR );
		}
		return $this->hydrate( $row );
	}

	private function hydrate( object $row ): Article {
		return ( new NewsHydrator( $this->database ) )->hydrate( $row );
	}

	private function assetValue( object $row ): Asset {
		return ( new NewsHydrator( $this->database ) )->assetValue( $row );
	}

	private function assertFile( object $row ): void {
		( new NewsHydrator( $this->database ) )->assertFile( $row );
	}

	/** Validate provenance against the locked latest version, never a request assertion. */
	private function admit( Version $next, ?Version $previous, array $retained ): void {
		$assets = static fn( Version $version ): array => array_merge( null === $version->content->featured ? array() : array( $version->content->featured ), $version->content->gallery, $version->content->attachments );
		$before = array();
		foreach ( null === $previous ? array() : $assets( $previous ) as $asset ) {
			$before[ $asset->blob->id ] = $asset;
		}
		$next_assets = $assets( $next );
		$policy      = array() === $next_assets ? null : ( new UploadAdmission( $this->database ) )->policy();
		$seen        = array();
		foreach ( $next_assets as $asset ) {
			$source = null;
			if ( array_key_exists( $asset->blob->id, $retained ) ) {
				$id = $retained[ $asset->blob->id ];
				if ( ! is_string( $id ) || ! isset( $before[ $id ] ) || $before[ $id ]->kind !== $asset->kind || isset( $seen[ $id ] ) ) {
					throw new RuntimeException( self::ERROR );
				}
				$seen[ $id ] = true;
				$source      = $before[ $id ]->blob;
				unset( $retained[ $asset->blob->id ] );
			}
			UploadAdmission::assertAllowed( $policy, 'attachment' === $asset->kind ? 'news_attachment' : 'news_image', $asset->blob, $source );
		}
		if ( array() !== $retained ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function insertVersion( int $id, Version $version ): void {
		$content      = $version->content;
		$json         = AudienceCodec::encode( $content->audience );
		$audience_sql = null === $json ? 'NULL' : $this->database->prepare( '%s', $json );
		$this->execute( $this->database->prepare( 'INSERT INTO %i (article_id,number,title,body,category,actor_id,created_at,audience_json) VALUES (%d,%d,%s,%s,%s,%d,%d,' . $audience_sql . ')', $this->schema->table( 'versions' ), $id, $version->number, $content->title, $content->body, $content->category, $version->actor_id, $version->created_at ), 1 );
		$groups = array(
			'featured'   => null === $content->featured ? array() : array( $content->featured ),
			'gallery'    => $content->gallery,
			'attachment' => $content->attachments,
		);
		foreach ( $groups as $kind => $group ) {
			foreach ( $group as $position => $asset ) {
				$blob = $asset->blob;
				$this->execute( $this->database->prepare( 'INSERT INTO %i (article_id,version,kind,position,blob_id,sha256,size_bytes,mime,description) VALUES (%d,%d,%s,%d,%s,%s,%d,%s,%s)', $this->schema->table( 'assets' ), $id, $version->number, $kind, $position, $blob->id, $blob->sha256, $blob->size, $blob->mime, $asset->description ), 1 );
				$asset_id = (int) $this->database->insert_id;
				$this->registry->registerInTransaction( new FileRecord( $blob, 'news_asset', $asset_id, 1, 'original', $version->actor_id, $version->created_at ) );
			}
		}
	}

	private function audit( int $id, int $revision, string $event, int $actor, string $reason, int $at, int $version ): void {
		$this->execute( $this->database->prepare( 'INSERT INTO %i (article_id,revision,event,actor_id,reason,occurred_at,version) VALUES (%d,%d,%s,%d,%s,%d,%d)', $this->schema->table( 'audit' ), $id, $revision, $event, $actor, $reason, $at, $version ), 1 );
	}

	private function transaction( callable $operation ): mixed {
		if ( $this->database->inTransaction() ) {
			throw new RuntimeException( self::ERROR );
		}
		$this->schema->assertReady();
		$this->file_schema->assertReady();
		$this->authorization_schema->validate();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$result = $operation();
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( self::ERROR );
		}
	}

	private function reason( string $reason ): void {
		if ( strlen( $reason ) < 1 || strlen( $reason ) > 500 || 1 !== preg_match( '//u', $reason ) || 1 === preg_match( '/\p{Cc}|\A\s|\s\z/u', $reason ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function one( string $sql ): ?object {
		$rows = $this->rows( $sql );
		if ( count( $rows ) > 1 ) {
			throw new RuntimeException( self::ERROR );
		}
		return $rows[0] ?? null;
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR );
		}
		return $rows;
	}

	private function execute( string $sql, ?int $affected = null ): void {
		$result = $this->database->query( $sql );
		if ( false === $result || '' !== $this->database->last_error || ( null !== $affected && $result !== $affected ) ) {
			throw new RuntimeException( self::ERROR );
		}
	}
}
