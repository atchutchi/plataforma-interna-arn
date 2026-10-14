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
		return ! $article->pending() && self::canAuthorAction( Action::CreateNewsDraft, $article->author_id, $snapshot, $session, $now );
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
		if ( $article->pending() ) {
			return false;
		}
		$content = $article->version( count( $article->versions ) )->content;
		return $content->completeForSubmission()
			&& self::canAuthorAction( Action::SubmitNews, $article->author_id, $snapshot, $session, $now );
	}

	/** Author history or only submitted version for a queue editor.
	 *
	 * @param Article               $article Existing article.
	 * @param int                   $version Version requested.
	 * @param AuthorizationSnapshot $snapshot Current snapshot.
	 * @param array                 $session Server session metadata.
	 * @param int                   $now Unix UTC time.
	 * @return bool
	 */
	public static function canRead( Article $article, int $version, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( null === $article->version( $version ) ) {
			return false;
		}
		if ( $snapshot->user_id === $article->author_id ) {
			return self::canAuthorAction( Action::CreateNewsDraft, $article->author_id, $snapshot, $session, $now );
		}
		return $article->pending() && $version === $article->submitted_version
			&& self::canReviewScope( $article->review_scope, $snapshot, $session, $now );
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
