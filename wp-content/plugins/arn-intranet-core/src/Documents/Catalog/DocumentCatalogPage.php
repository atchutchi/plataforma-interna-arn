<?php
/**
 * Local CMS document management.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Scope;
use Throwable;

// phpcs:disable Squiz.Commenting.FunctionComment,WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
/** Business-policy CMS page, separate from the native private CPT editor. */
final class DocumentCatalogPage {
	private const SLUG  = 'arn-document-catalog';
	private const SCOPE = 'unidade-ficticia-a';
	private const OWNER = 'Laboratório documental / Unidade fictícia A';

	public static function register(): void {
		add_action( 'init', array( self::class, 'registerPostType' ) );
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_post_arn_document_catalog', array( self::class, 'submit' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
	}

	/** The CPT is a generic transactional anchor. Native WordPress routes have no business bypass. */
	public static function registerPostType(): void {
		$denied = array_fill_keys(
			array(
				'edit_post',
				'read_post',
				'delete_post',
				'edit_posts',
				'edit_others_posts',
				'publish_posts',
				'read_private_posts',
				'delete_posts',
				'delete_private_posts',
				'delete_published_posts',
				'delete_others_posts',
				'edit_private_posts',
				'edit_published_posts',
				'create_posts',
			),
			'do_not_allow'
		);
		register_post_type(
			'arn_documento',
			array(
				'label'               => 'Documento privado',
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
				'capability_type'     => 'arn_document',
				'map_meta_cap'        => false,
				'capabilities'        => $denied,
			)
		);
	}

	private static function allowed(): bool {
		$actor = ( new CurrentDocumentActor() )->read();
		return LocalDocumentCatalog::configured() && null !== $actor
			&& DocumentPolicy::canManageScope( Scope::units( array( self::SCOPE ) ), $actor[0], $actor[2], time() );
	}

	public static function menu(): void {
		if ( self::allowed() ) {
			add_menu_page( 'Catálogo documental', 'Catálogo documental', 'read', self::SLUG, array( self::class, 'render' ), 'dashicons-media-document', 26 );
		}
	}

