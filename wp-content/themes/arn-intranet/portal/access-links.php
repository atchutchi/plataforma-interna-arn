<?php
/** Shared authorised links for the home and services pages.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$services = is_array( $args['services'] ?? null ) ? $args['services'] : array();
$title_id = wp_unique_id( 'arn-accesses-' );
?>
<section class="arn-service-directory" aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
	<h2 id="<?php echo esc_attr( $title_id ); ?>">Os teus acessos</h2>
	<p>Os serviços apresentados dependem dos acessos disponíveis para a tua conta.</p>
	<?php if ( $services ) : ?>
		<ul class="arn-service-list">
			<?php foreach ( $services as $service ) : ?>
				<li>
					<h3><a href="<?php echo esc_url( $service['url'] ); ?>"><?php echo esc_html( $service['title'] ); ?><span aria-hidden="true">→</span></a></h3>
					<p><?php echo esc_html( $service['description'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php else : ?>
		<div class="arn-portal-empty"><h3>Ainda não há acessos disponíveis para a tua conta</h3><p>Os acessos aparecem aqui quando estiverem disponíveis para ti. A informação institucional continua no menu principal.</p></div>
	<?php endif; ?>
</section>
