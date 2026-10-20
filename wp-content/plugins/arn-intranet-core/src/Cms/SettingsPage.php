<?php
/** Authorised native CMS transport, refusing before WordPress login redirects.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\F112\Controller;
use DomainException;
use InvalidArgumentException;
use Throwable;

// phpcs:disable Squiz.Commenting
final class SettingsPage {
	private static ?array $model = null;
	public static function register(): void {
		add_action( 'init', array( self::class, 'guard' ), -20 );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}
	public static function menu(): void {
		try {
			( new LocalSettings() )->read();
			add_menu_page( 'Parâmetros do CMS', 'Parâmetros do CMS', 'read', 'arn-cms-settings', array( self::class, 'render' ), 'dashicons-admin-settings', 30 );
		} catch ( Throwable ) {
			return;
		}
	}
	public static function guard(): void {
		if ( ! is_admin() || 'arn-cms-settings' !== ( $_GET['page'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route selection only.
			return;
		}
		Controller::privateHeaders();
		if ( ! Configuration::current() || is_network_admin() || is_user_admin() ) {
			Controller::refuse();
		}
		$get  = wp_unslash( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Strict query shape below.
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Native nonce verified in decide.
		unset( $get['page'] );
		$model = self::decide( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), $get, $post, new LocalSettings(), get_current_user_id(), static fn( string $nonce ): bool => false !== wp_verify_nonce( $nonce, SettingsInput::nonce( get_current_user_id() ) ) );
		if ( null === $model ) {
			Controller::refuse();
		}
		if ( ! empty( $model['saved'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=arn-cms-settings&saved=1' ), 303 );
			exit;
		}
		if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			header( 'Content-Length: 0' );
			exit;
		}
		self::$model = $model;
	}
	public static function decide( string $method, array $get, array $post, object $source, int $actor, callable $verify ): ?array {
		if ( $actor < 1 || ! in_array( $method, array( 'GET', 'HEAD', 'POST' ), true ) || array_diff( array_keys( $get ), array( 'before', 'saved' ) ) || ( isset( $get['before'] ) && null === SettingsInput::positive( $get['before'] ) ) || ( isset( $get['saved'] ) && '1' !== $get['saved'] ) || ( 'POST' !== $method && array() !== $post ) || ( 'POST' === $method && ( array() !== $get || ! SettingsInput::shape( $post ) || ! $verify( $post['nonce'] ) ) ) ) {
			return null;
		}
		try {
			$state = $source->read();
			$error = '';
			if ( 'POST' === $method ) {
				try {
					$source->change( SettingsInput::policy( $post ), (int) $post['revision'], $post['reason'] );
					return array( 'saved' => true );
				} catch ( InvalidArgumentException ) {
					$error = 'Verifica os limites, escolhe pelo menos um formato por destino e indica um motivo com até 1000 caracteres.';
				} catch ( DomainException ) {
					$error = 'Os parâmetros mudaram ou a operação foi recusada. Os teus campos foram conservados. Compara-os com os valores actuais antes de guardar novamente.';
				} catch ( Throwable ) {
					$error = 'Não foi possível confirmar a alteração. Compara os teus campos com os valores actuais antes de repetir.';
				}
				$state = $source->read(); // Recheck authority before recovering any editable form.
			}
			$fields = SettingsInput::fields( $state );
			if ( '' !== $error ) {
				foreach ( $fields as $key => $value ) {
					$fields[ $key ] = $post[ $key ] ?? ( is_array( $value ) ? array() : '' );
				}
			}
			$before  = isset( $get['before'] ) ? (int) $get['before'] : PHP_INT_MAX;
			$history = $source->history( min( $before, $state->revision < PHP_INT_MAX ? $state->revision + 1 : PHP_INT_MAX ) );
			return array(
				'state'   => $state,
				'fields'  => $fields,
				'error'   => $error,
				'history' => $history,
				'saved'   => false,
				'notice'  => isset( $get['saved'] ),
				'older'   => isset( $get['before'] ),
			);
		} catch ( Throwable ) {
			return null;
		}
	}
	public static function assets( string $hook ): void {
		if ( 'toplevel_page_arn-cms-settings' !== $hook || null === self::$model ) {
			return;
		}
		$file = dirname( __DIR__, 2 ) . '/arn-intranet-core.php';
		$path = dirname( $file ) . '/assets/css/cms-settings.css';
		wp_enqueue_style( 'arn-cms-settings', plugins_url( 'assets/css/cms-settings.css', $file ), array(), (string) filemtime( $path ) );
		$font_url = get_theme_root_uri( 'arn-intranet' ) . '/arn-intranet/assets/fonts/';
		wp_add_inline_style( 'arn-cms-settings', '@font-face{font-family:"ARN CMS Body";font-weight:200 900;font-display:swap;src:url("' . esc_url( $font_url . 'SourceSans3.ttf' ) . '")}@font-face{font-family:"ARN CMS Heading";font-weight:200 800;font-display:swap;src:url("' . esc_url( $font_url . 'Manrope.ttf' ) . '")}' );
	}
	public static function render(): void {
		if ( null === self::$model ) {
			Controller::refuse();
		}
		SettingsView::render( self::$model );
	}
}
