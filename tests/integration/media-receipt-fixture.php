<?php
/** Receipt factory for exclusive SQL fixtures. Never an application bootstrap.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Media\{ConsumptionReceipt, ConsumerJobs};
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;

// phpcs:disable Squiz.Commenting
/** A structurally valid blank page also exercises laboratories with PDF rendering enabled. */
function arn_receipt_pdf_fixture(): string {
	$pdf     = "%PDF-1.4\n";
	$offsets = array( 0 );
	$objects = array( '<< /Type /Catalog /Pages 2 0 R >>', '<< /Type /Pages /Count 1 /Kids [3 0 R] >>', '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] /Contents 4 0 R >>', "<< /Length 0 >>\nstream\n\nendstream" );
	foreach ( $objects as $index => $object ) {
		$offsets[] = strlen( $pdf );
		$pdf      .= ( $index + 1 ) . " 0 obj\n" . $object . "\nendobj\n";
	}
	$xref = strlen( $pdf );
	$pdf .= "xref\n0 5\n0000000000 65535 f \n";
	foreach ( array_slice( $offsets, 1 ) as $offset ) {
		$pdf .= sprintf( "%010d 00000 n \n", $offset ); }
	return $pdf . "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
}

function arn_receipt_schema_fixture( \Arn\Intranet\Identity\IdentityWpdb $db ): array {
	$schema = new \Arn\Intranet\Media\ReceiptSchema( $db );
	$schema->install();
	$tables = array( $schema->table() );
	if ( \Arn\Intranet\Cms\Configuration::current() ) {
		$cms = new \Arn\Intranet\Cms\SettingsSchema( $db );
		$cms->install();
		$clock = static fn(): int => time();
		( new \Arn\Intranet\Cms\SettingsRepository( $db, new \Arn\Intranet\Cms\CurrentActor( $db, $clock ), $clock ) )->initialize();
		foreach ( array( 'state', 'audit', 'schema' ) as $part ) {
			$tables[] = $cms->table( $part ); }
	}
	return $tables;
}

function arn_receipt_fixture( string $module, int $actor, int $id, int $expected, array $blobs, array $payload ): ConsumptionReceipt {
	return new ConsumptionReceipt(
		bin2hex( random_bytes( 16 ) ),
		array(
			'owner'   => $actor,
			'context' => ConsumerJobs::intent( $module, $id, $expected, 'Prova sintética de transacção', $payload ),
			'items'   => array_map(
				static fn( BlobDescriptor $b ): array => array(
					'blob' => array(
						'id'     => $b->id,
						'sha256' => $b->sha256,
						'size'   => $b->size,
						'mime'   => $b->mime,
					),
				),
				$blobs
			),
		)
	);
}
