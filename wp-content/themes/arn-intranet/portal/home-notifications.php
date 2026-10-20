<?php
/** Read-only notice preview. Receipts remain in the native module pages.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; }
$notices      = $args['notifications'] ?? array(
	'available' => false,
	'items'     => array(),
	'links'     => array(),
);
$notice_title = wp_unique_id( 'arn-home-notifications-' );
?>
<section class="arn-home-notifications" aria-labelledby="<?php echo esc_attr( $notice_title ); ?>">
<h2 id="<?php echo esc_attr( $notice_title ); ?>">Notificações</h2>
<p class="arn-home-summary-note">Resumo dos avisos recentes disponíveis para ti. Consultar o Início não marca os avisos como lidos.</p>
<?php if ( $notices['items'] ) : ?>
<ul class="arn-home-notification-list">
	<?php foreach ( $notices['items'] as $notice ) : ?>
<li><p class="arn-home-summary-date"><?php echo esc_html( $notice['module'] . ' · ' . $notice['event'] . ' · ' . $notice['date'] ); ?></p>
<h3><a href="<?php echo esc_url( $notice['url'] ); ?>"><?php echo esc_html( $notice['title'] ); ?></a></h3>
<p class="arn-home-notification-status"><?php echo esc_html( $notice['read'] ? 'Lida' : 'Por ler' ); ?></p></li>
<?php endforeach; ?></ul>
<?php else : ?>
<p class="arn-home-summary-empty"><?php echo esc_html( $notices['available'] ? 'Não tens notificações disponíveis neste momento.' : 'Não há notificações disponíveis para a tua conta nesta secção.' ); ?></p>
<?php endif; ?>
<?php if ( $notices['links'] ) : ?>
<nav aria-label="Consultar avisos nos módulos"><p>Continuar nos módulos</p><ul class="arn-home-notification-links">
	<?php
	foreach ( $notices['links'] as $notice_link ) :
		?>
		<li><a href="<?php echo esc_url( $notice_link['url'] ); ?>"><?php echo esc_html( $notice_link['title'] ); ?></a></li><?php endforeach; ?>
</ul></nav><?php endif; ?>
</section>
