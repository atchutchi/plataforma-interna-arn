<?php
/** Server-resolved F1-12 authority context.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

/** Membership and management remain independent proofs. */
final readonly class ActorContext {

	/** Server-only evidence.
	 *
	 * @param AuthorizationSnapshot $snapshot Current authority.
	 * @param array                 $session Native local session metadata.
	 * @param array                 $units Membership IDs.
	 * @param array                 $active_units Current active leaf IDs.
	 */
	public function __construct( public AuthorizationSnapshot $snapshot, public array $session, public array $units, public array $active_units ) {}

	/** Actor identifier.
	 *
	 * @return int
	 */
	public function id(): int {
		return $this->snapshot->user_id;
	}

	/** Fail closed on malformed organisational evidence.
	 *
	 * @param int $now Server clock.
	 * @return bool
	 */
	public function valid( int $now ): bool {
		foreach ( array( $this->units, $this->active_units ) as $ids ) {
			if ( ! array_is_list( $ids ) || count( array_unique( $ids, SORT_REGULAR ) ) !== count( $ids ) ) {
				return false;
			}
			foreach ( $ids as $id ) {
				if ( ! is_string( $id ) || ! in_array( $id, array( 'LAB-A', 'LAB-B' ), true ) ) {
					return false;
				}
			}
		}
		return $now >= 0 && LocalAccountSessionPolicy::valid( $this->snapshot, $this->id(), $this->session, true );
	}

	/** Explicit membership proof only.
	 *
	 * @param Audience $audience Required set.
	 * @param int      $now Clock.
	 * @return bool
	 */
	public function receives( Audience $audience, int $now ): bool {
		return $this->valid( $now ) && ( null === $audience->unit_id || ( in_array( $audience->unit_id, $this->units, true ) && in_array( $audience->unit_id, $this->active_units, true ) ) );
	}

	/** Delegate grant coverage without combining partial grants.
	 *
	 * @param Action $action Operation.
	 * @param Scope  $scope Required scope.
	 * @param int    $owner Known owner.
	 * @param int    $now Clock.
	 * @param int    $author Immutable author.
	 * @param bool   $current Current submitted version proof.
	 * @return bool
	 */
	public function allows( Action $action, Scope $scope, int $owner, int $now, int $author = 0, bool $current = true ): bool {
		return $this->valid( $now ) && ( new AuthorizationPolicy() )->allows( $this->snapshot->toAccount( true ), new Request( $action, $scope, $owner, $author, true, true, true, $current ), $this->snapshot->policyGrants(), $now );
	}
}
