<?php
/** Explicit installation of immutable queue receipts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
final class ReceiptSchema {

	public function __construct( private readonly IdentityWpdb $db ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $db->prefix ) ) {
			throw new RuntimeException( 'Recibo de envio indisponível.' ); }
	}
	public function table(): string {
		return $this->db->prefix . 'arn_media_receipts';
	}
	public function install(): void {
		if ( $this->db->inTransaction() ) {
			throw new RuntimeException( 'Instalação de recibos indisponível.' ); }
		$rows = $this->rows( $this->db->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table() ) );
		if ( array() === $rows ) {
			$sql = $this->db->prepare( 'CREATE TABLE %i (batch_id CHAR(32) NOT NULL, fingerprint CHAR(64) NOT NULL, module VARCHAR(16) NOT NULL, operation VARCHAR(8) NOT NULL, actor_id BIGINT UNSIGNED NOT NULL, object_id BIGINT UNSIGNED NOT NULL, revision BIGINT UNSIGNED NOT NULL, occurred_at BIGINT UNSIGNED NOT NULL, PRIMARY KEY(batch_id)) ENGINE=InnoDB DEFAULT CHARACTER SET ascii COLLATE ascii_bin', $this->table() );
			if ( false === $this->db->query( $sql ) || '' !== $this->db->last_error ) {
				throw new RuntimeException( 'Instalação de recibos indisponível.' ); }
		}
		$this->assertReady();
	}
	public function assertReady(): void {
		$engine = $this->rows( $this->db->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $this->table() ) );
		if ( 1 !== count( $engine ) || 'InnoDB' !== $engine[0]->ENGINE ) {
			throw new RuntimeException( 'Recibo de envio indisponível.' ); }
		$expected = array(
			'batch_id'    => 'char(32)',
			'fingerprint' => 'char(64)',
			'module'      => 'varchar(16)',
			'operation'   => 'varchar(8)',
			'actor_id'    => 'bigint unsigned',
			'object_id'   => 'bigint unsigned',
			'revision'    => 'bigint unsigned',
			'occurred_at' => 'bigint unsigned',
		);
		$fields   = $this->rows( $this->db->prepare( 'SHOW FULL COLUMNS FROM %i', $this->table() ) );
		if ( count( $expected ) !== count( $fields ) ) {
			throw new RuntimeException( 'Recibo de envio indisponível.' ); }
		foreach ( $fields as $f ) {
			$type      = preg_replace( '/^bigint\([0-9]+\)/', 'bigint', strtolower( $f->Type ) );
			$collation = str_starts_with( $type, 'bigint' ) ? null : 'ascii_bin';
			if ( ! isset( $expected[ $f->Field ] ) || $expected[ $f->Field ] !== $type || 'NO' !== $f->Null || null !== $f->Default || '' !== $f->Extra || $collation !== $f->Collation ) {
				throw new RuntimeException( 'Recibo de envio indisponível.' ); }
		}
		$indexes = $this->rows( $this->db->prepare( 'SHOW INDEX FROM %i', $this->table() ) );
		if ( 1 !== count( $indexes ) || 'PRIMARY' !== $indexes[0]->Key_name || 'batch_id' !== $indexes[0]->Column_name || 0 !== (int) $indexes[0]->Non_unique || null !== $indexes[0]->Sub_part || 'BTREE' !== $indexes[0]->Index_type ) {
			throw new RuntimeException( 'Recibo de envio indisponível.' ); }
	}
	private function rows( string $sql ): array {
		$rows = $this->db->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->db->last_error ) {
			throw new RuntimeException( 'Recibo de envio indisponível.' ); }
		return $rows;
	}
}
