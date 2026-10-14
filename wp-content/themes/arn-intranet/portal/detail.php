<?php
/**
 * Leitura do documento e acesso ao original autorizado.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$item = $args['item'];
?>
<article class="arn-portal-detail">
	<header class="arn-portal-page-heading"><p class="arn-portal-kicker"><?php echo esc_html( $args['categories'][ $item['category'] ] ?? $item['category'] ); ?></p><h1><?php echo esc_html( $item['title'] ); ?></h1>
		<div class="arn-portal-meta">
			<?php
			if ( ! empty( $item['date'] ) ) :
				?>
				<time datetime="<?php echo esc_attr( $item['date'] ); ?>"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $item['date'] . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></time>
				<?php
else :
	?>
				<span>Data não indicada no documento</span><?php endif; ?>
			<?php
			if ( ! empty( $item['reference'] ) ) :
				?>
				<span>Referência: <?php echo esc_html( $item['reference'] ); ?></span><?php endif; ?>
		</div>
	</header>
	<div class="arn-portal-columns">
		<div class="arn-portal-reading">
			<?php
			if ( ! empty( $item['summary'] ) ) :
				?>
				<p class="arn-portal-summary"><?php echo esc_html( $item['summary'] ); ?></p><?php endif; ?>
			<?php
			foreach ( $item['body'] as $paragraph ) :
				?>
				<p><?php echo esc_html( $paragraph ); ?></p><?php endforeach; ?>
			<a class="arn-portal-text-link" href="<?php echo esc_url( $args['current'] ); ?>">Voltar à colecção</a>
		</div>
		<aside class="arn-portal-source" aria-labelledby="arn-source-title"><p class="arn-portal-kicker">Arquivo documental</p><h2 id="arn-source-title">Documento original</h2><p><?php echo esc_html( $item['source_label'] ); ?></p><p class="arn-portal-meta">Formato: <?php echo esc_html( strtoupper( $item['format'] ) ); ?></p><a class="arn-portal-button" href="<?php echo esc_url( $item['download_url'] ); ?>">Descarregar original <span aria-hidden="true">↓</span></a>
			<?php
			if ( ! empty( $item['note'] ) ) :
				?>
				<div class="arn-portal-source-note"><h3>Nota documental</h3><p><?php echo esc_html( $item['note'] ); ?></p></div><?php endif; ?>
			<p class="arn-portal-source-context">Disponível para revisão local do administrador.</p>
		</aside>
	</div>
</article>
