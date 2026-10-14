<?php
/**
 * Pesquisa, filtros e paginação editorial.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$portal     = $args['portal'];
$current    = $args['current'];
$categories = $portal['categories'];
$total      = (int) $portal['total'];
// translators: %s is the number of matching documents.
$result_label = sprintf( _n( '%s resultado', '%s resultados', $total, 'arn-intranet' ), number_format_i18n( $total ) );
?>
<form class="arn-portal-filters" action="<?php echo esc_url( $current ); ?>" method="get" role="search" aria-label="Pesquisar nesta colecção">
	<?php arn_intranet_portal_form_route( $current ); ?>
	<div class="arn-portal-filter-query"><label for="arn-query">Pesquisar</label><input id="arn-query" name="q" type="search" maxlength="160" placeholder="Assunto, palavra ou referência" value="<?php echo esc_attr( $portal['query'] ); ?>"></div>
	<?php if ( 'regulations' !== $portal['view'] ) : ?>
		<div><label for="arn-category">Categoria</label><select id="arn-category" name="category"><option value="">Todas as categorias</option>
			<?php foreach ( $categories as $slug => $label ) : ?>
				<?php
				if ( 'news' === $portal['view'] && 'regulamento' === $slug ) {
					continue; }
				?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $portal['category'], $slug ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></div>
	<?php endif; ?>
	<div class="arn-portal-filter-year"><label for="arn-year">Ano</label><input id="arn-year" name="year" type="text" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="Todos" value="<?php echo esc_attr( $portal['year'] ?? '' ); ?>"></div>
	<button type="submit">Aplicar filtros</button>
	<a class="arn-portal-reset" href="<?php echo esc_url( $current ); ?>">Limpar filtros</a>
</form>
<section class="arn-portal-results" aria-labelledby="arn-results-title">
	<div class="arn-portal-section-heading"><h2 id="arn-results-title"><?php echo esc_html( $result_label ); ?></h2><p><?php echo '' !== $portal['query'] ? esc_html( sprintf( 'Pesquisa: «%s»', $portal['query'] ) ) : 'Nesta colecção'; ?></p></div>
	<?php if ( empty( $portal['items'] ) ) : ?>
		<div class="arn-portal-empty"><h3>Não foram encontrados documentos</h3><p>Experimenta outra palavra ou remove os filtros para consultar os conteúdos disponíveis.</p><a class="arn-portal-text-link" href="<?php echo esc_url( $current ); ?>">Ver a colecção sem filtros <span aria-hidden="true">→</span></a></div>
	<?php else : ?>
		<?php
		foreach ( $portal['items'] as $item ) {
			get_template_part(
				'portal/item',
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
	<nav class="arn-portal-pagination" aria-label="Paginação dos resultados">
		<?php for ( $number = 1; $number <= $portal['pages']; ++$number ) : ?>
			<?php
			$url = add_query_arg(
				array(
					'q'        => $portal['query'],
					'category' => $portal['category'],
					'year'     => $portal['year'] ?? '',
					'paged'    => $number,
				),
				$current
			);
			?>
			<a href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Página %d', $number ) ); ?>" 
			<?php
			if ( (int) $portal['page'] === $number ) :
				?>
				aria-current="page"<?php endif; ?>><?php echo esc_html( (string) $number ); ?></a>
		<?php endfor; ?>
	</nav>
<?php endif; ?>
