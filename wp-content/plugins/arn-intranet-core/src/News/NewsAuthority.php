<?php
/** Transactional authority contract for local news.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;

// phpcs:disable Squiz.Commenting.FunctionComment

/** Locks and checks the server actor for one operation. */
interface NewsAuthority {
	/** Returns actor_id and sorted eligible reviewer_ids after locking all accounts. */
	public function lockAndAssert( int $author_id, Scope $review_scope, string $operation ): array;
	/** Recheck the locked upload context without opening another account reader. */
	public function assertUploadCurrent(): void;
}
