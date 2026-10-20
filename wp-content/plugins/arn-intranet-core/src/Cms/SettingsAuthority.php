<?php
/** Same-connection authority contract for settings transactions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Identity\IdentityWpdb;

/** Implementations lock account, grants and native session before returning. */
interface SettingsAuthority {
	/** Dedicated connection shared with the repository. */
	public function connection(): IdentityWpdb;

	/** Current proof under caller-owned transaction, or an exception. */
	public function locked(): SettingsActor;
}
