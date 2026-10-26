<?php
/** Approved publication only, without editorial state or history.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$news       = $args['news'];
$categories = \Arn\Intranet\News\NewsPortal::categories();
?>
<article class="arn-portal arn-news-preview arn-news-published alignwide">
	<nav class="arn-portal-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( arn_intranet_portal_url( 'noticias' ) ); ?>">Notícias</a><span aria-hidden="true">/</span><span aria-current="page">Notícia</span></nav>
	<header class="arn-portal-page-heading"><p class="arn-portal-kicker"><?php echo esc_html( $categories[ $news['category'] ] ?? $news['category'] ); ?></p><h1><?php echo esc_html( $news['title'] ); ?></h1>
		<div class="arn-portal-meta"><span>Por <?php echo esc_html( $news['author'] ); ?></span><time datetime="<?php echo esc_attr( gmdate( 'c', $news['published_at'] ) ); ?>">Publicado em <?php echo esc_html( wp_date( 'd/m/Y', $news['published_at'], new DateTimeZone( 'UTC' ) ) ); ?></time><span>Audiência: <?php echo esc_html( $news['audience'] ); ?></span></div>
	</header>
	<?php if ( is_array( $news['featured'] ) ) : ?>
		<figure class="arn-news-preview-featured"><img src="<?php echo esc_url( \Arn\Intranet\Media\VariantUrl::image( $news['featured']['url'], 'preview' ) ); ?>" alt="<?php echo esc_attr( $news['featured']['description'] ); ?>"><figcaption><?php echo esc_html( $news['featured']['description'] ); ?></figcaption></figure>
	<?php endif; ?>
	<div class="arn-portal-reading arn-news-preview-body">
		<?php foreach ( preg_split( '/\n\s*\n/u', trim( $news['body'] ) ) as $paragraph ) : ?>
			<?php
			if ( '' !== trim( $paragraph ) ) :
				?>
				<p><?php echo nl2br( esc_html( $paragraph ) ); ?></p><?php endif; ?>
		<?php endforeach; ?>
	</div>
	<?php if ( array() !== $news['gallery'] ) : ?>
		<section aria-labelledby="arn-news-gallery"><h2 id="arn-news-gallery">Galeria</h2><div class="arn-news-preview-gallery">
			<?php foreach ( $news['gallery'] as $image ) : ?>
				<figure><img src="<?php echo esc_url( \Arn\Intranet\Media\VariantUrl::image( $image['url'], 'preview' ) ); ?>" alt="<?php echo esc_attr( $image['description'] ); ?>" loading="lazy" decoding="async"><figcaption><?php echo esc_html( $image['description'] ); ?></figcaption></figure>
			<?php endforeach; ?>
		</div></section>
	<?php endif; ?>
	<?php if ( array() !== $news['attachments'] ) : ?>
		<section aria-labelledby="arn-news-attachments"><h2 id="arn-news-attachments">Anexos</h2><ol class="arn-news-preview-attachments">
			<?php foreach ( $news['attachments'] as $attachment ) : ?>
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
								</span>
								<?php
								get_template_part(
									'portal/pdf-preview',
									null,
									array(
										'url'   => $attachment['url'],
										'mime'  => $attachment['mime'],
										'label' => $attachment['description'],
									)
								);
								?>
										</li>
			<?php endforeach; ?>
		</ol></section>
	<?php endif; ?>
	<p><a class="arn-portal-text-link" href="<?php echo esc_url( arn_intranet_portal_url( 'noticias' ) ); ?>">Voltar às notícias</a></p>
</article>
