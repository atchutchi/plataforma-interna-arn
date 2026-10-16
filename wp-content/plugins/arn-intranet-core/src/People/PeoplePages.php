<?php
/** Fixed native People pages.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are covered by the F1-07 plan and tests.
declare(strict_types=1);
namespace Arn\Intranet\People;

use RuntimeException;
use WP_Post;

/** Fixed native pages, created only by the explicit installer. */
final class PeoplePages {
	private const TITLES = array(
		'directory' => 'Funcionários',
		'profile'   => 'Meu perfil',
		'team'      => 'A minha equipa',
	);
	private const SLUGS  = array(
		'directory' => 'funcionarios',
		'profile'   => 'meu-perfil',
		'team'      => 'minha-equipa',
	);

	public function __construct( private readonly ?string $exclusive_prefix = null ) {}

	public function slug( string $view ): string {
		if ( ! isset( self::SLUGS[ $view ] ) ) {
			throw new RuntimeException( 'Vista People inválida.' );
		}
		return null === $this->exclusive_prefix ? self::SLUGS[ $view ] : rtrim( $this->exclusive_prefix, '_' ) . '-' . self::SLUGS[ $view ];
	}

	public static function content( string $view ): string {
		return '<!-- wp:arn/people-view {"view":"' . $view . '"} /-->';
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
					throw new RuntimeException( 'Páginas People incompatíveis.' );
				}
				$pages[ $view ] = (int) $post->ID;
			} elseif ( null !== $post ) {
				throw new RuntimeException( 'Página People ocupada.' );
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
				throw new RuntimeException( 'Criação de página People recusada.' );
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
