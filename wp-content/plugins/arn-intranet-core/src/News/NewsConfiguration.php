<?php
/** Exact runtime gates for fictitious local news.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

// phpcs:disable Squiz.Commenting.FunctionComment

/** Exact local runtime gate. */
final class NewsConfiguration {
	public static function enabled( string $environment, mixed $newsroom, mixed $files, mixed $fictional, mixed $authorization ): bool {
		return 'local' === $environment && true === $newsroom && true === $files && true === $fictional && true === $authorization;
	}

	public static function configured(): bool {
		return self::enabled(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_NEWSROOM' ) ? ARN_ALLOW_LOCAL_NEWSROOM : null,
			defined( 'ARN_ALLOW_LOCAL_PRIVATE_FILES' ) ? ARN_ALLOW_LOCAL_PRIVATE_FILES : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null
		);
	}
}
