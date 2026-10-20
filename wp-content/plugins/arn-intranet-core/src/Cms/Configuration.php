<?php
/** Explicit local opt-in for settings administration.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

// phpcs:disable Squiz.Commenting -- Exact boolean switches, without implicit activation.
/** Production activation requires its own validated authority adapter. */
final class Configuration {
	public static function enabled( string $environment, mixed $settings, mixed $fictional, mixed $authorization ): bool {
		return 'local' === $environment && true === $settings && true === $fictional && true === $authorization;
	}

	public static function current(): bool {
		return function_exists( 'wp_get_environment_type' ) && self::enabled(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_CMS_SETTINGS' ) ? ARN_ALLOW_LOCAL_CMS_SETTINGS : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null
		);
	}
}
