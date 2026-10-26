<?php
/** Independent opt-in for the fictional task module.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

// phpcs:disable Squiz.Commenting -- Strict switch matrix is documented in the F1-13 plan.
/** Never open tasks by borrowing a communication or calendar flag. */
final class Configuration {
	public static function enabled( string $environment, mixed $module, mixed $fictional, mixed $authorization, mixed $organization ): bool {
		return 'local' === $environment && true === $module && true === $fictional && true === $authorization && true === $organization;
	}

	public static function current(): bool {
		return function_exists( 'wp_get_environment_type' ) && ( ! function_exists( 'is_multisite' ) || ! is_multisite() ) && self::enabled(
			wp_get_environment_type(),
			defined( 'ARN_ALLOW_LOCAL_REQUESTS' ) ? ARN_ALLOW_LOCAL_REQUESTS : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
			defined( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE : null
		);
	}
}
