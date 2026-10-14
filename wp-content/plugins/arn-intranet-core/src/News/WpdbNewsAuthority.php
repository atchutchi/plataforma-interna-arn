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
final class WpdbNewsAuthority implements NewsAuthority {
	private const ERROR = 'Operação de notícia não autorizada.';
	private readonly WpdbAuthorizationRepository $repository;
	private readonly Closure $reviewer_provider;

	public function __construct( private readonly IdentityWpdb $database, private readonly CurrentNewsActor $current, ?Closure $reviewer_provider = null ) {
		$this->repository        = new WpdbAuthorizationRepository( $database );
		$this->reviewer_provider = $reviewer_provider ?? static fn(): array => $current->reviewerIds();
	}

	/** Locks actor, author and server-selected reviewers in ascending account ID order. */
	public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array {
		$row = $this->database->get_row( 'SELECT @@session.in_transaction AS active' );
		if ( ! is_object( $row ) || '' !== $this->database->last_error || '1' !== (string) $row->active
			|| $author_id < 1 || ScopeType::Queues !== $review_scope->type || 1 !== count( $review_scope->targets )
			|| ! in_array( $operation, array( 'create', 'revise', 'submit', 'read_notification' ), true ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$current = $this->current->read();
		if ( null === $current ) {
			throw new RuntimeException( self::ERROR );
		}
		[, $actor_id, $session] = $current;
		$reviewer_ids           = 'submit' === $operation || 'read_notification' === $operation ? ( $this->reviewer_provider )() : array();
		if ( ! is_array( $reviewer_ids ) || ( array_keys( $reviewer_ids ) !== range( 0, count( $reviewer_ids ) - 1 ) && array() !== $reviewer_ids ) ) {
			throw new RuntimeException( self::ERROR );
		}
		$ids = array_merge( array( $actor_id, $author_id ), $reviewer_ids );
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
		if ( 'read_notification' === $operation ) {
			if ( ! NewsPolicy::canReviewScope( $review_scope, $locked[ $actor_id ], $fresh, time() ) ) {
				throw new RuntimeException( self::ERROR );
			}
		} else {
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
		);
	}
}
