<?php
/** Pure access policy for the local document catalogue.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\ScopeType;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** No administrator or scope bypasses the business policy. */
final class DocumentPolicy {
	/** Check management of an existing document.
	 *
	 * @param Document              $doc      Persisted aggregate.
	 * @param AuthorizationSnapshot $snapshot Current account snapshot.
	 * @param array                 $session  Server session metadata.
	 * @param int                   $now      Server Unix time.
	 * @return bool
	 */
	public static function canManage( Document $doc, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		return self::canManageScope( $doc->management_scope, $snapshot, $session, $now );
	}

	/** Check management before a document exists, using the chosen persisted scope.
	 *
	 * @param Scope                 $scope    Intended management scope.
	 * @param AuthorizationSnapshot $snapshot Current account snapshot.
	 * @param array                 $session  Server session metadata.
	 * @param int                   $now      Server Unix time.
	 * @return bool
	 */
	public static function canManageScope( Scope $scope, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( ! in_array( $scope->type, array( ScopeType::Units, ScopeType::Institutional ), true ) || ! LocalAccountSessionPolicy::valid( $snapshot, $snapshot->user_id, $session, true ) ) {
			return false;
		}
		$request = new Request( Action::ManageDocuments, $scope, 0, 0, true, true, true, true );
		return ( new AuthorizationPolicy() )->allows( $snapshot->toAccount( true ), $request, $snapshot->policyGrants(), $now );
	}

	/** Read a published current version or management history.
	 *
	 * @param Document              $doc      Persisted aggregate.
	 * @param int                   $number   Version number.
	 * @param AuthorizationSnapshot $snapshot Current account snapshot.
	 * @param array                 $session  Server session metadata.
	 * @param int                   $now      Server Unix time.
	 * @return bool
	 */
	public static function canRead( Document $doc, int $number, AuthorizationSnapshot $snapshot, array $session, int $now ): bool {
		if ( ! LocalAccountSessionPolicy::valid( $snapshot, $snapshot->user_id, $session, true ) ) {
			return false;
		}
		if ( self::canManage( $doc, $snapshot, $session, $now ) ) {
			return null !== $doc->version( $number );
		}
		$version = $doc->version( $number );
		if ( $doc->archived || null === $version || $number !== $doc->published_version || ! in_array( $snapshot->user_id, $version->metadata->recipient_ids, true ) ) {
			return false;
		}
		$request = new Request( Action::ReadContent, Scope::own(), $snapshot->user_id, 0, true, true, true, true );
		return ( new AuthorizationPolicy() )->allows( $snapshot->toAccount( true ), $request, $snapshot->policyGrants(), $now );
	}
}
