<?php
/** Exact durable proof of one queued business mutation, never permission to repeat it.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions
final readonly class ConsumptionReceipt {
	public string $fingerprint;
	private array $context;
	private int $actor;

	public function __construct( public string $batch, array $state ) {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $batch ) || ! is_int( $state['owner'] ?? null ) || $state['owner'] < 1 || ! is_array( $state['context'] ?? null ) || ! is_array( $state['items'] ?? null ) || ! array_is_list( $state['items'] ) ) {
			throw new RuntimeException( 'Recibo de envio inválido.' ); }
		ConsumerIntent::validate( $state['context'], count( $state['items'] ) );
		$blobs = array_column( $state['items'], 'blob' );
		if ( count( $blobs ) !== count( $state['items'] ) ) {
			throw new RuntimeException( 'Recibo de envio inválido.' ); }
		foreach ( $blobs as $blob ) {
			if ( ! is_array( $blob ) || array_keys( $blob ) !== array( 'id', 'sha256', 'size', 'mime' ) ) {
				throw new RuntimeException( 'Recibo de envio inválido.' ); }
			new BlobDescriptor( ...$blob );
		}
		$this->actor       = $state['owner'];
		$this->context     = $state['context'];
		$this->fingerprint = hash( 'sha256', json_encode( array( $batch, $this->actor, $this->context, $blobs ), JSON_THROW_ON_ERROR ) );
	}

	public function assertTarget( string $module, string $operation, int $actor, int $object_id, int $revision ): void {
		$c = $this->context;
		if ( $module !== $c['module'] || $operation !== $c['operation'] || $actor !== $this->actor || $object_id < 1 || ( 0 !== $c['id'] && $object_id !== $c['id'] ) || $revision !== $c['expected'] + 1 ) {
			throw new RuntimeException( 'Recibo de envio divergente.' ); }
	}

	public function record( IdentityWpdb $db, string $module, string $operation, int $actor, int $object_id, int $revision ): void {
		$this->assertTarget( $module, $operation, $actor, $object_id, $revision );
		if ( ! $db->inTransaction() ) {
			throw new RuntimeException( 'Recibo exige transacção do módulo.' ); }
		$schema = new ReceiptSchema( $db );
		$schema->assertReady();
		$sql = $db->prepare( 'INSERT INTO %i (batch_id,fingerprint,module,operation,actor_id,object_id,revision,occurred_at) VALUES (%s,%s,%s,%s,%d,%d,%d,%d)', $schema->table(), $this->batch, $this->fingerprint, $module, $operation, $actor, $object_id, $revision, time() );
		if ( 1 !== $db->query( $sql ) || '' !== $db->last_error ) {
			throw new RuntimeException( 'Recibo de envio indisponível.' ); }
	}

	/** Only committed rows on a fresh connection may prove completion. */
	public function resolve( IdentityWpdb $db ): int {
		if ( $db->inTransaction() ) {
			throw new RuntimeException( 'Consulta de recibo indisponível.' ); }
		$schema = new ReceiptSchema( $db );
		$schema->assertReady();
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i WHERE batch_id=%s', $schema->table(), $this->batch ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $db->last_error || 1 !== count( $rows ) || ! hash_equals( $this->fingerprint, (string) $rows[0]['fingerprint'] ) || (int) $rows[0]['occurred_at'] < 1 ) {
			throw new RuntimeException( 'Não existe confirmação suficiente. Mantém o envio para revisão.' ); }
		$r = $rows[0];
		$this->assertTarget( $r['module'], $r['operation'], (int) $r['actor_id'], (int) $r['object_id'], (int) $r['revision'] );
		return (int) $r['object_id'];
	}
}
