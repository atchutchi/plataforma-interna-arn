<?php
/**
 * Apresentação editorial. Os dados chegam já autorizados pelo plugin.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$portal = $args['portal'] ?? array();
if ( ! is_array( $portal ) || empty( $portal['view'] ) ) {
	return;
}
if ( 'news_preview' === $portal['view'] && is_array( $portal['news'] ?? null ) ) {
	get_template_part( 'portal/news-preview', null, array( 'news' => $portal['news'] ) );
	return;
}
if ( 'news_published' === $portal['view'] && is_array( $portal['news'] ?? null ) ) {
	get_template_part( 'portal/news-published', null, array( 'news' => $portal['news'] ) );
	return;
}
if ( 'news' === $portal['view'] && ! empty( $portal['published_news'] ) ) {
	get_template_part( 'portal/news-list', null, array( 'portal' => $portal ) );
	return;
}
if ( in_array( $portal['view'], array( 'documents', 'regulations' ), true ) && ! empty( $portal['catalog'] ) ) {
	get_template_part( 'portal/document-catalog', null, array( 'portal' => $portal ) );
	return;
}
$view         = $portal['view'];
$titles       = array(
	'home'        => 'Início',
	'news'        => 'Notícias',
	'documents'   => 'Documentos',
	'regulations' => 'Regulamentos',
	'services'    => 'Serviços',
);
$portal_title = $titles[ $view ] ?? 'Portal interno';
$selected     = $portal['selected'] ?? null;
$categories   = $portal['categories'] ?? array();
$available    = ! empty( $portal['available'] );
$current      = get_permalink();
$collection   = add_query_arg(
	array(
		'q'        => $portal['query'],
		'category' => $portal['category'],
		'arn_year' => $portal['year'],
		'arn_page' => $portal['page'],
	),
	$current
);
$intro        = array(
	'news'        => 'Circulares, notas internas, despachos e comunicação sindical. Consulta as comunicações por assunto, categoria ou ano.',
	'documents'   => 'Encontra os documentos do arquivo e consulta a sua fonte original.',
	'regulations' => 'Consulta os regulamentos disponíveis e a informação documental que os acompanha.',
	'services'    => 'Conhece os serviços previstos para o portal e acompanha a sua preparação.',
);
?>
<div class="arn-portal alignwide">
	<?php if ( 'home' !== $view || $selected ) : ?>
		<nav class="arn-portal-breadcrumb" aria-label="Localização">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a><span aria-hidden="true">/</span>
			<?php
			if ( $selected ) :
				?>
				<a href="<?php echo esc_url( $collection ); ?>"><?php echo esc_html( $portal_title ); ?></a><span aria-hidden="true">/</span><span aria-current="page">Detalhe</span>
				<?php
			else :
				?>
				<span aria-current="page"><?php echo esc_html( $portal_title ); ?></span><?php endif; ?>
		</nav>
	<?php endif; ?>
	<?php if ( $selected ) : ?>
		<?php
		get_template_part(
			'portal/detail',
			null,
			array(
				'item'       => $selected,
				'categories' => $categories,
				'current'    => $collection,
			)
		);
		?>
	<?php elseif ( 'home' === $view ) : ?>
		<section class="arn-portal-welcome" aria-labelledby="arn-portal-title">
			<div><p class="arn-portal-kicker">Portal interno da ARN</p><h1 id="arn-portal-title">Bem-vindo ao teu espaço de informação</h1><p>Consulta as comunicações da ARN e encontra os documentos de que precisas.</p></div>
			<form class="arn-portal-search" action="<?php echo esc_url( arn_intranet_portal_url( 'documentos' ) ); ?>" method="get" role="search">
				<?php arn_intranet_portal_form_route( arn_intranet_portal_url( 'documentos' ) ); ?>
				<label for="arn-home-search">O que procuras?</label><div class="arn-portal-search-controls"><input id="arn-home-search" type="search" name="q" placeholder="Pesquisar no arquivo" maxlength="160"><button type="submit">Pesquisar</button></div>
				<p>Pesquisa por assunto, palavra ou referência.</p>
			</form>
		</section>
		<?php
		if ( empty( $portal['published_news'] ) ) {
			get_template_part( 'portal/notice', null, array( 'available' => $available ) ); }
		?>
		<div class="arn-portal-columns">
			<section aria-labelledby="arn-news-title">
				<div class="arn-portal-section-heading"><h2 id="arn-news-title">Comunicações recentes</h2><a href="<?php echo esc_url( arn_intranet_portal_url( 'noticias' ) ); ?>">Todas as notícias <span aria-hidden="true">↗</span></a></div>
				<?php if ( ! empty( $portal['featured'] ) ) : ?>
					<?php
					get_template_part(
						! empty( $portal['published_news'] ) ? 'portal/news-item' : 'portal/item',
						null,
						array(
							'item'       => $portal['featured'],
							'categories' => $categories,
							'featured'   => true,
						)
					);
					?>
					<?php foreach ( $portal['items'] as $item ) : ?>
						<?php
						if ( $item['id'] !== $portal['featured']['id'] ) {
							get_template_part(
								! empty( $portal['published_news'] ) ? 'portal/news-item' : 'portal/item',
								null,
								array(
									'item'       => $item,
									'categories' => $categories,
								)
							); }
						?>
					<?php endforeach; ?>
					<?php
				else :
					?>
					<div class="arn-portal-empty"><h3>Ainda não há comunicações disponíveis</h3><p>As notícias serão apresentadas aqui quando estiverem disponíveis para consulta.</p></div><?php endif; ?>
			</section>
			<aside class="arn-portal-sidebar" aria-label="Arquivo institucional">
				<section class="arn-portal-library"><p class="arn-portal-kicker">Arquivo institucional</p><h2>Biblioteca documental</h2><p>Circulares, notas internas, despachos, regulamentos e comunicação sindical num só lugar.</p><a class="arn-portal-text-link" href="<?php echo esc_url( arn_intranet_portal_url( 'documentos' ) ); ?>">Consultar documentos <span aria-hidden="true">→</span></a></section>
				<section class="arn-portal-regulations"><h2>Regulamentos</h2>
					<?php if ( ! empty( $portal['regulations'] ) ) : ?>
						<?php
						foreach ( $portal['regulations'] as $item ) :
							?>
							<p><a href="<?php echo esc_url( $item['detail_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></p><?php endforeach; ?>
						<?php
					else :
						?>
						<p>Os regulamentos disponíveis para consulta aparecerão nesta secção.</p><?php endif; ?>
					<a class="arn-portal-text-link" href="<?php echo esc_url( arn_intranet_portal_url( 'regulamentos' ) ); ?>">Consultar regulamentos <span aria-hidden="true">→</span></a>
				</section>
			</aside>
		</div>
	<?php else : ?>
		<header class="arn-portal-page-heading"><p class="arn-portal-kicker">Portal interno</p><h1><?php echo esc_html( $portal_title ); ?></h1><p><?php echo esc_html( $intro[ $view ] ?? '' ); ?></p></header>
		<?php if ( 'services' === $view ) : ?>
			<section class="arn-portal-services" aria-labelledby="arn-services-title"><h2 id="arn-services-title">Serviços em preparação</h2><p>A disponibilização destes serviços depende da validação dos processos e das permissões de acesso.</p>
				<dl><div><dt>Pedidos internos e férias</dt><dd>Submissão e acompanhamento de pedidos. Em preparação.</dd></div><div><dt>Diretório e unidades</dt><dd>Consulta de contactos e da estrutura institucional. Em preparação.</dd></div><div><dt>Suporte informático</dt><dd>Registo e acompanhamento de pedidos de apoio. Em preparação.</dd></div></dl>
				<p>A consulta de notícias, documentos e regulamentos está disponível através do menu do portal.</p>
			</section>
		<?php else : ?>
			<?php get_template_part( 'portal/notice', null, array( 'available' => $available ) ); ?>
			<?php
			get_template_part(
				'portal/list',
				null,
				array(
					'portal'  => $portal,
					'current' => $current,
				)
			);
			?>
		<?php endif; ?>
	<?php endif; ?>
</div>
