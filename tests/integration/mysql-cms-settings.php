<?php
/** Exclusive real-SQL settings persistence, rollback and writer concurrency.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress, Squiz.Commenting, Universal.Operators.DisallowShortTernary
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed -- Standalone SQL harness with WordPress function doubles and one authority double.
require __DIR__ . '/mysql-transaction-bootstrap.php';

use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{AuthorizationSnapshot, StoredGrant};
use Arn\Intranet\Cms\{NativeSession, SettingsActor, SettingsAuthority, SettingsRepository, SettingsSchema, UploadPolicy};
use Arn\Intranet\Identity\IdentityWpdb;

if ( 1 !== preg_match( '/\A(?:127\.0\.0\.1|localhost)(?::[0-9]{1,5})?\z/D', (string) getenv( 'ARN_TX_TEST_HOST' ) ) ) {
	throw new RuntimeException( 'Apenas servidor de ensaio loopback.' );
}
define( 'WP_CLI', true );
define( 'ARN_ALLOW_LOCAL_CMS_SETTINGS', true );
define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', true );
define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
function wp_get_environment_type(): string {
	return 'local'; }
function current_user_can( string $capability ): bool {
	return 'manage_options' === $capability; }

// PHP 8.4 has no mbstring function overloading. Core helpers are no-ops here.
function mbstring_binary_safe_encoding(): void {}
function reset_mbstring_encoding(): void {}

/** Synthetic authority isolates persistence from native WordPress sessions. */
final class ArnCmsSqlAuthority implements SettingsAuthority {
	public bool $allowed = true;
	public function __construct( private IdentityWpdb $db ) {}
	public function connection(): IdentityWpdb {
		return $this->db; }
	public function locked(): SettingsActor {
		if ( ! $this->db->inTransaction() ) {
			throw new RuntimeException( 'Authority called outside the transaction.' );
		}
		$grants = $this->allowed ? array( new StoredGrant( 1, new Grant( 7, Role::CmsAdministrator, Scope::institutional(), 0, 9999 ) ) ) : array();
		return new SettingsActor( new AuthorizationSnapshot( 7, AccountState::Active, 2, $grants ), array( 'arn_authz_local' => true, 'arn_authz_user_id' => 7, 'arn_authz_revision' => 2, 'expiration' => 9999 ) );
	}
}

$worker = in_array( $argv[3] ?? '', array( 'worker', 'expire-news', 'expire-document', 'expire-news-revise', 'expire-document-revise' ), true );
$prefix = $worker ? ( $argv[4] ?? '' ) : 'arn_cms_test_' . bin2hex( random_bytes( 6 ) ) . '_';
if ( 1 !== preg_match( '/\Aarn_cms_test_[a-f0-9]{12}_\z/D', $prefix ) ) {
	throw new RuntimeException( 'Exclusive prefix expected.' );
}
$db = arn_tx_connect();
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$authority  = new ArnCmsSqlAuthority( $db );
$repository = new SettingsRepository( $db, $authority, static fn(): int => 101 );
$schema     = new SettingsSchema( $db );
$values     = UploadPolicy::defaults()->toArray();
if ( $worker && 'worker' !== $argv[3] ) {
	$expiry_worker = require __DIR__ . '/mysql-cms-expiry-worker.php';
	$expiry_worker( $db, $argv[3], (int) ( $argv[5] ?? 0 ), (int) ( $argv[6] ?? 0 ) );
	exit;
}
if ( $worker ) {
	$values['document']['max_bytes'] = (int) ( $argv[5] ?? 2048 );
	try {
		$repository->change( UploadPolicy::fromArray( $values ), 2, 'Alteração concorrente fictícia.' );
		echo "saved\n";
	} catch ( DomainException ) {
		echo "conflict\n";
	}
	exit;
}

