<?php
/**
 * Published current document collection for fictional users.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$portal        = $args['portal'];
$current       = get_permalink();
$catalog_title = 'regulations' === $portal['view'] ? 'Regulamentos' : 'Documentos';
$back          = add_query_arg(
	array(
		'q'        => $portal['query'],
		'category' => $portal['category'],
		'arn_page' => $portal['page'],
	),
	$current
);
$format_date   = static fn( string $date ): string => substr( $date, 8, 2 ) . '/' . substr( $date, 5, 2 ) . '/' . substr( $date, 0, 4 );
?>
<div class="arn-portal arn-document-portal alignwide">
	<nav class="arn-portal-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a><span aria-hidden="true">/</span>
	<?php
	if ( null !== $portal['selected'] ) :
		?>
		<a href="<?php echo esc_url( $back ); ?>"><?php echo esc_html( $catalog_title ); ?></a><span aria-hidden="true">/</span><span aria-current="page">Detalhe</span>
		<?php
	else :
		?>
		<span aria-current="page"><?php echo esc_html( $catalog_title ); ?></span><?php endif; ?></nav>
	<?php
	if ( null !== $portal['selected'] ) :
		$item = $portal['selected'];
		?>
		<article class="arn-portal-detail"><header class="arn-portal-page-heading"><p class="arn-portal-kicker"><?php echo esc_html( $portal['categories'][ $item['category'] ] ); ?></p><h1><?php echo esc_html( $item['title'] ); ?></h1></header>
		<div class="arn-portal-columns"><div class="arn-portal-reading"><p class="arn-portal-summary"><?php echo esc_html( $item['summary'] ); ?></p>
		<?php
		get_template_part(
			'portal/pdf-preview',
			null,
			array(
				'url'   => $item['download_url'],
				'mime'  => $item['mime'],
				'label' => $item['title'],
			)
		);
		?>
																										<a class="arn-portal-text-link" href="<?php echo esc_url( $back ); ?>">Voltar à colecção</a></div>
		<aside class="arn-portal-source" aria-labelledby="arn-source-title"><p class="arn-portal-kicker">Ficha documental</p><h2 id="arn-source-title">Original privado</h2>
		<dl><dt>Proprietário</dt><dd><?php echo esc_html( $item['owner'] ); ?></dd><dt>Referência de origem</dt><dd><?php echo esc_html( $item['reference'] ); ?></dd><dt>Versão publicada</dt><dd><?php echo esc_html( (string) $item['version'] ); ?></dd><dt>Revisão prevista</dt><dd><?php echo esc_html( $format_date( $item['review_on'] ) ); ?></dd>
		<?php
		if ( null !== $item['valid_from'] ) :
			?>
			<dt>Válido desde</dt><dd><?php echo esc_html( $format_date( $item['valid_from'] ) ); ?></dd><?php endif; ?>
		<?php
		if ( null !== $item['valid_until'] ) :
			?>
			<dt>Válido até</dt><dd><?php echo esc_html( $format_date( $item['valid_until'] ) ); ?></dd><?php endif; ?></dl>
		<a class="arn-portal-button" href="<?php echo esc_url( $item['download_url'] ); ?>">Descarregar original</a></aside></div></article>
	<?php else : ?>
		<header class="arn-portal-page-heading"><p class="arn-portal-kicker">Portal interno</p><h1><?php echo esc_html( $catalog_title ); ?></h1><p>Consulta as versões publicadas para ti.</p></header>
		<?php
		if ( ! $portal['available'] ) :
			?>
			<div class="arn-portal-notice">O catálogo documental local está indisponível para esta conta.</div>
		<?php else : ?>
		<form class="arn-portal-filters" action="<?php echo esc_url( $current ); ?>" method="get" role="search" aria-label="Pesquisar documentos">
			<?php arn_intranet_portal_form_route( $current ); ?>
			<div class="arn-portal-filter-query"><label for="arn-document-q">Pesquisar</label><input id="arn-document-q" name="q" type="search" maxlength="160" value="<?php echo esc_attr( $portal['query'] ); ?>" placeholder="Título, resumo ou referência"></div>
			<?php
			if ( 'documents' === $portal['view'] ) :
				?>
				<div><label for="arn-document-category">Categoria</label><select id="arn-document-category" name="category"><option value="">Todas as categorias</option>
				<?php
				foreach ( $portal['categories'] as $slug => $label ) :
					?>
				<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $portal['category'], $slug ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div><?php endif; ?>
			<button type="submit">Aplicar filtros</button><a class="arn-portal-reset" href="<?php echo esc_url( $current ); ?>">Limpar filtros</a>
		</form>
			<?php // translators: %s is the count of authorised matching documents. ?>
		<section class="arn-portal-results" aria-labelledby="arn-results-title"><div class="arn-portal-section-heading"><h2 id="arn-results-title"><?php echo esc_html( sprintf( _n( '%s resultado', '%s resultados', $portal['total'], 'arn-intranet' ), number_format_i18n( $portal['total'] ) ) ); ?></h2></div>
			<?php
			if ( array() === $portal['items'] ) :
				?>
				<div class="arn-portal-empty"><h3>Sem documentos disponíveis</h3><p>Experimenta outros filtros ou consulta novamente mais tarde.</p><a class="arn-portal-text-link" href="<?php echo esc_url( $current ); ?>">Limpar filtros</a></div><?php endif; ?>
			<?php
			foreach ( $portal['items'] as $item ) :
				?>
				<article class="arn-portal-item"><div class="arn-portal-meta"><span class="arn-portal-category"><?php echo esc_html( $portal['categories'][ $item['category'] ] ); ?></span><span>Versão <?php echo esc_html( (string) $item['version'] ); ?></span><span><?php echo esc_html( $item['reference'] ); ?></span></div><h3><a href="<?php echo esc_url( $item['detail_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3><p><?php echo esc_html( $item['summary'] ); ?></p><p>Proprietário: <?php echo esc_html( $item['owner'] ); ?>. Revisão: <?php echo esc_html( $format_date( $item['review_on'] ) ); ?>.</p></article><?php endforeach; ?></section>
			<?php
			if ( $portal['pages'] > 1 ) :
				?>
				<nav class="arn-portal-pagination" aria-label="Paginação dos documentos">
				<?php
				for ( $number = 1; $number <= $portal['pages']; ++$number ) :
					?>
				<a href="
								<?php
								echo esc_url(
									add_query_arg(
										array(
											'q'        => $portal['query'],
											'category' => $portal['category'],
											'arn_page' => $number,
										),
										$current
									)
								);
								?>
				" <?php echo $portal['page'] === $number ? 'aria-current="page"' : ''; ?>><?php echo esc_html( (string) $number ); ?></a><?php endfor; ?></nav><?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
