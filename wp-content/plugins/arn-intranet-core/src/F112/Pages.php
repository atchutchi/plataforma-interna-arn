<?php
/** Fixed native F112 pages.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are covered by the F1-07 plan and tests.
declare(strict_types=1);
namespace Arn\Intranet\F112;

use RuntimeException;
use WP_Post;

/** Fixed native pages, created only by the explicit installer. */
final class Pages {
	private const TITLES = array(
		'communications' => 'Comunicados',
		'calendar'       => 'Calendário',
	);
	private const SLUGS  = array(
		'communications' => 'comunicados',
		'calendar'       => 'calendario',
	);

	public function __construct( private readonly ?string $exclusive_prefix = null ) {
		if ( null !== $exclusive_prefix && ! LocalRoster::validPrefix( $exclusive_prefix ) ) {
			throw new RuntimeException( 'Prefixo F112 inválido.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	public function slug( string $view ): string {
		if ( ! isset( self::SLUGS[ $view ] ) ) {
			throw new RuntimeException( 'Vista F112 inválida.' );
		}
		return null === $this->exclusive_prefix ? self::SLUGS[ $view ] : rtrim( $this->exclusive_prefix, '_' ) . '-' . self::SLUGS[ $view ];
	}

	public static function content( string $view ): string {
		if ( ! isset( self::TITLES[ $view ] ) ) {
			throw new RuntimeException( 'Vista F112 inválida.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return '<!-- wp:arn/f112-view {"module":"' . $view . '"} /-->';
	}

	/** Recognises only reserved destinations under this site's own base path. */
	public static function isReservedPath( string $request_uri, string $site_url ): bool {
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		$base = wp_parse_url( $site_url, PHP_URL_PATH );
		if ( ! is_string( $path ) || ( null !== $base && ! is_string( $base ) ) ) {
			return false;
		}
		$prefix = rtrim( (string) $base, '/' ) . '/';
		if ( ! str_starts_with( $path, $prefix ) ) {
			return false;
		}
		$relative = rawurldecode( substr( $path, strlen( $prefix ) ) );
		if ( str_starts_with( $relative, 'index.php/' ) ) {
			$relative = substr( $relative, strlen( 'index.php/' ) );
		}
		return in_array( rtrim( $relative, '/' ), self::SLUGS, true );
	}

	/** Checks all three destinations before the installer creates any resource. */
	public function preflight( bool $repeat ): array {
		$pages = array();
		foreach ( self::TITLES as $view => $title ) {
			$post = get_page_by_path( $this->slug( $view ), OBJECT, 'page' );
			if ( $repeat ) {
				if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || 'publish' !== $post->post_status || 0 !== (int) $post->post_parent || $title !== $post->post_title || self::content( $view ) !== $post->post_content ) {
					throw new RuntimeException( 'Páginas F112 incompatíveis.' );
				}
				$pages[ $view ] = (int) $post->ID;
			} elseif ( null !== $post ) {
				throw new RuntimeException( 'Página F112 ocupada.' );
			}
		}
		return $pages;
	}

	/** Called only after all other installer checks and roster creation. */
	public function install(): array {
		$pages = array();
		foreach ( self::TITLES as $view => $title ) {
			$id = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_name'    => $this->slug( $view ),
					'post_content' => self::content( $view ),
					'post_parent'  => 0,
				),
				true
			);
			if ( is_wp_error( $id ) || ! is_int( $id ) || $id <= 0 ) {
				throw new RuntimeException( 'Criação de página F112 recusada.' );
			}
			$pages[ $view ] = $id;
		}
		return $pages;
	}

	public function viewFor( ?WP_Post $post ): ?string {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		foreach ( self::TITLES as $view => $title ) {
			if ( 'page' === $post->post_type && 'publish' === $post->post_status && 0 === (int) $post->post_parent && $this->slug( $view ) === $post->post_name && $title === $post->post_title && self::content( $view ) === $post->post_content ) {
				return $view;
			}
		}
		return null;
	}
}
