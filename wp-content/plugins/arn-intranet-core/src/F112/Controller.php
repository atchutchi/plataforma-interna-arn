<?php
/**
 * Private F1-12 HTTP boundary.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Throwable;
use WP_Post;

// phpcs:disable Squiz.Commenting -- Controller contract is exercised without a live source.
final class Controller {
	private static ?array $model   = null;
	private static ?string $module = null;

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), -20 );
	}

	public static function decide( string $method, string $module, array $get, array $post, object $source, int $actor, callable $verify_nonce ): ?array {
		$query = Input::query( $module, $get );
		if ( ! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true ) || $actor < 1 || null === $query || ( 'POST' !== $method && array() !== $post ) ) {
			return null;
		}
		if ( 'POST' === $method && ( 'notices' !== $query['view'] || ! Input::shape( $module, $post, false ) || ! $verify_nonce( $post['nonce'], $actor, $module, 'mark-read', (int) $post['id'] ) ) ) {
			return null;
		}
		try {
			if ( ! $source->readable() ) {
				return null;
			}
			if ( 'POST' === $method ) {
				$source->markRead( (int) $post['id'] );
				return array( 'saved' => true );
			}
			$data = match ( $query['view'] ) {
				'detail' => $source->detail( $query['id'] ), 'notices' => $source->notices( $query['selection']->page ), default => $source->listing( $query['selection'] ) };
			return null === $data ? null : array(
				'module' => $module,
				'view'   => $query['view'],
				'data'   => $data,
				'query'  => $query,
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	public static function route(): void {
		$post    = get_queried_object();
		$pages   = new Pages();
		$module  = $post instanceof WP_Post ? $pages->viewFor( $post ) : null;
		$request = $_SERVER['REQUEST_URI'] ?? '';
		if ( null === $module ) {
			if ( ( is_string( $request ) && Pages::isReservedPath( $request, home_url( '/' ) ) ) || ( $post instanceof WP_Post && in_array( $post->post_name, array( 'comunicados', 'calendario' ), true ) ) ) {
				self::refuse();
			}
			return;
		}
		self::privateHeaders();
		if ( ! Configuration::current( $module ) ) {
			self::refuse();
		}
		$get   = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Strict read allowlist.
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in decide.
		if ( isset( $get['page_id'] ) && Input::id( $get['page_id'] ) !== (int) $post->ID ) {
			self::refuse();
		}
		$model = self::decide( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), $module, $get, $input, new Portal( $module ), get_current_user_id(), static fn( string $nonce, int $actor, string $name, string $operation, int $id ): bool => false !== wp_verify_nonce( $nonce, Input::nonce( $actor, $name, $operation, $id ) ) );
		if ( null === $model ) {
			self::refuse();
		}
		if ( ! empty( $model['saved'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'notices' => '1',
						'saved'   => '1',
					),
					get_permalink( $post )
				),
				303
			);
			exit;
		}
		if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			status_header( 200 );
			header( 'Content-Length: 0' );
			exit;
		}
		self::$module = $module;
		self::$model  = $model;
	}

	public static function model( string $module ): ?array {
		return self::$module === $module ? self::$model : null;
	}

	public static function privateHeaders(): void {
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
	}

	public static function refuse(): never {
		self::privateHeaders();
		status_header( 404 );
		header_remove( 'Location' );
		header( 'Content-Length: 0' );
		exit;
	}
}
