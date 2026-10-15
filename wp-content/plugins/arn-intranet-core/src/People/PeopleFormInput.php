<?php
/** Exact own-profile form fields.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are covered by the F1-07 plan and tests.
declare(strict_types=1);
namespace Arn\Intranet\People;

use InvalidArgumentException;

/** Exact browser input boundary for editing one's contact fields. */
final class PeopleFormInput {
	public static function parse( array $input ): ?array {
		if ( ! self::shape( $input ) ) {
			return null;
		}
		if ( 1 !== preg_match( '/\A[1-9][0-9]*\z/D', $input['expected_revision'] ) || (string) (int) $input['expected_revision'] !== $input['expected_revision'] || ( str_contains( $input['bio'], "\r" ) && 1 === preg_match( '/\r(?!\n)/', $input['bio'] ) ) ) {
			return null;
		}
		try {
			$service = new SelfService( $input['phone'], $input['office'], str_replace( "\r\n", "\n", $input['bio'] ) );
		} catch ( InvalidArgumentException ) {
			return null;
		}
		return array(
			'nonce'        => $input['nonce'],
			'revision'     => (int) $input['expected_revision'],
			'self_service' => $service,
		);
	}

	public static function shape( array $input ): bool {
		$keys = array_keys( $input );
		sort( $keys );
		$expected = array( 'bio', 'expected_revision', 'nonce', 'office', 'operation', 'phone' );
		if ( $keys !== $expected ) {
			return false;
		}
		foreach ( $input as $value ) {
			if ( ! is_string( $value ) ) {
				return false;
			}
		}
		return 'update_own' === $input['operation'] && '' !== $input['nonce'];
	}

	public static function ownFields( array $input ): array {
		$fields = array();
		foreach ( array( 'phone', 'office', 'bio' ) as $key ) {
			$fields[ $key ] = is_string( $input[ $key ] ?? null ) ? $input[ $key ] : '';
		}
		return $fields;
	}
}
