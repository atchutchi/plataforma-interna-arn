<?php
/**
 * Registo de metadados e auditoria na mesma transacção.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Mantém referências e eventos na mesma transacção dedicada. */
final class WpdbFileRegistry {
	private const ERROR = 'Registo de ficheiros indisponível.';
	/**
	 * Contrato do módulo.
	 *
	 * @var FileSchema
	 */
	private readonly FileSchema $schema;

	/**
	 * Recebe a ligação exclusiva sem reconnect.
	 *
	 * @param IdentityWpdb $database Ligação dedicada.
	 * @throws RuntimeException Quando o prefixo é inválido.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		$database->suppress_errors( true );
		$this->schema = new FileSchema( $database );
	}

	/**
	 * Regista referência e evento atomicamente.
	 *
	 * @param FileRecord $record Referência imutável.
	 * @throws RuntimeException Quando o registo é recusado.
	 */
	public function register( FileRecord $record ): void {
		$this->schema->assertReady();
		$this->assertNoTransaction();
		$this->execute( 'START TRANSACTION' );
		try {
			$b = $record->blob;
			$this->execute( $this->database->prepare( 'INSERT INTO %i (blob_id,sha256,size_bytes,mime,object_type,object_id,version,variant,actor_id,created_at) VALUES (%s,%s,%d,%s,%s,%d,%d,%s,%d,%d)', $this->schema->table( 'files' ), $b->id, $b->sha256, $b->size, $b->mime, $record->object_type, $record->object_id, $record->version, $record->variant, $record->actor_id, $record->created_at ) );
			$this->execute( $this->database->prepare( 'INSERT INTO %i (blob_id,event,actor_id,occurred_at) VALUES (%s,%s,%d,%d)', $this->schema->table( 'audit' ), $b->id, 'file_registered', $record->actor_id, $record->created_at ) );
			$this->execute( 'COMMIT' );
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( self::ERROR );
		}
	}

	/**
	 * Resolve uma referência pelo ID opaco.
	 *
	 * @param string $id Identificador do binário.
	 * @return FileRecord|null Referência encontrada.
	 * @throws RuntimeException Quando schema ou ligação não estão prontos.
	 */
	public function find( string $id ): ?FileRecord {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
			throw new RuntimeException( self::ERROR ); }
		$this->schema->assertReady();
		$this->assertNoTransaction();
		return $this->one( $this->database->prepare( 'SELECT * FROM %i WHERE blob_id=%s', $this->schema->table( 'files' ), $id ) );
	}

	/**
	 * Consulta singular para um objecto fechado do servidor.
	 *
	 * @param string $object_type Tipo de objecto.
	 * @param int    $object_id Identificador do objecto.
	 * @param int    $version Versão.
	 * @param string $variant Variante.
	 * @return FileRecord|null Referência encontrada.
	 * @throws RuntimeException Quando os argumentos ou schema são inválidos.
	 */
	public function findForObject( string $object_type, int $object_id, int $version, string $variant ): ?FileRecord {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $object_type ) || $object_id < 1 || $version < 1 || ! in_array( $variant, array( 'original', 'thumbnail', 'preview' ), true ) ) {
			throw new RuntimeException( self::ERROR ); }
		$this->schema->assertReady();
		$this->assertNoTransaction();
		return $this->one( $this->database->prepare( 'SELECT * FROM %i WHERE object_type=%s AND object_id=%d AND version=%d AND variant=%s', $this->schema->table( 'files' ), $object_type, $object_id, $version, $variant ) );
	}

	/**
	 * Projecta uma linha de metadados validada.
	 *
	 * @param string $sql Consulta singular preparada.
	 * @return FileRecord|null Referência encontrada.
	 * @throws RuntimeException Quando a linha é inválida.
	 */
	private function one( string $sql ): ?FileRecord {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error || count( $rows ) > 1 ) {
			throw new RuntimeException( self::ERROR ); }
		if ( array() === $rows ) {
			return null; }
		$r = $rows[0];
		try {
			return new FileRecord( new BlobDescriptor( $r->blob_id, $r->sha256, (int) $r->size_bytes, $r->mime ), $r->object_type, (int) $r->object_id, (int) $r->version, $r->variant, (int) $r->actor_id, (int) $r->created_at );
		} catch ( Throwable ) {
			throw new RuntimeException( self::ERROR );
		}
	}

	/**
	 * Impede misturar a ligação com transacções de outros módulos.
	 *
	 * @throws RuntimeException Quando existe transacção externa.
	 */
	private function assertNoTransaction(): void {
		$row = $this->database->get_row( 'SELECT @@session.in_transaction AS active' );
		if ( ! is_object( $row ) || '' !== $this->database->last_error || '0' !== (string) $row->active ) {
			throw new RuntimeException( self::ERROR ); }
	}

	/**
	 * Executa SQL preparado com falha fechada.
	 *
	 * @param string $sql Comando preparado.
	 * @throws RuntimeException Quando a base recusa a escrita.
	 */
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( self::ERROR ); }
	}
}
