<?php
/** Authorised editorial news preview.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$news = $args['news'] ?? array();
if ( ! is_array( $news ) ) {
	return;
}
?>
<article class="arn-portal arn-news-preview alignwide">
	<nav class="arn-portal-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( arn_intranet_portal_url( 'noticias' ) ); ?>">Notícias</a><span aria-hidden="true">/</span><span aria-current="page">Pré-visualização</span></nav>
	<div class="arn-news-preview-status" role="status"><strong>Pré-visualização: <?php echo esc_html( $news['state'] ); ?></strong><span>Versão <?php echo esc_html( (string) $news['version'] ); ?>. Vista editorial da versão indicada.</span></div>
	<p><a class="arn-portal-text-link" href="<?php echo esc_url( $news['cms_url'] ); ?>">Voltar à notícia no CMS</a></p>
	<header class="arn-portal-page-heading"><p class="arn-portal-kicker"><?php echo esc_html( $news['category'] ); ?></p><h1><?php echo esc_html( $news['title'] ); ?></h1></header>
	<?php
	if ( is_array( $news['featured'] ) ) :
		?>
		<figure class="arn-news-preview-featured"><img src="<?php echo esc_url( $news['featured']['url'] ); ?>" alt="<?php echo esc_attr( $news['featured']['description'] ); ?>"><figcaption><?php echo esc_html( $news['featured']['description'] ); ?></figcaption></figure><?php endif; ?>
	<div class="arn-portal-reading arn-news-preview-body">
	<?php
	foreach ( preg_split( '/\n\s*\n/u', trim( $news['body'] ) ) as $paragraph ) :
		if ( '' !== trim( $paragraph ) ) :
			?>
		<p><?php echo nl2br( esc_html( $paragraph ) ); ?></p>
			<?php
endif;
endforeach;
	?>
	</div>
	<?php
	if ( array() !== $news['gallery'] ) :
		?>
		<section aria-labelledby="arn-news-gallery"><h2 id="arn-news-gallery">Galeria</h2><div class="arn-news-preview-gallery">
		<?php
		foreach ( $news['gallery'] as $image ) :
			?>
		<figure><img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['description'] ); ?>"><figcaption><?php echo esc_html( $image['description'] ); ?></figcaption></figure><?php endforeach; ?></div></section><?php endif; ?>
	<?php
	if ( array() !== $news['attachments'] ) :
		?>
		<section aria-labelledby="arn-news-attachments"><h2 id="arn-news-attachments">Anexos</h2><ol class="arn-news-preview-attachments">
		<?php
		foreach ( $news['attachments'] as $attachment ) :
			?>
		<li><a href="<?php echo esc_url( $attachment['url'] ); ?>"><?php echo esc_html( $attachment['description'] ); ?></a> <span>
			<?php
			echo esc_html(
				match ( $attachment['mime'] ) {
					'application/pdf' => 'PDF',
					'image/png' => 'PNG',
					'image/jpeg' => 'JPEG',
					default => '' }
			);
			?>
		</span></li><?php endforeach; ?></ol></section><?php endif; ?>
</article>
