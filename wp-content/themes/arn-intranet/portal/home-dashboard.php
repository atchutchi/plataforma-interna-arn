<?php
/** Authorised home summaries. Publication bodies and management fields are absent.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$dashboard = $args['dashboard'] ?? array();
?>
<div class="arn-home-dashboard">
	<?php
	foreach ( array(
		'communications' => 'Comunicados recentes',
		'calendar'       => 'Próximos eventos',
	) as $module => $section_title ) :
		?>
		<?php
		$section  = $dashboard[ $module ] ?? array(
			'available' => false,
			'items'     => array(),
			'url'       => '',
		);
		$title_id = wp_unique_id( 'arn-dashboard-' );
		?>
		<section aria-labelledby="<?php echo esc_attr( $title_id ); ?>">
			<div class="arn-portal-section-heading">
				<h2 id="<?php echo esc_attr( $title_id ); ?>"><?php echo esc_html( $section_title ); ?></h2>
				<?php if ( $section['available'] ) : ?>
					<a href="<?php echo esc_url( $section['url'] ); ?>"><?php echo esc_html( 'calendar' === $module ? 'Ver calendário' : 'Todos os comunicados' ); ?><span aria-hidden="true"> →</span></a>
				<?php endif; ?>
			</div>
			<?php
			if ( 'calendar' === $module ) :
				?>
				<p class="arn-home-summary-note">Eventos em curso e futuros. Horários em UTC.</p><?php endif; ?>
			<?php if ( $section['items'] ) : ?>
				<ul class="arn-home-summary-list">
					<?php foreach ( $section['items'] as $item ) : ?>
						<li><p class="arn-home-summary-date"><?php echo esc_html( $item['date'] ); ?></p><h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="arn-home-summary-empty"><?php echo esc_html( ! $section['available'] ? 'Não há informação disponível para a tua conta nesta secção.' : ( 'calendar' === $module ? 'Não tens eventos em curso ou futuros para consultar.' : 'Ainda não há comunicados publicados disponíveis para ti.' ) ); ?></p>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>