	public static function assets( string $hook ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook || ! self::allowed() ) {
			return;
		}
		$path = dirname( __DIR__, 3 ) . '/assets/css/document-catalog.css';
		wp_enqueue_style( 'arn-document-catalog', plugins_url( 'assets/css/document-catalog.css', dirname( __DIR__, 3 ) . '/arn-intranet-core.php' ), array(), (string) filemtime( $path ) );
	}

	private static function roster(): array {
		$result = array();
		foreach ( array( LocalFixtureUser::LOGIN, 'rh.documentos.teste' ) as $login ) {
			$user = get_user_by( 'login', $login );
			if ( $user instanceof \WP_User && '1' === get_user_meta( $user->ID, LocalFixtureUser::META_KEY, true ) ) {
				$result[ $user->ID ] = $user->display_name;
			}
		}
		return $result;
	}

	private static function field( array $input, string $key ): string {
		$value = $input[ $key ] ?? '';
		if ( ! is_string( $value ) ) {
			throw new \InvalidArgumentException( 'Campo inválido.' );
		}
		return trim( $value );
	}

	private static function metadata( array $input ): Metadata {
		$roster   = self::roster();
		$received = $input['recipient_ids'] ?? array();
		if ( ! is_array( $received ) || array() === $received ) {
			throw new \InvalidArgumentException( 'Selecciona pelo menos um destinatário.' );
		}
		$ids = array();
		foreach ( $received as $value ) {
			if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]*$/D', $value ) || ! isset( $roster[ (int) $value ] ) ) {
				throw new \InvalidArgumentException( 'Destinatário inválido.' );
			}
			$ids[] = (int) $value;
		}
		return new Metadata(
			self::field( $input, 'title' ),
			self::field( $input, 'summary' ),
			self::field( $input, 'category' ),
			self::field( $input, 'source_reference' ),
			self::OWNER,
			self::field( $input, 'review_on' ),
			'' === self::field( $input, 'valid_from' ) ? null : self::field( $input, 'valid_from' ),
			'' === self::field( $input, 'valid_until' ) ? null : self::field( $input, 'valid_until' ),
			$ids
		);
	}

	private static function upload( bool $required ): ?string {
		$file = $_FILES['original'] ?? null;
		if ( ! is_array( $file ) || ! isset( $file['error'] ) || UPLOAD_ERR_NO_FILE === $file['error'] ) {
			if ( $required ) {
				throw new \InvalidArgumentException( 'Selecciona um original PDF, PNG ou JPEG.' );
			}
			return null;
		}
		if ( UPLOAD_ERR_OK !== $file['error'] || ! is_int( $file['size'] ) || $file['size'] < 1 || $file['size'] > 1048576
			|| ! is_string( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			throw new \InvalidArgumentException( 'O original não é um upload PHP válido até 1 MiB.' );
		}
		return $file['tmp_name'];
	}

	private static function positive( array $input, string $key ): int {
		$value = self::field( $input, $key );
		if ( 1 !== preg_match( '/^[1-9][0-9]{0,9}$/D', $value ) ) {
			throw new \InvalidArgumentException( 'Identificador ou revisão inválida.' );
		}
		return (int) $value;
	}

	/**
	 * Present the object row returned by WpdbDocumentRepository::history().
	 *
	 * @param \stdClass $event Stored audit row.
	 * @return string Portuguese audit summary, escaped by the caller.
	 */
	public static function auditLine( \stdClass $event ): string {
		$action = match ( $event->event ) {
			'created' => 'Rascunho criado',
			'revised' => 'Nova versão',
			'published' => 'Versão publicada',
			'archived' => 'Documento arquivado',
			default => 'Operação documental',
		};
		return 'Revisão ' . (int) $event->revision . ': ' . $action
			. '. Conta #' . (int) $event->actor_id
			. '. ' . gmdate( 'd/m/Y H:i', (int) $event->occurred_at ) . ' UTC'
			. '. Justificação: ' . $event->reason;
	}

	public static function submit(): void {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Operação documental não autorizada.', 'arn-intranet-core' ), '', array( 'response' => 403 ) );
		}
		$input     = wp_unslash( $_POST );
		$operation = isset( $input['operation'] ) && is_string( $input['operation'] ) ? $input['operation'] : '';
		$nonce     = isset( $input['_wpnonce'] ) && is_string( $input['_wpnonce'] ) ? $input['_wpnonce'] : '';
		if ( ! in_array( $operation, array( 'create', 'revise', 'publish', 'archive' ), true )
			|| ! wp_verify_nonce( $nonce, 'arn_document_' . $operation ) ) {
			wp_die( esc_html__( 'Pedido inválido. Atualiza a página e volta a tentar.', 'arn-intranet-core' ), '', array( 'response' => 403 ) );
		}
		try {
			$catalog  = new LocalDocumentCatalog();
			$reason   = self::field( $input, 'reason' );
			$document = match ( $operation ) {
				'create' => $catalog->create( Scope::units( array( self::SCOPE ) ), self::metadata( $input ), self::upload( true ), $reason ),
				'revise' => $catalog->revise( self::positive( $input, 'document_id' ), self::metadata( $input ), self::upload( false ), self::positive( $input, 'expected_revision' ), $reason ),
				'publish' => $catalog->publish( self::positive( $input, 'document_id' ), self::positive( $input, 'version' ), self::positive( $input, 'expected_revision' ), $reason ),
				'archive' => $catalog->archive( self::positive( $input, 'document_id' ), self::positive( $input, 'expected_revision' ), $reason ),
			};
			$url = add_query_arg(
				array(
					'page'        => self::SLUG,
					'document_id' => $document->id,
					'notice'      => 'saved',
				),
				admin_url( 'admin.php' )
			);
		} catch ( Throwable ) {
			$url = add_query_arg(
				array(
					'page'   => self::SLUG,
					'notice' => 'failed',
				),
				admin_url( 'admin.php' )
			);
		}
		wp_safe_redirect( $url );
		exit;
	}

	public static function render(): void {
		if ( ! self::allowed() ) {
			wp_die( esc_html__( 'Operação documental não autorizada.', 'arn-intranet-core' ), '', array( 'response' => 403 ) );
		}
		$catalog   = new LocalDocumentCatalog();
		$actor     = ( new CurrentDocumentActor() )->read();
		$documents = array_values( array_filter( $catalog->visible() ?? array(), static fn( Document $document ): bool => null !== $actor && DocumentPolicy::canManage( $document, $actor[0], $actor[2], time() ) ) );
		$chosen    = isset( $_GET['document_id'] ) && is_string( $_GET['document_id'] ) && ctype_digit( $_GET['document_id'] ) ? $catalog->findVisible( (int) $_GET['document_id'] ) : null;
		if ( null !== $chosen && ( null === $actor || ! DocumentPolicy::canManage( $chosen, $actor[0], $actor[2], time() ) ) ) {
			$chosen = null;
		}
		$history = null === $chosen ? null : $catalog->historyVisible( $chosen->id );
		$roster  = self::roster();
		?>
		<div class="wrap arn-document-admin"><h1>Catálogo documental local</h1>
		<p>Ambiente fictício. Os originais são privados e a publicação exige uma decisão explícita.</p>
		<?php
		if ( 'saved' === ( $_GET['notice'] ?? '' ) ) :
			?>
			<div class="notice notice-success"><p>Operação guardada. Confirma a versão e o estado abaixo.</p></div><?php endif; ?>
		<?php
		if ( 'failed' === ( $_GET['notice'] ?? '' ) ) :
			?>
			<div class="notice notice-error"><p>Não foi possível guardar. Confirma os campos, a revisão concorrente, os destinatários e o original.</p></div><?php endif; ?>
		<nav aria-label="Documentos geridos"><h2>Documentos no teu âmbito</h2>
		<?php
		if ( array() === $documents ) :
			?>
			<p>Ainda não há documentos. Cria um rascunho abaixo.</p><?php endif; ?>
		<ul>
		<?php
		foreach ( $documents as $document ) :
			$latest = $document->version( count( $document->versions ) );
			?>
		<li><a href="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'page'        => self::SLUG,
						'document_id' => $document->id,
					),
					admin_url( 'admin.php' )
				)
			);
			?>
						"><?php echo esc_html( $latest->metadata->title ); ?></a> <span>Versão <?php echo esc_html( (string) count( $document->versions ) ); ?>, <?php echo esc_html( $document->archived ? 'arquivado' : ( null === $document->published_version ? 'rascunho' : 'publicada v' . $document->published_version ) ); ?></span></li>
		<?php endforeach; ?></ul></nav>
		<?php if ( null !== $chosen ) : ?>
		<section><h2><?php echo esc_html( $chosen->version( count( $chosen->versions ) )->metadata->title ); ?></h2><p>Revisão concorrente <?php echo esc_html( (string) $chosen->revision ); ?>. Versão mais recente <?php echo esc_html( (string) count( $chosen->versions ) ); ?>. Estado <?php echo esc_html( $chosen->archived ? 'arquivado' : ( null === $chosen->published_version ? 'rascunho' : 'publicada v' . $chosen->published_version ) ); ?>.</p>
		<h3>Versões e originais</h3><ul>
			<?php
			foreach ( $chosen->versions as $version ) :
				?>
			<li>v<?php echo esc_html( (string) $version->number ); ?>: <?php echo esc_html( $version->metadata->title ); ?>. <a href="<?php echo esc_url( add_query_arg( 'arn_document_file', $version->blob->id, home_url( '/' ) ) ); ?>">Descarregar original</a></li><?php endforeach; ?></ul>
			<?php
			if ( is_array( $history ) ) :
				?>
				<h3>Auditoria</h3><ul>
				<?php
				foreach ( $history as $event ) :
					?>
				<li><?php echo esc_html( self::auditLine( $event ) ); ?></li><?php endforeach; ?></ul><?php endif; ?>
			<?php if ( ! $chosen->archived ) : ?>
		<h3>Acrescentar revisão</h3><?php self::form( 'revise', $chosen, $roster ); ?>
		<h3>Publicar versão <?php echo esc_html( (string) count( $chosen->versions ) ); ?></h3><?php self::form( 'publish', $chosen, $roster ); ?>
		<h3>Arquivar definitivamente</h3><?php self::form( 'archive', $chosen, $roster ); ?>
		<?php endif; ?></section>
		<?php endif; ?>
		<section><h2>Criar rascunho</h2><?php self::form( 'create', null, $roster ); ?></section></div>
		<?php
	}

	private static function form( string $operation, ?Document $document, array $roster ): void {
		$metadata = null === $document ? null : $document->version( count( $document->versions ) )->metadata;
		?>
		<form class="arn-document-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="arn_document_catalog"><input type="hidden" name="operation" value="<?php echo esc_attr( $operation ); ?>">
		<?php wp_nonce_field( 'arn_document_' . $operation ); ?>
		<?php
		if ( null !== $document ) :
			?>
			<input type="hidden" name="document_id" value="<?php echo esc_attr( (string) $document->id ); ?>"><input type="hidden" name="expected_revision" value="<?php echo esc_attr( (string) $document->revision ); ?>"><?php endif; ?>
		<?php if ( in_array( $operation, array( 'create', 'revise' ), true ) ) : ?>
		<label>Título <input name="title" maxlength="200" required value="<?php echo esc_attr( $metadata?->title ?? '' ); ?>"></label>
		<label>Resumo <textarea name="summary" maxlength="4000"><?php echo esc_textarea( $metadata?->summary ?? '' ); ?></textarea></label>
		<label>Categoria <select name="category" required>
			<?php
			foreach ( array(
				'circular'             => 'Circular',
				'nota-interna'         => 'Nota interna',
				'despacho'             => 'Despacho',
				'regulamento'          => 'Regulamento',
				'comunicacao-sindical' => 'Comunicação sindical',
			) as $value => $label ) :
				?>
																	<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $metadata?->category ?? '', $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></label>
		<label>Referência de origem <input name="source_reference" maxlength="200" required value="<?php echo esc_attr( $metadata?->source_reference ?? '' ); ?>"></label>
		<p>Proprietário institucional: <?php echo esc_html( self::OWNER ); ?>.</p>
		<label>Data de revisão <input type="date" name="review_on" required value="<?php echo esc_attr( $metadata?->review_on ?? '' ); ?>"></label>
		<label>Válido desde <input type="date" name="valid_from" value="<?php echo esc_attr( $metadata?->valid_from ?? '' ); ?>"></label>
		<label>Válido até <input type="date" name="valid_until" value="<?php echo esc_attr( $metadata?->valid_until ?? '' ); ?>"></label>
		<fieldset><legend>Destinatários fictícios</legend>
			<?php
			foreach ( $roster as $id => $name ) :
				?>
			<label><input type="checkbox" name="recipient_ids[]" value="<?php echo esc_attr( (string) $id ); ?>" <?php checked( null !== $metadata && in_array( $id, $metadata->recipient_ids, true ) ); ?>><?php echo esc_html( $name ); ?></label><?php endforeach; ?></fieldset>
		<label>Original PDF, PNG ou JPEG, até 1 MiB <?php echo 'create' === $operation ? '(obrigatório)' : '(opcional, sem ficheiro duplica o original verificado)'; ?><input type="file" name="original" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" <?php echo 'create' === $operation ? 'required' : ''; ?>></label>
		<?php endif; ?>
		<?php
		if ( 'publish' === $operation ) :
			?>
			<input type="hidden" name="version" value="<?php echo esc_attr( (string) count( $document->versions ) ); ?>"><p>Será publicada a versão <?php echo esc_html( (string) count( $document->versions ) ); ?>. A versão publicada actual é <?php echo esc_html( null === $document->published_version ? 'nenhuma' : (string) $document->published_version ); ?>.</p><?php endif; ?>
		<?php
		if ( 'archive' === $operation ) :
			?>
			<p>Esta acção é definitiva. O documento deixará de estar disponível aos destinatários.</p><?php endif; ?>
		<label>Justificação <textarea name="reason" maxlength="500" required></textarea></label>
		<button class="button button-primary" type="submit">
		<?php
		echo esc_html(
			match ( $operation ) {
			'create' => 'Criar rascunho',
			'revise' => 'Guardar nova versão',
			'publish' => 'Publicar versão',
			'archive' => 'Arquivar definitivamente' }
		);
		?>
															</button>
		</form>
		<?php
	}
}
