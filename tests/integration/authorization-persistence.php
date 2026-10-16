<?php
/**
 * Ensaio descartável da persistência de autorização.
 *
 * @package ArnIntranetCore
 */

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Action;
use Arn\Intranet\Authorization\AuthorizationPolicy;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Request;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio permitido apenas no laboratório por administrador.' );
}

$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$prefix   = 'arn_az_' . bin2hex( random_bytes( 6 ) ) . '_';
$database->set_prefix( $prefix );
$database->suppress_errors( true );
$second = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second->set_prefix( $prefix );
$second->suppress_errors( true );
$audit_table    = $prefix . 'arn_authz_audit';
$grants_table   = $prefix . 'arn_authz_grants';
$accounts_table = $prefix . 'arn_authz_accounts';
$schema_table   = $prefix . 'arn_authz_schema';
$trigger        = $prefix . 'deny_audit';
$query_filter   = null;
$users          = array();
$checks         = 0;
$assert         = static function ( bool $condition ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Asserção de autorização falhou.' );
	}
	++$checks;
};
$refused        = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable $error ) {
		$assert( null === $error->getPrevious() );
		return;
	}
	$assert( false );
};

try {
	$repository = new WpdbAuthorizationRepository( $database );
	$refused( static fn() => $repository->find( 1 ) );
	$assert( '0' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $accounts_table ) ) );
	$query_filter = static function ( string $query ) use ( $grants_table ): string {
		return str_starts_with( $query, 'CREATE TABLE `' . $grants_table . '`' ) ? 'INVALID FIXTURE DDL' : $query;
	};
	add_filter( 'query', $query_filter );
	$refused( static fn() => $repository->installSchema() );
	remove_filter( 'query', $query_filter );
	$query_filter = null;
	$assert( '1' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $accounts_table ) ) );
	$repository->installSchema();
	$repository->installSchema();
	$user_id = wp_insert_user(
		array(
			'user_login' => $prefix . 'fixture',
			'user_email' => $prefix . '@example.test',
			'user_pass'  => wp_generate_password( 32 ),
			'role'       => 'subscriber',
		)
	);
	$assert( is_int( $user_id ) );
	$users[]   = $user_id;
	$context   = new ChangeContext( get_current_user_id(), 'Ensaio descartável', 'fixture/authorization', time() );
	$authority = new class( get_current_user_id(), $user_id ) implements AuthorizationStorageAuthority {
		/**
		 * Recebe apenas as identidades efémeras do ensaio.
		 *
		 * @param int $actor Actor local.
		 * @param int $fixture Conta fictícia.
		 */
		public function __construct( private int $actor, private int $fixture ) {}
		/**
		 * Restringe o actor ao administrador local do ensaio.
		 *
		 * @param int $actor_id Actor.
		 * @return bool
		 */
		public function canManage( int $actor_id ): bool {
			return $actor_id === $this->actor; }
		/**
		 * Restringe o alvo à conta fictícia criada no ensaio.
		 *
		 * @param int $user_id Conta.
		 * @return bool
		 */
		public function canUseAccount( int $user_id ): bool {
			return $user_id === $this->fixture; }
	};
	$service   = new AuthorizationStorageService( $repository, $authority );
	$refused( static fn() => $service->find( $user_id, $user_id ) );
	$refused( static fn() => $service->find( get_current_user_id(), get_current_user_id() ) );
	$assert( null === $repository->find( $user_id ) );
	$repository->createAccount( $user_id, $context );
	$assert( AccountState::Pending === $repository->find( $user_id )->state );
	$assert( 1 === $repository->find( $user_id )->revision );
	$refused( static fn() => $repository->createAccount( $user_id, $context ) );
	$own = new Grant( $user_id, Role::Employee, Scope::own(), 0, null );
	$refused( static fn() => $repository->createGrant( $own, 1, $context ) );
	$repository->changeState( $user_id, AccountState::Pending, 1, $context );
	$assert( 1 === count( $repository->history( $user_id ) ) );
	$repository->changeState( $user_id, AccountState::Active, 1, $context );
	$grant_id = $repository->createGrant( new Grant( $user_id, Role::Employee, Scope::own(), 0, null ), 2, $context );
	$assert( $grant_id > 0 && 3 === $repository->find( $user_id )->revision );
	$assert( 3 === count( $repository->history( $user_id ) ) );
	$assert( 'Ensaio descartável' === $repository->history( $user_id )[0]['reason'] );
	$other    = new WpdbAuthorizationRepository( $second );
	$revision = $other->find( $user_id )->revision;
	$repository->changeState( $user_id, AccountState::Suspended, $revision, $context );
	$refused( static fn() => $other->changeState( $user_id, AccountState::Suspended, $revision, $context ) );
	$refused( static fn() => $repository->changeState( $user_id, AccountState::Pending, 4, $context ) );
	$refused( static fn() => $repository->createGrant( $own, 4, $context ) );
	$refused( static fn() => $repository->replaceGrant( $user_id, $grant_id, $own, 4, $context ) );
	$repository->revokeGrant( $user_id, $grant_id, 4, $context );
	$repository->revokeGrant( $user_id, $grant_id, 5, $context );
	$assert( 5 === $repository->find( $user_id )->revision );
	$refused( static fn() => $repository->revokeGrant( $user_id, $grant_id, 4, $context ) );
	$repository->changeState( $user_id, AccountState::Active, 5, $context );
	$refused( static fn() => $repository->replaceGrant( $user_id, $grant_id, $own, 6, $context ) );
	$refused( static fn() => $repository->createGrant( new Grant( $user_id, Role::Employee, Scope::own(), 0, null, true ), 6, $context ) );
	$live        = $repository->createGrant( $own, 6, $context );
	$replacement = new Grant( $user_id, Role::Employee, Scope::units( array( 'fixture-unit' ) ), 0, null );
	$new_id      = $repository->replaceGrant( $user_id, $live, $replacement, 7, $context );
	$snapshot    = $repository->find( $user_id );
	$assert( 8 === $snapshot->revision && 3 === count( $snapshot->stored_grants ) );
	$assert( $snapshot->stored_grants[1]->grant->revoked && 'Own' === $snapshot->stored_grants[1]->grant->scope->type->name && $new_id !== $live );
	$event   = $repository->history( $user_id, 1 )[0];
	$payload = json_decode( $event['new_json'] );
	$assert( 'grant_replaced' === $event['event'] && $payload->old_grant->id === $live && $payload->old_grant->revoked && $payload->new_grant->id === $new_id );
	$refused( static fn() => $repository->replaceGrant( $user_id, $new_id, new Grant( $user_id + 1, Role::Employee, Scope::own(), 0, null ), 8, $context ) );
	$refused( static fn() => $repository->revokeGrant( $user_id, PHP_INT_MAX, 8, $context ) );
	$repository->createGrant( new Grant( $user_id, Role::Employee, Scope::own(), 0, 1 ), 8, $context );
	$valid_id = $repository->createGrant( $own, 9, $context );
	$snapshot = $service->find( get_current_user_id(), $user_id );
	$policy   = new AuthorizationPolicy();
	$request  = new Request( Action::UpdateOwnProfile, Scope::own(), $user_id, 0, true, true, false, false );
	$assert( $policy->allows( $snapshot->toAccount( true ), $request, $snapshot->policyGrants(), time() ) );
	$assert( ! $policy->allows( $snapshot->toAccount( false ), $request, $snapshot->policyGrants(), time() ) );
	$assert( 10 === count( $repository->history( $user_id ) ) );
	$refused( static fn() => $repository->history( $user_id, 0 ) );
	$refused( static fn() => $repository->history( $user_id, 101 ) );

	// Uma falha de auditoria reverte estado, revisão e concessões.
	$assert( false !== $database->query( $database->prepare( "CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture audit failure'", $trigger, $audit_table ) ) );
	$refused( static fn() => $repository->changeState( $user_id, AccountState::Suspended, 10, $context ) );
	$refused( static fn() => $repository->replaceGrant( $user_id, $valid_id, $replacement, 10, $context ) );
	$snapshot = $repository->find( $user_id );
	$assert( 10 === $snapshot->revision && AccountState::Active === $snapshot->state && 5 === count( $snapshot->stored_grants ) && ! $snapshot->stored_grants[4]->grant->revoked );
	$assert( false !== $database->query( $database->prepare( 'DROP TRIGGER %i', $trigger ) ) );
	$database->query( 'START TRANSACTION' );
	$refused( static fn() => $repository->changeState( $user_id, AccountState::Suspended, 10, $context ) );
	$refused( static fn() => $repository->installSchema() );
	$assert( $database->inTransaction() );
	$database->query( 'ROLLBACK' );
	$assert( false === $database->query( $database->prepare( "INSERT INTO %i (user_id, role, scope_type, targets_json, starts_at, revoked) VALUES (%d, 'Employee', 'Own', '[]', 0, 0)", $grants_table, PHP_INT_MAX ) ) );
	$assert( false === $database->query( $database->prepare( 'INSERT INTO %i (user_id, actor_id, event, revision, new_json, reason, decision_ref, occurred_at) SELECT user_id, actor_id, event, revision, new_json, reason, decision_ref, occurred_at FROM %i LIMIT 1', $audit_table, $audit_table ) ) );

	// Um último grant inválido recusa o snapshot inteiro, mesmo havendo acesso anterior.
	foreach ( array( array( 'People', '{"0":1}' ), array( 'People', '[1.0]' ), array( 'People', '[9223372036854775808]' ), array( 'People', '[1,1]' ), array( 'People', '[]' ), array( 'Own', '[1]' ), array( 'Unknown', '[]' ) ) as $invalid ) {
		$assert( 1 === $database->query( $database->prepare( 'UPDATE %i SET scope_type = %s, targets_json = %s WHERE id = %d', $grants_table, $invalid[0], $invalid[1], $valid_id ) ) );
		$refused( static fn() => $repository->find( $user_id ) );
	}
	$assert( false === $database->query( $database->prepare( 'UPDATE %i SET targets_json = %s WHERE id = %d', $grants_table, '{', $valid_id ) ) );
	$database->query( $database->prepare( "UPDATE %i SET scope_type = 'Own', targets_json = '[]' WHERE id = %d", $grants_table, $valid_id ) );
	$database->query( $database->prepare( "UPDATE %i SET scope_type = 'People', targets_json = %s WHERE id = %d", $grants_table, '[' . ( PHP_INT_MAX - 1 ) . ',' . PHP_INT_MAX . ']', $valid_id ) );
	$assert( array( PHP_INT_MAX - 1, PHP_INT_MAX ) === $repository->find( $user_id )->stored_grants[4]->grant->scope->targets );
	$database->query( $database->prepare( "UPDATE %i SET scope_type = 'Own', targets_json = '[]' WHERE id = %d", $grants_table, $valid_id ) );
	foreach ( array( array( 'role', 'Unknown' ), array( 'revoked', '2' ), array( 'starts_at', '18446744073709551615' ) ) as $invalid ) {
		$database->query( $database->prepare( 'UPDATE %i SET %i = %s WHERE id = %d', $grants_table, $invalid[0], $invalid[1], $valid_id ) );
		$refused( static fn() => $repository->find( $user_id ) );
		$database->query( $database->prepare( "UPDATE %i SET role = 'Employee', revoked = 0, starts_at = 0 WHERE id = %d", $grants_table, $valid_id ) );
	}
	$database->query( $database->prepare( 'UPDATE %i SET revision = %s WHERE user_id = %d', $accounts_table, (string) PHP_INT_MAX, $user_id ) );
	$assert( PHP_INT_MAX === $repository->find( $user_id )->revision );
	$refused( static fn() => $repository->createGrant( $own, PHP_INT_MAX, $context ) );
	$assert( 5 === count( $repository->find( $user_id )->stored_grants ) );
	$database->query( $database->prepare( 'UPDATE %i SET revision = 10 WHERE user_id = %d', $accounts_table, $user_id ) );

	// Envelope e conteúdo do histórico são ambos tratados como não confiáveis.
	$original = $repository->history( $user_id, 1 )[0];
	foreach ( array( array( 'new_json', '{' ), array( 'new_json', '{}' ), array( 'new_json', str_replace( '"targets":[]', '"targets":{"0":1}', $original['new_json'] ) ), array( 'new_json', str_replace( '"user_id":' . $user_id, '"user_id":0', $original['new_json'] ) ), array( 'event', 'unknown' ), array( 'actor_id', '0' ), array( 'reason', ' ' ), array( 'decision_ref', '?' ), array( 'previous_json', '{}' ) ) as $invalid ) {
		$database->query( $database->prepare( 'UPDATE %i SET %i = %s WHERE id = %d', $audit_table, $invalid[0], $invalid[1], $original['id'] ) );
		$refused( static fn() => $repository->history( $user_id ) );
		if ( null === $original[ $invalid[0] ] ) {
			$database->query( $database->prepare( 'UPDATE %i SET %i = NULL WHERE id = %d', $audit_table, $invalid[0], $original['id'] ) );
		} else {
			$database->query( $database->prepare( 'UPDATE %i SET %i = CONVERT(UNHEX(%s) USING utf8mb4) WHERE id = %d', $audit_table, $invalid[0], bin2hex( (string) $original[ $invalid[0] ] ), $original['id'] ) );
		}
	}
	$assert( 10 === count( $repository->history( $user_id ) ) );
	$database->query( $database->prepare( 'UPDATE %i SET version = 2', $schema_table ) );
	$refused( static fn() => $repository->find( $user_id ) );
	$refused( static fn() => $repository->installSchema() );
	$assert( '2' === (string) $database->get_var( $database->prepare( 'SELECT version FROM %i', $schema_table ) ) );
	$database->query( $database->prepare( 'UPDATE %i SET version = 1', $schema_table ) );
	$database->query( $database->prepare( 'INSERT INTO %i VALUES (2, 1)', $schema_table ) );
	$refused( static fn() => $repository->history( $user_id ) );
	$database->query( $database->prepare( 'DELETE FROM %i WHERE id = 2', $schema_table ) );
	$database->query( $database->prepare( 'ALTER TABLE %i MODIFY actor_id bigint unsigned NULL', $audit_table ) );
	$refused( static fn() => $repository->installSchema() );
	$database->query( $database->prepare( 'ALTER TABLE %i MODIFY actor_id bigint unsigned NOT NULL', $audit_table ) );
	$database->query( $database->prepare( 'ALTER TABLE %i MODIFY occurred_at bigint NOT NULL', $audit_table ) );
	$refused( static fn() => $repository->find( $user_id ) );
	$database->query( $database->prepare( 'ALTER TABLE %i MODIFY occurred_at bigint unsigned NOT NULL', $audit_table ) );
	$database->query( $database->prepare( 'ALTER TABLE %i ENGINE=MyISAM', $audit_table ) );
	$refused( static fn() => $repository->installSchema() );
	$database->query( $database->prepare( 'ALTER TABLE %i ENGINE=InnoDB', $audit_table ) );
	$database->query( $database->prepare( 'ALTER TABLE %i DROP INDEX user_revision', $audit_table ) );
	$refused( static fn() => $repository->find( $user_id ) );
	$database->query( $database->prepare( 'ALTER TABLE %i ADD UNIQUE KEY user_revision (user_id, revision)', $audit_table ) );
	$foreign_name = 'az_fk_' . substr( hash( 'sha256', $grants_table ), 0, 24 );
	$database->query( $database->prepare( 'ALTER TABLE %i DROP FOREIGN KEY %i', $grants_table, $foreign_name ) );
	$refused( static fn() => $repository->find( $user_id ) );
	$database->query( $database->prepare( 'ALTER TABLE %i ADD CONSTRAINT %i FOREIGN KEY (user_id) REFERENCES %i (user_id) ON DELETE CASCADE ON UPDATE RESTRICT', $grants_table, $foreign_name, $accounts_table ) );
	$refused( static fn() => $repository->find( $user_id ) );
	$database->query( $database->prepare( 'ALTER TABLE %i DROP FOREIGN KEY %i', $grants_table, $foreign_name ) );
	$database->query( $database->prepare( 'ALTER TABLE %i ADD CONSTRAINT %i FOREIGN KEY (user_id) REFERENCES %i (user_id) ON DELETE RESTRICT ON UPDATE RESTRICT', $grants_table, $foreign_name, $accounts_table ) );
	$repository->installSchema();

	// Metadata MySQL simulada: a expressão mantém-se mas a CHECK não é aplicada.
	$query_filter = static function ( string $query ) use ( $grants_table ): string {
		if ( str_contains( $query, 'FROM information_schema.CHECK_CONSTRAINTS c' ) && str_contains( $query, "'" . $grants_table . "'" ) ) {
			return "SELECT 'json_valid(targets_json)' AS CHECK_CLAUSE, 'NO' AS ENFORCED";
		}
		return $query;
	};
	add_filter( 'query', $query_filter );
	$refused( static fn() => $repository->installSchema() );
	$refused( static fn() => $repository->find( $user_id ) );
	$refused( static fn() => $repository->revokeGrant( $user_id, $valid_id, 10, $context ) );
	remove_filter( 'query', $query_filter );
	$query_filter = null;
	$assert( 10 === $repository->find( $user_id )->revision && 10 === count( $repository->history( $user_id ) ) );
	$query_filter = static function ( string $query ) use ( $grants_table ): string {
		if ( str_contains( $query, 'FROM information_schema.CHECK_CONSTRAINTS c' ) && str_contains( $query, "'" . $grants_table . "'" ) ) {
			return "SELECT 'json_valid(targets_json)' AS CHECK_CLAUSE, 'YES' AS ENFORCED";
		}
		return $query;
	};
	add_filter( 'query', $query_filter );
	$assert( 10 === $repository->find( $user_id )->revision );
	remove_filter( 'query', $query_filter );
	$query_filter = null;

	// Falha de query dentro da mudança e perda da ligação recusam sem reconnect.
	$query_filter = static function ( string $query ) use ( $audit_table ): string {
		return str_starts_with( $query, 'INSERT INTO `' . $audit_table . '`' ) ? 'INVALID FIXTURE QUERY' : $query;
	};
	add_filter( 'query', $query_filter );
	$refused( static fn() => $repository->revokeGrant( $user_id, $valid_id, 10, $context ) );
	remove_filter( 'query', $query_filter );
	$query_filter = null;
	$assert( 10 === $other->find( $user_id )->revision );
	$connection_id = (int) $database->get_var( 'SELECT CONNECTION_ID()' );
	$query_filter  = static function ( string $query ) use ( $audit_table, $second, $connection_id ): string {
		if ( str_starts_with( $query, 'INSERT INTO `' . $audit_table . '`' ) ) {
			$second->query( $second->prepare( 'KILL %d', $connection_id ) );
		}
		return $query;
	};
	add_filter( 'query', $query_filter );
	$refused( static fn() => $repository->revokeGrant( $user_id, $valid_id, 10, $context ) );
	remove_filter( 'query', $query_filter );
	$query_filter = null;
	$snapshot     = $other->find( $user_id );
	$assert( 10 === $snapshot->revision && ! $snapshot->stored_grants[4]->grant->revoked && 10 === count( $other->history( $user_id ) ) );
	$refused( static fn() => $repository->find( $user_id ) );
	$assert( array( 'subscriber' ) === get_userdata( $user_id )->roles );
} finally {
	if ( null !== $query_filter ) {
		remove_filter( 'query', $query_filter );
	}
	$clean = false !== $second->query( $second->prepare( 'DROP TRIGGER IF EXISTS %i', $trigger ) );
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $suffix ) {
		$clean = false !== $second->query( $second->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_authz_' . $suffix ) ) && $clean;
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $fixture_id ) {
		$clean = wp_delete_user( $fixture_id ) && $clean;
	}
	$database->close();
	$second->close();
	if ( ! $clean ) {
		throw new RuntimeException( 'Limpeza das fixtures de autorização não confirmada.' );
	}
}
WP_CLI::success( 'Autorização persistente: ' . $checks . ' asserções e limpeza confirmada.' );
