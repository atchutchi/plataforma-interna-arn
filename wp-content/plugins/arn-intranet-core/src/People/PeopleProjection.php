<?php
/** Closed contact models for the presentation boundary.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\People;

/** No grant, session, audit or manager metadata crosses this boundary. */
final class PeopleProjection {
	/** Shared contact fields.
	 *
	 * @param Profile $profile Authorized profile.
	 * @param string  $unit_label Current unit label.
	 */
	public static function contact( Profile $profile, string $unit_label ): array {
		return array(
			'id'      => $profile->user_id,
			'code'    => $profile->professional->code,
			'name'    => $profile->professional->name,
			'email'   => $profile->professional->email,
			'job'     => $profile->professional->job,
			'unit_id' => $profile->professional->unit_id,
			'unit'    => $unit_label,
			'phone'   => $profile->self_service->phone,
			'office'  => $profile->self_service->office,
			'bio'     => $profile->self_service->bio,
		);
	}

	/** Add only the owner's revision token for CAS.
	 *
	 * @param Profile $profile Own authorized profile.
	 * @param string  $unit_label Current unit label.
	 */
	public static function own( Profile $profile, string $unit_label ): array {
		return array_merge( self::contact( $profile, $unit_label ), array( 'revision' => $profile->revision ) );
	}
}
