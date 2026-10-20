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
		'tasks_own'      => 'Tarefas pendentes',
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
					<a href="<?php echo esc_url( $section['url'] ); ?>">
					<?php
					echo esc_html(
						match ( $module ) {
						'calendar' => 'Ver calendário',
						'tasks_own' => 'Todas as minhas tarefas',
						default => 'Todos os comunicados' }
					);
					?>
								<span aria-hidden="true"> →</span></a>
				<?php endif; ?>
			</div>
			<?php
			if ( 'calendar' === $module ) :
				?>
				<p class="arn-home-summary-note">Eventos em curso e futuros. Horários em UTC.</p><?php endif; ?>
			<?php if ( 'tasks_own' === $module ) : ?>
				<p class="arn-home-summary-note">As tuas tarefas por iniciar e em curso. Prazos em UTC.</p>
			<?php endif; ?>
			<?php if ( $section['items'] ) : ?>
				<ul class="arn-home-summary-list">
					<?php foreach ( $section['items'] as $item ) : ?>
						<li><p class="arn-home-summary-date"><?php echo esc_html( $item['date'] ); ?></p><h3><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
						<?php if ( 'tasks_own' === $module ) : ?>
							<p class="arn-home-summary-date"><?php echo esc_html( $item['state'] . ' · Prioridade ' . mb_strtolower( $item['priority'], 'UTF-8' ) ); ?>
							<?php
							if ( $item['overdue'] ) :
								?>
								· <strong>Prazo ultrapassado</strong><?php endif; ?></p>
						<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="arn-home-summary-empty">
				<?php
				echo esc_html(
					! $section['available'] ? 'Não há informação disponível para a tua conta nesta secção.' : match ( $module ) {
					'calendar' => 'Não tens eventos em curso ou futuros para consultar.',
					'tasks_own' => 'Não tens tarefas pendentes neste momento.',
					default => 'Ainda não há comunicados publicados disponíveis para ti.' }
				);
				?>
													</p>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>
