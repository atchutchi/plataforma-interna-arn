<?php
/** Integração exclusiva das notificações locais.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\Records\CurrentLocalAccount;
use Arn\Intranet\Notifications\LocalNotificationsPage;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Notifications\WpdbNotificationRepository;
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Rótulo interno CLI.
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix = 'arn_notif_' . bin2hex( random_bytes( 6 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$auth          = new WpdbAuthorizationRepository( $db );
$notifications = new WpdbNotificationRepository( $db );
$users         = array();
$saved_actor   = get_current_user_id();
$saved_cookies = $_COOKIE;
$query_failure = null;
$other         = null;
try {
	$auth->installSchema();
	$missing = false;
	try {
		$notifications->page( 1, 1 );
	} catch ( RuntimeException ) {
		$missing = true; }
	$assert( $missing, 'tabela ausente recusa leitura sem instalação' );
	$query_failure = static function ( string $sql ) use ( $prefix ): string {
		return str_starts_with( $sql, 'CREATE TABLE' ) && str_contains( $sql, $prefix . 'arn_notification_reads' ) ? 'INVALID NOTIFICATION DDL' : $sql;
	};
	add_filter( 'query', $query_failure );
	$ddl_failed = false;
	try {
		$notifications->installSchema();
	} catch ( RuntimeException ) {
		$ddl_failed = true; }
	remove_filter( 'query', $query_failure );
	$query_failure = null;
	$assert( $ddl_failed, 'falha DDL recusada' );
	$notifications->installSchema();
	$notifications->installSchema();
	foreach ( array( 'a', 'b' ) as $name ) {
		$fixture_id = wp_insert_user(
			array(
				'user_login' => $prefix . $name,
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => 'subscriber',
			)
		);
		$assert( is_int( $fixture_id ), 'conta exclusiva' );
		$users[] = $fixture_id;
		$auth->createAccount( $fixture_id, new ChangeContext( get_current_user_id(), 'Ensaio exclusivo', 'local-test', time() ) );
		$auth->changeState( $fixture_id, AccountState::Active, 1, new ChangeContext( get_current_user_id(), 'Ensaio exclusivo', 'local-test', time() ) );
	}
	[$a, $b] = $users;
	$first   = $notifications->page( $a, 2 );
	$assert( count( $first['rows'] ) === 1 && null === $first['next'], 'apenas alteração confirmada' );
	$fixture_id = $first['rows'][0]['id'];
	$notifications->markRead( $a, 2, $fixture_id, 123 );
	$notifications->markRead( $a, 2, $fixture_id, 456 );
	$assert( 123 === $notifications->page( $a, 2 )['rows'][0]['read_at'], 'primeiro timestamp preservado' );
	$assert( 2 === $auth->find( $a )->revision && 2 === count( $auth->history( $a ) ), 'auditoria e revisão preservadas' );
	$deny = static function ( callable $run ) use ( $assert ): void {
		try {
			$run();
		} catch ( RuntimeException ) {
			$assert( true, 'recusa protegida' );
			return;
		}
		$assert( false, 'operação devia ser recusada' );
	};
	$deny( static fn()=> $notifications->markRead( $b, 2, $fixture_id, 789 ) );
	$deny( static fn()=> $notifications->markRead( $a, 1, $fixture_id, 789 ) );
	$deny( static fn()=> $notifications->markRead( $a, 2, PHP_INT_MAX, 789 ) );
	$deny( static fn()=> $notifications->page( $a, 1 ) );
	// Prova real de sessão, com composição exclusiva que não altera as flags.
	$manager                       = WP_Session_Tokens::get_instance( $a );
	$token                         = $manager->create( time() + 3600 );
	$session                       = $manager->get( $token );
	$session['arn_authz_local']    = true;
	$session['arn_authz_user_id']  = $a;
	$session['arn_authz_revision'] = 2;
	$manager->update( $token, $session );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $a, time() + 3600, 'logged_in', $token );
	wp_set_current_user( $a );
	$current = new CurrentLocalAccount( static fn()=>$auth, static fn()=>$a );
	$ui      = new LocalNotificationsPage( $current, static fn()=>$notifications );
	$body    = array(
		'event_id' => (string) $fixture_id,
		'revision' => '2',
		'_wpnonce' => wp_create_nonce( 'arn-local-notifications-read' ),
		'user_id'  => (string) $b,
		'actor_id' => (string) $b,
	);
	$assert( $ui->process( $body, 'POST' ), 'POST válido usa titular do servidor' );
	$assert( ! $ui->process( $body, 'GET' ), 'método GET recusado' );
	$assert( ! $ui->process( array_replace( $body, array( '_wpnonce' => 'bad' ) ), 'POST' ), 'nonce inválido recusado' );
	foreach ( array( '01', '0', '-1', str_repeat( '9', 30 ), array( '1' ) ) as $bad ) {
		$assert( ! $ui->process( array_replace( $body, array( 'event_id' => $bad ) ), 'POST' ), 'id inválido recusado' );
		$assert( ! $ui->process( array_replace( $body, array( 'revision' => $bad ) ), 'POST' ), 'revisão inválida recusada' );
	}
	$session['arn_authz_revision'] = 1;
	$manager->update( $token, $session );
	$assert( ! $ui->process( $body, 'POST' ), 'sessão antiga recusada' );
	$session['arn_authz_revision'] = 2;
	$manager->update( $token, $session );
	$other = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$other->suppress_errors( true );
	$other->query( 'START TRANSACTION' );
	$other->get_row( $other->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $prefix . 'arn_authz_accounts', $a ) );
	$db->query( 'SET SESSION innodb_lock_wait_timeout=1' );
	$deny( static fn()=> $notifications->markRead( $a, 2, $fixture_id, 999 ) );
	$other->query( 'ROLLBACK' );
	$assert( 123 === $notifications->page( $a, 2 )['rows'][0]['read_at'], 'bloqueio concorrente conserva primeiro recibo' );
	$b_event       = $notifications->page( $b, 2 )['rows'][0]['id'];
	$query_failure = static function ( string $sql ) use ( $prefix ): string {
		return str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, $prefix . 'arn_notification_reads' ) ? 'INVALID NOTIFICATION RECEIPT SQL' : $sql;
	};
	add_filter( 'query', $query_failure );
	$deny( static fn()=> $notifications->markRead( $b, 2, $b_event, 123 ) );
	remove_filter( 'query', $query_failure );
	$query_failure = null;
	$assert( null === $notifications->page( $b, 2 )['rows'][0]['read_at'], 'falha SQL não produz recibo' );
	$db->query( 'START TRANSACTION' );
	$inserted          = $db->query( $db->prepare( 'INSERT INTO %i (user_id,actor_id,event,revision,previous_json,new_json,reason,decision_ref,occurred_at) VALUES (%d,%d,%s,3,NULL,%s,%s,%s,123)', $prefix . 'arn_authz_audit', $a, $saved_actor, 'grant_created', '{}', 'fixture', 'fixture' ) );
	$rolled_back_event = (int) $db->insert_id;
	$assert( 1 === $inserted && '' === $db->last_error && $rolled_back_event > 0, 'evento inserido antes do rollback' );
	$db->query( 'ROLLBACK' );
	$remaining_event = $db->get_var( $db->prepare( 'SELECT id FROM %i WHERE id=%d', $prefix . 'arn_authz_audit', $rolled_back_event ) );
	$assert( null === $remaining_event && '' === $db->last_error, 'rollback remove o evento independentemente do filtro de revisão' );
	$assert( 1 === count( $notifications->page( $a, 2 )['rows'] ), 'evento revertido não cria aviso' );
	wp_set_current_user( $saved_actor );
	// Eventos sintéticos só nas tabelas exclusivas: não precisam de payloads públicos.
	for ( $rev = 3;$rev <= 28;++$rev ) {
		$db->query( $db->prepare( 'INSERT INTO %i (user_id,actor_id,event,revision,previous_json,new_json,reason,decision_ref,occurred_at) VALUES (%d,%d,%s,%d,NULL,%s,%s,%s,%d)', $prefix . 'arn_authz_audit', $a, get_current_user_id(), 27 === $rev ? 'unknown' : 'grant_created', $rev, '{}', 'segredo', 'fixture', time() ) );
	}
	$db->query( $db->prepare( 'UPDATE %i SET revision=28 WHERE user_id=%d', $prefix . 'arn_authz_accounts', $a ) );
	$unknown = (int) $db->get_var( $db->prepare( 'SELECT id FROM %i WHERE user_id=%d AND event=%s', $prefix . 'arn_authz_audit', $a, 'unknown' ) );
	$deny( static fn()=> $notifications->markRead( $a, 28, $unknown, 123 ) );
	$creation = (int) $db->get_var( $db->prepare( 'SELECT id FROM %i WHERE user_id=%d AND event=%s', $prefix . 'arn_authz_audit', $a, 'account_created' ) );
	$deny( static fn()=> $notifications->markRead( $a, 28, $creation, 123 ) );
	$db->query( $db->prepare( 'INSERT INTO %i (user_id,actor_id,event,revision,previous_json,new_json,reason,decision_ref,occurred_at) VALUES (%d,%d,%s,29,NULL,%s,%s,%s,123)', $prefix . 'arn_authz_audit', $a, $saved_actor, 'grant_created', '{}', 'fixture', 'fixture' ) );
	$future = (int) $db->insert_id;
	$deny( static fn()=> $notifications->markRead( $a, 28, $future, 123 ) );
	$p1 = $notifications->page( $a, 28 );
	$p2 = $notifications->page( $a, 28, $p1['next'] );
	$assert( 20 === count( $p1['rows'] ) && 6 === count( $p2['rows'] ) && null === $p2['next'], 'paginação 20 mais 1 e exclusão catálogo' );
	$assert( array() === array_intersect( array_column( $p1['rows'], 'id' ), array_column( $p2['rows'], 'id' ) ), 'sem duplicados' );
	$assert( array( 'id', 'occurred_at', 'title', 'read_at' ) === array_keys( $p1['rows'][0] ), 'envelope público mínimo' );
	$assert( 1 === count( $notifications->page( $b, 2 )['rows'] ), 'outro destinatário isolado' );
	$assert( ! in_array( $b_event, array_column( $notifications->page( $a, 28, $b_event + 1 )['rows'], 'id' ), true ), 'cursor de outra conta não inclui dados alheios' );
	$assert( ! in_array( $future, array_column( $p1['rows'], 'id' ), true ), 'evento futuro não é projectado' );
	$db->query( 'START TRANSACTION' );
	$db->query( $db->prepare( 'DELETE FROM %i WHERE user_id=%d', $prefix . 'arn_authz_audit', $a ) );
	$db->query( 'ROLLBACK' );
	$assert( 20 === count( $notifications->page( $a, 28 )['rows'] ), 'rollback preserva histórico' );
	$db->query( $db->prepare( 'UPDATE %i SET state=%s WHERE user_id=%d', $prefix . 'arn_authz_accounts', 'Suspended', $a ) );
	$deny( static fn()=> $notifications->markRead( $a, 28, $fixture_id, 789 ) );
	$deny( static fn()=> $notifications->page( $a, 28 ) );
	$db->query( $db->prepare( 'ALTER TABLE %i MODIFY read_at VARCHAR(20) NOT NULL', $prefix . 'arn_notification_reads' ) );
	$deny( static fn()=> $notifications->installSchema() );
	$deny( static fn()=> $notifications->page( $b, 2 ) );
	$assert( 'varchar(20)' === $db->get_row( $db->prepare( 'SHOW COLUMNS FROM %i WHERE Field=%s', $prefix . 'arn_notification_reads', 'read_at' ) )->Type, 'schema incompatível não reparado' );
	WP_CLI::success( 'Notificações locais: ' . $checks . ' verificações.' );
} finally {
	if ( null !== $query_failure ) {
		remove_filter( 'query', $query_failure ); }
	if ( null !== $other ) {
		$other->query( 'ROLLBACK' ); }
	$db->query( 'ROLLBACK' );
	$_COOKIE = $saved_cookies;
	wp_set_current_user( $saved_actor );
	foreach ( array( 'arn_notification_reads', 'arn_authz_grants', 'arn_authz_audit', 'arn_authz_accounts', 'arn_authz_schema' ) as $suffix ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $fixture_id ) {
		wp_delete_user( $fixture_id );
	}
}
