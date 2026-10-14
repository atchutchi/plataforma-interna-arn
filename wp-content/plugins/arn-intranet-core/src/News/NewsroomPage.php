<?php
/** Local, role-authorised news CMS.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Scope;
use stdClass;
use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.FunctionCommentThrowTag, WordPress.Security.NonceVerification.Missing, WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
/** WordPress only supplies transport and presentation. NewsPolicy supplies authority. */
final class NewsroomPage {
	private const SLUG      = 'arn-newsroom';
	private const PAGE_SIZE = 10;
	/** POST recovery exists only for the current request.
	 *
	 * @var array|null
	 */
	private static ?array $recovery = null;

	public static function register(): void {
		add_action( 'init', array( self::class, 'registerPostType' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_init', array( self::class, 'submit' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/** The post is a transactional anchor, never a native editorial surface. */
	public static function registerPostType(): void {
		$denied = array_fill_keys( array( 'edit_post', 'read_post', 'delete_post', 'edit_posts', 'edit_others_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts', 'edit_private_posts', 'edit_published_posts', 'create_posts' ), 'do_not_allow' );
		register_post_type(
			'arn_noticia',
			array(
				'label'               => 'Notícia privada',
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => false,
				'query_var'           => false,
				'supports'            => array(),
				'capability_type'     => 'arn_noticia',
				'map_meta_cap'        => false,
				'capabilities'        => $denied,
			)
		);
	}

	/** Read server actor and business capabilities.
	 *
	 * @return array{0:array,1:bool,2:bool}|null
	 */
	private static function access(): ?array {
		if ( ! LocalNewsroom::configured() ) {
			return null;
		}
		$actor = ( new CurrentNewsActor() )->read();
		if ( null === $actor ) {
			return null;
		}
		$author = NewsPolicy::canCreate( $actor[0], $actor[2], time() );
		$editor = NewsPolicy::canReviewScope( Scope::queues( array( 'editorial-ficticia' ) ), $actor[0], $actor[2], time() );
		return $author || $editor ? array( $actor, $author, $editor ) : null;
	}

	public static function menu(): void {
		if ( null !== self::access() ) {
			add_menu_page( 'Notícias locais', 'Notícias locais', 'read', self::SLUG, array( self::class, 'render' ), 'dashicons-edit-page', 27 );
		}
	}

	public static function assets( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook || null === self::access() ) {
			return;
		}
		$root = self::assetRoot();
		wp_enqueue_style( 'arn-newsroom', plugins_url( 'assets/css/newsroom.css', $root . '/arn-intranet-core.php' ), array(), (string) filemtime( $root . '/assets/css/newsroom.css' ) );
		wp_enqueue_script( 'arn-newsroom', plugins_url( 'assets/js/newsroom.js', $root . '/arn-intranet-core.php' ), array(), (string) filemtime( $root . '/assets/js/newsroom.js' ), true );
	}

	/** Resolve the plugin root for the page's own CSS and JavaScript. */
	public static function assetRoot(): string {
		return dirname( __DIR__, 2 );
	}

	public static function nonceAction( string $operation, int $id = 0 ): string {
		return 'arn_news_' . $operation . '_' . $id;
	}

	/** Pure request shape check, before any database or binary operation. */
	public static function postGate( array $post, string $method, string $page, callable $verify ): bool {
		$operation = $post['operation'] ?? null;
		$id        = $post['article_id'] ?? null;
		if ( 'POST' !== $method || self::SLUG !== $page || ! is_string( $operation ) || ! in_array( $operation, array( 'create', 'revise', 'submit', 'receipt' ), true ) ) {
			return false;
		}
		if ( 'create' === $operation ) {
			if ( isset( $id ) ) {
				return false;
			}
			$id = 0;
		} elseif ( ! is_string( $id ) || 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $id ) ) {
			return false;
		}
		return is_string( $post['_wpnonce'] ?? null ) && $verify( $post['_wpnonce'], self::nonceAction( $operation, (int) $id ) );
	}

	public static function submit(): void {
		if ( self::SLUG === ( $_GET['page'] ?? null ) ) {
			nocache_headers();
			header( 'Cache-Control: no-store, private' );
		}
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || self::SLUG !== ( $_GET['page'] ?? null ) ) {
			return;
		}
		if ( array() === $_POST && (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > 0 ) {
			wp_die( esc_html__( 'O pedido não chegou completo ao PHP. Reduz os ficheiros ou pede a verificação dos limites HTTP e preenche o formulário novamente.', 'arn-intranet-core' ), '', array( 'response' => 413 ) );
		}
		if ( 'arn_newsroom' !== ( $_POST['action'] ?? null ) ) {
			self::deny();
		}
		$access = self::access();
		if ( null === $access ) {
			self::deny();
		}
		$post = wp_unslash( $_POST );
		if ( ! is_array( $post ) || ! self::postGate( $post, 'POST', self::SLUG, 'wp_verify_nonce' ) ) {
			self::deny();
		}
		$operation = $post['operation'];
		$id        = 'create' === $operation ? 0 : (int) $post['article_id'];
		$news      = new LocalNewsroom();
		if ( 'receipt' === $operation ) {
			if ( ! $access[2] || array_diff( array_keys( $post ), array( 'action', 'operation', 'article_id', '_wpnonce', '_wp_http_referer' ) ) ) {
				self::deny();
			}
			try {
				$news->markRead( $id );
				self::redirect( 0, 'read' );
			} catch ( Throwable ) {
				self::deny();
			}
		}
		if ( ! $access[1] ) {
			self::deny();
		}
		$expected = $post['expected_revision'] ?? null;
		if ( 'create' !== $operation && ( ! is_string( $expected ) || 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $expected ) ) ) {
			self::deny();
		}
		$article = 0 === $id ? null : $news->findVisible( $id );
		if ( 0 !== $id && ( null === $article || $article->author_id !== $access[0][1] || $article->pending() ) ) {
			self::deny();
		}
		$errors = array();
		$reason = $post['reason'] ?? null;
		if ( ! is_string( $reason ) || '' === trim( $reason ) || strlen( $reason ) > 500 || 1 !== preg_match( '/\A[^\p{C}<>]+\z/u', $reason ) || trim( $reason ) !== $reason ) {
			$errors['reason'] = 'Escreve uma justificação breve numa linha, até 500 bytes.';
		}
		$draft = null;
		if ( in_array( $operation, array( 'create', 'revise' ), true ) ) {
			$parsed = NewsFormInput::validate( $post, $_FILES );
			$draft  = $parsed['draft'];
			$errors = array_merge( $errors, $parsed['errors'] );
		} elseif ( array_diff( array_keys( $post ), array( 'action', 'operation', 'article_id', 'expected_revision', 'reason', '_wpnonce', '_wp_http_referer' ) ) || array() !== $_FILES ) {
			$errors['form'] = 'O pedido contém campos não permitidos.';
		}
		if ( array() !== $errors ) {
			self::$recovery = compact( 'operation', 'post', 'errors' );
			return;
		}
		try {
			$result = match ( $operation ) {
				'create' => $news->create( $draft, $reason ),
				'revise' => $news->revise( $id, $draft, (int) $expected, $reason ),
				'submit' => $news->submit( $id, (int) $expected, $reason ),
			};
			self::redirect( $result->id, 'saved' );
		} catch ( Throwable ) {
			self::$recovery = array(
				'operation' => $operation,
				'post'      => $post,
				'errors'    => array( 'form' => 'Não foi possível guardar. Confirma a revisão e as permissões actuais. Selecciona novamente os ficheiros novos.' ),
			);
		}
	}

