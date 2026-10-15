<?php
/** Current publication collection and separate administrative source review.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$portal     = $args['portal'];
$current    = arn_intranet_portal_url( 'noticias' );
$categories = $portal['categories'];
$total      = (int) $portal['total'];
// translators: %s is the number of authorised matching publications.
$result_label = sprintf( _n( '%s notícia', '%s notícias', $total, 'arn-intranet' ), number_format_i18n( $total ) );
?>
<div class="arn-portal arn-news-list alignwide">
	<nav class="arn-portal-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a><span aria-hidden="true">/</span><span aria-current="page">Notícias</span></nav>
	<header class="arn-portal-page-heading"><h1>Notícias</h1><p>Consulta as comunicações publicadas para ti por assunto, categoria ou data.</p></header>
	<form class="arn-portal-filters" action="<?php echo esc_url( $current ); ?>" method="get" role="search" aria-label="Pesquisar notícias publicadas">
		<?php arn_intranet_portal_form_route( $current ); ?>
		<div class="arn-portal-filter-query"><label for="arn-news-query">Pesquisar</label><input id="arn-news-query" name="q" type="search" maxlength="160" value="<?php echo esc_attr( $portal['query'] ); ?>" placeholder="Assunto ou palavra"></div>
		<div><label for="arn-news-category">Categoria</label><select id="arn-news-category" name="category"><option value="">Todas as categorias</option>
			<?php
			foreach ( $categories as $slug => $label ) :
				?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $portal['category'], $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></div>
		<div><label for="arn-news-from">Publicado desde</label><input id="arn-news-from" name="arn_from" type="date" value="<?php echo esc_attr( $portal['from'] ); ?>"></div>
		<div><label for="arn-news-to">Publicado até</label><input id="arn-news-to" name="arn_to" type="date" value="<?php echo esc_attr( $portal['to'] ); ?>"></div>
		<button type="submit">Aplicar filtros</button><a class="arn-portal-reset" href="<?php echo esc_url( $current ); ?>">Limpar filtros</a>
	</form>
	<section class="arn-portal-results" aria-labelledby="arn-news-results">
		<h2 id="arn-news-results"><?php echo esc_html( $result_label ); ?></h2>
		<?php if ( ! $portal['available'] ) : ?>
			<div class="arn-portal-empty"><h3>Consulta indisponível</h3><p>Confirma os filtros e tenta novamente. Se o problema continuar, contacta o suporte.</p></div>
		<?php elseif ( empty( $portal['items'] ) ) : ?>
			<div class="arn-portal-empty"><h3>Não há notícias para apresentar</h3><p>Experimenta outra pesquisa ou consulta esta página mais tarde. As notícias aparecem quando estão publicadas para ti.</p></div>
		<?php else : ?>
			<?php
			foreach ( $portal['items'] as $item ) {
				get_template_part(
					'portal/news-item',
					null,
					array(
						'item'       => $item,
						'categories' => $categories,
					)
				); }
			?>
		<?php endif; ?>
	</section>
	<?php if ( $portal['pages'] > 1 ) : ?>
		<nav class="arn-portal-pagination" aria-label="Paginação das notícias">
			<?php for ( $number = 1; $number <= $portal['pages']; ++$number ) : ?>
				<?php
				$url = add_query_arg(
					array(
						'q'        => $portal['query'],
						'category' => $portal['category'],
						'arn_from' => $portal['from'],
						'arn_to'   => $portal['to'],
						'arn_page' => $number,
					),
					$current
				);
				?>
				<a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Página %d', $number ) ); ?>"
				<?php
				if ( $number === $portal['page'] ) {
					echo 'aria-current="page"'; }
				?>
><?php echo esc_html( (string) $number ); ?></a>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
	<?php if ( is_array( $portal['review'] ?? null ) ) : ?>
		<section class="arn-news-source-review" aria-labelledby="arn-source-review-title"><h2 id="arn-source-review-title">Revisão administrativa dos originais</h2><p>Fonte reservada à revisão administrativa. Estes documentos não integram as notícias publicadas nem as respectivas contagens.</p><p><a class="arn-portal-text-link" href="<?php echo esc_url( arn_intranet_portal_url( 'documentos' ) ); ?>">Consultar todos os originais</a></p>
			<?php $review = $portal['review']; ?>
			<?php if ( is_array( $review['selected'] ) ) : ?>
				<?php
				get_template_part(
					'portal/detail',
					null,
					array(
						'item'       => $review['selected'],
						'categories' => $review['categories'],
						'current'    => $current,
					)
				);
				?>
			<?php else : ?>
				<?php
				get_template_part(
					'portal/list',
					null,
					array(
						'portal'  => $review,
						'current' => $current,
					)
				);
				?>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
