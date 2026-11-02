<?php
/** Native request/support attachment receipts on the respective exclusive fixture.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped
if ( ! isset( $repo, $db, $tables, $ids, $admin, $login, $key, $assert, $deny ) || ! ( $repo instanceof \Arn\Intranet\Requests\Repository || $repo instanceof \Arn\Intranet\Support\Repository ) ) {
	throw new RuntimeException( 'Ensaio de serviço exclusivo obrigatório.' ); }
require_once __DIR__ . '/media-receipt-fixture.php';
wp_set_current_user( $admin );
$receipt_tables = arn_receipt_schema_fixture( $db );
$file_schema    = new \Arn\Intranet\Documents\PrivateFiles\FileSchema( $db );
$file_schema->install();
foreach ( array( 'files', 'audit', 'schema' ) as $part ) {
	$receipt_tables[] = $file_schema->table( $part ); }
$receipt_baseline = $snapshot( $tables );
$receipt_item     = null;
try {
	$login( $ids['a'] );
	$receipt_module = $repo instanceof \Arn\Intranet\Requests\Repository ? 'requests' : 'tickets';
	$receipt_item   = 'requests' === $receipt_module
	? $repo->create( $request_type->id, $request_type->revision, 'Recibo fictício', 'Ensaio isolado de anexo', $key() )
	: $repo->create( 'LAB-A', 'Recibo fictício', 'Ensaio isolado de anexo', 'normal', $key() );
	$receipt_blob   = new \Arn\Intranet\Documents\PrivateFiles\BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'b', 64 ), 12, 'application/pdf' );
	$receipt        = arn_receipt_fixture(
		$receipt_module,
		$ids['a'],
		$receipt_item->id,
		1,
		array( $receipt_blob ),
		array(
			'label' => 'Anexo fictício',
			'view'  => 'own',
		)
	);
	$receipt_item   = $repo->attach( $receipt_item->id, $receipt_blob, 'Anexo fictício', 1, $ids['a'], $receipt );
	$assert( 2 === $receipt_item->revision && $receipt_item->id === $receipt->resolve( $db ), 'anexo e recibo do serviço confirmados atomicamente' );
	$receipt_before     = $repo->detail( $receipt_item->id, 'own' );
	$receipt_other_blob = new \Arn\Intranet\Documents\PrivateFiles\BlobDescriptor( bin2hex( random_bytes( 16 ) ), str_repeat( 'c', 64 ), 12, 'application/pdf' );
	$deny( static fn() => $repo->attach( $receipt_item->id, $receipt_other_blob, 'Recibo divergente', 2, $ids['a'], $receipt ) );
	$assert( json_encode( $receipt_before, JSON_THROW_ON_ERROR ) === json_encode( $repo->detail( $receipt_item->id, 'own' ), JSON_THROW_ON_ERROR ), 'recibo divergente reverte anexo e revisão' );
	$assert( null === ( new \Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry( $db ) )->find( $receipt_other_blob->id ), 'rollback não deixa ficheiro registado' );

} finally {
	if ( null !== $receipt_item ) {
		$receipt_column = 'object_id';
		foreach ( array( 'notifications', 'audit', 'requests' === $receipt_module ? 'requests' : 'tickets' ) as $part ) {
			$column = in_array( $part, array( 'requests', 'tickets' ), true ) ? 'id' : $receipt_column;
			$sql    = $db->prepare( 'DELETE FROM %i WHERE %i=%d', $schema->table( $part ), $column, $receipt_item->id );
			if ( 'audit' === $part ) {
				$sql .= $db->prepare( ' AND kind=%s', $receipt_module ); }
			if ( false === $db->query( $sql ) ) {
				throw new RuntimeException( 'Limpeza exclusiva recusada.' ); }
		}
	}
	foreach ( array_reverse( $receipt_tables ) as $receipt_table ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $receipt_table ) );
	}
}
$assert( $receipt_baseline === $snapshot( $tables ), 'ensaio de recibos conserva linhas anteriores do prefixo exclusivo' );
