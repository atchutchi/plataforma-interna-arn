<?php
/**
 * Acessos já autorizados. Os serviços futuros não apresentam acções.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$services = is_array( $args['services'] ?? null ) ? $args['services'] : array();
?>
<section class="arn-service-directory" aria-labelledby="arn-services-title">
	<h2 id="arn-services-title">Os teus acessos</h2>
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
<section class="arn-portal-services" aria-labelledby="arn-services-planned-title">
	<h2 id="arn-services-planned-title">Em preparação</h2>
	<p>Estes serviços dependem da implementação e da validação dos respectivos processos.</p>
	<dl>
		<div><dt>Tarefas de equipa</dt><dd>Distribuição e acompanhamento de tarefas.</dd></div>
		<div><dt>Pedidos internos e férias</dt><dd>Submissão e acompanhamento de pedidos.</dd></div>
		<div><dt>Suporte informático</dt><dd>Registo e acompanhamento de pedidos de apoio.</dd></div>
	</dl>
</section>
