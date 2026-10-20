<?php
/** Fixed task pages contain only a dynamic marker.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use RuntimeException;
use WP_Post;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exact native page contracts and CLI-only creation.
final class Pages {
	private const TITLES = array(
		'own'  => 'Minhas tarefas',
		'team' => 'Tarefas da equipa',
	);
	private const SLUGS  = array(
		'own'  => 'minhas-tarefas',
		'team' => 'tarefas-da-equipa',
	);

	public function __construct( private readonly ?string $exclusive_prefix = null ) {
		if ( null !== $exclusive_prefix && ! LocalRoster::validPrefix( $exclusive_prefix ) ) {
			throw new RuntimeException( 'Prefixo de páginas de tarefas inválido.' );
		}
	}

	public function slug( string $view ): string {
		if ( ! isset( self::SLUGS[ $view ] ) ) {
			throw new RuntimeException( 'Vista de tarefas inválida.' );
		}
		return null === $this->exclusive_prefix ? self::SLUGS[ $view ] : rtrim( $this->exclusive_prefix, '_' ) . '-' . self::SLUGS[ $view ];
	}

	public static function content( string $view ): string {
		if ( ! isset( self::TITLES[ $view ] ) ) {
			throw new RuntimeException( 'Vista de tarefas inválida.' );
		}
		return '<!-- wp:arn/tasks-view {"view":"' . $view . '"} /-->';
	}

	public static function isReservedSlug( string $slug ): bool {
		return in_array( $slug, self::SLUGS, true );
	}

	public static function isReservedPath( string $uri, string $site ): bool {
		$path = wp_parse_url( $uri, PHP_URL_PATH );
		$base = wp_parse_url( $site, PHP_URL_PATH );
		if ( ! is_string( $path ) || ( null !== $base && ! is_string( $base ) ) ) {
			return false;
		}
		$prefix = rtrim( (string) $base, '/' ) . '/';
		if ( ! str_starts_with( $path, $prefix ) ) {
			return false;
		}
		$relative = rawurldecode( substr( $path, strlen( $prefix ) ) );
		if ( str_starts_with( $relative, 'index.php/' ) ) {
			$relative = substr( $relative, 10 );
		}
		return in_array( rtrim( $relative, '/' ), self::SLUGS, true );
	}

	public function preflight( bool $repeat ): array {
		$ids = array();
		foreach ( self::TITLES as $view => $title ) {
			$post = get_page_by_path( $this->slug( $view ), OBJECT, 'page' );
			if ( $repeat ) {
				if ( ! $post instanceof WP_Post || $this->viewFor( $post ) !== $view ) {
					throw new RuntimeException( 'Páginas de tarefas incompatíveis.' );
				}
				$ids[ $view ] = (int) $post->ID;
			} elseif ( null !== $post ) {
				throw new RuntimeException( 'Página de tarefas ocupada.' );
			}
		}
		return $ids;
	}

	public function install(): array {
		if ( ! Configuration::current() || ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
			throw new RuntimeException( 'Instalação de páginas de tarefas recusada.' );
		}
		// Infrastructure pages must not consume the site's first content publication.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress filter signature.
		$preserve = static fn( mixed $proposed, mixed $previous ): mixed => $previous;
		add_filter( 'pre_update_option_fresh_site', $preserve, PHP_INT_MAX, 2 );
		try {
			$result = array();
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
				if ( ! is_int( $id ) || $id < 1 ) {
					throw new RuntimeException( 'Criação de página de tarefas recusada.' );
				}
				$result[ $view ] = $id;
			}
			return $result;
		} finally {
			remove_filter( 'pre_update_option_fresh_site', $preserve, PHP_INT_MAX );
		}
	}

	public function viewFor( ?WP_Post $post ): ?string {
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		foreach ( self::TITLES as $view => $title ) {
			if ( 'page' === $post->post_type && 'publish' === $post->post_status && 0 === (int) $post->post_parent && $title === $post->post_title && $this->slug( $view ) === $post->post_name && self::content( $view ) === $post->post_content ) {
				return $view;
			}
		}
		return null;
	}
}
