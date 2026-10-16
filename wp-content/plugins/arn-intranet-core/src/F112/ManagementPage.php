<?php
/**
 * Business-authorised CMS transport.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use InvalidArgumentException;
use DomainException;
use Throwable;

// phpcs:disable Squiz.Commenting -- The CMS has its own guard before native auth_redirect.
final class ManagementPage {
	private static ?array $model   = null;
	private static ?string $module = null;

	public static function register(): void {
		add_action( 'init', array( self::class, 'guard' ), -20 );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	public static function menu(): void {
		foreach ( array(
			'communications' => 'Comunicados',
			'calendar'       => 'Calendário',
		) as $module => $label ) {
			if ( ( new Portal( $module ) )->manageable() ) {
				add_menu_page( $label, $label, 'read', 'arn-' . $module, array( self::class, 'render' ), 'communications' === $module ? 'dashicons-megaphone' : 'dashicons-calendar-alt', 'communications' === $module ? 28 : 29 );
			}
		}
	}

	public static function guard(): void {
		if ( ! is_admin() ) {
			return;
		}
		$page   = $_GET['page'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route selection only.
		$module = match ( $page ) {
			'arn-communications' => 'communications', 'arn-calendar' => 'calendar', default => null };
		if ( null === $module ) {
			return;
		}
		Controller::privateHeaders();
		if ( ! Configuration::current( $module ) ) {
			Controller::refuse();
		}
		$get  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Closed query and business check below.
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in decide.
		unset( $get['page'] );
		$model = self::decide( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), $module, $get, $post, new Portal( $module ), get_current_user_id(), static fn( string $nonce, string $operation, int $id ): bool => false !== wp_verify_nonce( $nonce, Input::nonce( get_current_user_id(), $module, $operation, $id ) ) );
		if ( null === $model ) {
			Controller::refuse();
		}
		if ( isset( $model['saved_id'] ) ) {
			$next        = array_diff_key( $get, array_flip( array( 'new', 'entry' ) ) );
			$destination = array(
				'page'  => 'arn-' . $module,
				'saved' => '1',
			);
			if ( empty( $model['queue'] ) ) {
				$destination['entry'] = (string) $model['saved_id'];
			}
			wp_safe_redirect(
				add_query_arg(
					array_merge(
						$next,
						$destination
					),
					admin_url( 'admin.php' )
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

	public static function decide( string $method, string $module, array $get, array $post, object $source, int $actor, callable $verify ): ?array {
		$new = $get['new'] ?? null;
		unset( $get['new'] );
		$query = Input::query( $module, $get, true );
		if ( ! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true ) || $actor < 1 || null === $query || ( null !== $new && ( ! is_string( $new ) || 'list' !== $query['view'] ) ) || ( 'POST' !== $method && array() !== $post ) ) {
			return null;
		}
		if ( 'POST' === $method && ( ! Input::shape( $module, $post, true ) || ! $verify( $post['nonce'], $post['operation'], (int) ( $post['id'] ?? 0 ) ) || ( isset( $get['entry'] ) && ( $post['id'] ?? null ) !== $get['entry'] ) || ( 'create' === $post['operation'] && null === $new ) || ( 'create' !== $post['operation'] && null !== $new ) ) ) {
			return null;
		}
		try {
			if ( ! $source->manageable() ) {
				return null;
			}
			$options = $source->creationOptions();
			if ( null !== $new && ! isset( $options[ $new ] ) ) {
				return null;
			}
			$errors   = array();
			$recovery = array();
			if ( 'POST' === $method ) {
				try {
					$errors = Input::validation( $module, $post );
					if ( $errors ) {
						throw new InvalidArgumentException( 'Campos inválidos.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					}
					$result = array( 'saved_id' => $source->mutate( $post ) );
					if ( in_array( $post['operation'], array( 'approve', 'reject', 'return' ), true ) ) {
						$result['queue'] = true;
					}
					return $result;
				} catch ( InvalidArgumentException ) {
					$errors['form'] = 'Verifica os campos indicados. O título e o texto são obrigatórios. Datas e ligações devem respeitar os formatos apresentados.';
					$recovery       = array_intersect_key( $post, array_flip( array_merge( Input::NOTICE_FIELDS, Input::EVENT_FIELDS, array( 'reason' ) ) ) );
				} catch ( DomainException ) {
					$errors['form'] = 'A operação foi recusada ou o registo mudou entretanto. Revê a versão actual antes de voltar a guardar.';
					$recovery       = array_intersect_key( $post, array_flip( array_merge( Input::NOTICE_FIELDS, Input::EVENT_FIELDS, array( 'reason' ) ) ) );
				} catch ( Throwable ) {
					$errors['form'] = 'Não foi possível concluir a operação. Os dados apresentados devem ser revistos antes de repetir.';
				}
			}
			$row  = null;
			$list = null;
			if ( null !== $new ) {
				$fields = array_fill_keys( 'communications' === $module ? Input::NOTICE_FIELDS : Input::EVENT_FIELDS, '' );
				$fields = array_merge(
					$fields,
					array(
						'all_day' => '0',
						'mode'    => 'presencial',
					),
					$options[ $new ]
				);
				unset( $fields['label'] );
				$row = array(
					'id'      => 0,
					'fields'  => $fields,
					'actions' => array( 'create' ),
					'history' => array(),
					'title'   => 'Novo registo',
					'state'   => 'Draft',
				);
			} elseif ( 'detail' === $query['view'] ) {
				$row = $source->managementDetail( $query['id'] );
				if ( null === $row ) {
					return null;
				}
			} else {
				$list = $source->managementList( $query['selection'] );
				if ( null === $list ) {
					return null;
				}
			}
			return array(
				'module'           => $module,
				'query'            => $query,
				'options'          => $options,
				'row'              => $row,
				'list'             => $list,
				'errors'           => $errors,
				'recovery'         => $recovery,
				'failed_operation' => $errors ? ( $post['operation'] ?? null ) : null,
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	public static function assets( string $hook ): void {
		if ( null === self::$model || ! in_array( $hook, array( 'toplevel_page_arn-communications', 'toplevel_page_arn-calendar' ), true ) ) {
			return;
		}
		$base = dirname( __DIR__, 2 );
		wp_enqueue_style( 'arn-f112-admin', plugins_url( 'assets/css/f112.css', $base . '/arn-intranet-core.php' ), array(), (string) filemtime( $base . '/assets/css/f112.css' ) );
		$fonts = get_stylesheet_directory_uri() . '/assets/fonts/';
		wp_add_inline_style( 'arn-f112-admin', "@font-face{font-family:'ARN F112 Body';src:url(" . wp_json_encode( $fonts . 'SourceSans3.ttf', JSON_UNESCAPED_SLASHES ) . ") format('truetype');font-display:swap}@font-face{font-family:'ARN F112 Heading';src:url(" . wp_json_encode( $fonts . 'Manrope.ttf', JSON_UNESCAPED_SLASHES ) . ") format('truetype');font-display:swap}" );
		wp_enqueue_script( 'arn-f112-forms', plugins_url( 'assets/js/f112.js', $base . '/arn-intranet-core.php' ), array(), (string) filemtime( $base . '/assets/js/f112.js' ), true );
	}

	public static function render(): void {
		if ( null === self::$model || null === self::$module || ! ( new Portal( self::$module ) )->manageable() ) {
			Controller::refuse();
		}
		View::management( self::$model );
	}
}
