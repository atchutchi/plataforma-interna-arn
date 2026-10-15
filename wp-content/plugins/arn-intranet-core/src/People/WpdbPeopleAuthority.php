<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Account and unit snapshots on one dedicated connection. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\LocalUnitSnapshot;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Closure;

final class WpdbPeopleAuthority implements PeopleAuthority {
	private readonly WpdbAuthorizationRepository $accounts;
	private readonly WpdbLocalUnitRepository $units;
	private readonly Closure $actor_resolver;

	public function __construct( private readonly IdentityWpdb $database, ?Closure $actor_resolver = null ) {
		$this->accounts       = new WpdbAuthorizationRepository( $database );
		$this->units          = new WpdbLocalUnitRepository( $database );
		$this->actor_resolver = $actor_resolver ?? CurrentPeopleActor::resolve( ... );
	}

	public function connection(): IdentityWpdb {
		return $this->database;
	}

	public function actor(): ?array {
		$value = ( $this->actor_resolver )();
		return is_array( $value ) && array_is_list( $value ) && 2 === count( $value ) && is_int( $value[0] ) && $value[0] > 0 && is_array( $value[1] ) ? $value : null;
	}

	public function account( int $user_id, bool $in_transaction = false ): ?AuthorizationSnapshot {
		return $in_transaction ? $this->accounts->findInTransaction( $user_id ) : $this->accounts->find( $user_id );
	}

	public function units( bool $in_transaction = false ): LocalUnitSnapshot {
		return $in_transaction ? $this->units->snapshotInTransaction() : $this->units->snapshot();
	}
}
