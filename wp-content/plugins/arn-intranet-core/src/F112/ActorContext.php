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

	/** Only the detached session proof needed by the business policy.
	 *
	 * @var array
	 */
	public array $session;

	/** Detached personal memberships.
	 *
	 * @var array
	 */
	public array $units;

	/** Detached active leaf unit IDs.
	 *
	 * @var array
	 */
	public array $active_units;

	/** Server-only evidence.
	 *
	 * @param AuthorizationSnapshot $snapshot Current authority.
	 * @param array                 $session Native local session metadata.
	 * @param array                 $units Membership IDs.
	 * @param array                 $active_units Current active leaf IDs.
	 */
	public function __construct( public AuthorizationSnapshot $snapshot, array $session, array $units, array $active_units ) {
		$proof = array();
		foreach ( array( 'arn_authz_local', 'arn_authz_user_id', 'arn_authz_revision', 'expiration' ) as $key ) {
			if ( array_key_exists( $key, $session ) ) {
				$proof[ $key ] = $session[ $key ];
			}
		}
		$unit_copy = array();
		foreach ( $units as $key => $unit ) {
			$unit_copy[ $key ] = $unit;
		}
		$active_copy = array();
		foreach ( $active_units as $key => $unit ) {
			$active_copy[ $key ] = $unit;
		}
		$this->session      = $proof;
		$this->units        = $unit_copy;
		$this->active_units = $active_copy;
	}

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
		return $now >= 0 && $this->unitsValid()
			&& ( ! array_key_exists( 'expiration', $this->session ) || ( is_int( $this->session['expiration'] ) && $now < $this->session['expiration'] ) )
			&& LocalAccountSessionPolicy::valid( $this->snapshot, $this->id(), $this->session, true );
	}

	/** Validate server memberships without inventing session evidence.
	 *
	 * @return bool
	 */
	private function unitsValid(): bool {
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
		return array() === $this->units || array() !== array_intersect( $this->units, $this->active_units );
	}

	/** Offline notice delivery eligibility only, never an authenticated route proof.
	 *
	 * @param Audience $audience Publication audience.
	 * @param int      $now Clock.
	 * @return bool
	 */
	public function canReceive( Audience $audience, int $now ): bool {
		if ( $now < 0 || ! $this->unitsValid() || ( null !== $audience->unit_id && ( ! in_array( $audience->unit_id, $this->units, true ) || ! in_array( $audience->unit_id, $this->active_units, true ) ) ) ) {
			return false;
		}
		return ( new AuthorizationPolicy() )->allows( $this->snapshot->toAccount( true ), new Request( Action::ReadContent, Scope::own(), $this->id(), 0, true, true, true, true ), $this->snapshot->policyGrants(), $now );
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