$checks         = 0;
$processes      = array();
$assert         = static function ( bool $value, string $message ) use ( &$checks ): void {
	if ( ! $value ) {
		throw new RuntimeException( 'CMS: ' . $message ); }
	++$checks;
};
$reject         = static function ( callable $operation, string $message ) use ( $assert ): void {
	$refused = false;
	try {
		$operation();
	} catch ( Throwable ) {
		$refused = true; }
	$assert( $refused, $message );
};
$tables_before  = $db->get_col( 'SHOW TABLES' );
$sessions_table = $prefix . 'native_sessions';
try {
	$db->query( 'CREATE TABLE `' . $sessions_table . '` (user_id BIGINT NOT NULL, meta_key VARCHAR(64) NOT NULL, meta_value LONGTEXT NOT NULL) ENGINE=InnoDB' );
	$token = str_repeat( 'a', 32 );
	$proof = array( 'expiration' => 9999, 'arn_authz_local' => true, 'arn_authz_user_id' => 7, 'arn_authz_revision' => 2 );
	$db->query( $db->prepare( 'INSERT INTO %i (user_id,meta_key,meta_value) VALUES (7,%s,%s)', $sessions_table, 'session_tokens', serialize( array( hash( 'sha256', $token ) => $proof ) ) ) );
	$sessions = new NativeSession( $db, $sessions_table );
	$reject( static fn() => $sessions->read( 7, $token, 100 ), 'native session requires caller transaction' );
	$db->query( 'START TRANSACTION' );
	$assert( $proof === $sessions->read( 7, $token, 100 ), 'locked native session proof' );
	$assert( array() === $sessions->read( 7, $token, 9999 ), 'expired native session rejected' );
	$assert( array() === $sessions->read( 8, $token, 100 ), 'another native user has no session proof' );
	$db->query( 'COMMIT' );
	$db->query( 'ALTER TABLE `' . $sessions_table . '` ENGINE=MyISAM' );
	$db->query( 'START TRANSACTION' );
	$reject( static fn() => $sessions->read( 7, $token, 100 ), 'nontransactional native sessions fail closed' );
	$db->query( 'ROLLBACK' );
	$db->query( 'DROP TABLE `' . $sessions_table . '`' );
	$reject( static fn() => $repository->read(), 'missing schema fails closed' );
	$schema->install();
	$schema->install();
	$reject( static fn() => $repository->read(), 'missing singleton not created by a read' );
	$repository->initialize();
	$repository->initialize();
	$first = $repository->read();
	$assert( 1 === $first->revision && array() === $repository->history(), 'initial defaults without fabricated changes' );
	$values['document']['max_bytes'] = 1024;
	$second                          = $repository->change( UploadPolicy::fromArray( $values ), 1, 'Redução fictícia.' );
	$assert( 2 === $second->revision && 1024 === $second->policy->toArray()['document']['max_bytes'], 'persisted revision' );
	$history = $repository->history();
	$assert( 1 === count( $history ) && 1048576 === $history[0]['before']['policy']['document']['max_bytes'] && $history[0]['after'] === $second->toArray(), 'atomic before and after audit' );
	$assert( 2 === $repository->change( $second->policy, 2, 'Valores confirmados.' )->revision && 1 === count( $repository->history() ), 'no-op has no event' );
	$reject( static fn() => $repository->change( $second->policy, 1, 'Revisão antiga.' ), 'stale revision even for no-op' );
	$authority->allowed = false;
	$reject( static fn() => $repository->read(), 'revoked read' );
	$reject( static fn() => $repository->history(), 'revoked audit read' );
	$reject( static fn() => $repository->change( UploadPolicy::defaults(), 2, 'Revogado.' ), 'revoked write' );
	$authority->allowed = true;
	$fault              = static fn( string $sql ): string => str_contains( $sql, 'INSERT INTO `' . $schema->table( 'audit' ) . '`' ) ? 'INSERT INTO `' . $prefix . 'missing` (id) VALUES (1)' : $sql;
	add_filter( 'query', $fault );
	try {
		$reject( static fn() => $repository->change( UploadPolicy::defaults(), 2, 'Falha na auditoria.' ), 'audit insertion failure' );
	} finally {
		remove_filter( 'query', $fault );
	}
	$assert( $repository->read()->toArray() === $second->toArray() && 1 === count( $repository->history() ), 'failed audit rolls back state' );
	$clock_calls = 0;
	$expiring    = new SettingsRepository(
		$db,
		$authority,
		static function () use ( &$clock_calls ): int {
			return ++$clock_calls === 1 ? 101 : 9999;
		}
	);
	$reject( static fn() => $expiring->change( UploadPolicy::defaults(), 2, 'Sessão expira antes do commit.' ), 'expiry before commit' );
	$assert( $repository->read()->toArray() === $second->toArray(), 'expiry rolls back change and audit' );

	$owner = arn_tx_connect();
	$owner->query( 'START TRANSACTION' );
	$owner->query( 'SELECT id FROM `' . $schema->table( 'state' ) . '` FOR UPDATE' );
	foreach ( array( 2048, 4096 ) as $bytes ) {
		$child = proc_open( array( PHP_BINARY, __FILE__, $argv[1], $argv[2] ?? dirname( __DIR__, 2 ), 'worker', $prefix, (string) $bytes ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, dirname( __DIR__, 2 ), null, array( 'bypass_shell' => true ) );
		if ( ! is_resource( $child ) ) {
			throw new RuntimeException( 'Worker unavailable.' ); }
		fclose( $pipes[0] );
		$processes[] = array( $child, $pipes );
	}
	$deadline = microtime( true ) + 10;
	$blocked  = 0;
	while ( microtime( true ) < $deadline ) {
		$blocked = (int) $owner->get_var( $owner->prepare( 'SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE DB=DATABASE() AND INFO=%s', 'SELECT * FROM `' . $schema->table( 'state' ) . '` FOR UPDATE' ) );
		if ( 2 === $blocked ) {
			break; }
		usleep( 20000 );
	}
	$assert( 2 === $blocked, 'both writers reached the SQL row lock before release' );
	$owner->query( 'COMMIT' );
	$outcomes = array();
	foreach ( $processes as [$process, $pipes] ) {
		$outcomes[] = trim( stream_get_contents( $pipes[1] ) );
		$error      = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$assert( 0 === proc_close( $process ) && '' === $error, 'worker successful exit' );
	}
	$processes = array();
	sort( $outcomes );
	$assert( array( 'conflict', 'saved' ) === $outcomes, 'one winner and one conflict' );
	$assert( 3 === $repository->read()->revision && 2 === count( $repository->history() ), 'single concurrent revision and audit' );
	$assert( 1 === count( $repository->history( 3, 1 ) ) && 2 === $repository->history( 3, 1 )[0]['after']['revision'], 'bounded exclusive cursor' );
	$reject( static fn() => $repository->history( 3, 100 ), 'oversized history page rejected' );
	$schema->install();
	$repository->initialize();
	$assert( 3 === $repository->read()->revision, 'reinstallation preserves existing settings' );
	$saved_digest = $db->get_var( 'SELECT digest FROM `' . $schema->table( 'state' ) . '` WHERE id=1' );
	$db->query( 'UPDATE `' . $schema->table( 'state' ) . "` SET digest='" . str_repeat( '0', 64 ) . "' WHERE id=1" );
	$reject( static fn() => $repository->read(), 'corrupt digest fails closed' );
	$db->query( $db->prepare( 'UPDATE %i SET digest=%s WHERE id=1', $schema->table( 'state' ), $saved_digest ) );
	$db->query( 'DELETE FROM `' . $schema->table( 'schema' ) . '`' );
	$reject( static fn() => $repository->read(), 'missing schema marker fails closed without repair' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $schema->table( 'schema' ) . '`' ), 'read did not recreate marker' );
	$db->query( 'INSERT INTO `' . $schema->table( 'schema' ) . '` (id,version) VALUES (1,1)' );
	$assert( 3 === $repository->read()->revision, 'valid state restored after exclusive corruption test' );
	$consumer_checks = require __DIR__ . '/mysql-cms-consumers-fixture.php';
	$consumer_checks( $db, $repository, $assert, $reject );
} finally {
	if ( isset( $owner ) && $owner->inTransaction() ) {
		$owner->query( 'ROLLBACK' ); }
	foreach ( $processes as [$process, $pipes] ) {
		if ( is_resource( $process ) ) {
			proc_terminate( $process );
			proc_close( $process ); }
		foreach ( $pipes as $pipe ) {
			if ( is_resource( $pipe ) ) {
				fclose( $pipe ); } }
	}
	$db->query( 'ROLLBACK' );
	$db->query( 'DROP TABLE IF EXISTS `' . $sessions_table . '`' );
	foreach ( array( 'audit', 'state', 'schema' ) as $part ) {
		$db->query( 'DROP TABLE IF EXISTS `' . $schema->table( $part ) . '`' );
	}
}
$tables_after = $db->get_col( 'SHOW TABLES' );
$assert( $tables_before === $tables_after, 'exclusive tables removed and outer table inventory preserved' );
echo 'CMS SQL: ' . $checks . " checks passed.\n";
