<?php
/** Static first page, never an embedded PDF interpreter.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$pdf_preview = \Arn\Intranet\Media\PdfPreview::url( $args['url'], $args['mime'] );
if ( null === $pdf_preview ) {
	return;
}
?>
<details class="arn-pdf-preview">
	<summary>Pré-visualizar a primeira página</summary>
	<figure>
		<img src="<?php echo esc_url( $pdf_preview ); ?>" alt="<?php echo esc_attr( 'Primeira página: ' . $args['label'] ); ?>" loading="lazy" decoding="async">
		<figcaption>Esta imagem mostra apenas a primeira página. Descarrega o original para consultar o PDF completo e verificar eventuais assinaturas.</figcaption>
	</figure>
</details>
