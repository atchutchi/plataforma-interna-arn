<?php
/** Current authorised news row shared by home and list.
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
<article class="arn-portal-item arn-news-item<?php echo $featured ? ' arn-portal-featured' : ''; ?>">
	<?php if ( $featured && is_array( $item['featured'] ) ) : ?>
		<img class="arn-news-item-image" src="<?php echo esc_url( $item['featured']['url'] ); ?>" alt="<?php echo esc_attr( $item['featured']['description'] ); ?>" decoding="async">
	<?php endif; ?>
	<div class="arn-portal-meta"><span class="arn-portal-category"><?php echo esc_html( $categories[ $item['category'] ] ?? $item['category'] ); ?></span><time datetime="<?php echo esc_attr( gmdate( 'c', $item['published_at'] ) ); ?>"><?php echo esc_html( wp_date( 'd/m/Y', $item['published_at'], new DateTimeZone( 'UTC' ) ) ); ?></time><span><?php echo esc_html( $item['author'] ); ?></span></div>
	<h3><a href="<?php echo esc_url( $item['detail_url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
	<p><?php echo esc_html( mb_strimwidth( preg_replace( '/\s+/u', ' ', $item['body'] ), 0, 220, '…' ) ); ?></p>
	<a class="arn-portal-text-link" href="<?php echo esc_url( $item['detail_url'] ); ?>">Ler notícia <span aria-hidden="true">→</span></a>
</article>
