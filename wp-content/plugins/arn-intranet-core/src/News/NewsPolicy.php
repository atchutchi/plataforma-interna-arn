<?php
/** Pure business authorization for local news.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\ScopeType;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** Reuses the closed authorization matrix without technical exceptions. */
final class NewsPolicy {
	/** Check whether the actor may create a draft.
	 *
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @return bool
	 */
	public static function canCreate( AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		return self::canAuthorAction( Action::CreateNewsDraft, $snapshot->user_id, $snapshot, $session, $now );
	}

	/** Check a specific author operation before or after aggregate loading.
	 *
	 * @param Action                $action Create or submit action.
	 * @param int                   $author_id Fixed author ID.
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @return bool
	 */
	public static function canAuthorAction( Action $action, int $author_id, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( ! in_array( $action, array( Action::CreateNewsDraft, Action::SubmitNews ), true ) || $author_id <= 0 ) {
			return false;
		}
		return self::allows( $action, Scope::own(), $author_id, $author_id, $snapshot, $session, $now );
	}

	/** Check draft editing competence.
	 *
	 * @param Article               $article Existing article.
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @return bool
	 */
	public static function canEdit( Article $article, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		return in_array( $article->state, array( NewsState::Draft, NewsState::Rejected, NewsState::Published ), true )
			&& self::canAuthorAction( Action::CreateNewsDraft, $article->author_id, $snapshot, $session, $now );
	}

	/** Check submission competence and completeness.
	 *
	 * @param Article               $article Existing article.
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @return bool
	 */
	public static function canSubmit( Article $article, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( NewsState::Draft !== $article->state || count( $article->versions ) <= ( $article->submitted_version ?? 0 ) ) {
			return false;
		}
		$content = $article->version( count( $article->versions ) )->content;
		return $content->completeForPublication()
			&& self::canAuthorAction( Action::SubmitNews, $article->author_id, $snapshot, $session, $now );
	}

	/** Author history or only submitted version for a queue editor.
	 *
	 * @param Article               $article Existing article.
	 * @param int                   $version Version requested.
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @param array                 $unit_ids Current unit memberships.
	 * @return bool
	 */
	public static function canRead( Article $article, int $version, AuthorizationSnapshot $snapshot, array $session, int $now, array $unit_ids = array() ): bool {
		if ( null === $article->version( $version ) ) {
			return false;
		}
		if ( $snapshot->user_id === $article->author_id ) {
			return self::canAuthorAction( Action::CreateNewsDraft, $article->author_id, $snapshot, $session, $now );
		}
		if ( $article->wasSubmitted( $version ) && self::canReviewScope( $article->review_scope, $snapshot, $session, $now ) ) {
			return true;
		}
		return $version === $article->published_version && self::canReadPublished( $article, $snapshot, $session, $now, $unit_ids );
	}

	/** Read only the current approved version under the reader's own grant.
	 *
	 * @param Article               $article Existing aggregate.
	 * @param AuthorizationSnapshot $snapshot Current account and grants.
	 * @param array                 $session Current session generation.
	 * @param int                   $now Unix UTC time.
	 * @param array                 $unit_ids Current unit memberships.
	 */
	public static function canReadPublished( Article $article, AuthorizationSnapshot $snapshot, array $session, int $now, array $unit_ids ): bool {
		if ( null === $article->published_version || NewsState::Archived === $article->state ) {
			return false;
		}
		$version = $article->version( $article->published_version );
		return null !== $version && null !== $version->content->audience
			&& $version->content->audience->includes( $snapshot->user_id, $unit_ids )
			&& self::allows( Action::ReadContent, Scope::own(), $snapshot->user_id, 0, $snapshot, $session, $now );
	}

	/** Editorial queue competence and separate complete publication coverage.
	 *
	 * @param Article               $article Existing aggregate.
	 * @param string                $operation Approve, reject, return or archive.
	 * @param int                   $version Version affected by the decision.
	 * @param AuthorizationSnapshot $snapshot Current account and grants.
	 * @param array                 $session Current session generation.
	 * @param int                   $now Unix UTC time.
	 */
	public static function canDecide( Article $article, string $operation, int $version, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( $snapshot->user_id === $article->author_id || ! in_array( $operation, array( 'approve', 'reject', 'return', 'archive' ), true ) ) {
			return false;
		}
		if ( ! self::canReviewScope( $article->review_scope, $snapshot, $session, $now ) ) {
			return false;
		}
		if ( 'archive' === $operation ) {
			if ( NewsState::Archived === $article->state || null === $article->published_version || $version !== $article->published_version ) {
				return false;
			}
			$audience = $article->version( $version )->content->audience;
			return null !== $audience && self::allows( Action::PublishNews, $audience->permissionScope(), 0, $article->author_id, $snapshot, $session, $now );
		}
		if ( NewsState::Pending !== $article->state || $version !== $article->submitted_version
			|| ! self::allows( Action::ApproveNews, $article->review_scope, 0, $article->author_id, $snapshot, $session, $now ) ) {
			return false;
		}
		if ( 'approve' !== $operation ) {
			return true;
		}
		$audience = $article->version( $version )->content->audience;
		return null !== $audience && self::allows( Action::PublishNews, $audience->permissionScope(), 0, $article->author_id, $snapshot, $session, $now );
	}

	/** Check a single designated editorial queue.
	 *
	 * @param Scope                 $scope One queue.
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @return bool
	 */
	public static function canReviewScope( Scope $scope, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		return ScopeType::Queues === $scope->type && 1 === count( $scope->targets )
			&& self::allows( Action::ReviewNews, $scope, 0, 0, $snapshot, $session, $now );
	}

	/** Evaluate the existing matrix after checking the session generation.
	 *
	 * @param Action                $action Explicit business action.
	 * @param Scope                 $scope Required scope.
	 * @param int                   $owner_id Record owner.
	 * @param int                   $author_id Record author.
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @return bool
	 */
	private static function allows( Action $action, Scope $scope, int $owner_id, int $author_id, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( ! LocalAccountSessionPolicy::valid( $snapshot, $snapshot->user_id, $session, true ) ) {
			return false;
		}
		$request = new Request( $action, $scope, $owner_id, $author_id, true, true, true, true );
		return ( new AuthorizationPolicy() )->allows( $snapshot->toAccount( true ), $request, $snapshot->policyGrants(), $now );
	}
}
