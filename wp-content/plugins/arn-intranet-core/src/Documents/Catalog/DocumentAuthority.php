<?php
/**
 * Transactional authority contract.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Authorization\Scope;

// phpcs:disable Squiz.Commenting.FunctionComment

/** The repository obtains the actor only from this server-side authority. */
interface DocumentAuthority {
	/** Lock account rows before the document and return the authenticated actor. */
	public function lockAndAssert( Scope $scope, array $recipient_ids ): int;
	/** Recheck the locked upload context without opening another account reader. */
	public function assertUploadCurrent(): void;

	/** Require each already locked recipient to be active before publication. */
	public function assertActiveRecipients( array $recipient_ids ): void;
}
