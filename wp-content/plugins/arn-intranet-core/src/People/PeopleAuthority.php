<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Server-side identity and prerequisite snapshots. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Organization\Storage\LocalUnitSnapshot;

interface PeopleAuthority {
	/** Returns [user_id, session], from the server only. */
	public function actor(): ?array;
	public function account( int $user_id, bool $in_transaction = false ): ?AuthorizationSnapshot;
	public function units( bool $in_transaction = false ): LocalUnitSnapshot;
}
