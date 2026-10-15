<?php
/** Transactional editorial authority contract.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment

/** Only server generated locked contexts may enter these methods. */
interface EditorialAuthority {
	/** Returns actor_id and a recipient ID => notice kind map. */
	public function authorizeDecision( Article $article, int $version, string $operation, array $locked ): array;
	public function authorizeNotice( Article $article, object $notice, array $locked ): bool;
}
