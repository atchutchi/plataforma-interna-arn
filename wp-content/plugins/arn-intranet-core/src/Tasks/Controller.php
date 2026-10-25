<?php
/** Private task HTTP decisions with no storage access for invalid transport.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tasks;

use DomainException;
use InvalidArgumentException;
use Throwable;

// phpcs:disable Squiz.Commenting -- HTTP decisions are exercised separately from WordPress routing.
final class Controller {
	private static ?array $model = null;

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), -25 );
	}

	public static function model(): ?array {
		return self::$model;
	}

	public static function route(): void {
		$post  = get_queried_object();
		$pages = new Pages();
		$view  = $post instanceof \WP_Post ? $pages->viewFor( $post ) : null;
		$uri   = $_SERVER['REQUEST_URI'] ?? '';
		if ( null === $view ) {
			if ( ( $post instanceof \WP_Post && Pages::isReservedSlug( $post->post_name ) ) || ( is_string( $uri ) && Pages::isReservedPath( $uri, home_url( '/' ) ) ) ) {
				self::refuse();
			}
			return;
		}
		self::privateHeaders();
		if ( ! Configuration::current() ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Refuse uploads before source or nonce processing.
			self::refuse();
		}
		$get   = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Strict read allowlist.
		$input = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified before the source in decide.
		if ( isset( $get['page_id'] ) && Input::id( $get['page_id'] ) !== (int) $post->ID ) {
			self::refuse();
		}
		$portal = new Portal();
		$files  = $_FILES; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in decide before inspecting the upload.
		$model  = self::decide( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), $view, $get, $input, $portal, get_current_user_id(), static fn( string $nonce, int $actor, string $surface, string $operation, int $id, string $request ): bool => false !== wp_verify_nonce( $nonce, Input::nonce( $actor, $surface, $operation, $id, $request ) ), $files );
		if ( null === $model ) {
			self::refuse();
		}
		if ( isset( $model['queued_batch'] ) ) {
			\Arn\Intranet\Media\ConsumerPage::redirect( $model['queued_batch'] ); }
		if ( array_key_exists( 'saved_id', $model ) ) {
			$filters = array_intersect_key( $get, array_flip( array( 'q', 'state', 'assignee', 'arn_tasks_page' ) ) );
			$target  = $filters + ( ! empty( $model['saved_notice'] ) ? array( 'notices' => '1' ) : array( 'entry' => (string) $model['saved_id'] ) ) + array( 'saved' => '1' );
			wp_safe_redirect( add_query_arg( $target, get_permalink( $post ) ), 303 );
			exit;
		}
		if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			status_header( 200 );
			header( 'Content-Length: 0' );
			exit;
		}
		self::$model = $model;
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
	public static function decide( string $method, string $view, array $get, array $post, object $source, int $actor, callable $verify_nonce, array $files = array(), ?callable $is_uploaded = null ): ?array {
		$query = Input::query( $view, $get );
		if ( $actor < 1 || ! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true ) || null === $query || ( 'POST' !== $method && array() !== $post ) ) {
			return null;
		}
		$operation = $post['operation'] ?? '';
		if ( ( $files && ( 'POST' !== $method || 'attach' !== $operation ) ) || ( 'attach' === $operation && ! AttachmentConfiguration::enabled() ) ) {
			return null; }
		if ( 'POST' === $method ) {
			if ( ! Input::shape( $post ) || ( 'create' === $query['screen'] ? 'create' !== $operation : ( 'notices' === $query['screen'] ? 'mark-read' !== $operation : ( 'detail' !== $query['screen'] || in_array( $operation, array( 'create', 'mark-read' ), true ) || (int) $post['id'] !== $query['id'] ) ) ) ) {
				return null;
			}
			if ( ! $verify_nonce( $post['nonce'], $actor, $view, $operation, (int) ( $post['id'] ?? 0 ), $post['create_request_id'] ?? '' ) ) {
				return null;
			}
		}
		try {
			$access = $source->access( $view );
			if ( ! is_array( $access ) || ( $access['actor_id'] ?? null ) !== $actor || ( 'create' === $query['screen'] && true !== ( $access['create'] ?? false ) ) ) {
				return null;
			}
			$data = match ( $query['screen'] ) {
				'detail' => $source->detail( $query['id'], $view ),
				'notices' => $source->notices( $query['selection']->page ),
				'create' => array( 'assignees' => $access['assignees'] ?? array() ),
				default => $source->listing( $query['selection'], $view ),
			};
			if ( null === $data ) {
				return null;
			}
			$model = array(
				'view'              => $view,
				'screen'            => $query['screen'],
				'data'              => $data,
				'query'             => $query,
				'access'            => $access,
				'errors'            => array(),
				'input'             => $post,
				'create_request_id' => 'create' === $query['screen'] ? ( $post['create_request_id'] ?? bin2hex( random_bytes( 16 ) ) ) : '',
			);
			if ( 'POST' !== $method ) {
				return $model;
			}
			$model['errors'] = Input::validation( $post );
			if ( $model['errors'] ) {
				return $model;
			}
			try {
				if ( 'mark-read' === $operation ) {
					$source->markRead( (int) $post['id'] );
					return array(
						'saved_id'     => null,
						'saved_notice' => true,
					);
				}
				if ( 'attach' === $operation ) {
					$upload = AttachmentUpload::source( $files, $is_uploaded ?? is_uploaded_file( ... ) );
					if ( null === $upload || ! in_array( 'attach', $data['operations'] ?? array(), true ) ) {
						$model['errors']['form'] = 'Selecciona um ficheiro PDF, PNG ou JPEG válido até 1 MiB. O envio não foi guardado.';
						return $model;
					}
					if ( $source instanceof Portal && \Arn\Intranet\Media\ConsumerJobs::enabled() ) {
						return array( 'queued_batch' => \Arn\Intranet\Media\ConsumerJobs::task( $post, $upload, $view ) ); }
					return array( 'saved_id' => $source->attach( $post, $upload, $view ) );
				}
				$id = 'create' === $operation ? $source->create( $post ) : $source->change( $post );
				return array( 'saved_id' => $id );
			} catch ( DomainException | \RuntimeException | InvalidArgumentException ) {
				if ( 'mark-read' === $operation ) {
					return null;
				}
				$current = $source->access( $view );
				if ( ! is_array( $current ) || ( $current['actor_id'] ?? null ) !== $actor || ( 'create' === $query['screen'] && true !== ( $current['create'] ?? false ) ) ) {
					return null;
				}
				if ( 'detail' === $query['screen'] ) {
					$model['data'] = $source->detail( $query['id'], $view );
					if ( null === $model['data'] ) {
						return null;
					}
				}
				$model['access']         = $current;
				$model['errors']['form'] = 'attach' === $operation ? 'Não foi possível analisar ou guardar o anexo. Actualiza a página e selecciona o ficheiro novamente.' : 'A tarefa foi alterada ou esta operação já não está disponível. Actualiza a página antes de tentar novamente.';
				return $model;
			}
		} catch ( Throwable ) {
			return null;
		}
	}
}
