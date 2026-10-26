<?php
/** Atomic fictional catalogue, audit and idempotent receipts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Register;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Arn\Intranet\Organization\ImportPreview\StrictCsv;
use Closure;
use RuntimeException;
use Throwable;
// phpcs:disable WordPress.PHP.YodaConditions -- Structured server values compared in business order.
// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class Repository {
	private readonly Schema $schema;
	public function __construct( private readonly IdentityWpdb $db, private readonly Authority $authority, private readonly Confirmation $confirmation, private readonly Closure $clock ) {
		$this->schema = new Schema( $db );
	}
	private function transaction( Closure $operation ): mixed {
		if ( $this->db->inTransaction() ) {
			throw new RuntimeException( 'Transacção externa recusada.' ); }
		$old = $this->db->suppress_errors( true );
		try {
			$this->schema->validate();
			$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
			$this->execute( 'START TRANSACTION' );
			$actor  = $this->authority->lock();
			$result = $operation( $actor );
			$this->authority->assertCurrent();
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable $e ) {
			$this->db->query( 'ROLLBACK' );
			throw new RuntimeException( 'Operação não confirmada. Consulta o histórico e valida novamente.', 0, $e );
		} finally {
			$this->db->suppress_errors( $old );
		}
	}
	private function catalog(): Catalog {
		$rows = $this->rows( $this->db->prepare( 'SELECT id, revision, payload FROM %i FOR UPDATE', $this->schema->table( 'header' ) ) );
		if ( count( $rows ) !== 1 || '1' !== (string) $rows[0]->id ) {
			throw new RuntimeException( 'Cadastro indisponível.' ); }
		$catalog = Catalog::decode( WpdbLocalUnitRepository::revision( $rows[0]->revision ), $rows[0]->payload );
		$latest  = $this->rows( $this->db->prepare( 'SELECT reason, revision FROM %i ORDER BY revision DESC LIMIT 1', $this->schema->table( 'audit' ) ) );
		if ( 0 === $catalog->revision ) {
			if ( array() !== $latest || array() !== $this->rows( $this->db->prepare( 'SELECT id FROM %i LIMIT 1', $this->schema->table( 'batches' ) ) ) ) {
				throw new RuntimeException( 'Histórico divergente.' ); }
		} else {
			if ( count( $latest ) !== 1 || (string) $catalog->revision !== (string) $latest[0]->revision ) {
				throw new RuntimeException( 'Histórico divergente.' ); }
			$receipts = $this->rows( $this->db->prepare( 'SELECT payload FROM %i WHERE id = %s', $this->schema->table( 'batches' ), $latest[0]->reason ) );
			if ( count( $receipts ) !== 1 ) {
				throw new RuntimeException( 'Histórico divergente.' ); }
			$r = self::receipt( $receipts[0]->payload );
			if ( $r['id'] !== $latest[0]->reason || $r['revision'] !== $catalog->revision || $r['after'] !== hash( 'sha256', $catalog->encode() ) ) {
				throw new RuntimeException( 'Histórico divergente.' ); }
			$this->verifyAudit( $r );
		}
		return $catalog;
	}
	public function preview( string $csv ): array {
		return $this->transaction(
			function ( int $actor ) use ( $csv ): array {
				$units   = ( new WpdbLocalUnitRepository( $this->db ) )->snapshotInTransaction();
				$catalog = $this->catalog();
				$report  = Validator::validate( $csv, $units, $catalog );
				$proof   = $report->valid ? $this->confirmation->issue( $report->batch_id, $actor, $this->authority->token(), ( $this->clock )() ) : '';
				return array(
					'report' => $report,
					'proof'  => $proof,
				);
			}
		);
	}
	public function apply( string $csv, string $proof ): array {
		if ( strlen( $csv ) > StrictCsv::MAX_BYTES || strlen( $proof ) > 150 ) {
			throw new RuntimeException( 'Lote inválido.' ); }
		return $this->transaction(
			function ( int $actor ) use ( $csv, $proof ): array {
				$batch    = $this->confirmation->validate( $proof, $actor, $this->authority->token(), ( $this->clock )() );
				$units    = ( new WpdbLocalUnitRepository( $this->db ) )->snapshotInTransaction();
				$before   = $this->catalog();
				$id       = hash( 'sha256', $actor . ':' . $batch );
				$existing = $this->rows( $this->db->prepare( 'SELECT payload FROM %i WHERE id = %s FOR UPDATE', $this->schema->table( 'batches' ), $id ) );
				if ( array() !== $existing ) {
					$r = self::receipt( $existing[0]->payload );
					if ( $r['id'] !== $id || $r['actor'] !== $actor || $r['batch'] !== $batch || ! hash_equals( $r['csv_hash'], hash( 'sha256', $csv ) ) ) {
						throw new RuntimeException( 'Recibo divergente.' ); }
					$this->verifyAudit( $r );
					return $r;
				}
				$report = Validator::validate( $csv, $units, $before );
				if ( ! $report->valid || ! hash_equals( $report->batch_id, $batch ) || $before->revision >= 10000 ) {
					throw new RuntimeException( 'Validação desactualizada ou limite atingido.' ); }
				$people = $before->people;
				foreach ( $report->rows as $row ) {
					[ $person_id, $name, $email, $unit ] = $row['values'];
					$people[ $person_id ]                = array(
						'name'    => $name,
						'email'   => $email,
						'unit_id' => $unit,
					);
				}
				ksort( $people, SORT_STRING );
				$after = new Catalog( $before->revision + 1, $people );
				$r     = array(
					'id'             => $id,
					'actor'          => $actor,
					'batch'          => $batch,
					'csv_hash'       => $report->csv_hash,
					'revision'       => $after->revision,
					'units_revision' => $units->revision,
					'totals'         => $report->totals,
					'at'             => ( $this->clock )(),
					'before'         => hash( 'sha256', $before->encode() ),
					'after'          => hash( 'sha256', $after->encode() ),
				);
				$json  = json_encode( $r, JSON_THROW_ON_ERROR );
				self::receipt( $json );
				$this->execute( $this->db->prepare( 'UPDATE %i SET revision = %d, payload = CONVERT(UNHEX(%s) USING utf8mb4) WHERE id = 1 AND revision = %d', $this->schema->table( 'header' ), $after->revision, bin2hex( $after->encode() ), $before->revision ) );
				if ( 1 !== $this->db->rows_affected ) {
					throw new RuntimeException( 'Revisão divergente.' ); }
				$this->execute( $this->db->prepare( 'INSERT INTO %i (id, payload) VALUES (%s, CONVERT(UNHEX(%s) USING utf8mb4))', $this->schema->table( 'batches' ), $id, bin2hex( $json ) ) );
				$this->execute( $this->db->prepare( 'INSERT INTO %i (actor_id, event, revision, previous_json, new_json, reason, occurred_at) VALUES (%d, %s, %d, CONVERT(UNHEX(%s) USING utf8mb4), CONVERT(UNHEX(%s) USING utf8mb4), %s, %d)', $this->schema->table( 'audit' ), $actor, 'import', $after->revision, bin2hex( $before->encode() ), bin2hex( $after->encode() ), $id, $r['at'] ) );
				return $r;
			}
		);
	}
	public function listing(): array {
		return $this->transaction(
			function (): array {
				$catalog = $this->catalog();
				$history = array();
				foreach ( $this->rows( $this->db->prepare( 'SELECT reason FROM %i ORDER BY revision DESC LIMIT 20', $this->schema->table( 'audit' ) ) ) as $a ) {
					$stored = $this->rows( $this->db->prepare( 'SELECT payload FROM %i WHERE id = %s', $this->schema->table( 'batches' ), $a->reason ) );
					if ( count( $stored ) !== 1 ) {
						throw new RuntimeException( 'Histórico indisponível.' ); }
					$r = self::receipt( $stored[0]->payload );
					if ( $r['id'] !== $a->reason ) {
						throw new RuntimeException( 'Histórico divergente.' ); }
					$this->verifyAudit( $r );
					$history[] = $r;
				}
				if ( ( 0 === $catalog->revision && array() !== $history ) || ( $catalog->revision > 0 && ( ( $history[0]['revision'] ?? null ) !== $catalog->revision || ( $history[0]['after'] ?? '' ) !== hash( 'sha256', $catalog->encode() ) ) ) ) {
					throw new RuntimeException( 'Cadastro e histórico divergentes.' ); }
				return array(
					'catalog' => $catalog,
					'history' => $history,
				);
			}
		);
	}
	private function verifyAudit( array $r ): void {
		$rows = $this->rows( $this->db->prepare( 'SELECT actor_id, event, revision, previous_json, new_json, reason, occurred_at FROM %i WHERE revision = %d', $this->schema->table( 'audit' ), $r['revision'] ) );
		if ( count( $rows ) !== 1 ) {
			throw new RuntimeException( 'Auditoria ausente.' ); }
		$a = $rows[0];
		if ( (string) $r['actor'] !== (string) $a->actor_id || 'import' !== $a->event || $a->reason !== $r['id'] || (string) $r['at'] !== (string) $a->occurred_at || $r['before'] !== hash( 'sha256', Catalog::decode( $r['revision'] - 1, $a->previous_json )->encode() ) || $r['after'] !== hash( 'sha256', Catalog::decode( $r['revision'], $a->new_json )->encode() ) ) {
			throw new RuntimeException( 'Auditoria divergente.' ); }
	}
	public static function receipt( string $json ): array {
		if ( strlen( $json ) > 4096 ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		$r = json_decode( $json, true, 8, JSON_THROW_ON_ERROR );
		if ( ! is_array( $r ) || array_keys( $r ) !== array( 'id', 'actor', 'batch', 'csv_hash', 'revision', 'units_revision', 'totals', 'at', 'before', 'after' ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		foreach ( array( 'id', 'batch', 'csv_hash', 'before', 'after' ) as $field ) {
			if ( ! is_string( $r[ $field ] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $r[ $field ] ) ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
		}
		foreach ( array( 'actor', 'revision', 'units_revision', 'at' ) as $field ) {
			if ( ! is_int( $r[ $field ] ) || $r[ $field ] < 1 ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
		}
		if ( ! is_array( $r['totals'] ) || array_keys( $r['totals'] ) !== array( 'create', 'update', 'unchanged', 'invalid' ) || 0 !== $r['totals']['invalid'] || $r['id'] !== hash( 'sha256', $r['actor'] . ':' . $r['batch'] ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		foreach ( $r['totals'] as $count ) {
			if ( ! is_int( $count ) || $count < 0 || $count > 20 ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
		}
		if ( array_sum( $r['totals'] ) < 1 || array_sum( $r['totals'] ) > 20 ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		return $r;
	}
	private function execute( string $sql ): void {
		if ( false === $this->db->query( $sql ) || '' !== $this->db->last_error ) {
			throw new RuntimeException( 'Persistência indisponível.' ); }
	}
	private function rows( string $sql ): array {
		$r = $this->db->get_results( $sql );
		if ( ! is_array( $r ) || '' !== $this->db->last_error ) {
			throw new RuntimeException( 'Leitura indisponível.' ); }
		return $r;
	}
}
