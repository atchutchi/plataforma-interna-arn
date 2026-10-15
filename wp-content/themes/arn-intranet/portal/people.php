<?php
/** People view. The plugin supplies an authorized, closed model.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$people = $args['people'] ?? array();
$view   = $people['view'] ?? '';
$titles = array(
	'directory' => 'Funcionários',
	'contact'   => 'Contacto profissional',
	'profile'   => 'Meu perfil',
	'team'      => 'A minha equipa',
);
if ( ! isset( $titles[ $view ] ) ) {
	return;
}
$current       = get_permalink();
$directory     = get_page_by_path( 'funcionarios', OBJECT, 'page' );
$directory_url = $directory instanceof WP_Post ? get_permalink( $directory ) : $current;
$initials      = static function ( string $name ): string {
	$parts   = preg_split( '/\s+/u', trim( $name ) );
	$letters = array();
	foreach ( array_slice( is_array( $parts ) ? $parts : array(), 0, 2 ) as $part ) {
		if ( preg_match( '/^./u', $part, $match ) ) {
			$letters[] = $match[0];
		}
	}
	return implode( '', $letters );
};
$plain_route   = static function ( string $url ): void {
	$query = wp_parse_url( $url, PHP_URL_QUERY );
	if ( is_string( $query ) ) {
		parse_str( $query, $parts );
		if ( isset( $parts['page_id'] ) && is_scalar( $parts['page_id'] ) ) {
			echo '<input type="hidden" name="page_id" value="' . esc_attr( (string) absint( $parts['page_id'] ) ) . '">';
		}
	}
};
$labels        = array(
	'phone'  => 'Telefone',
	'office' => 'Local de trabalho',
	'bio'    => 'Apresentação',
);
?>
<div class="arn-people alignwide">
	<nav class="arn-people-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a><span aria-hidden="true">/</span>
	<?php
	if ( 'contact' === $view ) :
		?>
		<a href="<?php echo esc_url( $directory_url ); ?>">Funcionários</a><span aria-hidden="true">/</span><?php endif; ?><span aria-current="page"><?php echo esc_html( $titles[ $view ] ); ?></span></nav>
	<header class="arn-people-heading"><p>Contactos internos</p><h1><?php echo esc_html( $titles[ $view ] ); ?></h1>
	<?php
	if ( 'directory' === $view ) :
		?>
		<p>Encontra colegas pelo nome, unidade ou cargo.</p>
		<?php
elseif ( 'team' === $view ) :
	?>
	<p>Consulta os contactos da tua equipa.</p>
	<?php
elseif ( 'profile' === $view ) :
	?>
	<p>Consulta os teus dados profissionais e actualiza os teus contactos.</p><?php endif; ?></header>
	<?php if ( 'directory' === $view || 'team' === $view ) : ?>
		<?php
		$list  = $people['list'];
		$query = $people['query'];
		?>
		<form class="arn-people-filters" action="<?php echo esc_url( $current ); ?>" method="get" role="search" aria-label="Pesquisar pessoas">
			<?php $plain_route( $current ); ?>
			<div><label for="arn-people-q">Nome</label><input id="arn-people-q" type="search" name="q" maxlength="120" value="<?php echo esc_attr( $query->q ); ?>"></div>
			<div><label for="arn-people-unit">Unidade</label><select id="arn-people-unit" name="unit"><option value="">Todas as unidades</option><option value="LAB-A" <?php selected( $query->unit, 'LAB-A' ); ?>>LAB-A</option><option value="LAB-B" <?php selected( $query->unit, 'LAB-B' ); ?>>LAB-B</option></select></div>
			<div><label for="arn-people-job">Cargo</label><input id="arn-people-job" type="text" name="job" maxlength="100" value="<?php echo esc_attr( $query->job ); ?>"></div>
			<button type="submit">Aplicar filtros</button><a href="<?php echo esc_url( $current ); ?>">Limpar filtros</a>
		</form>
		<section aria-labelledby="arn-people-results"><h2 id="arn-people-results"><?php // translators: %s is the number of matching people. ?><?php echo esc_html( sprintf( _n( '%s pessoa', '%s pessoas', $list['total'], 'arn-intranet' ), number_format_i18n( $list['total'] ) ) ); ?></h2>
			<?php
			if ( empty( $list['items'] ) ) :
				?>
				<div class="arn-people-empty"><h3>Nenhuma pessoa encontrada</h3><p>Experimenta outro nome ou remove os filtros para consultar os contactos disponíveis.</p><a href="<?php echo esc_url( $current ); ?>">Ver sem filtros</a></div><?php else : ?>
				<ul class="arn-people-list">
																																														<?php
																																														foreach ( $list['items'] as $item ) :
																																															?>
					<li><span class="arn-people-avatar" aria-hidden="true"><?php echo esc_html( $initials( $item['name'] ) ); ?></span><div><h3><?php echo esc_html( $item['name'] ); ?></h3><p><?php echo esc_html( $item['job'] ); ?> · <?php echo esc_html( $item['unit'] ); ?></p></div><a href="<?php echo esc_url( add_query_arg( 'person', (string) $item['id'], $directory_url ) ); ?>">Ver contacto <span class="screen-reader-text">de <?php echo esc_html( $item['name'] ); ?></span></a></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</section>
		<?php
		if ( $list['pages'] > 1 ) :
			?>
			<nav class="arn-people-pages" aria-label="Páginas de resultados">
			<?php
			for ( $number = 1; $number <= $list['pages']; ++$number ) :
				?>
	<a href="
				<?php
				echo esc_url(
					add_query_arg(
						array(
							'q'               => $query->q,
							'unit'            => $query->unit,
							'job'             => $query->job,
							'arn_people_page' => $number,
						),
						$current
					)
				);
				?>
			"
						<?php
						if ( $number === $list['page'] ) :
							?>
	aria-current="page"<?php endif; ?>><?php echo esc_html( (string) $number ); ?></a><?php endfor; ?></nav><?php endif; ?>
	<?php elseif ( 'contact' === $view || 'profile' === $view ) : ?>
		<?php $item = 'contact' === $view ? $people['contact'] : $people['own']; ?>
		<article class="arn-people-detail"><div class="arn-people-identity"><span class="arn-people-avatar" aria-hidden="true"><?php echo esc_html( $initials( $item['name'] ) ); ?></span><div><h2><?php echo esc_html( $item['name'] ); ?></h2><p><?php echo esc_html( $item['job'] ); ?></p></div></div>
			<dl><div><dt>Unidade</dt><dd><?php echo esc_html( $item['unit'] ); ?></dd></div><div><dt>Email institucional</dt><dd><a href="mailto:<?php echo esc_attr( $item['email'] ); ?>"><?php echo esc_html( $item['email'] ); ?></a></dd></div><div><dt>Telefone</dt><dd><?php echo '' !== $item['phone'] ? esc_html( $item['phone'] ) : 'Não indicado'; ?></dd></div><div><dt>Local de trabalho</dt><dd><?php echo '' !== $item['office'] ? esc_html( $item['office'] ) : 'Não indicado'; ?></dd></div>
			<?php
			if ( '' !== $item['bio'] ) :
				?>
				<div><dt>Apresentação</dt><dd class="arn-people-bio"><?php echo nl2br( esc_html( $item['bio'] ) ); ?></dd></div><?php endif; ?></dl>
		</article>
		<?php
		if ( 'contact' === $view ) :
			?>
			<p><a href="<?php echo esc_url( $directory_url ); ?>">Voltar a Funcionários</a></p><?php else : ?>
							<?php
							if ( ! empty( $people['confirmation'] ) ) :
								?>
					<p class="arn-people-message" role="status">Os teus contactos foram guardados.</p><?php endif; ?>
			<section class="arn-people-edit" aria-labelledby="arn-people-edit-title"><h2 id="arn-people-edit-title">Actualizar contactos</h2><p>Nome, email, cargo e unidade são dados de leitura.</p>
							<?php
							if ( null !== $people['error'] ) :
								?>
				<p class="arn-people-error" role="alert"><?php echo esc_html( $people['error'] ); ?></p><?php endif; ?>
				<form action="<?php echo esc_url( $current ); ?>" method="post"><input type="hidden" name="operation" value="update_own"><input type="hidden" name="nonce" value="<?php echo esc_attr( $people['nonce'] ); ?>"><input type="hidden" name="expected_revision" value="<?php echo esc_attr( (string) $item['revision'] ); ?>"><div><label for="arn-people-phone">Telefone</label><input id="arn-people-phone" name="phone" type="tel" maxlength="32" value="<?php echo esc_attr( $people['fields']['phone'] ); ?>"></div><div><label for="arn-people-office">Local de trabalho</label><input id="arn-people-office" name="office" type="text" maxlength="100" value="<?php echo esc_attr( $people['fields']['office'] ); ?>"></div><div><label for="arn-people-bio">Apresentação</label><textarea id="arn-people-bio" name="bio" maxlength="500" rows="5"><?php echo esc_textarea( $people['fields']['bio'] ); ?></textarea></div><button type="submit">Guardar contactos</button></form>
			</section>
							<?php
							if ( ! empty( $people['history'] ) ) :
								?>
					<section class="arn-people-history" aria-labelledby="arn-people-history-title"><h2 id="arn-people-history-title">Alterações recentes</h2><ul>
								<?php
								foreach ( $people['history'] as $event ) :
									?>
	<li><time datetime="<?php echo esc_attr( gmdate( DATE_ATOM, $event['occurred_at'] ) ); ?>"><?php echo esc_html( wp_date( 'j \d\e F \d\e Y', $event['occurred_at'] ) ); ?></time><span><?php echo esc_html( implode( ', ', array_map( static fn( string $field ): string => $labels[ $field ] ?? $field, $event['changed_fields'] ) ) ); ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
