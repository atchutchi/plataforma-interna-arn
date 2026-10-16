<?php
/**
 * Renovação explícita de fonte sem reabrir estados ou criar competências.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\PreRegistration\{WorkspaceBatch, WpdbPreRegistrationRepository, LocalWorkspaceReadiness};
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Harness local com tabelas e fontes só fictícias.
require_once __DIR__ . '/workspace-fixture.php';
require_once dirname( __DIR__ ) . '/phpunit/doubles/WorkspaceInventoryFixture.php';
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio Workspace recusado.' );}
if ( 'development' === wp_get_environment_type() ) {
	if ( \Arn\Intranet\Identity\PreRegistration\WorkspacePreRegistrationCommand::permitted() ) {
		throw new RuntimeException( 'Gate Workspace inválido.' );}
	WP_CLI::success( 'Workspace readiness: development negativo, sem escrita.' );
	return;
}
$fixture            = new ArnWorkspaceSqlFixture();
$db                 = $fixture->database;
$checks             = 0;
$normal_write_guard = ArnWorkspaceSqlFixture::normalWriteGuard();
add_filter( 'query', $normal_write_guard, PHP_INT_MAX );
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Workspace readiness: ' . $label );
	}++$checks;
};
$deny   = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, 'recusa' );
		return;
	}$assert( false, 'devia recusar' );
};
try {
	$repository = new WpdbPreRegistrationRepository( $db );
	$repository->installSchema();
	$f      = WorkspaceInventoryFixture::make( 1 );
	$batch  = WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], $f['inventory_hash'], 'example.invalid', 1, 1894705200 );
	$actor  = get_current_user_id();
	$record = $repository->provision( $batch->entries[0], $batch, $actor, 1894705200 );
	$before = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY ID', $fixture->prefix . 'users' ), ARRAY_A );
	$deny( static fn()=>$repository->renew( $record->id, $batch, 1, $actor, 'Mesma observação não prolonga prazo', 1894790000 ) );
	$f['inventory']['observed_at_utc'] = '2030-01-16T10:00:00Z';
	$new                               = WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], str_repeat( 'd', 64 ), 'example.invalid', 1, 1894791600 );
	$trigger                           = $fixture->prefix . 'renew_audit_deny';
	$db->query( $db->prepare( "CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture renew failure'", $trigger, $fixture->prefix . 'arn_workspace_audit' ) );
	$deny( static fn()=>$repository->renew( $record->id, $new, 1, $actor, 'Falha da auditoria', 1894791600 ) );
	$assert( 1 === $repository->findForUser( $record->user_id )->revision && $record->expires_at === $repository->findForUser( $record->user_id )->expires_at, 'falha de auditoria reverte a renovação' );
	$db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) );
	$renewed = $repository->renew( $record->id, $new, 1, $actor, 'Nova origem verificada', 1894791600 );
	$assert( 2 === $renewed->revision && $renewed->expires_at === $new->expires_at && $renewed->observed_at === $new->observed_at, 'prazo vem da nova observação' );
	$assert( $before === $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY ID', $fixture->prefix . 'users' ), ARRAY_A ), 'renovação não muda a conta ou password' );
	$again = $repository->renew( $record->id, $new, 2, $actor, 'Repetição', 1894791601 );
	$assert( 2 === $again->revision && 2 === $fixture->count( 'arn_workspace_audit' ), 'retoma da renovação não repete evento' );
	$deny( static fn()=>$repository->renew( $record->id, $new, 1, $actor, 'Revisão antiga', 1894791601 ) );
	$disabled = $repository->setEnabled( $record->id, 2, false, $actor, 'Suspensão fictícia', 1894791602 );
	$deny( static fn()=>$repository->renew( $record->id, $new, $disabled->revision, $actor, 'Não reabrir Disabled', 1894791603 ) );
	$assert( 'Disabled' === $repository->findForUser( $record->user_id )->state->value, 'renovação não reactiva' );
	$assert( class_exists( LocalWorkspaceReadiness::class ), 'preparação explícita disponível' );
	$deny( static fn()=>( new LocalWorkspaceReadiness( $new ) )->complete( $actor + 1 ) );
	WP_CLI::success( 'Workspace readiness: ' . $checks . ' verificações.' );
} finally {
	remove_filter( 'query', $normal_write_guard, PHP_INT_MAX );
	$fixture->close();}
