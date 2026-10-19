<?php
/** Search presents only the closed, already-authorised result model.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
$search_model = $args['search'];
$current      = get_permalink();
$reset        = add_query_arg( 'arn_search', '1', $current );
$page_link    = static fn( int $page ): string => add_query_arg(
	array(
		'arn_search'      => '1',
		'q'               => $search_model['query'],
		'type'            => $search_model['type'],
		'arn_search_page' => (string) $page,
	),
	$current
);
?>
<div class="arn-portal alignwide arn-integrated-search">
	<nav class="arn-portal-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a><span aria-hidden="true">/</span><span aria-current="page">Pesquisa</span></nav>
	<header class="arn-search-heading"><p class="arn-portal-kicker">Portal interno da ARN</p><h1>Pesquisar no portal</h1><p>Encontra funcionários, notícias, documentos, comunicados e eventos disponíveis para a tua conta.</p></header>
	<form class="arn-portal-filters arn-integrated-search-form" action="<?php echo esc_url( $current ); ?>" method="get" role="search" aria-label="Pesquisa integrada">
		<?php arn_intranet_portal_form_route( $current ); ?>
		<input type="hidden" name="arn_search" value="1">
		<div><label for="arn-search-query">O que procuras?</label><input id="arn-search-query" name="q" type="search" value="<?php echo esc_attr( $search_model['query'] ); ?>" maxlength="100" required aria-describedby="arn-search-help"></div>
		<div><label for="arn-search-type">Tipo de resultado</label><select id="arn-search-type" name="type">
			<?php foreach ( \Arn\Intranet\Search\Query::TYPES as $result_type => $label ) : ?>
				<option value="<?php echo esc_attr( $result_type ); ?>" <?php selected( $search_model['type'], $result_type ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></div>
		<div class="arn-search-actions"><button type="submit">Pesquisar</button><a href="<?php echo esc_url( $reset ); ?>">Limpar pesquisa</a></div>
	</form>
	<p id="arn-search-help" class="arn-search-help">Pesquisa por nome, assunto, palavra ou referência. Os resultados estão agrupados por tipo. Os eventos incluem o histórico e os cancelados, identificados na lista.</p>
	<?php if ( $search_model['invalid'] ) : ?>
		<div class="arn-portal-empty" role="status"><h2>Verifica a pesquisa</h2><p>Usa uma expressão mais curta, sem caracteres de controlo, e os filtros apresentados neste formulário.</p><a href="<?php echo esc_url( $reset ); ?>">Recomeçar a pesquisa</a></div>
	<?php elseif ( ! $search_model['searched'] ) : ?>
		<div class="arn-portal-empty"><h2>Começa por uma palavra</h2><p>Introduz o que procuras. Podes escolher um tipo para limitar os resultados.</p></div>
	<?php else : ?>
		<?php
		if ( $search_model['partial'] ) :
			?>
			<p role="status">Algumas fontes não estão disponíveis. Podes tentar novamente.</p><?php endif; ?>
		<section aria-labelledby="arn-search-results-title">
			<div class="arn-portal-section-heading"><h2 id="arn-search-results-title">Resultados da pesquisa</h2><p><?php echo esc_html( (string) $search_model['total'] . ( 1 === $search_model['total'] ? ' resultado disponível' : ' resultados disponíveis' ) ); ?></p></div>
			<?php if ( $search_model['items'] ) : ?>
				<ul class="arn-search-results">
					<?php foreach ( $search_model['items'] as $item ) : ?>
						<li><p class="arn-portal-kicker"><?php echo esc_html( $item['type'] ); ?></p><h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<div class="arn-portal-empty"><h3><?php echo esc_html( $search_model['total'] > 0 ? 'Esta página já não tem resultados' : 'Não foram encontrados resultados disponíveis' ); ?></h3><p>Experimenta outra palavra, remove o filtro por tipo ou volta à primeira página.</p><a href="<?php echo esc_url( $search_model['total'] > 0 ? $page_link( 1 ) : $reset ); ?>"><?php echo esc_html( $search_model['total'] > 0 ? 'Voltar à primeira página' : 'Limpar pesquisa' ); ?></a></div>
			<?php endif; ?>
			<?php if ( $search_model['pages'] > 1 && $search_model['page'] <= $search_model['pages'] ) : ?>
				<nav class="arn-portal-pagination" aria-label="Paginação da pesquisa">
					<?php
					if ( $search_model['page'] > 1 ) :
						?>
						<a rel="prev" href="<?php echo esc_url( $page_link( $search_model['page'] - 1 ) ); ?>">Página anterior</a><?php endif; ?>
					<span aria-current="page"><?php echo esc_html( 'Página ' . $search_model['page'] . ' de ' . $search_model['pages'] ); ?></span>
					<?php
					if ( $search_model['page'] < $search_model['pages'] ) :
						?>
						<a rel="next" href="<?php echo esc_url( $page_link( $search_model['page'] + 1 ) ); ?>">Página seguinte</a><?php endif; ?>
				</nav>
			<?php endif; ?>
		</section>
	<?php endif; ?>
</div>