	private static function redirect( int $id, string $notice ): never {
		$url = add_query_arg(
			array_filter(
				array(
					'page'       => self::SLUG,
					'article_id' => $id,
					'notice'     => $notice,
				)
			),
			admin_url( 'admin.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	private static function deny(): never {
		wp_die( esc_html__( 'Pedido de notícias indisponível.', 'arn-intranet-core' ), '', array( 'response' => 404 ) );
	}

	private static function url( int $id = 0, int $page = 1 ): string {
		return add_query_arg(
			array_filter(
				array(
					'page'       => self::SLUG,
					'article_id' => $id,
					'arn_paged'  => $page > 1 ? $page : null,
				)
			),
			admin_url( 'admin.php' )
		);
	}

	public static function render(): void {
		$access = self::access();
		if ( null === $access ) {
			self::deny();
		}
		$news = new LocalNewsroom();
		$list = $news->visible();
		if ( null === $list ) {
			self::deny();
		}
		$id_raw = $_GET['article_id'] ?? null;
		if ( null !== $id_raw && ( ! is_string( $id_raw ) || 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $id_raw ) ) ) {
			self::deny();
		}
		$chosen = null === $id_raw ? null : $news->findVisible( (int) $id_raw );
		if ( null !== $id_raw && null === $chosen ) {
			self::deny();
		}
		$page_raw = $_GET['arn_paged'] ?? '1';
		$page     = is_string( $page_raw ) && preg_match( '/^[1-9][0-9]{0,5}$/D', $page_raw ) ? (int) $page_raw : 1;
		$total    = count( $list );
		$pages    = max( 1, (int) ceil( $total / self::PAGE_SIZE ) );
		$page     = min( $page, $pages );
		$rows     = array_slice( $list, ( $page - 1 ) * self::PAGE_SIZE, self::PAGE_SIZE );
		$notices  = $access[2] ? ( $news->notifications() ?? array() ) : array();
		?>
		<div class="wrap arn-newsroom-admin"><div class="arn-newsroom-heading"><div><h1>Notícias locais</h1><p>Redacção e revisão em ambiente fictício. As notícias nesta área não estão publicadas.</p></div>
		<?php
		if ( $access[1] ) :
			?>
			<a class="button" href="<?php echo esc_url( self::url() ); ?>#arn-news-create">Criar notícia</a><?php endif; ?></div>
		<?php
		if ( in_array( $_GET['notice'] ?? '', array( 'saved', 'read' ), true ) ) :
			?>
			<div class="notice notice-success arn-newsroom-notice" role="status"><p><?php echo esc_html( 'read' === $_GET['notice'] ? 'Aviso marcado como lido.' : 'Operação guardada. Confirma o estado e a versão abaixo.' ); ?></p></div><?php endif; ?>
		<?php
		if ( null !== self::$recovery ) :
			?>
			<div class="notice notice-error arn-newsroom-notice" role="alert"><p>Corrige os campos assinalados. Os ficheiros novos têm de ser seleccionados novamente.</p>
			<?php
			if ( isset( self::$recovery['errors']['form'] ) ) :
				?>
			<p><?php echo esc_html( self::$recovery['errors']['form'] ); ?></p><?php endif; ?></div><?php endif; ?>
		<?php
		if ( $access[2] ) :
			self::noticeList( $notices );
endif;
		?>
		<section class="arn-newsroom-list" aria-labelledby="arn-newsroom-list-title"><div class="arn-newsroom-section-heading"><h2 id="arn-newsroom-list-title"><?php echo esc_html( $access[1] ? 'As minhas notícias' : 'Pendentes de revisão' ); ?></h2><p><?php echo esc_html( (string) $total ); ?> notícia<?php echo 1 === $total ? '' : 's'; ?></p></div>
		<?php
		if ( array() === $rows ) :
			?>
			<p>Ainda não há notícias nesta lista.</p>
			<?php
else :
	?>
			<ul>
			<?php
			foreach ( $rows as $article ) :
				$own     = $article->author_id === $access[0][1];
				$version = $article->version( $own ? count( $article->versions ) : (int) $article->submitted_version );
				?>
	<li><a href="<?php echo esc_url( self::url( $article->id ) ); ?>"><?php echo esc_html( $version->content->title ); ?></a><span><?php echo esc_html( $article->pending() ? 'Pendente' : 'Rascunho' ); ?> · versão <?php echo esc_html( (string) $version->number ); ?></span></li><?php endforeach; ?></ul><?php endif; ?>
		<?php
		if ( $pages > 1 ) :
			?>
			<nav class="arn-newsroom-pages" aria-label="Páginas de notícias">
			<?php
			for ( $number = 1; $number <= $pages; $number++ ) :
				?>
			<a href="<?php echo esc_url( self::url( 0, $number ) ); ?>" <?php echo $number === $page ? 'aria-current="page"' : ''; ?>><?php echo esc_html( (string) $number ); ?></a><?php endfor; ?></nav><?php endif; ?></section>
		<?php
		if ( null !== $chosen ) :
			self::detail( $chosen, $access[0][1], $news );
endif;
		?>
		<?php
		if ( $access[1] ) :
			?>
			<section id="arn-news-create" class="arn-newsroom-section" aria-labelledby="arn-news-create-title"><h2 id="arn-news-create-title">Criar rascunho</h2><?php self::form( 'create', null ); ?></section><?php endif; ?>
		</div>
		<?php
	}

	private static function detail( Article $article, int $actor_id, LocalNewsroom $news ): void {
		$own     = $article->author_id === $actor_id;
		$version = $article->version( $own ? count( $article->versions ) : (int) $article->submitted_version );
		$preview = NewsPortal::url( $article->id );
		?>
		<section class="arn-newsroom-section" aria-labelledby="arn-news-detail-title"><div class="arn-newsroom-section-heading"><div><h2 id="arn-news-detail-title"><?php echo esc_html( $version->content->title ); ?></h2><p><?php echo esc_html( $article->pending() ? 'Pendente' : 'Rascunho' ); ?> · versão <?php echo esc_html( (string) $version->number ); ?> · revisão <?php echo esc_html( (string) $article->revision ); ?></p></div><a class="button" href="<?php echo esc_url( $preview ); ?>" target="_blank" rel="noopener">Pré-visualizar</a></div>
		<?php
		if ( $own ) :
			$history = $news->historyVisible( $article->id ); if ( is_array( $history ) ) :
				?>
			<div class="arn-newsroom-history"><h3>Histórico e auditoria</h3><ol>
				<?php
				foreach ( $history as $event ) :
					if ( $event instanceof stdClass ) :
						?>
	<li><?php echo esc_html( self::auditLine( $event ) ); ?></li>
						<?php
				endif;
			endforeach;
				?>
</ol></div>
				<?php
endif;
endif;
		?>
		<?php
		if ( $own && ! $article->pending() ) :
			?>
			<h3>Guardar nova versão</h3><?php self::form( 'revise', $article ); ?><h3>Enviar para aprovação</h3><p>O envio bloqueia a edição nesta fase e cria um aviso para a fila editorial.</p><?php self::form( 'submit', $article ); ?><?php endif; ?></section>
		<?php
	}

	public static function auditLine( stdClass $event ): string {
		$action = match ( $event->event ?? '' ) {
			'created' => 'Rascunho criado', 'revised' => 'Nova versão', 'submitted' => 'Enviada para aprovação', default => 'Operação de notícias' };
		return 'Revisão ' . (int) ( $event->revision ?? 0 ) . ': ' . $action . '. Conta #' . (int) ( $event->actor_id ?? 0 ) . '. ' . gmdate( 'd/m/Y H:i', (int) ( $event->occurred_at ?? 0 ) ) . ' UTC. Justificação: ' . (string) ( $event->reason ?? '' );
	}

	private static function noticeList( array $notices ): void {
		?>
		<section class="arn-newsroom-section" aria-labelledby="arn-news-notices-title"><div class="arn-newsroom-section-heading"><h2 id="arn-news-notices-title">Avisos da fila editorial</h2><p><?php echo esc_html( (string) count( $notices ) ); ?> aviso<?php echo 1 === count( $notices ) ? '' : 's'; ?></p></div>
		<?php
		if ( array() === $notices ) :
			?>
			<p>Não há avisos pendentes.</p>
			<?php
else :
	?>
			<ul class="arn-newsroom-notices">
			<?php
			foreach ( $notices as $notice ) :
				?>
	<li><div><a href="<?php echo esc_url( self::url( (int) $notice->article_id ) ); ?>">Notícia #<?php echo esc_html( (string) $notice->article_id ); ?>, versão <?php echo esc_html( (string) $notice->version ); ?></a><span><?php echo esc_html( gmdate( 'd/m/Y H:i', (int) $notice->created_at ) ); ?> UTC · <?php echo null === $notice->read_at ? 'Por ler' : 'Lido'; ?></span></div>
				<?php
				if ( null === $notice->read_at ) :
					?>
	<form method="post" action="<?php echo esc_url( self::url() ); ?>"><input type="hidden" name="action" value="arn_newsroom"><input type="hidden" name="operation" value="receipt"><input type="hidden" name="article_id" value="<?php echo esc_attr( (string) $notice->id ); ?>"><?php wp_nonce_field( self::nonceAction( 'receipt', (int) $notice->id ) ); ?><button class="button" type="submit">Marcar como lido</button></form><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?></section>
		<?php
	}

	private static function form( string $operation, ?Article $article ): void {
		$id      = $article?->id ?? 0;
		$recover = null !== self::$recovery && $operation === self::$recovery['operation'] && (string) $id === (string) ( self::$recovery['post']['article_id'] ?? '0' ); // phpcs:ignore WordPress.PHP.YodaConditions.NotYoda -- All comparisons are strict and variables are immutable here.
		$post    = $recover ? self::$recovery['post'] : array();
		$errors  = $recover ? self::$recovery['errors'] : array();
		$content = null === $article ? null : $article->version( count( $article->versions ) )->content;
		$value   = static fn( string $key, string $fallback ): string => is_string( $post[ $key ] ?? null ) ? $post[ $key ] : $fallback;
		?>
		<form class="arn-newsroom-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( self::url( $id ) ); ?>" data-news-form><input type="hidden" name="action" value="arn_newsroom"><input type="hidden" name="operation" value="<?php echo esc_attr( $operation ); ?>"><?php wp_nonce_field( self::nonceAction( $operation, $id ) ); ?>
		<?php
		if ( $id > 0 ) :
			?>
			<input type="hidden" name="article_id" value="<?php echo esc_attr( (string) $id ); ?>"><input type="hidden" name="expected_revision" value="<?php echo esc_attr( $value( 'expected_revision', (string) $article->revision ) ); ?>"><?php endif; ?>
		<?php if ( 'submit' !== $operation ) : ?>
		<div class="arn-newsroom-field"><label for="arn-news-title-<?php echo esc_attr( $operation ); ?>">Título</label><input id="arn-news-title-<?php echo esc_attr( $operation ); ?>" name="title" maxlength="200" required value="<?php echo esc_attr( $value( 'title', $content?->title ?? '' ) ); ?>" <?php self::described( $errors, 'title', $operation ); ?>><small id="arn-news-title-help-<?php echo esc_attr( $operation ); ?>">Até 200 caracteres.</small><?php self::error( $errors, 'title', $operation ); ?></div>
		<div class="arn-newsroom-field"><label for="arn-news-body-<?php echo esc_attr( $operation ); ?>">Corpo da notícia</label><textarea id="arn-news-body-<?php echo esc_attr( $operation ); ?>" name="body" maxlength="20000" rows="9" <?php self::described( $errors, 'body', $operation ); ?>><?php echo esc_textarea( $value( 'body', $content?->body ?? '' ) ); ?></textarea><small id="arn-news-body-help-<?php echo esc_attr( $operation ); ?>">Texto simples. Separa os parágrafos com uma linha em branco. O corpo é obrigatório para enviar.</small><?php self::error( $errors, 'body', $operation ); ?></div>
		<div class="arn-newsroom-field"><label for="arn-news-category-<?php echo esc_attr( $operation ); ?>">Categoria</label><select id="arn-news-category-<?php echo esc_attr( $operation ); ?>" name="category" required <?php self::described( $errors, 'category', $operation ); ?>><option value="">Selecciona</option>
			<?php
			foreach ( NewsPortal::categories() as $key => $label ) :
				?>
			<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value( 'category', $content?->category ?? '' ), $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><small id="arn-news-category-help-<?php echo esc_attr( $operation ); ?>">Categoria da comunicação.</small><?php self::error( $errors, 'category', $operation ); ?></div>
			<?php self::mediaFields( $operation, $post, $errors, $content, $recover ); ?>
		<?php endif; ?>
		<div class="arn-newsroom-field"><label for="arn-news-reason-<?php echo esc_attr( $operation ); ?>">Justificação</label><input id="arn-news-reason-<?php echo esc_attr( $operation ); ?>" name="reason" maxlength="500" required value="<?php echo esc_attr( $value( 'reason', '' ) ); ?>" <?php self::described( $errors, 'reason', $operation ); ?>><small id="arn-news-reason-help-<?php echo esc_attr( $operation ); ?>">Uma linha breve para a auditoria.</small><?php self::error( $errors, 'reason', $operation ); ?></div>
		<button class="button button-primary" type="submit">
		<?php
		echo esc_html(
			match ( $operation ) {
			'create' => 'Criar rascunho',
			'revise' => 'Guardar nova versão',
			'submit' => 'Enviar para aprovação' }
		);
		?>
															</button></form>
		<?php
	}

	private static function mediaFields( string $operation, array $post, array $errors, ?Content $content, bool $recovered ): void {
		$featured = $post['featured'] ?? null;
		if ( ! $recovered && null === $featured && null !== $content?->featured ) {
			$featured = array(
				'id'          => $content->featured->blob->id,
				'description' => $content->featured->description,
			);
		}
		?>
		<fieldset class="arn-newsroom-media" data-media-group="featured" data-limit="1"><legend>Imagem de destaque</legend><p>PNG ou JPEG até 1 MiB. Obrigatória antes do envio.</p><div data-media-rows>
		<?php
		if ( is_array( $featured ) ) :
			self::mediaRow( 'featured', 0, $featured, $errors, $operation );
endif;
		?>
		</div><button class="button" type="button" data-add-media <?php echo is_array( $featured ) ? 'hidden' : ''; ?>>Adicionar destaque</button><?php self::error( $errors, 'featured', $operation ); ?></fieldset>
		<?php
		foreach ( array(
			'gallery'     => array( 'Galeria', 8, $content?->gallery ?? array() ),
			'attachments' => array( 'Anexos', 5, $content?->attachments ?? array() ),
		) as $key => [$label, $limit, $assets] ) :
			$rows = $post[ $key ] ?? array();
			if ( ! $recovered ) {
				$rows = array_map(
					static fn( Asset $asset ): array => array(
						'id'          => $asset->blob->id,
						'description' => $asset->description,
					),
					$assets
				); }
			?>
			<fieldset class="arn-newsroom-media" data-media-group="<?php echo esc_attr( $key ); ?>" data-limit="<?php echo esc_attr( (string) $limit ); ?>"><legend><?php echo esc_html( $label ); ?></legend><p><?php echo esc_html( 'gallery' === $key ? 'Até 8 imagens PNG ou JPEG, cada uma com texto alternativo.' : 'Até 5 anexos PDF, PNG ou JPEG, cada um com designação.' ); ?> Cada ficheiro pode ter até 1 MiB.</p><div data-media-rows>
			<?php
			if ( is_array( $rows ) ) :
				foreach ( $rows as $index => $row ) :
					if ( is_array( $row ) ) :
						self::mediaRow( $key, (int) $index, $row, $errors, $operation );
				endif;
endforeach;
endif;
			?>
			</div><button class="button" type="button" data-add-media>Adicionar <?php echo esc_html( 'gallery' === $key ? 'imagem' : 'anexo' ); ?></button><?php self::error( $errors, $key, $operation ); ?></fieldset><?php endforeach; ?>
			<?php
	}

	private static function mediaRow( string $kind, int $index, array $row, array $errors, string $operation ): void {
		$key  = 'featured' === $kind ? 'featured' : $kind . '_' . $index;
		$name = 'featured' === $kind ? 'featured' : $kind . '[' . $index . ']';
		$file = match ( $kind ) {
			'featured' => 'featured_file', 'gallery' => 'gallery_file[' . $index . ']', default => 'attachment_file[' . $index . ']' };
		$label = 'attachments' === $kind ? 'Designação' : 'Texto alternativo';
		$id    = 'arn-news-' . $kind . '-' . $index . '-' . $operation;
		?>
		<div class="arn-newsroom-media-row" data-media-row><div class="arn-newsroom-media-row-head"><strong>Posição <span data-position><?php echo esc_html( (string) ( $index + 1 ) ); ?></span></strong><div><button class="button" type="button" data-move="up" aria-label="Mover para cima">Subir</button><button class="button" type="button" data-move="down" aria-label="Mover para baixo">Descer</button><button class="button" type="button" data-remove-media>Retirar</button></div></div><input type="hidden" name="<?php echo esc_attr( $name . '[id]' ); ?>" value="<?php echo esc_attr( is_string( $row['id'] ?? null ) ? $row['id'] : '' ); ?>"><div class="arn-newsroom-field"><label for="<?php echo esc_attr( $id ); ?>-description"><?php echo esc_html( $label ); ?></label><input type="text" id="<?php echo esc_attr( $id ); ?>-description" name="<?php echo esc_attr( $name . '[description]' ); ?>" value="<?php echo esc_attr( is_string( $row['description'] ?? null ) ? $row['description'] : '' ); ?>" maxlength="<?php echo 'attachments' === $kind ? '160' : '300'; ?>" required <?php self::described( $errors, $key, $operation ); ?>><small id="arn-news-<?php echo esc_attr( $key . '-help-' . $operation ); ?>">Usa uma descrição compreensível para quem não vê o ficheiro.</small><?php self::error( $errors, $key, $operation ); ?></div><div class="arn-newsroom-field"><label for="<?php echo esc_attr( $id ); ?>-file"><?php echo esc_html( '' === ( $row['id'] ?? '' ) ? 'Seleccionar ficheiro' : 'Ficheiro conservado. Deixa vazio para manter.' ); ?></label><input id="<?php echo esc_attr( $id ); ?>-file" type="file" name="<?php echo esc_attr( $file ); ?>" accept="<?php echo 'attachments' === $kind ? '.pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg' : '.png,.jpg,.jpeg,image/png,image/jpeg'; ?>"><small>Após erro, selecciona novamente o ficheiro novo.</small></div></div>
		<?php
	}

	private static function described( array $errors, string $key, string $operation ): void {
		$help  = 'arn-news-' . $key . '-help-' . $operation;
		$error = 'arn-news-' . $key . '-error-' . $operation;
		echo 'aria-describedby="' . esc_attr( $help . ( isset( $errors[ $key ] ) ? ' ' . $error : '' ) ) . '"';
		if ( isset( $errors[ $key ] ) ) {
			echo ' aria-invalid="true"'; }
	}

	private static function error( array $errors, string $key, string $operation ): void {
		if ( isset( $errors[ $key ] ) ) {
			echo '<span class="arn-newsroom-field-error" id="' . esc_attr( 'arn-news-' . $key . '-error-' . $operation ) . '" role="alert">' . esc_html( $errors[ $key ] ) . '</span>';
		}
	}
}
