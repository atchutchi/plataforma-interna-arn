<?php
/** Private People HTTP boundary.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are covered by the F1-07 plan and tests.
declare(strict_types=1);
namespace Arn\Intranet\People;

use RuntimeException;
use WP_Post;

/** Private HTTP boundary. All rejected People routes terminate before login redirects. */
final class PeopleController {
	private static ?array $model = null;
	private static ?string $view = null;

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), -10 );
	}

	public static function route(): void {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post || 'page' !== $post->post_type || ! in_array( $post->post_name, array( 'funcionarios', 'meu-perfil', 'minha-equipa' ), true ) ) {
			return;
		}
		self::privateHeaders();
		$pages = new PeoplePages();
		$view  = $pages->viewFor( $post );
		if ( null === $view || ! PeopleConfiguration::current() ) {
			self::refuse();
		}
		// WordPress slashes request arrays once. Validation sees exactly one unslashed copy.
		$get   = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read boundary only.
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.
		$model = self::decide( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), $view, $get, $input, new LocalPeopleDirectory(), get_current_user_id(), static fn( string $nonce, int $actor ): bool => 1 === wp_verify_nonce( $nonce, 'arn_people_update_' . $actor ) );
		if ( null === $model ) {
			self::refuse();
		}
		if ( ! empty( $model['saved'] ) ) {
			wp_safe_redirect( add_query_arg( 'saved', '1', get_permalink( $post ) ), 303 );
			exit;
		}
		if ( 'profile' === $view ) {
			$model['nonce'] = wp_create_nonce( 'arn_people_update_' . get_current_user_id() );
		}
		self::$view  = $view;
		self::$model = $model;
	}

	/** Pure controller decision, suitable for a limited loopback HTTP harness. */
	public static function decide( string $method, string $view, array $get, array $post, object $source, int $actor, callable $verify_nonce ): ?array {
		if ( ! in_array( $view, array( 'directory', 'profile', 'team' ), true ) || ! in_array( $method, array( 'GET', 'POST' ), true ) ) {
			return null;
		}
		if ( 'POST' === $method ) {
			if ( 'profile' !== $view || $actor <= 0 || array_diff( array_keys( $get ), array( 'page_id' ) ) || ! PeopleFormInput::shape( $post ) || ! $verify_nonce( $post['nonce'], $actor ) ) {
				return null;
			}
			$own = $source->own();
			if ( null === $own ) {
				return null;
			}
			$parsed = PeopleFormInput::parse( $post );
			if ( null === $parsed ) {
				return self::profileModel( $own, $source->ownHistory(), PeopleFormInput::ownFields( $post ), 'Verifica os três campos de contacto e a revisão antes de guardar.' );
			}
			try {
				$source->updateOwn( $parsed['self_service'], $parsed['revision'] );
				return array(
					'view'  => 'profile',
					'saved' => true,
				);
			} catch ( RuntimeException ) {
				$own = $source->own();
				return null === $own ? null : self::profileModel( $own, $source->ownHistory(), PeopleFormInput::ownFields( $post ), 'O perfil foi alterado entretanto. Revê os dados e volta a guardar.' );
			}
		}
		$allowed = array(
			'directory' => array( 'q', 'unit', 'job', 'arn_people_page', 'person', 'page_id' ),
			'profile'   => array( 'saved', 'page_id' ),
			'team'      => array( 'q', 'unit', 'job', 'arn_people_page', 'page_id' ),
		);
		if ( array_diff( array_keys( $get ), $allowed[ $view ] ) || array() !== $post ) {
			return null;
		}
		if ( 'profile' === $view ) {
			if ( array_key_exists( 'saved', $get ) && '1' !== $get['saved'] ) {
				return null;
			}
			$own = $source->own();
			return null === $own ? null : self::profileModel(
				$own,
				$source->ownHistory(),
				array(
					'phone'  => $own['phone'],
					'office' => $own['office'],
					'bio'    => $own['bio'],
				),
				null,
				array_key_exists( 'saved', $get )
			);
		}
		if ( array_key_exists( 'person', $get ) ) {
			$id = $get['person'];
			if ( 'directory' !== $view || ! is_string( $id ) || 1 !== preg_match( '/\A[1-9][0-9]*\z/D', $id ) || (string) (int) $id !== $id || count( array_diff( array_keys( $get ), array( 'person', 'page_id' ) ) ) ) {
				return null;
			}
			$contact = $source->contact( (int) $id );
			return null === $contact ? null : array(
				'view'    => 'contact',
				'contact' => $contact,
			);
		}
		$query = PeopleQuery::parse( $get );
		if ( null === $query ) {
			return null;
		}
		$list = 'team' === $view ? $source->team( $query ) : $source->contactList( $query );
		return null === $list ? null : array(
			'view'  => $view,
			'list'  => $list,
			'query' => $query,
		);
	}

	private static function profileModel( array $own, ?array $history, array $fields, ?string $error, bool $confirmation = false ): ?array {
		return null === $history ? null : array(
			'view'         => 'profile',
			'own'          => $own,
			'history'      => $history,
			'fields'       => $fields,
			'error'        => $error,
			'confirmation' => $confirmation,
		);
	}

	public static function model( string $view ): ?array {
		return self::$view === $view ? self::$model : null;
	}

	public static function privateHeaders(): void {
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
	}

	public static function refuse(): never {
		self::privateHeaders();
		status_header( 404 );
		header( 'Content-Length: 0' );
		exit;
	}
}
