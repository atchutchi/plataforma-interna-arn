<?php
/** SQL-locking authority for news writes and receipts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\ScopeType;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use RuntimeException;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, Squiz.Commenting.VariableComment

/** Rechecks persisted grants after acquiring ordered account locks. */
final class WpdbNewsAuthority implements NewsAuthority, EditorialAuthority {
	private const ERROR = 'Operação de notícia não autorizada.';
	private readonly WpdbAuthorizationRepository $repository;
	private readonly Closure $reviewer_provider;
	private readonly Closure $participant_provider;

	public function __construct( private readonly IdentityWpdb $database, private readonly CurrentNewsActor $current, ?Closure $reviewer_provider = null, ?Closure $participant_provider = null ) {
		$this->repository           = new WpdbAuthorizationRepository( $database );
		$this->reviewer_provider    = $reviewer_provider ?? static fn(): array => $current->reviewerIds();
		$this->participant_provider = $participant_provider ?? static fn(): array => array();
	}

	/** Locks actor, author and server-selected reviewers in ascending account ID order. */
	public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array {
		if ( ! $this->database->inTransaction() || $author_id < 1 || ScopeType::Queues !== $review_scope->type || 1 !== count( $review_scope->targets )
			|| ! in_array( $operation, array( 'create', 'revise', 'submit', 'read_notification', 'approve', 'reject', 'return', 'archive' ), true ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$current = $this->current->read();
		if ( null === $current ) {
			throw new RuntimeException( self::ERROR );
		}
		[, $actor_id, $session] = $current;
		$editorial              = in_array( $operation, array( 'approve', 'reject', 'return', 'archive' ), true );
		$participants           = $editorial || 'read_notification' === $operation ? $this->participants() : array();
		if ( $editorial && ( array() === $participants || ! isset( $participants[ $actor_id ], $participants[ $author_id ] ) ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$reviewer_ids = 'submit' === $operation || 'read_notification' === $operation ? ( $this->reviewer_provider )() : array();
		if ( ! is_array( $reviewer_ids ) || ( array_keys( $reviewer_ids ) !== range( 0, count( $reviewer_ids ) - 1 ) && array() !== $reviewer_ids ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$ids = array_merge( array( $actor_id, $author_id ), $reviewer_ids, array_keys( $participants ) );
		foreach ( $ids as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$ids = array_values( array_unique( $ids ) );
		sort( $ids, SORT_NUMERIC );
		$locked = array();
		foreach ( $ids as $id ) {
			$locked[ $id ] = $this->repository->findInTransaction( $id );
			if ( null === $locked[ $id ] ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$fresh = $this->current->sessionFor( $actor_id );
		if ( $fresh !== $session || ! LocalAccountSessionPolicy::valid( $locked[ $actor_id ], $actor_id, $fresh, true ) ) {
			throw new RuntimeException( self::ERROR );
		}
		if ( ( $editorial || 'read_notification' === $operation ) && $participants !== $this->participants() ) {
			throw new RuntimeException( self::ERROR );
		}
		if ( $editorial ) {
			if ( ! NewsPolicy::canReviewScope( $review_scope, $locked[ $actor_id ], $fresh, time() ) ) {
				throw new RuntimeException( self::ERROR );
			}
		} elseif ( 'read_notification' !== $operation ) {
			$action = 'submit' === $operation ? Action::SubmitNews : Action::CreateNewsDraft;
			if ( $actor_id !== $author_id || ! NewsPolicy::canAuthorAction( $action, $author_id, $locked[ $actor_id ], $fresh, time() ) ) {
				throw new RuntimeException( self::ERROR );
			}
		}
		$eligible = array();
		foreach ( $reviewer_ids as $id ) {
			if ( AccountState::Active === $locked[ $id ]->state && NewsPolicy::canReviewScope(
				$review_scope,
				$locked[ $id ],
				array(
					'arn_authz_local'    => true,
					'arn_authz_user_id'  => $id,
					'arn_authz_revision' => $locked[ $id ]->revision,
				),
				time()
			) ) {
				$eligible[] = $id;
			}
		}
		$eligible = array_values( array_unique( $eligible ) );
		sort( $eligible, SORT_NUMERIC );
		if ( 'submit' === $operation && array() === $eligible ) {
			throw new RuntimeException( self::ERROR );
		}
		return array(
			'actor_id'     => $actor_id,
			'reviewer_ids' => $eligible,
			'snapshots'    => $locked,
			'session'      => $fresh,
			'participants' => $participants,
			'operation'    => $operation,
		);
	}

	public function authorizeDecision( Article $article, int $version, string $operation, array $locked ): array {
		$this->revalidate( $locked );
		$actor = $locked['actor_id'];
		if ( $locked['operation'] !== $operation || array() === $locked['participants'] || ! NewsPolicy::canDecide( $article, $operation, $version, $locked['snapshots'][ $actor ], $locked['session'], time() ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$kind = match ( $operation ) {
			'approve' => 'approved',
			'reject' => 'rejected',
			'return' => 'returned',
			'archive' => 'archived',
		};
		$recipients = array();
		if ( 'approve' === $operation ) {
			$published = $article->approve( $article->revision, $version, $actor, time(), 'Verificação de destinatários' );
			foreach ( $locked['participants'] as $id => $units ) {
				$snapshot = $locked['snapshots'][ $id ];
				if ( NewsPolicy::canReadPublished( $published, $snapshot, $this->generation( $id, $snapshot->revision ), time(), $units ) ) {
					$recipients[ $id ] = 'publication';
				}
			}
		}
		$recipients[ $article->author_id ] = $kind;
		ksort( $recipients, SORT_NUMERIC );
		return array(
			'actor_id'        => $actor,
			'recipient_kinds' => $recipients,
		);
	}

	public function authorizeNotice( Article $article, object $notice, array $locked ): bool {
		$this->revalidate( $locked );
		$actor = $locked['actor_id'];
		$event = $article->transitions[ (int) $notice->revision - 1 ] ?? null;
		if ( 'read_notification' !== $locked['operation'] || (int) $notice->recipient_id !== $actor || (int) $notice->article_id !== $article->id || null === $event || $event->version !== (int) $notice->version || $event->occurred_at !== (int) $notice->created_at ) {
			return false;
		}
		$snapshot = $locked['snapshots'][ $actor ];
		if ( 'publication' === $notice->kind ) {
			return 'approved' === $event->event && (int) $notice->version === $article->published_version && isset( $locked['participants'][ $actor ] ) && NewsPolicy::canReadPublished( $article, $snapshot, $locked['session'], time(), $locked['participants'][ $actor ] );
		}
		if ( $notice->kind !== $event->event ) {
			return false;
		}
		if ( 'submitted' === $notice->kind ) {
			return NewsPolicy::canReviewScope( $article->review_scope, $snapshot, $locked['session'], time() ) && $article->wasSubmitted( (int) $notice->version );
		}
		return in_array( $notice->kind, array( 'approved', 'rejected', 'returned', 'archived' ), true ) && $actor === $article->author_id && NewsPolicy::canRead( $article, (int) $notice->version, $snapshot, $locked['session'], time() );
	}

	/** A mutable external roster cannot change unnoticed while a SQL lock waits. */
	private function revalidate( array $locked ): void {
		$actor = $locked['actor_id'];
		$fresh = $this->current->sessionFor( $actor );
		if ( ! $this->database->inTransaction() || $fresh !== $locked['session'] || ! LocalAccountSessionPolicy::valid( $locked['snapshots'][ $actor ], $actor, $fresh, true ) || $locked['participants'] !== $this->participants() ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	private function participants(): array {
		$participants = ( $this->participant_provider )();
		if ( ! is_array( $participants ) ) {
			throw new RuntimeException( self::ERROR );
		}
		foreach ( $participants as $id => $units ) {
			if ( ! is_int( $id ) || $id < 1 || ! is_array( $units ) || ! array_is_list( $units ) ) {
				throw new RuntimeException( self::ERROR );
			}
			if ( array() !== $units ) {
				Audience::units( $units );
			}
		}
		ksort( $participants, SORT_NUMERIC );
		return $participants;
	}

	private function generation( int $id, int $revision ): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $id,
			'arn_authz_revision' => $revision,
		);
	}
}
