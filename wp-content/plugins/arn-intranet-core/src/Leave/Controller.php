<?php
/** Private native leave pages and CSRF-bound actions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

use Throwable;
// phpcs:disable Squiz.Commenting, WordPress.Security.NonceVerification -- decide verifies the complete action nonce before mutation.
final class Controller {
	private static ?array $model = null;
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), -25 );}
	public static function model(): ?array {
		return self::$model;}
	public static function route(): void {
		$post = get_queried_object();
		$view = $post instanceof \WP_Post ? ( new Pages() )->viewFor( $post ) : null;
		$uri  = $_SERVER['REQUEST_URI'] ?? '';
		if ( null === $view ) {
			if ( ( $post instanceof \WP_Post && Pages::isReservedSlug( $post->post_name ) ) || ( is_string( $uri ) && Pages::isReservedPath( $uri, home_url( '/' ) ) ) ) {
				\Arn\Intranet\Requests\Controller::refuse();
			}return;}
		\Arn\Intranet\Requests\Controller::privateHeaders();
		$get = wp_unslash( $_GET );
		if ( ! Configuration::current() || ( isset( $get['page_id'] ) && Input::id( $get['page_id'] ) !== (int) $post->ID ) ) {
			\Arn\Intranet\Requests\Controller::refuse();
		}
		$model = self::decide( $_SERVER['REQUEST_METHOD'] ?? '', $view, $get, wp_unslash( $_POST ), new Portal(), get_current_user_id(), static fn( string $nonce, string $action ): bool=>false !== wp_verify_nonce( $nonce, $action ), $_FILES );
		if ( null === $model ) {
			\Arn\Intranet\Requests\Controller::refuse();
		}
		if ( isset( $model['saved'] ) ) {
			$target = match ( $model['operation'] ) {
				'calendar'=>array(
					'holidays'       => '1',
					'arn_leave_year' => (string) $model['saved'],
				), 'read'=>array( 'notices' => '1' ), default=>array( 'entry' => (string) $model['saved'] )};
			wp_safe_redirect( add_query_arg( $target + array( 'saved' => '1' ), get_permalink( $post ) ), 303 );
			exit;}
		if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			header( 'Content-Length: 0' );
			exit;
		}self::$model = $model;
	}
	public static function decide( string $method, string $view, array $get, array $post, object $source, int $actor, callable $verify, array $files = array() ): ?array {
		$query = Input::query( $get );
		if ( $actor < 1 || null === $query || ! in_array( $view, array( 'own', 'team' ), true ) || ! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true ) || $files || ( 'POST' !== $method && $post ) || ( 'new' === $query['screen'] && 'own' !== $view ) || ( 'holidays' === $query['screen'] && 'team' !== $view ) ) {
			return null;
		}
		$input = array();
		if ( 'POST' === $method ) {
			try {
				$input = Input::post( $post );
			} catch ( Throwable ) {
				return null;
			}$op      = $input['operation'];
			$matching = match ( $query['screen'] ) {
				'new'=>'create' === $op, 'holidays'=>'calendar' === $op, 'notices'=>'read' === $op, 'detail'=> ! in_array( $op, array( 'create', 'calendar', 'read' ), true ) && $input['id'] === $query['id'], default=>false};
			if ( ! $matching || ! $verify( $input['nonce'], Input::nonce( $actor, $view, $op, $input['key'] ) ) ) {
				return null;
			}
		}
		try {
			$access = $source->access( $view );
			if ( null === $access || $access['actor'] !== $actor || ( 'holidays' === $query['screen'] && ! $access['configure'] ) ) {
				return null;
			}
			$detail = 'detail' === $query['screen'] ? $source->detail( $query['id'], $view ) : null;
			if ( 'detail' === $query['screen'] && null === $detail ) {
				return null;
			}
			if ( null !== $detail ) {
				$query['year'] = $detail['request']->calendar->year;
			}
			$listing = $source->listing( $view, $query['year'], $query['page'] );
			if ( null === $listing ) {
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
			);
			if ( 'POST' === $method ) {
				if ( null !== $detail && ! in_array( $input['operation'], 'own' === $view ? array( 'edit', 'submit', 'withdraw' ) : array( 'start', 'return', 'approve', 'reject', 'cancel' ), true ) ) {
						return null;
				}
				try {
					return array(
						'saved'     => $source->mutate( $input ),
						'operation' => $input['operation'],
					);
				} catch ( Throwable ) {
					$model['error'] = 'Alteração não confirmada. Verifica o saldo, as sobreposições, as datas futuras e a versão do calendário. Consulta o histórico antes de repetir.';}
			}
			return $model;
		} catch ( Throwable ) {
			return null;}
	}
}
