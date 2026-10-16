<?php
/**
 * F1-12 public projections, never aggregates or private history.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) || ! isset( $args['f112'] ) ) {
	return;
}
$f112       = $args['f112'];
$calendar   = 'calendar' === $f112['module'];
$f112_title = $calendar ? 'Calendário' : 'Comunicados';
$view       = $f112['view'];
$data       = $f112['data'];
$query      = $f112['query']['selection'];
$base       = get_permalink();
$filters    = array_intersect_key( $f112['query']['get'], array_flip( array( 'q', 'unit', 'state', 'month', 'arn_f112_page' ) ) );
$back       = add_query_arg( $filters, $base );
$detail_url = static fn( int $id ): string => add_query_arg( $filters + array( 'entry' => (string) $id ), $base );
$period     = static function ( array $item ): string {
	if ( $item['all_day'] ) {
		$first = gmdate( 'd/m/Y', $item['start'] );
		$last  = gmdate( 'd/m/Y', $item['end'] - 1 );
		return $first === $last ? $first . ' · Dia inteiro' : $first . ' a ' . $last . ' · Dias inteiros';
	}
	return gmdate( 'd/m/Y H:i', $item['start'] ) . ' a ' . gmdate( 'd/m/Y H:i', $item['end'] ) . ' UTC';
};
?>
<div class="arn-f112 alignwide">
<nav class="arn-f112-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a><span aria-hidden="true">/</span><a href="<?php echo esc_url( $back ); ?>"><?php echo esc_html( $f112_title ); ?></a>
<?php
if ( 'list' !== $view ) :
	?>
	<span aria-hidden="true">/</span><span aria-current="page"><?php echo esc_html( 'detail' === $view ? 'Detalhe' : 'Avisos internos' ); ?></span><?php endif; ?></nav>
<header class="arn-f112-heading"><h1><?php echo esc_html( 'detail' === $view ? $data['title'] : ( 'notices' === $view ? $f112_title . ' · Avisos internos' : $f112_title ) ); ?></h1>
<?php
if ( 'list' === $view ) :
	?>
	<p><?php echo esc_html( $calendar ? 'Consulta os próximos eventos e recupera o histórico da tua comunidade.' : 'Consulta as comunicações publicadas para ti e para a tua equipa.' ); ?></p><?php endif; ?>
<p class="arn-f112-local">Laboratório local. Conteúdo e contas fictícios.</p>
<nav class="arn-f112-actions" aria-label="Vistas de consulta"><a href="<?php echo esc_url( $base ); ?>">Ver <?php echo esc_html( strtolower( $f112_title ) ); ?></a><a href="<?php echo esc_url( add_query_arg( 'notices', '1', $base ) ); ?>">Avisos internos</a></nav></header>
<?php if ( 'detail' === $view ) : ?>
<article class="arn-f112-detail"><div class="arn-f112-meta"><span><?php echo esc_html( $data['audience'] ); ?></span><span>Versão <?php echo esc_html( (string) $data['version'] ); ?></span><span>Publicado em <?php echo esc_html( gmdate( 'd/m/Y H:i', $data['published_at'] ) ); ?> UTC</span></div>
	<?php
	if ( $calendar ) :
		?>
		<p class="arn-f112-period"><?php echo esc_html( $period( $data ) ); ?></p>
		<?php
		if ( $data['cancelled'] ) :
			?>
		<p class="arn-f112-status">Evento cancelado</p><?php endif; ?><dl><dt>Modalidade</dt><dd>
		<?php
		echo esc_html(
			array(
				'presencial' => 'Presencial',
				'online'     => 'Online',
				'hybrid'     => 'Híbrido',
			)[ $data['mode'] ]
		);
		?>
		</dd>
		<?php
		if ( '' !== $data['location'] ) :
			?>
	<dt>Local</dt><dd><?php echo esc_html( $data['location'] ); ?></dd><?php endif; ?>
		<?php
		if ( '' !== $data['url'] ) :
			?>
	<dt>Participação online</dt><dd><a href="<?php echo esc_url( $data['url'], array( 'https' ) ); ?>" rel="noreferrer noopener"><?php echo esc_html( $data['url'] ); ?></a></dd><?php endif; ?></dl>
		<?php
elseif ( null !== $data['valid_until'] ) :
	?>
	<p>Disponível até <?php echo esc_html( gmdate( 'd/m/Y H:i', $data['valid_until'] ) ); ?> UTC</p><?php endif; ?>
<div class="arn-f112-prose"><?php echo nl2br( esc_html( $data['body'] ) ); ?></div><p><a href="<?php echo esc_url( $back ); ?>">Voltar aos resultados</a></p></article>
<?php else : ?>
	<?php if ( 'list' === $view ) : ?>
<form class="arn-f112-filters" method="get" action="<?php echo esc_url( $base ); ?>" role="search" aria-label="Filtrar <?php echo esc_attr( strtolower( $f112_title ) ); ?>">
		<?php arn_intranet_portal_form_route( $base ); ?>
<div><label for="f112-q">Pesquisar</label><input type="search" id="f112-q" name="q" maxlength="100" value="<?php echo esc_attr( $query->q ); ?>"></div>
<div><label for="f112-unit">Unidade</label><select id="f112-unit" name="unit"><option value="">Todas as audiências</option>
		<?php
		foreach ( array( 'LAB-A', 'LAB-B' ) as $unit ) :
			?>
	<option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $query->unit, $unit ); ?>><?php echo esc_html( $unit ); ?></option><?php endforeach; ?></select></div>
		<?php
		if ( $calendar ) :
			?>
			<div><label for="f112-state">Período ou estado</label><select id="f112-state" name="state">
			<?php
			foreach ( array(
				'future'    => 'Em curso e próximos',
				'history'   => 'Histórico',
				'cancelled' => 'Cancelados',
			) as $state => $label ) :
				?>
			<option value="<?php echo esc_attr( $state ); ?>" <?php selected( $query->state, $state ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div><div><label for="f112-month">Mês (AAAA-MM)</label><input id="f112-month" type="text" inputmode="numeric" name="month" placeholder="2030-01" maxlength="7" value="<?php echo esc_attr( $query->month ); ?>"></div><?php endif; ?>
<button type="submit">Aplicar filtros</button><a href="<?php echo esc_url( $base ); ?>">Limpar filtros</a></form>
<?php endif; ?>
<section aria-labelledby="f112-results"><h2 id="f112-results"><?php echo esc_html( sprintf( '%s resultados', number_format_i18n( $data['total'] ) ) ); ?></h2>
	<?php
	if ( array() === $data['items'] ) :
		?>
		<div class="arn-f112-empty"><h3><?php echo esc_html( 'notices' === $view ? 'Sem avisos nesta página' : 'Sem resultados nesta consulta' ); ?></h3><p><?php echo esc_html( 'notices' === $view ? 'Os avisos aparecem quando uma publicação ou alteração é dirigida a ti. Não representam aceitação ou presença.' : 'Experimenta outra pesquisa ou remove os filtros. Só aparecem conteúdos publicados para a tua audiência.' ); ?></p><a href="<?php echo esc_url( 'notices' === $view ? add_query_arg( 'notices', '1', $base ) : $base ); ?>">Voltar à primeira página</a></div>
		<?php
else :
	?>
	<ul class="arn-f112-list"><?php foreach ( $data['items'] as $item ) : ?>
<li>
		<?php
		if ( $calendar && 'list' === $view ) :
			?>
	<time class="arn-f112-date" datetime="<?php echo esc_attr( gmdate( 'Y-m-d', $item['start'] ) ); ?>"><strong><?php echo esc_html( gmdate( 'd', $item['start'] ) ); ?></strong><span><?php echo esc_html( gmdate( 'm/Y', $item['start'] ) ); ?></span></time><?php endif; ?>
<div class="arn-f112-list-copy"><h3><a href="<?php echo esc_url( $detail_url( 'notices' === $view ? $item['object_id'] : $item['id'] ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
		<?php
		if ( 'notices' === $view ) :
			?>
		<p><?php echo esc_html( 'cancelled' === $item['kind'] ? 'Cancelamento' : 'Publicação' ); ?> · <?php echo esc_html( gmdate( 'd/m/Y H:i', $item['created_at'] ) ); ?> UTC · <?php echo esc_html( null === $item['read_at'] ? 'Por ler' : 'Lido' ); ?></p>
			<?php
			if ( null === $item['read_at'] ) :
				?>
			<form method="post" action="<?php echo esc_url( add_query_arg( 'notices', '1', $base ) ); ?>"><input type="hidden" name="operation" value="mark-read"><input type="hidden" name="id" value="<?php echo esc_attr( (string) $item['id'] ); ?>"><input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( \Arn\Intranet\F112\Input::nonce( get_current_user_id(), $f112['module'], 'mark-read', $item['id'] ) ) ); ?>"><button type="submit" class="arn-f112-secondary">Marcar como lido<span class="screen-reader-text">: <?php echo esc_html( $item['title'] ); ?></span></button></form><?php endif; ?>
			<?php
else :
	?>
	<p class="arn-f112-meta"><span><?php echo esc_html( $item['audience'] ); ?></span><span><?php echo esc_html( $calendar ? $period( $item ) : 'Publicado em ' . gmdate( 'd/m/Y', $item['published_at'] ) ); ?></span>
	<?php
	if ( $calendar && $item['cancelled'] ) :
		?>
	<span>Cancelado</span><?php endif; ?></p><?php endif; ?></div></li>
<?php endforeach; ?></ul><?php endif; ?></section>
	<?php
	if ( $data['pages'] > 1 ) :
		?>
		<nav class="arn-f112-pagination" aria-label="Páginas de resultados">
		<?php
		if ( $data['page'] > 1 ) :
			?>
		<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, 'notices' === $view ? array( 'notices' => '1' ) : array(), array( 'arn_f112_page' => (string) ( $data['page'] - 1 ) ) ), $base ) ); ?>">Anterior</a><?php endif; ?><span>Página <?php echo esc_html( (string) $data['page'] ); ?> de <?php echo esc_html( (string) $data['pages'] ); ?></span>
		<?php
		if ( $data['page'] < $data['pages'] ) :
			?>
	<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, 'notices' === $view ? array( 'notices' => '1' ) : array(), array( 'arn_f112_page' => (string) ( $data['page'] + 1 ) ) ), $base ) ); ?>">Seguinte</a><?php endif; ?></nav><?php endif; ?>
<?php endif; ?>
</div>
