<?php
/** Independent local-only module switches.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

/** Never interpret truthy configuration as an explicit opt-in. */
final class Configuration {

	/** Pure gate before any data source.
	 *
	 * @param string $environment Effective WordPress environment.
	 * @param mixed  $module Module opt-in.
	 * @param mixed  $fictional Fictional access opt-in.
	 * @param mixed  $authorization Persistent authorization opt-in.
	 * @param mixed  $organization Unit storage opt-in.
	 * @return bool
	 */
	public static function enabled( string $environment, mixed $module, mixed $fictional, mixed $authorization, mixed $organization ): bool {
		return 'local' === $environment && true === $module && true === $fictional && true === $authorization && true === $organization;
	}

	/** A closed module name resolves one independent flag.
	 *
	 * @param string $module communications/calendar.
	 * @return bool
	 */
	public static function current( string $module ): bool {
		$flag = match ( $module ) {
			'communications' => 'ARN_ALLOW_LOCAL_COMMUNICATIONS',
			'calendar' => 'ARN_ALLOW_LOCAL_CALENDAR',
			default => null,
		};
		return null !== $flag && function_exists( 'wp_get_environment_type' ) && self::enabled(
			wp_get_environment_type(),
			defined( $flag ) ? constant( $flag ) : null,
			defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ? ARN_ALLOW_FICTIONAL_LOCAL_ACCESS : null,
			defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE : null,
			defined( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) ? ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE : null
		);
	}
}
