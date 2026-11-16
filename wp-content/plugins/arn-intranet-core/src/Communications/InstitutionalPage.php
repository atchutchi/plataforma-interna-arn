<?php
/** Private institutional panel, with no public REST projection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Communications;

use Arn\Intranet\Identity\{GoogleLoginController, IdentityWpdb};
use DomainException;
use Throwable;
// phpcs:disable Squiz.Commenting.FunctionComment, WordPress.Security.NonceVerification.Recommended -- Read-only route, always authorised server-side.
/** Explicit private route. */
final class InstitutionalPage {
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), 1 );
	}
	public static function route(): void {
		if ( ! isset( $_GET['arn_panel'] ) ) {
			return;
		}
		nocache_headers();
		header( 'Cache-Control: private, no-store, max-age=0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: same-origin' );
		if ( ! InstitutionalPanel::enabled() || ! GoogleLoginController::institutionalMode() || '1' !== $_GET['arn_panel'] ) {
			wp_die( 'Painel indisponível.', 'ARN', array( 'response' => 404 ) );
		}
		$database = null;
		try {
			$source = GoogleLoginController::institutionalSource();
			if ( null === $source ) {
				throw new DomainException();
			}
			$before = $_GET['before'] ?? (string) PHP_INT_MAX;
			if ( ! is_string( $before ) || 1 !== preg_match( '/^[1-9][0-9]{0,18}$/D', $before ) || (string) (int) $before !== $before ) {
				throw new DomainException();
			}
			global $wpdb;
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $wpdb->prefix );
			$data = ( new InstitutionalPanel( $database, $source, static fn(): int => time() ) )->read( (int) $before );
		} catch ( DomainException ) {
			wp_die( 'É necessária uma sessão institucional válida com permissão de leitura. Inicia sessão através do acesso Google da plataforma.', 'Acesso reservado', array( 'response' => 403 ) );
		} catch ( Throwable ) {
			wp_die( 'O painel está temporariamente indisponível. Tenta novamente mais tarde.', 'ARN', array( 'response' => 503 ) );
		} finally {
			$database?->close();
		}
		$data['next_url']   = null === $data['next'] ? null : add_query_arg(
			array(
				'arn_panel' => '1',
				'before'    => $data['next'],
			),
			home_url( '/' )
		);
		$data['home_url']   = add_query_arg( 'arn_panel', '1', home_url( '/' ) );
		$data['logout_url'] = wp_logout_url( home_url( '/' ) );
		$data['logo_url']   = get_theme_file_uri( 'assets/images/arn-logo.png' );
		unset( $data['next'] );
		status_header( 200 );
		wp_enqueue_style( 'arn-panel', get_theme_file_uri( 'assets/panel/panel.css' ), array(), (string) filemtime( get_theme_file_path( 'assets/panel/panel.css' ) ) );
		wp_enqueue_script( 'arn-panel', get_theme_file_uri( 'assets/panel/panel.js' ), array(), (string) filemtime( get_theme_file_path( 'assets/panel/panel.js' ) ), true );
		?>
		<!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Painel institucional | ARN</title><?php wp_head(); ?></head>
		<body class="arn-institutional-panel"><div id="arn-panel-root">
		<a href="#main">Saltar para o conteúdo</a>
		<header><img width="72" src="<?php echo esc_url( $data['logo_url'] ); ?>" alt="ARN"><a href="<?php echo esc_url( $data['logout_url'] ); ?>">Terminar sessão</a></header>
		<main id="main"><h1>Painel institucional</h1><h2>Comunicados</h2>
		<?php
		if ( ! $data['notices'] ) :
			?>
			<p>Não existem comunicados publicados nesta página.</p><?php endif; ?>
		<?php foreach ( $data['notices'] as $notice ) : ?>
		<article><h3><?php echo esc_html( $notice['title'] ); ?></h3><p style="white-space:pre-wrap"><?php echo esc_html( $notice['body'] ); ?></p></article>
		<?php endforeach; ?>
		<?php
		if ( null !== $data['next_url'] ) :
			?>
			<a href="<?php echo esc_url( $data['next_url'] ); ?>">Continuar a consulta</a><?php endif; ?>
		</main></div>
		<script id="arn-panel-data" type="application/json"><?php echo wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Safe JSON text, not executable code. ?></script>
		<?php wp_footer(); ?></body></html>
		<?php
		exit;
	}
}
