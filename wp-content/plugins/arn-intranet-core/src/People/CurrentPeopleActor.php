<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Current marked fixture and native session. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use Arn\Intranet\Access\LocalFixtureUser;
use WP_Session_Tokens;
use WP_User;

final class CurrentPeopleActor {
	public static function resolve(): ?array {
		if ( ! PeopleConfiguration::current() ) {
			return null;
		}
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		if ( $user_id <= 0 || ! $user instanceof WP_User || $user->ID !== $user_id || '1' !== get_user_meta( $user_id, LocalFixtureUser::META_KEY, true ) || array( 'subscriber' ) !== array_values( $user->roles ) ) {
			return null;
		}
		$token = wp_get_session_token();
		if ( '' === $token ) {
			return null;
		}
		$session = WP_Session_Tokens::get_instance( $user_id )->get( $token );
		return is_array( $session ) ? array( $user_id, $session ) : null;
	}
}
