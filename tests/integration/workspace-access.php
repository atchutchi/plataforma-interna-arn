<?php
/**
 * Estado e guardas Workspace com SQL exclusivo e hooks reais.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\PreRegistration\{WorkspaceBatch, WpdbPreRegistrationRepository, WordPressPreRegistrationAccess, PreRegistrationStateService, WorkspacePreRegistrationCommand};
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Harness sintético, sem contas reais ou alterações às tabelas anteriores.
require_once __DIR__ . '/workspace-fixture.php';
require_once dirname( __DIR__ ) . '/phpunit/doubles/WorkspaceInventoryFixture.php';
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio Workspace recusado.' );}
if ( 'development' === wp_get_environment_type() ) {
	if ( WorkspacePreRegistrationCommand::permitted() ) {
		throw new RuntimeException( 'Gate Workspace inválido.' );
	}WP_CLI::success( 'Workspace access: development negativo.' );
	return;}
$fixture = new ArnWorkspaceSqlFixture();
$db      = $fixture->database;
$checks  = 0;
$assert  = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Workspace access: ' . $label );
	}++$checks;
};
$deny    = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, 'recusa' );
		return;
	}$assert( false, 'devia recusar' );
};
global $wpdb;
$normal_tables      = $wpdb->tables( 'all', true );
$normal_write_guard = static function ( string $sql ) use ( $normal_tables ): string {
	if ( preg_match( '/\A\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE|TRUNCATE)\b/i', $sql ) ) {
		foreach ( $normal_tables as $normal_table ) {
			if ( preg_match( '/(?<![A-Za-z0-9_])`?' . preg_quote( $normal_table, '/' ) . '`?(?![A-Za-z0-9_])/', $sql ) ) {
				throw new RuntimeException( 'Escrita normal recusada pelo ensaio exclusivo.' ); }
		}
	}
	return $sql;
};
add_filter( 'query', $normal_write_guard, PHP_INT_MAX );
try {
	$repository = new WpdbPreRegistrationRepository( $db );
	$repository->installSchema();
	$f        = WorkspaceInventoryFixture::make( 1 );
	$batch    = WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], $f['inventory_hash'], 'example.invalid', 1, 1894705200 );
	$record   = $repository->provision( $batch->entries[0], $batch, get_current_user_id(), 1894705200 );
	$guard    = new WordPressPreRegistrationAccess( static fn( int $id )=>$repository->findForUser( $id ), static fn(): bool=>true );
	$user     = new WP_User();
	$user->ID = $record->user_id;
	$assert( $guard->filterAuthentication( $user ) instanceof WP_Error, 'password nativa recusada' );
	$assert( false === $guard->filterPasswordReset( true, $record->user_id ), 'recuperação recusada' );
	$reset_errors = new WP_Error();
	$guard->validatePasswordReset( $reset_errors, $user );
	$assert( $reset_errors->has_errors(), 'chave antiga recusada' );
	$assert( false === $guard->filterApplicationPasswords( true, $user ), 'API password recusada' );
	// A autenticação nativa usa a tabela exclusiva durante este bloco.
	global $wpdb;
	$normal_prefix    = $wpdb->prefix;
	$fixture_password = bin2hex( random_bytes( 24 ) );
	$db->query( $db->prepare( 'UPDATE %i SET user_pass=%s WHERE ID=%d', $fixture->prefix . 'users', wp_hash_password( $fixture_password ), $record->user_id ) );
	$guard->register();
	try {
		$wpdb->set_prefix( $fixture->prefix );
		wp_cache_delete( $record->user_id, 'users' );
		$native_user = get_userdata( $record->user_id );
		$assert( $native_user instanceof WP_User && wp_check_password( $fixture_password, $native_user->user_pass, $record->user_id ), 'password fictícia tecnicamente correcta' );
		$assert( wp_authenticate( $native_user->user_login, $fixture_password ) instanceof WP_Error, 'authenticate nativo recusa password correcta' );
		$assert( get_password_reset_key( $native_user ) instanceof WP_Error, 'recuperação nativa recusada' );
		$assert( false === wp_is_application_passwords_available_for_user( $native_user ), 'application passwords nativas recusadas' );
	} finally {
		$wpdb->set_prefix( $normal_prefix );
		wp_cache_delete( $record->user_id, 'users' );
		wp_cache_delete( $record->user_id, 'user_meta' );
		unset( $fixture_password );
	}
	$destroyed = array();
	$actor     = get_current_user_id();
	$service   = new PreRegistrationStateService(
		$repository,
		static fn( int $id ): bool=>$id === $actor,
		static function ( int $id ) use ( &$destroyed ): void {
			$destroyed[] = $id;
		}
	);
	$disabled  = $service->setEnabled( $record->id, 1, false, $actor, 'Ensaio local', 1894705201 );
	$assert( 'Disabled' === $disabled->state->value && 2 === $disabled->revision && array( $record->user_id ) === $destroyed, 'suspensão auditada e sessões destruídas' );
	$deny( static fn()=>$service->setEnabled( $record->id, 1, true, $actor, 'Revisão antiga', 1894705202 ) );
	$enabled = $service->setEnabled( $record->id, 2, true, $actor, 'Reactivação explícita', 1894705202 );
	$assert( 'AwaitingGoogle' === $enabled->state->value && 3 === $enabled->revision && 2 === count( $destroyed ), 'reactivação exige nova sessão' );
	$noop = $service->setEnabled( $record->id, 3, true, $actor, 'No-op', 1894705203 );
	$assert( 3 === $noop->revision && 3 === $fixture->count( 'arn_workspace_audit' ) && 2 === count( $destroyed ), 'no-op não repete evento ou invalidação' );
	$deny( static fn()=>$service->setEnabled( $record->id, 3, false, $actor + 1, 'Actor indevido', 1894705204 ) );
	$trigger = $fixture->prefix . 'state_audit_deny';
	$db->query( $db->prepare( "CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture state failure'", $trigger, $fixture->prefix . 'arn_workspace_audit' ) );
	$deny( static fn()=>$service->setEnabled( $record->id, 3, false, $actor, 'Falha auditada', 1894705204 ) );
	$assert( 3 === $repository->findForUser( $record->user_id )->revision && 2 === count( $destroyed ), 'rollback de estado e nenhuma invalidação prematura' );
	$db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) );
	$db->query( $db->prepare( 'UPDATE %i SET meta_value=%s WHERE user_id=%d AND meta_key=%s', $fixture->prefix . 'usermeta', 'malformed', $record->user_id, WpdbPreRegistrationRepository::MARKER ) );
	$assert( $guard->filterAuthentication( $user ) instanceof WP_Error, 'marcador malformado não recupera password' );
	WP_CLI::success( 'Workspace access: ' . $checks . ' verificações.' );
} finally {
	remove_filter( 'query', $normal_write_guard, PHP_INT_MAX );
	$fixture->close();}
