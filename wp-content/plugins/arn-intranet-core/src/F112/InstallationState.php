<?php
/**
 * Final installation proof, never an authorisation grant.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Server-only completion record.
final class InstallationState {
	public static function optionName( string $prefix ): string {
		return '_arn_f112_complete_' . hash( 'sha256', $prefix );
	}

	public static function decode( string $raw, string $prefix ): ?array {
		try {
			$data = json_decode( $raw, true, 16, JSON_THROW_ON_ERROR );
		} catch ( Throwable ) {
			return null;
		}
		if ( ! is_array( $data ) || 4 !== count( $data ) || array_diff( array_keys( $data ), array( 'version', 'prefix', 'users', 'pages' ) ) || 1 !== ( $data['version'] ?? null ) || ( $data['prefix'] ?? null ) !== $prefix || ! is_array( $data['users'] ?? null ) || ! is_array( $data['pages'] ?? null ) || array_keys( $data['users'] ) !== array_keys( LocalRoster::definitions() ) || array_keys( $data['pages'] ) !== array( 'communications', 'calendar' ) ) {
			return null;
		}
		foreach ( array( $data['users'], $data['pages'] ) as $ids ) {
			if ( count( array_unique( $ids, SORT_REGULAR ) ) !== count( $ids ) ) {
				return null;
			}
			foreach ( $ids as $id ) {
				if ( ! is_int( $id ) || $id < 1 ) {
					return null;
				}
			}
		}
		return $data;
	}

	public static function read( string $prefix ): ?array {
		$raw = get_option( self::optionName( $prefix ), null );
		return is_string( $raw ) ? self::decode( $raw, $prefix ) : null;
	}

	public static function exists( string $prefix ): bool {
		return null !== get_option( self::optionName( $prefix ), null );
	}

	private static function matchesResources( array $state, string $module ): bool {
		global $wpdb;
		$exclusive = $state['prefix'] === $wpdb->prefix ? null : $state['prefix'];
		try {
			$roster = LocalRoster::resolve( $exclusive );
			foreach ( LocalRoster::definitions( $exclusive ) as $label => $person ) {
				$id = $state['users'][ $label ];
				if ( ! isset( $roster[ $id ] ) || $roster[ $id ] !== $person ) {
					return false;
				}
			}
			return ( new Pages( $exclusive ) )->viewFor( get_post( $state['pages'][ $module ] ) ) === $module;
		} catch ( Throwable ) {
			return false;
		}
	}

	public static function ready( string $module, ?string $prefix = null ): bool {
		if ( ! in_array( $module, array( 'communications', 'calendar' ), true ) ) {
			return false;
		}
		global $wpdb;
		$state = self::read( $prefix ?? $wpdb->prefix );
		return null !== $state && self::matchesResources( $state, $module );
	}

	public static function write( string $prefix, array $users, array $pages ): void {
		if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || ! Configuration::current( 'communications' ) || ! Configuration::current( 'calendar' ) || self::exists( $prefix ) ) {
			throw new RuntimeException( 'Conclusão F112 recusada.' );
		}
		$raw   = wp_json_encode(
			array(
				'version' => 1,
				'prefix'  => $prefix,
				'users'   => $users,
				'pages'   => $pages,
			)
		);
		$state = is_string( $raw ) ? self::decode( $raw, $prefix ) : null;
		if ( null === $state || ! self::matchesResources( $state, 'communications' ) || ! self::matchesResources( $state, 'calendar' ) || ! add_option( self::optionName( $prefix ), $raw, '', false ) ) {
			throw new RuntimeException( 'Conclusão F112 recusada.' );
		}
	}
}
