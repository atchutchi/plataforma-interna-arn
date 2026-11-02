<?php
/** Receipts use only the media processing harness's exclusive SQL prefix.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Media\{ConsumptionReceipt, ReceiptSchema, ConsumerJobs};
// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions
if ( ! isset( $db, $prefix, $assert, $registry ) || ! preg_match( '/^arn_mp_[a-f0-9]{12}_$/D', $prefix ) ) {
	throw new RuntimeException( 'Prefixo exclusivo obrigatório.' ); }
$receipt_schema = new ReceiptSchema( $db );
$refuse_receipt = static function ( callable $call ) use ( $assert ): void {
	try {
		$call();
	} catch ( RuntimeException ) {
		$assert( true, 'recusa de recibo' );
		return; }
	$assert( false, 'recibo deveria ser recusado' );
};
$refuse_receipt( static fn() => $receipt_schema->assertReady() );
$receipt_schema->install();
$receipt_schema->install();
$second_receipt = new \Arn\Intranet\Identity\IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second_receipt->set_prefix( $prefix );
$second_receipt->suppress_errors( true );
try {
	foreach ( array( 'tasks', 'requests', 'tickets' ) as $module ) {
		$blob    = new \Arn\Intranet\Documents\PrivateFiles\BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'b', 64 ), 12, 'application/pdf' );
		$state   = array(
			'owner'   => 42,
			'context' => ConsumerJobs::intent(
				$module,
				777,
				3,
				'Prova sintética',
				array(
					'label' => 'Anexo fictício',
					'view'  => 'own',
				)
			),
			'items'   => array(
				array(
					'blob' => array(
						'id'     => $blob->id,
						'sha256' => $blob->sha256,
						'size'   => $blob->size,
						'mime'   => $blob->mime,
					),
				),
			),
		);
		$receipt = new ConsumptionReceipt( bin2hex( random_bytes( 16 ) ), $state );
		$refuse_receipt( static fn() => $receipt->record( $db, $module, 'attach', 42, 777, 4 ) );
		$db->query( 'START TRANSACTION' );
		$receipt->record( $db, $module, 'attach', 42, 777, 4 );
		$refuse_receipt( static fn() => $receipt->resolve( $second_receipt ) );
		$db->query( 'ROLLBACK' );
		$refuse_receipt( static fn() => $receipt->resolve( $second_receipt ) );
		$db->query( 'START TRANSACTION' );
		$registry->registerInTransaction( new \Arn\Intranet\Documents\PrivateFiles\FileRecord( $blob, $module . '_receipt_test', 777, 4, 'original', 42, time() ) );
		$receipt->record( $db, $module, 'attach', 42, 777, 4 );
		$db->query( 'COMMIT' );
		$assert( 777 === $receipt->resolve( $second_receipt ) && null !== $registry->find( $blob->id ), 'ficheiro, auditoria e recibo confirmados na mesma transacção' );
		$state['context']['payload']['label'] = 'Outro conteúdo';
		$wrong                                = new ConsumptionReceipt( $receipt->batch, $state );
		$refuse_receipt( static fn() => $wrong->resolve( $second_receipt ) );
		$db->query( 'START TRANSACTION' );
		$refuse_receipt( static fn() => $receipt->record( $db, $module, 'attach', 42, 777, 4 ) );
		$db->query( 'ROLLBACK' );
		$assert( 777 === $receipt->resolve( $second_receipt ), 'duplicação não altera recibo confirmado' );
	}
	$assert( '3' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $receipt_schema->table() ) ), 'um recibo por lote' );
	$db->query( $db->prepare( 'ALTER TABLE %i ENGINE=MyISAM', $receipt_schema->table() ) );
	$refuse_receipt( static fn() => $receipt_schema->assertReady() );
	$refuse_receipt( static fn() => $receipt_schema->install() );
} finally {
	$db->query( 'ROLLBACK' );
	$second_receipt->close();
	$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $receipt_schema->table() ) );
}
