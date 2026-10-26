<?php
/** Private native pages and closed request transport.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.NonceVerification -- Nonce verification occurs in decide before any mutation or upload.
final class Controller {
	private static ?array $model = null;
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), -25 );
	}
	public static function model(): ?array {
		return self::$model;
	}
	public static function privateHeaders(): void {
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'Pragma: no-cache' );
		header( 'X-Content-Type-Options: nosniff' );
	}
	public static function refuse(): never {
		self::privateHeaders();
		status_header( 404 );
		header_remove( 'Location' );
		header( 'Content-Length: 0' );
		exit;
	}
	public static function route(): void {
		$post = get_queried_object();
		$view = $post instanceof \WP_Post ? ( new Pages() )->viewFor( $post ) : null;
		$uri  = $_SERVER['REQUEST_URI'] ?? '';
		if ( null === $view ) {
			if ( ( $post instanceof \WP_Post && Pages::isReservedSlug( $post->post_name ) ) || ( is_string( $uri ) && Pages::isReservedPath( $uri, home_url( '/' ) ) ) ) {
				self::refuse();
			}
			return;
		}
		self::privateHeaders();
		$get = wp_unslash( $_GET );
		if ( ! Configuration::current() || ( isset( $get['page_id'] ) && Input::id( $get['page_id'] ) !== (int) $post->ID ) ) {
			self::refuse();
		}
		$model = self::decide( $_SERVER['REQUEST_METHOD'] ?? '', $view, $get, wp_unslash( $_POST ), new Portal(), get_current_user_id(), static fn( string $nonce, string $action ): bool => false !== wp_verify_nonce( $nonce, $action ), $_FILES );
		if ( null === $model ) {
			self::refuse();
		}
		if ( isset( $model['queued'] ) ) {
			\Arn\Intranet\Media\ConsumerPage::redirect( $model['queued'] );
		}
		if ( isset( $model['saved'] ) ) {
			$target = match ( $model['operation'] ) {
				'type' => array( 'types' => '1' ), 'read' => array( 'notices' => '1' ), default => array( 'entry' => (string) $model['saved'] ) };
			wp_safe_redirect( add_query_arg( $target + array( 'saved' => '1' ), get_permalink( $post ) ), 303 );
			exit;
		}
		if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			header( 'Content-Length: 0' );
			exit;
		}
		self::$model = $model;
	}
	public static function decide( string $method, string $view, array $get, array $post, object $source, int $actor, callable $verify, array $files = array(), ?callable $uploaded = null ): ?array {
		$query = Input::query( $get );
		if ( $actor < 1 || ! in_array( $view, array( 'own', 'team' ), true ) || ! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true ) || null === $query || ( 'POST' !== $method && ( $post || $files ) ) || ( 'new' === $query['screen'] && 'own' !== $view ) || ( 'types' === $query['screen'] && 'team' !== $view ) ) {
			return null;
		}
		$input = array();
		if ( 'POST' === $method ) {
			try {
				$input = Input::post( $post );
			} catch ( Throwable ) {
				return null;
			}
			$op       = $input['operation'];
			$matching = match ( $query['screen'] ) {
				'new' => 'create' === $op, 'types' => 'type' === $op, 'notices' => 'read' === $op, 'detail' => ! in_array( $op, array( 'create', 'type', 'read' ), true ) && $input['id'] === $query['id'], default => false };
			if ( ! $matching || ! $verify( $input['nonce'], Input::nonce( $actor, $view, $op, $input['key'] ) ) || ( $files && 'attach' !== $op ) || ( 'attach' === $op && ( ! AttachmentConfiguration::enabled() || ! \Arn\Intranet\Media\ConsumerJobs::enabled() ) ) ) {
				return null;
			}
		}
		try {
			$access = $source->access( $view );
			if ( null === $access || $access['actor'] !== $actor || ( 'types' === $query['screen'] && ! $access['configure'] ) ) {
				return null;
			}
			$listing = $source->listing( $view, $query['q'], $query['state'], $query['page'] );
			if ( null === $listing ) {
				return null;
			}
			$detail = 'detail' === $query['screen'] ? $source->detail( $query['id'], $view ) : null;
			if ( 'detail' === $query['screen'] && null === $detail ) {
				return null;
			}
			$model = array(
				'view'    => $view,
				'query'   => $query,
				'access'  => $access,
				'listing' => $listing,
				'detail'  => $detail,
				'notices' => 'notices' === $query['screen'] ? $source->notices() : array(),
				'error'   => '',
				'input'   => array(),
			);
			if ( 'POST' === $method ) {
				if ( null !== $detail && ! in_array( $input['operation'], 'own' === $view ? array( 'edit', 'submit', 'withdraw', 'attach' ) : array( 'start', 'return', 'approve', 'reject', 'cancel', 'substitute', 'revoke_substitute' ), true ) ) {
					return null;
				}
				try {
					if ( 'attach' === $input['operation'] ) {
						$path = \Arn\Intranet\Tasks\AttachmentUpload::source( $files, $uploaded ?? is_uploaded_file( ... ) );
						if ( null === $path ) {
							throw new \RuntimeException( 'Anexo inválido.' );
						}
						return array( 'queued' => \Arn\Intranet\Media\ConsumerJobs::request( $post, $path, $view ) );
					}
					return array(
						'saved'     => $source->mutate( $input ),
						'operation' => $input['operation'],
					);
				} catch ( Throwable ) {
					$model['error'] = 'Não foi possível confirmar a alteração. O pedido pode ter mudado. Consulta o histórico e recarrega antes de repetir.';
					$model['input'] = array_intersect_key( $post, array_flip( array( 'title', 'body', 'reason', 'type_name', 'instructions', 'label' ) ) );
				}
			}
			return $model;
		} catch ( Throwable ) {
			return null;
		}
	}
}
