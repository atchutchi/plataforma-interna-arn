<?php
/** Persistência isolada com bloqueio global e auditoria atómica.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Storage;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped
/** Persistência auditada com bloqueio global. */
final class WpdbLocalUnitRepository {
	/**
	 * Esquema estrito das tabelas.
	 *
	 * @var LocalUnitSchema
	 */
	private readonly LocalUnitSchema $schema;
	/**
	 * Inicializa e valida o contexto local.
	 *
	 * @param IdentityWpdb $database Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		$this->schema = new LocalUnitSchema( $database ); }
	/**
	 * Operação install schema.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function installSchema(): void {
		$this->silently( fn() => $this->schema->install() ); }
	/**
	 * Lê um snapshot consistente da árvore.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function snapshot(): LocalUnitSnapshot {
		return $this->silently( fn(): LocalUnitSnapshot => $this->readSnapshot() );
	}
	/** Read and lock the same tree inside the caller's transaction. */
	public function snapshotInTransaction(): LocalUnitSnapshot {
		return $this->silently(
			function (): LocalUnitSnapshot {
				$row = $this->database->get_row( 'SELECT @@session.in_transaction AS active' );
				if ( ! is_object( $row ) || '' !== $this->database->last_error || '1' !== (string) $row->active ) {
					throw new RuntimeException( 'Transacção de unidades ausente.' );
				}
				$this->schema->validate();
				return $this->load( true );
			}
		);
	}
	/**
	 * Executa a leitura consistente na ligação dedicada.
	 *
	 * @throws RuntimeException Quando a leitura falha.
	 */
	private function readSnapshot(): LocalUnitSnapshot {
		$this->schema->validate();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ' );
		$this->execute( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' );
		try {
			$snapshot = $this->load();
			$this->execute( 'COMMIT' );
			return $snapshot; } catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( 'Leitura local indisponível.' ); }
	}
	/**
	 * Carrega a revisão e unidades da ligação corrente.
	 *
	 * @param bool $locked Bloqueia linhas na transacção do chamador.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	private function load( bool $locked = false ): LocalUnitSnapshot {
		$header = $this->rows( $this->database->prepare( 'SELECT id, revision FROM %i', $this->schema->table( 'header' ) ) . ( $locked ? ' FOR UPDATE' : '' ) );
		if ( 1 !== count( $header ) || '1' !== (string) $header[0]->id ) {
			throw new RuntimeException( 'Cabeçalho local inválido.' ); }
		$revision = self::revision( $header[0]->revision );
		$payloads = array();
		$ids      = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT id, payload FROM %i ORDER BY id', $this->schema->table( 'units' ) ) . ( $locked ? ' FOR UPDATE' : '' ) ) as $row ) {
			$payloads[] = $row->payload;
			$ids[]      = $row->id;
		}
		$snapshot = LocalUnitSnapshot::decode( $revision, '[' . implode( ',', $payloads ) . ']' );
		if ( array_map( static fn( $unit ): string => $unit->id, $snapshot->units ) !== $ids ) {
			throw new RuntimeException( 'Unidade local inválida.' ); }
		return $snapshot;
	}
	/**
	 * Valida a revisão decimal canónica.
	 *
	 * @param mixed $value Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public static function revision( mixed $value ): int {
		if ( ! is_string( $value ) || ! preg_match( '/^(0|[1-9][0-9]*)$/D', $value ) || strlen( $value ) > strlen( (string) PHP_INT_MAX ) || ( strlen( $value ) === strlen( (string) PHP_INT_MAX ) && strcmp( $value, (string) PHP_INT_MAX ) > 0 ) ) {
			throw new RuntimeException( 'Revisão local inválida.' ); }
		return (int) $value;
	}
	/**
	 * Aplica um cenário sobre uma árvore validada.
	 *
	 * @param string           $scenario Argumento da operação.
	 * @param int              $expected_revision Argumento da operação.
	 * @param LocalUnitContext $context Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function apply( string $scenario, int $expected_revision, LocalUnitContext $context ): void {
		$this->silently( fn() => $this->applyInternal( $scenario, $expected_revision, $context ) );
	}
	/**
	 * Aplica a mutação e o evento numa única transacção.
	 *
	 * @param string           $scenario Cenário fechado.
	 * @param int              $expected_revision Revisão esperada.
	 * @param LocalUnitContext $context Contexto auditado.
	 * @throws RuntimeException Quando a alteração é recusada.
	 */
	private function applyInternal( string $scenario, int $expected_revision, LocalUnitContext $context ): void {
		if ( 0 > $expected_revision || PHP_INT_MAX === $expected_revision || ! isset( LocalUnitScenarios::labels()[ $scenario ] ) ) {
			throw new RuntimeException( 'Pedido local inválido.' ); }
		$this->schema->validate();
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$this->rows( $this->database->prepare( 'SELECT id FROM %i WHERE id = 1 FOR UPDATE', $this->schema->table( 'header' ) ) );
			$before = $this->load();
			if ( $before->revision !== $expected_revision ) {
				throw new RuntimeException( 'Revisão local desactualizada.' ); }
			$after    = LocalUnitScenarios::apply( $scenario, $before->units );
			$payloads = json_decode( LocalUnitSnapshot::encode( $after ), true, 32, JSON_THROW_ON_ERROR );
			foreach ( $after as $index => $unit ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Exige falha explícita antes de gravar dados.
				$payload = json_encode( $payloads[ $index ], JSON_THROW_ON_ERROR );
				$this->execute( $this->database->prepare( 'INSERT INTO %i (id, payload) VALUES (%s, %s) ON DUPLICATE KEY UPDATE payload = VALUES(payload)', $this->schema->table( 'units' ), $unit->id, $payload ) );
			}
			$this->execute( $this->database->prepare( 'UPDATE %i SET revision = %d WHERE id = 1', $this->schema->table( 'header' ), $expected_revision + 1 ) );
			$this->execute( $this->database->prepare( 'INSERT INTO %i (actor_id, event, revision, previous_json, new_json, reason, occurred_at) VALUES (%d, %s, %d, %s, %s, CONVERT(UNHEX(%s) USING utf8mb4), %d)', $this->schema->table( 'audit' ), $context->actor_id, $scenario, $expected_revision + 1, LocalUnitSnapshot::encode( $before->units ), LocalUnitSnapshot::encode( $after ), bin2hex( $context->reason ), $context->occurred_at ) );
			$this->execute( 'COMMIT' );
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( 'Alteração local recusada. Recarrega a página.' ); }
	}
	/**
	 * Lê os últimos eventos auditados.
	 *
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	public function history(): array {
		return $this->silently( fn(): array => $this->readHistory() );
	}
	/**
	 * Lê e valida eventos da ligação dedicada.
	 *
	 * @throws RuntimeException Quando a leitura é recusada.
	 */
	private function readHistory(): array {
		$this->schema->validate();
		$rows = $this->rows( $this->database->prepare( 'SELECT actor_id, event, revision, previous_json, new_json, reason, occurred_at FROM %i ORDER BY revision DESC LIMIT 20', $this->schema->table( 'audit' ) ) );
		foreach ( $rows as $row ) {
			$revision = self::revision( $row->revision );
			if ( $revision < 1 || ! isset( LocalUnitScenarios::labels()[ $row->event ] ) ) {
				throw new RuntimeException( 'Auditoria local inválida.' ); }
			LocalUnitSnapshot::decode( $revision - 1, $row->previous_json );
			LocalUnitSnapshot::decode( $revision, $row->new_json );
			new LocalUnitContext( self::revision( $row->actor_id ), $row->reason, self::revision( $row->occurred_at ) );
		}
		return $rows;
	}
	/**
	 * Suprime saída SQL apenas durante esta operação e repõe o estado anterior.
	 *
	 * @param callable $operation Operação interna sobre a ligação dedicada.
	 * @return mixed
	 * @throws RuntimeException Quando a persistência recusa a operação.
	 */
	private function silently( callable $operation ): mixed {
		$previous = $this->database->suppress_errors( true );
		try {
			return $operation();
		} catch ( Throwable ) {
			throw new RuntimeException( 'Persistência local indisponível.' );
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}
	/**
	 * Executa SQL interno e recusa falhas.
	 *
	 * @param string $sql Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Persistência local indisponível.' ); }
	}
	/**
	 * Lê linhas SQL e recusa falhas.
	 *
	 * @param string $sql Argumento da operação.
	 * @throws \RuntimeException Quando a operação é recusada.
	 */
	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura local indisponível.' ); }
		return $rows;
	}
}
