<?php
/**
 * Criação, retoma e rollback em tabelas exclusivamente fictícias.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\PreRegistration\{WorkspaceBatch, WpdbPreRegistrationRepository, WorkspacePreRegistrationCommand};
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;

// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Ensaio SQL exclusivo com limpeza de nomes gerados pelo servidor.
require_once __DIR__ . '/workspace-fixture.php';
require_once dirname( __DIR__ ) . '/phpunit/doubles/WorkspaceInventoryFixture.php';

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio Workspace recusado.' ); }
if ( 'development' === wp_get_environment_type() ) {
	if ( WorkspacePreRegistrationCommand::permitted() ) {
		throw new RuntimeException( 'Gate Workspace abriu fora de local.' ); }
	WP_CLI::success( 'Workspace: development negativo, sem escrita.' );
	return;
}
$fixture            = new ArnWorkspaceSqlFixture();
$db                 = $fixture->database;
$checks             = 0;
$emails             = 0;
$user_hooks         = 0;
$assert             = static function ( bool $ok, string $name ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Workspace SQL: ' . $name );
	} ++$checks;
};
$deny               = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable $error ) {
		$assert( null === $error->getPrevious(), 'erro sem payload SQL' );
		return;
	} $assert( false, 'operação devia recusar' );
};
$mail_guard         = static function () use ( &$emails ): bool {
	++$emails;
	return false;
};
$register_guard     = static function () use ( &$user_hooks ): void {
	++$user_hooks;
};
$normal_write_guard = static function ( string $sql ): string {
	global $wpdb;
	if ( preg_match( '/\A\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|DROP|CREATE|TRUNCATE)\b/i', $sql ) ) {
		foreach ( $wpdb->tables( 'all', true ) as $normal_table ) {
			if ( preg_match( '/(?<![A-Za-z0-9_])`?' . preg_quote( $normal_table, '/' ) . '`?(?![A-Za-z0-9_])/', $sql ) ) {
				throw new RuntimeException( 'Escrita normal recusada pelo ensaio exclusivo.' ); }
		}
	}
	return $sql;
};
add_filter( 'pre_wp_mail', $mail_guard, PHP_INT_MAX );
add_action( 'user_register', $register_guard );
add_filter( 'query', $normal_write_guard, PHP_INT_MAX );
try {
	$repository = new WpdbPreRegistrationRepository( $db );
	$deny( static fn() => $repository->findForUser( 1 ) );
	$repository->installSchema();
	$repository->installSchema();
	$f     = WorkspaceInventoryFixture::make( 2 );
	$batch = WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], $f['inventory_hash'], 'example.invalid', 2, 1894705200 );
	$a     = $repository->provision( $batch->entries[0], $batch, get_current_user_id(), 1894705200 );
	$assert( 1 === $fixture->count( 'users' ) && 1 === $fixture->count( 'arn_workspace_accounts' ) && 1 === $fixture->count( 'arn_workspace_audit' ), 'uma criação e um evento' );
	$hash  = $db->get_var( $db->prepare( 'SELECT user_pass FROM %i WHERE ID=%d', $fixture->prefix . 'users', $a->user_id ) );
	$again = $repository->provision( $batch->entries[0], $batch, get_current_user_id(), 1894705201 );
	$assert( $a->id === $again->id && $a->user_id === $again->user_id && 1 === $again->revision && 1 === $fixture->count( 'arn_workspace_audit' ), 'retoma não repete conta ou evento' );
	$assert( $hash === $db->get_var( $db->prepare( 'SELECT user_pass FROM %i WHERE ID=%d', $fixture->prefix . 'users', $a->user_id ) ), 'retoma conserva hash' );
	$cap = $db->get_var( $db->prepare( 'SELECT meta_value FROM %i WHERE user_id=%d AND meta_key=%s', $fixture->prefix . 'usermeta', $a->user_id, $fixture->prefix . 'capabilities' ) );
	$assert( 'a:0:{}' === $cap, 'zero capabilities apesar de default administrator' );
	$assert( '1' === $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id=%d AND meta_key=%s AND meta_value=%s', $fixture->prefix . 'usermeta', $a->user_id, '_arn_workspace_pre_registration', $a->id ) ), 'marcador na mesma conta' );
	$assert( '0' === $db->get_var( $db->prepare( "SELECT COUNT(*) FROM %i WHERE meta_key IN ('session_tokens','_application_passwords')", $fixture->prefix . 'usermeta' ) ), 'zero sessões e passwords de aplicação' );
	$meta_before = $fixture->count( 'usermeta' );
	$trigger     = $fixture->prefix . 'deny_audit';
	$assert( false !== $db->query( $db->prepare( "CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='fixture audit failure'", $trigger, $fixture->prefix . 'arn_workspace_audit' ) ), 'trigger de falha instalado' );
	$deny( static fn() => $repository->provision( $batch->entries[1], $batch, get_current_user_id(), 1894705200 ) );
	$assert( 1 === $fixture->count( 'users' ) && 1 === $fixture->count( 'arn_workspace_accounts' ) && $meta_before === $fixture->count( 'usermeta' ) && null === $repository->findForEmail( $batch->entries[1]->primary_email ), 'rollback de conta meta registo e evento' );
	$db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) );
	$b = $repository->provision( $batch->entries[1], $batch, get_current_user_id(), 1894705202 );
	$assert( 2 === $fixture->count( 'users' ) && 2 === $fixture->count( 'arn_workspace_audit' ) && $b->user_id !== $a->user_id, 'retoma cria apenas a entrada em falta' );
	$again_expired = $repository->provision( $batch->entries[0], $batch, get_current_user_id(), $batch->expires_at + 1 );
	$assert( $again_expired->id === $a->id && $again_expired->expires_at === $a->expires_at && 2 === $fixture->count( 'arn_workspace_audit' ), 'repetição não renova a validade' );
	$different_batch = WorkspaceBatch::validate( $f['inventory'], $f['source'], $f['source_hash'], str_repeat( 'd', 64 ), 'example.invalid', 2, 1894705200 );
	$deny( static fn() => $repository->provision( $different_batch->entries[0], $different_batch, get_current_user_id(), 1894705200 ) );
	$deny( static fn() => $repository->provision( new \Arn\Intranet\Identity\PreRegistration\WorkspaceEntry( 'Não seleccionado', 'other@example.invalid' ), $batch, get_current_user_id(), 1894705200 ) );
	$f3     = WorkspaceInventoryFixture::make( 3 );
	$batch3 = WorkspaceBatch::validate( $f3['inventory'], $f3['source'], $f3['source_hash'], $f3['inventory_hash'], 'example.invalid', 3, 1894705200 );
	$deny( static fn() => $repository->provision( $batch3->entries[2], $batch3, get_current_user_id(), $batch3->expires_at ) );
	$assert( 2 === $fixture->count( 'users' ) && null === $repository->findForEmail( $batch3->entries[2]->primary_email ), 'nova conta expirada não criada' );
	$assert( false !== $db->query( $db->prepare( 'INSERT INTO %i (user_login,user_pass,user_nicename,user_email,user_url,user_registered,user_activation_key,user_status,display_name) SELECT %s,user_pass,%s,%s,user_url,user_registered,user_activation_key,user_status,%s FROM %i WHERE ID=%d', $fixture->prefix . 'users', 'existing.fixture', 'existing.fixture', $batch3->entries[2]->primary_email, $batch3->entries[2]->display_name, $fixture->prefix . 'users', $a->user_id ) ), 'conta anterior exclusiva preparada' );
	$deny( static fn() => $repository->provision( $batch3->entries[2], $batch3, get_current_user_id(), 1894705200 ) );
	$assert( 3 === $fixture->count( 'users' ) && 2 === $fixture->count( 'arn_workspace_accounts' ) && 2 === $fixture->count( 'arn_workspace_audit' ), 'conta anterior nunca adoptada' );
	$db->query( 'START TRANSACTION' );
	$db->query( $db->prepare( "INSERT INTO %i (option_name,option_value,autoload) VALUES ('outer-probe','fixture','off')", $fixture->prefix . 'options' ) );
	$deny( static fn() => $repository->provision( $batch->entries[0], $batch, get_current_user_id(), 1894705200 ) );
	$assert( 'fixture' === $db->get_var( $db->prepare( "SELECT option_value FROM %i WHERE option_name='outer-probe'", $fixture->prefix . 'options' ) ), 'transacção exterior não foi revertida' );
	$db->query( 'ROLLBACK' );
	$assert( null === $db->get_var( $db->prepare( "SELECT option_value FROM %i WHERE option_name='outer-probe'", $fixture->prefix . 'options' ) ), 'transacção exterior não foi confirmada' );
	$db->query( $db->prepare( 'UPDATE %i SET version=2 WHERE id=1', $fixture->prefix . 'arn_workspace_schema' ) );
	$deny( static fn() => $repository->findForUser( $a->user_id ) );
	$db->query( $db->prepare( 'UPDATE %i SET version=1 WHERE id=1', $fixture->prefix . 'arn_workspace_schema' ) );
	$assert( $repository->findForUser( $a->user_id )->id === $a->id, 'restauro exclusivo sem reparar por leitura' );
	$assert( 0 === $emails && 0 === $user_hooks, 'sem emails ou hooks de criação nativos' );
	WP_CLI::success( 'Workspace provisioning: ' . $checks . ' verificações, zero escrita nas tabelas anteriores.' );
} finally {
	remove_filter( 'pre_wp_mail', $mail_guard, PHP_INT_MAX );
	remove_action( 'user_register', $register_guard );
	remove_filter( 'query', $normal_write_guard, PHP_INT_MAX );
	$fixture->close();
}
