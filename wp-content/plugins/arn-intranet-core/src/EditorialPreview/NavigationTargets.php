<?php
/**
 * Closed module routes. Recognition grants no permission.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\EditorialPreview;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure URL recognition without WordPress.
final class NavigationTargets {
	public const SLUGS = array(
		'directory'      => 'funcionarios',
		'profile'        => 'meu-perfil',
		'team'           => 'minha-equipa',
		'communications' => 'comunicados',
		'calendar'       => 'calendario',
	);
	private readonly array $site;
	public function __construct( string $site, private readonly array $page_ids ) {
		$parts      = parse_url( $site );
		$this->site = is_array( $parts ) ? $parts : array();
	}

	public function identify( array $attributes ): ?string {
		$url = $attributes['url'] ?? '';
		if ( '' === $url && 'page' === ( $attributes['type'] ?? null ) && is_int( $attributes['id'] ?? null ) ) {
			return $this->page( $attributes['id'] );
		}
		if ( ! is_string( $url ) || '' === $url || str_starts_with( $url, '#' ) || preg_match( '/[\x00-\x20\x7f\\\\]/', $url ) ) {
			return null;
		}
		$parts = parse_url( $url );
		if ( ! is_array( $parts ) || ! isset( $this->site['host'] ) ) {
			return null;
		}
		$scheme = strtolower( $parts['scheme'] ?? $this->site['scheme'] ?? '' );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || strtolower( $this->site['scheme'] ?? '' ) !== $scheme ) {
			return null;
		}
		if ( isset( $parts['host'] ) && ( strtolower( $parts['host'] ) !== strtolower( $this->site['host'] ) || ( $parts['port'] ?? ( 'https' === $scheme ? 443 : 80 ) ) !== ( $this->site['port'] ?? ( 'https' === $scheme ? 443 : 80 ) ) ) ) {
			return null;
		}
		$base = rtrim( $this->site['path'] ?? '', '/' ) . '/';
		$path = self::normalPath( $parts['path'] ?? $base );
		if ( ! str_starts_with( $path, $base ) ) {
			return null;
		}
		$relative = trim( rawurldecode( substr( $path, strlen( $base ) ) ), '/' );
		if ( str_starts_with( $relative, 'index.php/' ) ) {
			$relative = substr( $relative, 10 );
		}
		parse_str( $parts['query'] ?? '', $query );
		if ( is_string( $query['page_id'] ?? null ) && ctype_digit( $query['page_id'] ) ) {
			return $this->page( (int) $query['page_id'] );
		}
		if ( is_string( $query['pagename'] ?? null ) ) {
			$relative = trim( $query['pagename'], '/' );
		}
		$view = array_search( $relative, self::SLUGS, true );
		return false === $view ? null : $view;
	}

	private function page( int $page_id ): ?string {
		foreach ( $this->page_ids as $view => $id ) {
			if ( $page_id > 0 && $page_id === $id && isset( self::SLUGS[ $view ] ) ) {
				return $view;
			}
		}
		return null;
	}

	/** Browsers resolve dot segments, including percent-encoded dots, before sending a path. */
	private static function normalPath( string $path ): string {
		if ( ! str_starts_with( $path, '/' ) ) {
			return $path;
		}
		$segments = array();
		$last_dot = false;
		foreach ( explode( '/', $path ) as $index => $segment ) {
			if ( 0 === $index && '' === $segment ) {
				continue;
			}
			$dot      = preg_replace( '/%2e/i', '.', $segment );
			$last_dot = '.' === $dot || '..' === $dot;
			if ( '.' === $dot ) {
				continue;
			}
			if ( '..' === $dot ) {
				array_pop( $segments );
				continue;
			}
			$segments[] = $segment;
		}
		if ( $last_dot ) {
			$segments[] = '';
		}
		return '/' . implode( '/', $segments );
	}
}
