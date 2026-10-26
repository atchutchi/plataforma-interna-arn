<?php
/** Server completion record, never an authorisation grant.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Explicit CLI completion and read-only resource checks.
final class InstallationState {
	public static function optionName( string $prefix ): string {
		return '_arn_requests_complete_' . hash( 'sha256', $prefix );
	}

	public static function decode( string $raw, string $prefix ): ?array {
		try {
			$data = json_decode( $raw, true, 16, JSON_THROW_ON_ERROR );
		} catch ( Throwable ) {
			return null;
		}
		if ( ! is_array( $data ) || 4 !== count( $data ) || array_diff( array_keys( $data ), array( 'version', 'prefix', 'users', 'pages' ) ) || 1 !== ( $data['version'] ?? null ) || ( $data['prefix'] ?? null ) !== $prefix || ! is_array( $data['users'] ?? null ) || ! is_array( $data['pages'] ?? null ) || array_keys( $data['users'] ) !== array( 'a', 'b', 'manager', 'deputy' ) || array_keys( $data['pages'] ) !== array( 'own', 'team' ) ) {
			return null;
		}
		foreach ( array( $data['users'], $data['pages'] ) as $ids ) {
			if ( count( $ids ) !== count( array_unique( $ids, SORT_REGULAR ) ) ) {
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

	private static function matchesResources( array $state ): bool {
		global $wpdb;
		$exclusive = $state['prefix'] === $wpdb->prefix ? null : $state['prefix'];
		try {
			$roster = LocalRoster::resolve( $exclusive );
			foreach ( LocalRoster::definitions( $exclusive ) as $label => $person ) {
				if ( ( $roster[ $state['users'][ $label ] ] ?? null ) !== $person ) {
					return false;
				}
			}
			$pages = new Pages( $exclusive );
			foreach ( $state['pages'] as $view => $id ) {
				if ( $pages->viewFor( get_post( $id ) ) !== $view ) {
					return false;
				}
			}
			return true;
		} catch ( Throwable ) {
			return false;
		}
	}

	public static function ready( ?string $prefix = null ): bool {
		if ( ! Configuration::current() ) {
			return false;
		}
		global $wpdb;
		$state = self::read( $prefix ?? $wpdb->prefix );
		return null !== $state && self::matchesResources( $state );
	}

	public static function write( string $prefix, array $users, array $pages ): void {
		if ( ! Configuration::current() || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || self::exists( $prefix ) ) {
			throw new RuntimeException( 'Conclusão de pedidos recusada.' );
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
		if ( null === $state || ! self::matchesResources( $state ) || ! add_option( self::optionName( $prefix ), $raw, '', false ) ) {
			throw new RuntimeException( 'Conclusão de pedidos recusada.' );
		}
	}
}
