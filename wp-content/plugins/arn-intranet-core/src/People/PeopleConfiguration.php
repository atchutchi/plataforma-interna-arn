<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Effective local gates for fictional People data. */
declare(strict_types=1);
namespace Arn\Intranet\People;

final class PeopleConfiguration {
	public static function enabled( string $environment, mixed $people, mixed $fictional, mixed $authorization, mixed $organization ): bool {
		return 'local' === $environment && true === $people && true === $fictional && true === $authorization && true === $organization;
	}

	public static function current(): bool {
		return self::enabled(
			function_exists( 'wp_get_environment_type' ) ? wp_get_environment_type() : '',
			defined( 'ARN_ALLOW_LOCAL_PEOPLE' ) ? ARN_ALLOW_LOCAL_PEOPLE : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
			defined( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE : null
		);
	}
}
