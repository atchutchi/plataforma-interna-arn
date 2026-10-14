<?php
/**
 * Contexto da consulta local.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php if ( ! empty( $args['available'] ) ) : ?>
	<p class="arn-portal-notice"><strong>Revisão local do arquivo.</strong> Conteúdos reservados ao administrador. As datas identificam os documentos e não indicam acontecimentos futuros ou aprovação editorial.</p>
<?php else : ?>
	<p class="arn-portal-notice">O arquivo está em preparação. Ainda não existem conteúdos disponíveis para consulta com este acesso.</p>
<?php endif; ?>
