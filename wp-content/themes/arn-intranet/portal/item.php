<?php
/**
 * Resultado editorial partilhado pelo início e pelas colecções.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$item       = $args['item'];
$categories = $args['categories'];
$featured   = ! empty( $args['featured'] );
?>
<article class="arn-portal-item<?php echo $featured ? ' arn-portal-featured' : ''; ?>">
	<?php
	if ( $featured ) :
		?>
		<p class="arn-portal-kicker">Em destaque no arquivo</p><?php endif; ?>
	<div class="arn-portal-meta"><span class="arn-portal-category"><?php echo esc_html( $categories[ $item['category'] ] ?? $item['category'] ); ?></span>
		<?php
		if ( ! empty( $item['date'] ) ) :
			?>
			<time datetime="<?php echo esc_attr( $item['date'] ); ?>"><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $item['date'] . ' 12:00:00 UTC' ), new DateTimeZone( 'UTC' ) ) ); ?></time>
			<?php
else :
	?>
			<span>Data não indicada</span><?php endif; ?>
		<?php
		if ( ! empty( $item['reference'] ) ) :
			?>
			<span><?php echo esc_html( $item['reference'] ); ?></span><?php endif; ?>
	</div>
	<h3><a href="<?php echo esc_url( $item['detail_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
	<?php
	if ( ! empty( $item['summary'] ) ) :
		?>
		<p><?php echo esc_html( $item['summary'] ); ?></p><?php endif; ?>
	<?php
	if ( $featured ) :
		?>
		<a class="arn-portal-text-link" href="<?php echo esc_url( $item['detail_url'] ); ?>">Ler comunicação <span aria-hidden="true">→</span></a><?php endif; ?>
</article>
