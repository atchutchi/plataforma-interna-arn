<?php
/**
 * Acessos já autorizados. Os serviços futuros não apresentam acções.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
get_template_part( 'portal/access-links', null, array( 'services' => $args['services'] ?? array() ) );
?>
<section class="arn-portal-services" aria-labelledby="arn-services-planned-title">
	<h2 id="arn-services-planned-title">Em preparação</h2>
	<p>Estes serviços dependem da implementação e da validação dos respectivos processos.</p>
	<dl>
		<div><dt>Pedidos internos e férias</dt><dd>Submissão e acompanhamento de pedidos.</dd></div>
		<div><dt>Suporte informático</dt><dd>Registo e acompanhamento de pedidos de apoio.</dd></div>
	</dl>
</section>
