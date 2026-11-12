<?php
/** Aplicação explícita de schema institucional, sem fixtures ou contas.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\Institutional;

use Arn\Intranet\Identity\{IdentityWpdb, WpdbIdentityLinkRepository};
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use RuntimeException;
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
final class MigrationRunner {
	public function __construct( private readonly IdentityWpdb $database, private readonly MaintenanceGate $gate, private readonly MigrationReceipts $receipts ) {}
	public function apply( int $actor, string $backup_ref ): void {
		$this->locked( fn() => $this->applyLocked( $actor, $backup_ref ) );
	}
	private function applyLocked( int $actor, string $backup_ref ): void {
		$this->gate->assertExclusive();
		$links = new WpdbIdentityLinkRepository( $this->database );
		$auth  = new AuthorizationSchema( $this->database );
		// Validar ambos os componentes antes de qualquer DDL. Nenhuma reparação de schema desconhecido.
		$links->validateInstallation();
		$auth->missingTables();
		$this->receipts->append( 'started', $actor, $backup_ref );
		$links->installSchema( false );
		$links->migrateGenerations();
		$this->receipts->append( 'identity', $actor, $backup_ref );
		$auth->install();
		$this->receipts->append( 'authorization', $actor, $backup_ref );
		$result = ( new MigrationPreflight( $this->database ) )->inspect();
		if ( 'current' !== $result['identity'] || 'current' !== $result['authorization'] ) {
			throw new RuntimeException( 'Migração não confirmada.' ); }
		$this->receipts->append( 'complete', $actor, $backup_ref );
	}
	public function release( int $actor ): void {
		$this->locked( fn() => $this->releaseLocked( $actor ) );
	}
	private function releaseLocked( int $actor ): void {
		$this->gate->assertExclusive();
		$records = $this->receipts->all();
		$last    = end( $records );
		if ( ! is_array( $last ) || ! in_array( $last['phase'], array( 'complete', 'released' ), true ) ) {
			throw new RuntimeException( 'Migração incompleta. Manutenção mantida.' ); }
		$result = ( new MigrationPreflight( $this->database ) )->inspect();
		if ( 'current' !== $result['identity'] || 'current' !== $result['authorization'] ) {
			throw new RuntimeException( 'Schema incompatível. Manutenção mantida.' ); }
		$this->receipts->append( 'released', $actor, $last['backup_ref'] );
		$this->gate->releaseMaintenance();
	}
	private function locked( \Closure $operation ): void {
		$this->gate->assertExclusive();
		$old   = $this->database->suppress_errors( true );
		$name  = null;
		$owned = false;
		try {
			$db_name = $this->database->get_var( 'SELECT DATABASE()' );
			if ( ! is_string( $db_name ) || '' === $db_name || '' !== $this->database->last_error ) {
				throw new RuntimeException( 'Destino indisponível.' ); }
			$name  = 'arn_migration_' . substr( hash( 'sha256', $db_name . '|' . $this->database->prefix ), 0, 40 );
			$owned = '1' === (string) $this->database->get_var( $this->database->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) );
			if ( ! $owned || '' !== $this->database->last_error ) {
				throw new RuntimeException( 'Outra migração está em curso.' ); }
			$operation();
		} finally {
			if ( $owned ) {
				$this->database->get_var( $this->database->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); }
			$this->database->suppress_errors( $old );
		}
	}
}
