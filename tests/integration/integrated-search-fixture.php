<?php
/** Search acceptance inside the exclusive Services fixture ownership boundary.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationStorageService, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository, ChangeContext};
use Arn\Intranet\Documents\PrivateFiles\{BlobDescriptor, FileSchema};
use Arn\Intranet\Documents\Catalog\{CatalogSchema, CurrentDocumentActor, WpdbDocumentAuthority, WpdbDocumentRepository, Metadata, Revision};
use Arn\Intranet\News\{NewsSchema, CurrentNewsActor, WpdbNewsAuthority, WpdbNewsRepository, Content, Version, Asset, Audience};
use Arn\Intranet\F112\Portal as F112Portal;
use Arn\Intranet\Search\WordPressSources;
use Arn\Intranet\EditorialPreview\Portal;

// phpcs:disable Squiz.Commenting, WordPress.WP.AlternativeFunctions -- Synthetic fixture only, never loaded by the plugin.
function arn_integrated_search_fixture( object $db, int $admin, array $people, array $f112, Closure $login, Closure $assert, Closure $snapshot, array $tables, array &$owned_anchors ): void {
	if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'local' !== wp_get_environment_type() ) {
		throw new RuntimeException( 'Pesquisa fictícia apenas em CLI local.' ); }
	wp_set_current_user( $admin );
	foreach ( array( 'ARN_ALLOW_LOCAL_NEWSROOM', 'ARN_ALLOW_LOCAL_PRIVATE_FILES', 'ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW', 'ARN_ALLOW_LOCAL_DOCUMENT_CATALOG' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true ); }
		if ( true !== constant( $flag ) ) {
			throw new RuntimeException( 'Opt-in de pesquisa recusado.' ); }
	}
	( new FileSchema( $db ) )->install();
	( new CatalogSchema( $db ) )->install();
	( new NewsSchema( $db ) )->install();
	foreach ( array(
		'noticias'   => 'news',
		'documentos' => 'documents',
	) as $slug => $view ) {
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_name'    => $slug,
				'post_title'   => 'Colecção fictícia ' . $slug,
				'post_content' => '<!-- wp:arn/portal-view {"view":"' . $view . '"} /-->',
			),
			true
		);
		$assert( is_int( $id ) && $id > 0, 'colecção nativa de pesquisa' );
	}
	$auth     = new WpdbAuthorizationRepository( $db );
	$service  = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
	$context  = static fn(): ChangeContext => new ChangeContext( $admin, 'Preparação fictícia da pesquisa integrada', 'LAB-SEARCH', time() );
	$accounts = array(
		'author'       => 'autor.noticias.teste',
		'editor'       => 'editor.noticias.teste',
		'alternate'    => 'editor.suplente.teste',
		'news_a'       => 'colaborador.noticias.a.teste',
		'news_b'       => 'colaborador.noticias.b.teste',
		'docs'         => 'ana.teste',
		'docs_manager' => 'rh.documentos.teste',
	);
	$ids      = array();
	foreach ( $accounts as $label => $user_login ) {
		$id = wp_insert_user(
			array(
				'user_login'   => $user_login,
				'user_email'   => $user_login . '@example.invalid',
				'user_pass'    => bin2hex( random_bytes( 24 ) ),
				'display_name' => 'Pesquisa ' . $label . ' Teste',
				'role'         => 'subscriber',
				'meta_input'   => array( LocalFixtureUser::META_KEY => '1' ),
			)
		);
		if ( ! is_int( $id ) || $id < 1 ) {
			throw new RuntimeException( 'Conta de pesquisa fictícia recusada.' ); }
		$ids[ $label ] = $id;
		$service->createAccount( $id, $context() );
		$service->changeState( $id, AccountState::Active, $auth->find( $id )->revision, $context() );
		$grants = 'docs_manager' === $label ? array( array( Role::HumanResources, Scope::units( array( 'unidade-ficticia-a' ) ) ) ) : array( array( Role::Employee, Scope::own() ) );
		if ( in_array( $label, array( 'editor', 'alternate' ), true ) ) {
			$grants[] = array( Role::Editorial, Scope::queues( array( 'editorial-ficticia' ) ) );
			$grants[] = array( Role::Editorial, Scope::audiences( array( 'news_all', 'news_unit_LAB-A', 'news_unit_LAB-B' ) ) );
		}
		foreach ( $grants as [$role, $scope] ) {
			$service->createGrant( new Grant( $id, $role, $scope, time(), null ), $auth->find( $id )->revision, $context() );
		}
	}
	$current_news = new CurrentNewsActor();
	$news         = new WpdbNewsRepository( $db, new WpdbNewsAuthority( $db, $current_news, null, $current_news->participantUnits( ... ) ) );
	$content      = static fn( string $title, string $unit ): Content => new Content( $title, 'Corpo publicado fictício', 'circular', new Asset( 'featured', new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'a', 64 ), 12, 'image/png' ), 'Imagem sintética de metadados' ), array(), array(), Audience::units( array( $unit ) ) );
	$news_ids     = array();
	foreach ( array( 'A', 'B', 'rascunho' ) as $label ) {
		$login( $ids['author'] );
		$article            = $news->create( $ids['author'], Scope::queues( array( 'editorial-ficticia' ) ), new Version( 1, $content( 'Pesquisa notícia ' . $label, 'B' === $label ? 'LAB-B' : 'LAB-A' ), $ids['author'], time() ), 'Notícia sintética' );
		$owned_anchors[]    = $article->id;
		$news_ids[ $label ] = $article->id;
		if ( 'rascunho' !== $label ) {
			$news->submit( $article->id, 1, 'Submissão sintética' );
			$login( $ids['editor'] );
			$news->decide( $article->id, 'approve', 1, 2, 'Aprovação sintética' );
		}
	}
	$login( $ids['author'] );
	$news->revise( $news_ids['A'], new Version( 2, $content( 'SEGREDO_EDITORIAL', 'LAB-A' ), $ids['author'], time() ), 3, 'Revisão privada sintética' );
	$login( $ids['docs_manager'] );
	$documents    = new WpdbDocumentRepository( $db, new WpdbDocumentAuthority( $db, new CurrentDocumentActor() ) );
	$metadata     = static fn( string $title, int $recipient ): Metadata => new Metadata( $title, 'Resumo sintético', 'regulamento', 'LAB/RG/001', 'Unidade fictícia', '2030-01-01', null, null, array( $recipient ) );
	$blob         = static fn(): BlobDescriptor => new BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'b', 64 ), 12, 'application/pdf' );
	$document_ids = array();
	foreach ( array( 'autorizado', 'reservado RH', 'rascunho' ) as $label ) {
		$doc                    = $documents->create( Scope::units( array( 'unidade-ficticia-a' ) ), new Revision( 1, $metadata( 'Pesquisa regulamento ' . $label, 'reservado RH' === $label ? $ids['docs_manager'] : $ids['docs'] ), $blob(), $ids['docs_manager'], time() ), 'Documento sintético' );
		$owned_anchors[]        = $doc->id;
		$document_ids[ $label ] = $doc->id;
		if ( 'rascunho' !== $label ) {
			$documents->publish( $doc->id, 1, 1, 'Publicação documental sintética' ); }
	}
	$documents->revise( $document_ids['autorizado'], new Revision( 2, $metadata( 'SEGREDO_DOCUMENTAL', $ids['docs'] ), $blob(), $ids['docs_manager'], time() ), 2, 'Revisão documental privada' );
	$mutate   = static function ( string $module, string $actor, string $operation, array $input ) use ( $login, $f112, &$owned_anchors ): int {
		$login( $f112[ $actor ] );
		$id = ( new F112Portal( $module ) )->mutate(
			array_merge(
				$input,
				array(
					'operation' => $operation,
					'reason'    => 'Pesquisa integrada fictícia',
				)
			)
		);
		if ( 'create' === $operation ) {
			$owned_anchors[] = $id; }
		return $id;
	};
	$comm_ids = array();
	foreach ( range( 1, 25 ) as $number ) {
		$id = $mutate(
			'communications',
			'manager',
			'create',
			array(
				'title'       => 'Pesquisa comunicado A ' . str_pad( (string) $number, 2, '0', STR_PAD_LEFT ),
				'body'        => 'Texto do comunicado sintético',
				'audience'    => 'LAB-A',
				'circuit'     => 'team-direct',
				'valid_until' => '',
			)
		);
		$mutate(
			'communications',
			'manager',
			'publish',
			array(
				'id'       => (string) $id,
				'revision' => '1',
			)
		);
		$comm_ids[] = $id;
	}
	$now = time();
	foreach ( array( array( 'próximo A', 'LAB-A', 86400 ), array( 'histórico A', 'LAB-A', -86400 ), array( 'cancelado A', 'LAB-A', 86400 ), array( 'reservado B', 'LAB-B', 86400 ) ) as [$label, $audience, $seconds] ) {
		$id = $mutate(
			'calendar',
			'events',
			'create',
			array(
				'title'              => 'Pesquisa evento ' . $label,
				'description'        => 'Descrição do evento sintético',
				'audience'           => $audience,
				'mode'               => 'presencial',
				'location'           => 'Sala de teste',
				'url'                => '',
				'all_day'            => '0',
				'start_at'           => gmdate( 'Y-m-d\TH:i:s\Z', $now + $seconds ),
				'end_at'             => gmdate( 'Y-m-d\TH:i:s\Z', $now + $seconds + 3600 ),
				'start_date'         => '',
				'end_date_exclusive' => '',
			)
		);
		$mutate(
			'calendar',
			'events',
			'publish',
			array(
				'id'       => (string) $id,
				'revision' => '1',
			)
		);
		if ( 'cancelado A' === $label ) {
			$mutate(
				'calendar',
				'events',
				'cancel',
				array(
					'id'       => (string) $id,
					'revision' => '2',
				)
			); }
	}
	$before = array();
	foreach ( $tables as $table ) {
		$before[ $table ] = $snapshot( $table ); }
	$search = static fn( string $q, string $type = '', string $page = '1' ): array => Portal::model(
		'home',
		array(
			'arn_search'      => '1',
			'q'               => $q,
			'type'            => $type,
			'arn_search_page' => $page,
		)
	);
	$login( $f112['a'] );
	$all    = $search( 'Pesquisa' );
	$assert( 28 === $all['total'] && 3 === $all['pages'] && 10 === count( $all['items'] ), 'pesquisa F112 conta só audiência A antes de paginar' );
	$assert( 25 === $search( 'Pesquisa', 'communications' )['total'] && 5 === count( $search( 'Pesquisa', 'communications', '3' )['items'] ), 'paginação global atravessa páginas nativas de vinte' );
	$assert( array( 'Evento', 'Evento passado', 'Evento cancelado' ) === array_column( $search( 'Pesquisa', 'calendar' )['items'], 'type' ), 'pesquisa distingue próximos, históricos e cancelados' );
	$login( $f112['b'] );
	$assert( 1 === $search( 'Pesquisa' )['total'] && 'Pesquisa evento reservado B' === $search( 'Pesquisa' )['items'][0]['title'], 'pesquisa B não conta comunicados ou eventos de A' );
	$login( $people['alfa'] );
	$directory = $search( 'Teste' );
	$assert( $directory['total'] > 0 && array( 'Funcionário' ) === array_values( array_unique( array_column( $directory['items'], 'type' ) ) ), 'pesquisa People usa apenas contactos elegíveis' );
	$login( $ids['news_a'] );
	$assert( array( 'Pesquisa notícia A' ) === array_column( $search( 'Pesquisa' )['items'], 'title' ), 'notícia aprovada continua pesquisável durante revisão privada' );
	$assert( 0 === $search( 'SEGREDO_EDITORIAL' )['total'], 'pesquisa não encontra título de versão editorial privada' );
	$login( $ids['news_b'] );
	$assert( array( 'Pesquisa notícia B' ) === array_column( $search( 'Pesquisa' )['items'], 'title' ), 'notícia B não expõe título, rascunho ou contagem de A' );
	$login( $ids['docs'] );
	$assert( array( 'Pesquisa regulamento autorizado' ) === array_column( $search( 'Pesquisa' )['items'], 'title' ), 'regulamento publicado usa destinatário e versão aprovados' );
	$assert( 0 === $search( 'SEGREDO_DOCUMENTAL' )['total'] && 0 === $search( 'reservado RH' )['total'], 'pesquisa documental não encontra rascunhos, revisão ou destinatário alheio' );
	$large_page = \Arn\Intranet\Documents\Catalog\DocumentPortal::model(
		'documents',
		array(
			'q'        => 'Pesquisa',
			'arn_page' => '1000',
		)
	);
	$assert( $large_page['available'] && 1 === $large_page['total'] && array() === $large_page['items'], 'paginação documental aceita o intervalo canónico da pesquisa integrada' );
	foreach ( array(
		'documentos' => $ids['docs'],
		'noticias'   => $ids['news_a'],
	) as $slug => $actor ) {
		$native_page      = get_page_by_path( $slug, OBJECT, 'page' );
		$original_content = $native_page->post_content;
		wp_update_post(
			array(
				'ID'           => $native_page->ID,
				'post_content' => 'Colecção fictícia indisponível',
			)
		);
		try {
			$login( $actor );
			$assert( 0 === $search( 'Pesquisa' )['total'], 'colecção adulterada não fornece resultados ' . $slug );
			$login( $f112['a'] );
			$assert( 28 === $search( 'Pesquisa' )['total'], 'outras fontes conservam resultados com colecção adulterada ' . $slug );
		} finally {
			wp_update_post(
				array(
					'ID'           => $native_page->ID,
					'post_content' => $original_content,
				)
			);
		}
	}
	$login( $ids['docs_manager'] );
	$assert( 2 === $search( 'Pesquisa' )['total'] && 0 === $search( 'SEGREDO_DOCUMENTAL' )['total'], 'gestão documental não acrescenta pesquisa de versões privadas' );
	wp_set_current_user( $admin );
	$assert( 0 === $search( 'Pesquisa' )['total'], 'administrador técnico não pesquisa originais ou conteúdos de negócio' );
	foreach ( array( array( $f112['a'], 'Pesquisa', 28 ), array( $ids['news_a'], 'Pesquisa', 1 ), array( $ids['docs'], 'Pesquisa', 1 ), array( $people['alfa'], 'Teste', null ) ) as [$actor, $expression, $expected] ) {
		$login( $actor );
		$connections_before = (int) $db->get_var( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE()' );
		for ( $repeat = 0; $repeat < 3; ++$repeat ) {
			$count = $search( $expression )['total'];
			$assert( null === $expected ? $count > 0 : $count === $expected, 'pesquisa repetida conserva resultados autorizados' );
		}
		$connections_after = (int) $db->get_var( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE()' );
		$assert( '' === $db->last_error && $connections_before > 0 && $connections_before === $connections_after, 'adaptadores libertam ligações próprias após pesquisas repetidas' );
	}
	$login( $f112['a'], false );
	$assert( 0 === $search( 'Pesquisa' )['total'], 'geração de sessão antiga não devolve itens ou contagens' );
	$login( $f112['a'] );
	$assert(
		Portal::model(
			'home',
			array(
				'arn_search' => '1',
				'q'          => array( 'Pesquisa' ),
			)
		)['invalid'],
		'consulta estruturada recusada antes das fontes'
	);
	$original_method           = $_SERVER['REQUEST_METHOD'] ?? null;
	$_SERVER['REQUEST_METHOD'] = 'POST';
	try {
		$assert( 0 === $search( 'Pesquisa' )['total'], 'POST de pesquisa não consulta fontes de negócio' ); } finally {
		if ( null === $original_method ) {
			unset( $_SERVER['REQUEST_METHOD'] );
		} else {
			$_SERVER['REQUEST_METHOD'] = $original_method; }
		}
		// Private browser pause may be inserted here by the local, ignored QA harness.
		foreach ( $before as $table => $rows ) {
			$assert( $rows === $snapshot( $table ), 'pesquisa conserva tabela preenchida ' . $table ); }
		wp_set_current_user( $admin );
		$service->changeState( $ids['news_a'], AccountState::Suspended, $auth->find( $ids['news_a'] )->revision, $context() );
		$login( $ids['news_a'] );
		$assert( 0 === $search( 'Pesquisa' )['total'], 'conta suspensa não devolve notícias nem contagens' );
		wp_set_current_user( $admin );
		$service->changeState( $ids['news_a'], AccountState::Active, $auth->find( $ids['news_a'] )->revision, $context() );
		wp_set_current_user( $ids['news_a'] );
		$assert( 0 === $search( 'Pesquisa' )['total'], 'reactivação não recupera a geração anterior da sessão' );
		$login( $ids['news_a'] );
		$assert( 1 === $search( 'Pesquisa' )['total'], 'novo login após reactivação recupera apenas a publicação autorizada' );
		$login( $f112['a'] );
		foreach ( $comm_ids as $id ) {
			$mutate(
				'communications',
				'manager',
				'archive',
				array(
					'id'       => (string) $id,
					'revision' => '2',
				)
			); }
		$login( $f112['a'] );
}
