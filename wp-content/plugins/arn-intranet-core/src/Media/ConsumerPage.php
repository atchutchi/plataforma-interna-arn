<?php
/** Authenticated author status and explicit completion, independent of wp-admin access.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
final class ConsumerPage {
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'route' ), -40 );
	}

	public static function url( string $id = '' ): string {
		return add_query_arg( 'arn_media_job', '' === $id ? 'all' : $id, home_url( '/' ) );
	}

	public static function redirect( string $id ): never {
		wp_safe_redirect( self::url( $id ), 303 );
		exit;
	}

	public static function link(): void {
		if ( ConsumerJobs::enabled() && ConsumerJobs::allowed( get_current_user_id() ) ) {
			echo '<p><a href="' . esc_url( self::url() ) . '">Envios em processamento</a>. Os ficheiros são analisados em fila. Depois, confirma a conclusão do envio.</p>';
		}
	}

	public static function shape( string $method, array $get, array $post, array $files, callable $nonce ): bool {
		$id = $get['arn_media_job'] ?? null;
		if ( array_keys( $get ) !== array( 'arn_media_job' ) || ! is_string( $id ) || ( 'all' !== $id && 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) ) || array() !== $files ) {
			return false; }
		if ( in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
			return array() === $post; }
		return 'POST' === $method && 'all' !== $id && array_keys( $post ) === array( 'revision', 'nonce' ) && is_string( $post['revision'] ) && 1 === preg_match( '/^[1-9][0-9]{0,2}$/D', $post['revision'] ) && is_string( $post['nonce'] ) && $nonce( $post['nonce'], $id, $post['revision'] );
	}

	public static function route(): void {
		if ( ! array_key_exists( 'arn_media_job', $_GET ) ) {
			return; }
		\Arn\Intranet\Tasks\Controller::privateHeaders();
		$get    = wp_unslash( $_GET );
		$post   = wp_unslash( $_POST );
		$method = (string) ( $_SERVER['REQUEST_METHOD'] ?? '' );
		if ( ! self::shape( $method, $get, $post, $_FILES, static fn( string $nonce, string $id, string $revision ): bool => false !== wp_verify_nonce( $nonce, 'arn_media_complete_' . get_current_user_id() . '_' . $id . '_' . $revision ) ) || ! ConsumerJobs::allowed( get_current_user_id() ) ) {
			\Arn\Intranet\Tasks\Controller::refuse(); }
		$id    = $get['arn_media_job'];
		$error = '';
		try {
			$rows = ConsumerJobs::listing();
			if ( 'all' !== $id && ! isset( $rows[ $id ] ) ) {
				\Arn\Intranet\Tasks\Controller::refuse(); }
			if ( 'POST' === $method ) {
				try {
					ConsumerJobs::complete( $id, (int) $post['revision'] );
					self::redirect( $id ); } catch ( Throwable ) {
					$error = 'Não foi possível concluir. Actualiza o estado e verifica a revisão e as permissões. Se a conclusão já foi iniciada, pede a consulta da auditoria antes de repetir.';
					$rows  = ConsumerJobs::listing(); }
			}
		} catch ( Throwable ) {
			\Arn\Intranet\Tasks\Controller::refuse(); }
		if ( 'HEAD' === $method ) {
			status_header( 200 );
			header( 'Content-Length: 0' );
			exit; }
		status_header( 200 );
		$root = dirname( __DIR__, 2 );
		wp_enqueue_style( 'arn-consumer-jobs', plugins_url( 'assets/css/media-consumers.css', $root . '/arn-intranet-core.php' ), array(), (string) filemtime( $root . '/assets/css/media-consumers.css' ) );
		self::render( 'all' === $id ? $rows : array_intersect_key( $rows, array( $id => true ) ), $error );
		exit;
	}

	private static function returnUrl( array $row ): string {
		$c  = $row['context'];
		$id = $row['receipt'] ?? $c['id'];
		if ( 'requests' === $c['module'] ) {
			$page = get_page_by_path( ( new \Arn\Intranet\Requests\Pages() )->slug( 'own' ) );
			return $page instanceof \WP_Post ? add_query_arg( 'entry', $id, get_permalink( $page ) ) : home_url( '/' );
		}
		if ( 'tickets' === $c['module'] ) {
			$page = get_page_by_path( ( new \Arn\Intranet\Support\Pages() )->slug( $c['payload']['view'] ) );
			return $page instanceof \WP_Post ? add_query_arg( 'entry', $id, get_permalink( $page ) ) : home_url( '/' );
		}
		if ( 'tasks' === $c['module'] ) {
			$page = get_page_by_path( ( new \Arn\Intranet\Tasks\Pages() )->slug( $c['payload']['view'] ) );
			return $page instanceof \WP_Post ? add_query_arg( 'entry', $id, get_permalink( $page ) ) : home_url( '/' );
		}
		return add_query_arg(
			array_filter(
				array(
					'page' => 'news' === $c['module'] ? 'arn-newsroom' : 'arn-document-catalog',
					'news' === $c['module'] ? 'article_id' : 'document_id' => $id,
				)
			),
			admin_url( 'admin.php' )
		);
	}

	private static function render( array $rows, string $error ): void {
		?><!doctype html><html lang="pt-PT"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Os meus envios | ARN</title><?php wp_head(); ?></head><body class="arn-media-consumers"><a class="arn-media-skip" href="#envios">Saltar para os envios</a><header class="arn-media-header"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">ARN · Portal interno</a><span>Laboratório local</span></header><main id="envios"><nav aria-label="Navegação de envios"><a href="<?php echo esc_url( self::url() ); ?>">Todos os meus envios</a></nav><h1>Os meus envios</h1><p>Os ficheiros ficam privados durante a análise. Quando estiverem prontos, confirma o envio para guardar no respectivo módulo.</p>
		<?php
		if ( '' !== $error ) {
			echo '<p role="alert" class="arn-media-alert">' . esc_html( $error ) . '</p>'; }
		?>
		<p><a class="arn-media-button secondary" href="">Actualizar estado</a></p>
		<?php
		if ( array() === $rows ) {
			echo '<p>Ainda não tens envios disponíveis para acompanhar.</p>'; }
		foreach ( $rows as $id => $row ) {
			$c      = $row['context'];
			$p      = $c['payload'];
			$title  = $p['title'] ?? $p['metadata']['title'] ?? $p['label'];
			$module = array(
				'news'      => 'Notícias',
				'documents' => 'Documentos',
				'tasks'     => 'Tarefas',
				'requests'  => 'Pedidos internos',
				'tickets'   => 'Suporte TI',
			)[ $c['module'] ];
			$ready  = ! array_filter( $row['items'], static fn( array $item ): bool => 'ready' !== $item['status'] );
			?>
			<article class="arn-media-card"><p class="arn-media-kicker"><?php echo esc_html( $module . ' · ' . gmdate( 'd/m/Y H:i', $row['created_at'] ) . ' UTC' ); ?></p><h2><a href="<?php echo esc_url( self::url( $id ) ); ?>"><?php echo esc_html( $title ); ?></a></h2>
			<?php
			if ( 'applied' === $row['consumption'] ) :
				?>
				<p role="status">Envio concluído. O registo foi guardado no módulo.</p>
				<?php
			elseif ( 'applying' === $row['consumption'] ) :
				?>
				<p role="alert" class="arn-media-alert">Conclusão iniciada, sem recibo confirmado. Não voltes a enviar. Pede a verificação da auditoria do módulo.</p>
				<?php
			else :
				?>
				<ol>
				<?php
				foreach ( $row['items'] as $i => $item ) :
					?>
				<li>
								<?php
								echo esc_html(
									'Ficheiro ' . ( $i + 1 ) . ': ' . array(
										'queued'     => 'A aguardar análise',
										'processing' => 'Em análise',
										'ready'      => 'Analisado',
										'held'       => 'Retido para verificação',
										'cancelled'  => 'Cancelado',
									)[ $item['status'] ]
								);
								?>
				</li><?php endforeach; ?></ol>
				<?php
				if ( $ready ) :
					?>
					<p>A análise terminou. A conclusão volta a verificar as tuas permissões e a revisão do conteúdo. Notícias e documentos ficam sujeitos à aprovação habitual.</p><form method="post" action="<?php echo esc_url( self::url( $id ) ); ?>"><input type="hidden" name="revision" value="<?php echo esc_attr( (string) $row['revision'] ); ?>"><input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'arn_media_complete_' . get_current_user_id() . '_' . $id . '_' . $row['revision'] ) ); ?>"><button type="submit">Concluir envio</button></form>
					<?php
else :
	?>
					<p>Podes sair desta página e regressar mais tarde. Um ficheiro retido exige intervenção do operador do laboratório.</p>
					<?php
endif;
endif;
			?>
			<p><a href="<?php echo esc_url( self::returnUrl( $row ) ); ?>"><?php echo esc_html( 'applied' === $row['consumption'] ? 'Abrir registo guardado' : 'Voltar ao módulo' ); ?></a></p></article>
			<?php
		}
		?>
		</main><?php wp_footer(); ?></body></html>
		<?php
	}
}
