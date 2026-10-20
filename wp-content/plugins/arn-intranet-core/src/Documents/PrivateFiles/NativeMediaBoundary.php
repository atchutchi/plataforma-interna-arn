<?php
/**
 * Fecha o circuito público de media do WordPress. Os módulos usam PrivateBlobStore.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use WP_Error;

/** Não concede excepções administrativas nem altera anexos já existentes. */
final class NativeMediaBoundary {

	public const MESSAGE = 'Usa os módulos Notícias ou Catálogo documental para guardar ficheiros privados. A biblioteca pública de multimédia está desactivada.';

	/** Regista a fronteira independentemente das flags dos módulos locais. */
	public static function register(): void {
		add_filter( 'map_meta_cap', array( self::class, 'capabilities' ), PHP_INT_MAX, 4 );
		add_filter( 'upload_dir', array( self::class, 'refuseUpload' ), PHP_INT_MAX );
		add_filter( 'wp_handle_upload_prefilter', array( self::class, 'refuseUpload' ), PHP_INT_MAX );
		add_filter( 'wp_handle_sideload_prefilter', array( self::class, 'refuseUpload' ), PHP_INT_MAX );
		add_filter( 'wp_upload_bits', static fn() => self::MESSAGE, PHP_INT_MAX );
		add_filter( 'wp_insert_post_empty_content', array( self::class, 'refuseAttachment' ), PHP_INT_MAX, 2 );
		add_filter( 'pre_delete_attachment', '__return_false', PHP_INT_MAX );
		add_filter( 'rest_endpoints', array( self::class, 'restEndpoints' ), PHP_INT_MAX );
		add_filter( 'rest_pre_dispatch', array( self::class, 'restRequest' ), PHP_INT_MAX, 3 );
		add_filter( 'is_post_embeddable', array( self::class, 'embeddable' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_get_attachment_url', '__return_empty_string', PHP_INT_MAX );
		add_filter( 'wp_get_attachment_metadata', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'wp_get_attachment_image', '__return_empty_string', PHP_INT_MAX );
		add_filter( 'wp_prepare_attachment_for_js', '__return_null', PHP_INT_MAX );
		add_filter( 'image_downsize', static fn() => array( '', 0, 0, false ), PHP_INT_MAX );
		add_filter( 'wp_image_editors', '__return_empty_array', PHP_INT_MAX );
		add_action( 'pre_get_posts', array( self::class, 'filterQueries' ), PHP_INT_MAX );
		add_filter( 'posts_where', array( self::class, 'excludeAttachments' ), PHP_INT_MAX );
		add_filter( 'comments_clauses', array( self::class, 'excludeAttachmentComments' ), PHP_INT_MAX );
		add_filter( 'comments_open', array( self::class, 'commentsOpen' ), PHP_INT_MAX, 2 );
		add_action( 'init', array( self::class, 'adminRequest' ), -100 );
		add_action( 'template_redirect', array( self::class, 'frontendRequest' ), -100 );
		add_action( 'admin_menu', static fn() => remove_menu_page( 'upload.php' ), PHP_INT_MAX );
		add_action( 'admin_notices', array( self::class, 'editorNotice' ) );
	}

	/**
	 * Recusa uploads e operações sobre attachments, incluindo superadministrador.
	 *
	 * @param array  $caps Capacidades anteriores.
	 * @param string $cap Capacidade pedida.
	 * @param int    $user_id Actor, sem excepção.
	 * @param array  $args Argumentos de objecto.
	 * @return array Capacidades efectivas.
	 */
	public static function capabilities( array $caps, string $cap, int $user_id, array $args ): array {
		if ( in_array( $cap, array( 'upload_files', 'unfiltered_upload', 'export' ), true ) ) {
			return array( 'do_not_allow' );
		}
		if ( in_array( $cap, array( 'read_post', 'edit_post', 'delete_post', 'edit_post_meta', 'delete_post_meta', 'add_post_meta' ), true ) && isset( $args[0] ) && 'attachment' === get_post_type( (int) $args[0] ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	/**
	 * O core interrompe antes de mover ou escrever os bytes do ficheiro público.
	 *
	 * @param array $upload Argumentos nativos.
	 * @return array Recusa sem substituir a configuração de caminhos.
	 */
	public static function refuseUpload( array $upload ): array {
		$upload['error'] = self::MESSAGE;
		return $upload;
	}

	/**
	 * Fecha também wp_insert_attachment e actualizações directas via wp_insert_post.
	 *
	 * @param bool  $is_empty Decisão anterior.
	 * @param array $post Registo normalizado pelo core.
	 * @return bool Recusar a gravação.
	 */
	public static function refuseAttachment( bool $is_empty, array $post ): bool {
		return $is_empty || 'attachment' === ( $post['post_type'] ?? '' );
	}

	/**
	 * Não anunciar colecções ou edição de media na descoberta REST.
	 *
	 * @param array $endpoints Rotas registadas.
	 * @return array Rotas restantes.
	 */
	public static function restEndpoints( array $endpoints ): array {
		foreach ( array_keys( $endpoints ) as $route ) {
			if ( self::isMediaRoute( $route ) ) {
				unset( $endpoints[ $route ] );
			}
		}
		return $endpoints;
	}

	/**
	 * Fecha GET/HEAD/POST/DELETE e pedidos REST internos/batch, sem revelar objectos.
	 *
	 * @param mixed  $result Resposta anterior.
	 * @param object $server Servidor REST.
	 * @param object $request Pedido REST.
	 * @return mixed Erro genérico ou resposta anterior.
	 */
	public static function restRequest( mixed $result, object $server, object $request ): mixed {
		$route = $request->get_route();
		$deny  = self::isMediaRoute( $route );
		if ( 1 === preg_match( '~^/wp/v2/comments(?:/(\d+))?/?$~i', $route, $matches ) ) {
			$comment = isset( $matches[1] ) ? get_comment( (int) $matches[1] ) : null;
			$parent  = $request->get_param( 'post' );
			$deny    = ( $comment && 'attachment' === get_post_type( (int) $comment->comment_post_ID ) ) || ( is_numeric( $parent ) && 'attachment' === get_post_type( (int) $parent ) );
		}
		return $deny ? new WP_Error( 'rest_no_route', 'Recurso indisponível.', array( 'status' => 404 ) ) : $result;
	}

	/**
	 * Reconhece apenas o recurso nativo, sem afectar rotas dos módulos privados.
	 *
	 * @param string $route Rota REST.
	 * @return bool Se é media nativa.
	 */
	public static function isMediaRoute( string $route ): bool {
		return 1 === preg_match( '~^/wp/v2/(?:media|types/attachment)(?:/|$)~i', $route );
	}

	/**
	 * O oEmbed resolve IDs sem WP_Query. Aplicar a fronteira também nesse caminho.
	 *
	 * @param bool   $allowed Decisão anterior.
	 * @param object $post Objecto resolvido pelo core.
	 * @return bool Se pode ser incorporado.
	 */
	public static function embeddable( bool $allowed, object $post ): bool {
		return $allowed && 'attachment' !== $post->post_type;
	}

	/**
	 * Mantém o filtro SQL mesmo nas pesquisas nativas com suppress_filters.
	 *
	 * @param object $query Consulta WordPress.
	 */
	public static function filterQueries( object $query ): void {
		$type = $query->get( 'post_type' );
		if ( ! $type || $query->get( 'attachment_id' ) || $query->get( 'attachment' ) || 'any' === $type || 'attachment' === $type || ( is_array( $type ) && in_array( 'attachment', $type, true ) ) ) {
			$query->set( 'suppress_filters', false );
		}
	}

	/**
	 * Exclui attachments antes de contar/paginar, incluindo pesquisas mistas.
	 *
	 * @param string $where Condições existentes.
	 * @return string Condições fechadas.
	 */
	public static function excludeAttachments( string $where ): string {
		global $wpdb;
		return $where . $wpdb->prepare( ' AND %i.post_type <> %s', $wpdb->posts, 'attachment' );
	}

	/**
	 * Os comentários têm consulta própria. Excluir antes de contar e paginar.
	 *
	 * @param array $clauses Partes SQL do core.
	 * @return array Consulta sem comentários de attachments.
	 */
	public static function excludeAttachmentComments( array $clauses ): array {
		global $wpdb;
		if ( '' === trim( $clauses['where'] ) ) {
			$clauses['where'] = '1=1';
		}
		$clauses['where'] .= $wpdb->prepare( ' AND %i.comment_post_ID NOT IN (SELECT ID FROM %i WHERE post_type=%s)', $wpdb->comments, $wpdb->posts, 'attachment' );
		return $clauses;
	}

	/**
	 * Um attachment não recebe comentários através do formulário nativo.
	 *
	 * @param bool $open Decisão anterior.
	 * @param int  $post_id Objecto alvo.
	 * @return bool Decisão conservadora.
	 */
	public static function commentsOpen( bool $open, int $post_id ): bool {
		return $open && 'attachment' !== get_post_type( $post_id );
	}

	/** Fecha biblioteca, carregamento clássico/assíncrono e AJAX antes dos handlers. */
	public static function adminRequest(): void {
		$page = (string) ( $GLOBALS['pagenow'] ?? '' );
		if ( in_array( $page, array( 'upload.php', 'media-new.php', 'media.php', 'async-upload.php', 'export.php' ), true ) ) {
			self::deny();
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Só decide recusa, nunca executa a acção.
		$action = $_REQUEST['action'] ?? '';
		if ( 'admin-ajax.php' === $page && is_string( $action ) && in_array( $action, array( 'query-attachments', 'upload-attachment', 'get-attachment', 'save-attachment', 'save-attachment-compat', 'send-attachment-to-editor', 'image-editor', 'crop-image', 'get-post-thumbnail-html', 'set-post-thumbnail' ), true ) ) {
			self::deny();
		}
	}

	/** Fecha páginas de attachment antes do template ou redireccionamento canónico. */
	public static function frontendRequest(): void {
		if ( is_attachment() || get_query_var( 'attachment_id' ) || get_query_var( 'attachment' ) ) {
			self::deny();
		}
	}

	/** Orientação no editor nativo, sem nomes, contagens ou ligações não autorizadas. */
	public static function editorNotice(): void {
		if ( in_array( $GLOBALS['pagenow'] ?? '', array( 'post.php', 'post-new.php', 'site-editor.php' ), true ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html( self::MESSAGE ) . '</p></div>';
		}
	}

	/** Recusa indistinta, sem sessão, payload ou existência do recurso. */
	private static function deny(): never {
		status_header( 404 );
		nocache_headers();
		header( 'Cache-Control: private, no-store' );
		exit;
	}
}
