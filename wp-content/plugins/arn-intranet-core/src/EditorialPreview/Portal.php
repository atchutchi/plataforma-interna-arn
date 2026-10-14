<?php
/**
 * Ligação do portal editorial local ao WordPress.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\EditorialPreview;

use InvalidArgumentException;
use RuntimeException;

// phpcs:disable WordPress.WP.AlternativeFunctions -- A descarga privada usa stream local verificado.

/** Publica o bloco e a descarga somente no laboratório autorizado. */
final class Portal {
	/** Regista os hooks WordPress. */
	public static function register(): void {
		add_action( 'init', array( self::class, 'registerBlock' ) );
		add_action( 'template_redirect', array( self::class, 'download' ), -1 );
		add_action( 'template_redirect', array( self::class, 'privateCache' ), 1 );
	}

	/** Confirma o ambiente, a flag estrita e a capacidade. */
	public static function allowed(): bool {
		return 'local' === wp_get_environment_type()
			&& defined( 'ARN_ALLOW_LOCAL_EDITORIAL_PREVIEW' )
			&& true === ARN_ALLOW_LOCAL_EDITORIAL_PREVIEW
			&& current_user_can( 'manage_options' );
	}

	/**
	 * Obtém a fonte após a guarda.
	 *
	 * @throws RuntimeException Fonte indisponível.
	 */
	private static function source(): Manifest {
		if ( ! self::allowed() || ! defined( 'ARN_LOCAL_EDITORIAL_DIR' ) || ! is_string( ARN_LOCAL_EDITORIAL_DIR ) || '' === ARN_LOCAL_EDITORIAL_DIR ) {
			throw new RuntimeException( 'Fonte editorial indisponível.' );
		}
		$directory     = realpath( ARN_LOCAL_EDITORIAL_DIR );
		$webroot       = realpath( ABSPATH );
		$content       = realpath( WP_CONTENT_DIR );
		$document_root = isset( $_SERVER['DOCUMENT_ROOT'] ) && is_string( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : false;
		if ( false === $directory || is_link( ARN_LOCAL_EDITORIAL_DIR ) || false === $webroot || false === $content ) {
			throw new RuntimeException( 'Fonte editorial indisponível.' );
		}
		foreach ( array( $webroot, $content, $document_root ) as $root ) {
			if ( false !== $root && ( 0 === strcasecmp( $directory, $root ) || str_starts_with( strtolower( $directory ), strtolower( $root . DIRECTORY_SEPARATOR ) ) ) ) {
				throw new RuntimeException( 'Fonte editorial indisponível.' );
			}
		}
		return new Manifest( $directory );
	}

	/** Impede cache de páginas que contenham o bloco privado. */
	public static function privateCache(): void {
		if ( self::allowed() && is_singular() && has_block( 'arn/portal-view', get_post() ) ) {
			nocache_headers();
		}
	}

	/** Regista o bloco mesmo quando a flag está desligada. */
	public static function registerBlock(): void {
		register_block_type(
			'arn/portal-view',
			array(
				'attributes'      => array(
					'view' => array(
						'type'    => 'string',
						'default' => 'home',
					),
				),
				'render_callback' => array( self::class, 'render' ),
			)
		);
	}

	/**
	 * Entrega o modelo ao tema.
	 *
	 * @param array<string,mixed> $attributes Atributos do bloco.
	 * @return string HTML renderizado.
	 */
	public static function render( array $attributes ): string {
		$view = $attributes['view'] ?? 'home';
		if ( ! is_string( $view ) || ! in_array( $view, array( 'home', 'news', 'documents', 'regulations', 'services' ), true ) ) {
			$view = 'home';
		}
		$model = self::model( $view, self::requestInput() );
		ob_start();
		get_template_part( 'portal/view', null, array( 'portal' => $model ) );
		return (string) ob_get_clean();
	}

	/**
	 * Retira as barras acrescentadas pelo WordPress aos parâmetros GET.
	 *
	 * @return array<string,mixed> Parâmetros ainda não validados.
	 */
	public static function requestInput(): array {
		return wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas filtros de leitura.
	}

	/**
	 * Constrói o modelo privado ou o estado vazio genérico.
	 *
	 * @param string              $view Vista.
	 * @param array<string,mixed> $input Parâmetros GET.
	 * @return array<string,mixed> Modelo para o tema.
	 */
	public static function model( string $view, array $input ): array {
		$base = array(
			'view'        => $view,
			'available'   => false,
			'items'       => array(),
			'featured'    => null,
			'regulations' => array(),
			'categories'  => Manifest::CATEGORIES,
			'counts'      => array_fill_keys( array_keys( Manifest::CATEGORIES ), 0 ),
			'query'       => '',
			'category'    => '',
			'year'        => '',
			'page'        => 1,
			'pages'       => 0,
			'total'       => 0,
			'selected'    => null,
		);
		if ( ! self::allowed() ) {
			return $base;
		}
		if ( 'services' === $view ) {
			$base['available'] = true;
			return $base;
		}
		try {
			$query = Selection::query( $input );
			$items = self::source()->items();
		} catch ( InvalidArgumentException | RuntimeException $error ) {
			return $base;
		}
		$base['available'] = true;
		$base['query']     = $query['q'];
		$base['category']  = $query['category'];
		$base['year']      = $query['year'];
		$base['page']      = $query['paged'];
		foreach ( $items as $item ) {
			++$base['counts'][ $item['category'] ];
		}
		$decorate = static function ( array $item ) use ( $query ): array {
			$item['detail_url']   = add_query_arg( Selection::detailQuery( $query, $item['id'] ), get_permalink() );
			$item['download_url'] = add_query_arg( 'arn_document', $item['id'], home_url( '/' ) );
			return $item;
		};
		$filtered = Selection::filter( $items, $view, $query );
		if ( '' !== $query['item'] ) {
			foreach ( $filtered as $item ) {
				if ( $item['id'] === $query['item'] ) {
					$base['selected'] = $decorate( $item );
					break;
				}
			}
		}
		if ( 'home' === $view ) {
			$news                = Selection::filter( $items, 'news', array() );
			$base['items']       = array_map( $decorate, array_slice( $news, 0, 4 ) );
			$base['featured']    = $base['items'][0] ?? null;
			$base['regulations'] = array_map( $decorate, array_slice( Selection::filter( $items, 'regulations', array() ), 0, 3 ) );
			$base['total']       = count( $news );
			$base['pages']       = (int) ceil( $base['total'] / 10 );
			return $base;
		}
		$base['total'] = count( $filtered );
		$base['pages'] = (int) ceil( $base['total'] / 10 );
		$base['items'] = array_map( $decorate, array_slice( $filtered, ( $query['paged'] - 1 ) * 10, 10 ) );
		return $base;
	}

	/** Envia um original apenas após verificação integral. */
	public static function download(): void {
		if ( ! array_key_exists( 'arn_document', $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Descarga apenas de leitura.
			return;
		}
		if ( ! self::allowed() ) {
			self::notFound();
		}
		try {
			$id = $_GET['arn_document']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Identificador validado antes do acesso.
			if ( ! is_string( $id ) || ! preg_match( '/^[a-z0-9][a-z0-9-]{0,79}$/D', $id ) ) {
				self::notFound();
			}
			$source = self::source();
			$found  = null;
			foreach ( $source->items() as $item ) {
				if ( $item['id'] === $id ) {
					$found = $item;
					break;
				}
			}
			if ( null === $found ) {
				self::notFound();
			}
			$file = $source->verifiedFile( $found );
		} catch ( RuntimeException $error ) {
			self::notFound();
		}
		header( 'Content-Type: ' . $file['mime'] );
		header( 'Content-Length: ' . $file['size'] );
		header( 'Content-Disposition: attachment; filename="' . $found['file'] . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: no-store, private' );
		$handle = fopen( $file['path'], 'rb' );
		if ( false === $handle ) {
			self::notFound();
		}
		fpassthru( $handle );
		fclose( $handle );
		exit;
	}

	/** Resposta indistinguível para recusa e ausência. */
	private static function notFound(): never {
		status_header( 404 );
		header( 'Cache-Control: no-store, private' );
		exit;
	}
}
