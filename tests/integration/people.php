<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Exclusive private files use atomic xb creation and verified cleanup.
/** Exclusive SQL contract for People, units and authorization revalidation. */
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\LocalUnitContext;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Arn\Intranet\People\LocalPeopleDirectory;
use Arn\Intranet\People\LocalPeopleInstaller;
use Arn\Intranet\People\LocalPeopleRoster;
use Arn\Intranet\People\PeopleQuery;
use Arn\Intranet\People\SelfService;
use Arn\Intranet\People\WpdbPeopleAuthority;
use Arn\Intranet\People\WpdbPeopleRepository;

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Ensaio People indisponível.' );
}
$prefix = 'arn_peo_' . bin2hex( random_bytes( 5 ) ) . '_';
$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_people_private_' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Raiz privada indisponível.' );
}
$logins  = LocalPeopleRoster::logins( $prefix );
$owned   = array();
$capture = static function ( int $id ) use ( &$owned, $logins ): void {
	$user = get_userdata( $id );
	if ( $user instanceof WP_User && in_array( $user->user_login, $logins, true ) && $user->user_email === $user->user_login . '@example.invalid' ) {
		$owned[] = $id;
	}
};
add_action( 'user_register', $capture, PHP_INT_MIN, 1 );
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$other   = null;
$failure = null;
$checks  = 0;
$assert  = static function ( bool $value, string $name ) use ( &$checks ): void {
	if ( ! $value ) {
		throw new RuntimeException( 'Falhou People: ' . esc_html( $name ) );
	}
	++$checks;
};
$deny    = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, 'recusa' );
		return;
	}
	$assert( false, 'operação devia recusar' );
};
try {
	if ( 'development' === wp_get_environment_type() ) {
		$before = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) ) );
		$deny( static fn() => ( new LocalPeopleInstaller( $prefix, $root ) )->install() );
		$after = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) ) );
		$assert( $before === $after && array() === $owned, 'development negativo sem efeitos' );
		echo esc_html( 'People SQL: development negativo, ' . $checks . ' verificações.' . PHP_EOL );
		return;
	}
	foreach ( array( 'ARN_ALLOW_LOCAL_PEOPLE', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		}
		$assert( true === constant( $flag ), 'flag local' );
	}
	$installed = ( new LocalPeopleInstaller( $prefix, $root ) )->install();
	$ids       = $installed['user_ids'];
	$auth      = new WpdbAuthorizationRepository( $db );
	$units     = new WpdbLocalUnitRepository( $db );
	$assert( 3 === count( $ids ), 'três perfis próprios' );
	$deny( static fn() => $units->snapshotInTransaction() );
	$db->query( 'START TRANSACTION' );
	$locked    = $units->snapshotInTransaction();
	$active_tx = $db->get_var( 'SELECT @@session.in_transaction' );
	$db->query( 'ROLLBACK' );
	$assert( 1 === $locked->revision && '1' === (string) $active_tx && 1 === $units->snapshot()->revision, 'snapshotInTransaction preserva transacção do chamador' );
	$context  = new LocalUnitContext( get_current_user_id(), 'Ensaio exclusivo People', time() );
	$before_b = array_column( $units->snapshot()->units, null, 'id' )['LAB-B'];
	$units->apply( 'deactivate-b', 1, $context );
	$deny( static fn() => $units->apply( 'reactivate-b', 1, $context ) );
	$units->apply( 'reactivate-b', 2, $context );
	$after_b = array_column( $units->snapshot()->units, null, 'id' )['LAB-B'];
	$assert( 3 === $units->snapshot()->revision && get_object_vars( $before_b ) === get_object_vars( $after_b ) && 3 === count( $units->history() ), 'reactivação auditada conserva atributos' );
	$deny( static fn() => $units->apply( 'reactivate-b', 3, $context ) );
	$session         = static function ( int $id ) use ( $auth ): array {
		$snapshot = $auth->find( $id );
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $id,
			'arn_authz_revision' => $snapshot->revision,
		);
	};
	$current_id      = $ids['alfa'];
	$current_session = $session( $current_id );
	$authority       = new WpdbPeopleAuthority(
		$db,
		static function () use ( &$current_id, &$current_session ): array {
			return array( $current_id, $current_session );
		}
	);
	$people          = new WpdbPeopleRepository( $db, $authority );
	$before          = $people->find( $ids['alfa'] );
	$assert( 3 === count( $people->all() ) && 1 === count( $people->history( $ids['alfa'] ) ), 'seed e auditoria criada' );
	$db->query( $db->prepare( 'ALTER TABLE %i ADD unexpected int NULL', $prefix . 'arn_people' ) );
	$deny( static fn() => $people->find( $ids['alfa'] ) );
	$db->query( $db->prepare( 'ALTER TABLE %i DROP COLUMN unexpected', $prefix . 'arn_people' ) );
	$db->query( $db->prepare( 'UPDATE %i SET digest=%s WHERE user_id=%d', $prefix . 'arn_people', str_repeat( '0', 64 ), $ids['alfa'] ) );
	$deny( static fn() => $people->find( $ids['alfa'] ) );
	$db->query( $db->prepare( 'UPDATE %i SET digest=%s WHERE user_id=%d', $prefix . 'arn_people', $before->digest(), $ids['alfa'] ) );
	$assert( $before->digest() === $people->find( $ids['alfa'] )->digest(), 'schema e hash actuais validados' );
	$actor_factory     = static function () use ( &$current_id, &$current_session ): array {
		return array( $current_id, $current_session );
	};
	$authority_factory = static function () use ( $prefix ): WpdbPeopleAuthority {
		$read_db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$read_db->set_prefix( $prefix );
		return new WpdbPeopleAuthority( $read_db );
	};
	$profiles_factory  = static function () use ( $prefix ): WpdbPeopleRepository {
		$read_db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$read_db->set_prefix( $prefix );
		return new WpdbPeopleRepository( $read_db, new WpdbPeopleAuthority( $read_db ) );
	};
	$directory         = new LocalPeopleDirectory( $actor_factory, $authority_factory, $profiles_factory, static fn(): WpdbPeopleRepository => $people, $prefix );
	$query             = PeopleQuery::parse( array() );
	$list              = $directory->contactList( $query );
	$assert( null !== $list && 3 === $list['total'] && 3 === count( $list['items'] ), 'directório exclusivo' );
	$assert( null !== $directory->own() && null === $directory->team( $query ) && null !== $directory->contact( $ids['beta'] ), 'alfa lê contactos sem equipa' );
	$current_id      = $ids['manager'];
	$current_session = $session( $current_id );
	$team            = $directory->team( $query );
	$assert( null !== $team && 2 === $team['total'], 'gestor vê apenas A' );
	$units->apply( 'deactivate-b', 3, $context );
	$assert( 2 === $directory->contactList( $query )['total'] && 2 === $directory->team( $query )['total'], 'B inactiva excluída sem fechar A' );
	$current_id      = $ids['beta'];
	$current_session = $session( $current_id );
	$assert( null === $directory->own(), 'actor de B inactiva recusado' );
	$units->apply( 'reactivate-b', 4, $context );
	$current_id      = $ids['manager'];
	$current_session = $session( $current_id );
	$assert( 3 === $directory->contactList( $query )['total'] && 2 === $directory->team( $query )['total'], 'reactivação B restaura elegibilidade' );
	$current_id      = $ids['alfa'];
	$current_session = $session( $current_id );
	$before_grants   = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY id', $prefix . 'arn_authz_grants' ), ARRAY_A );
	$before_accounts = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY user_id', $prefix . 'arn_authz_accounts' ), ARRAY_A );
	$before_sessions = get_user_meta( $ids['alfa'], 'session_tokens', true );
	$updated         = $people->updateOwn( $ids['alfa'], new SelfService( '+245 123', 'Sala Á', "Linha 1\nLinha 2" ), 1 );
	$assert( 2 === $updated->revision && get_object_vars( $updated->professional ) === get_object_vars( $before->professional ) && $updated->digest() !== $before->digest(), 'apenas autosserviço e revisão mudam' );
	$assert( 2 === count( $people->history( $ids['alfa'] ) ) && array( 'phone', 'office', 'bio' ) === $people->history( $ids['alfa'] )[1]['changed_fields'], 'auditoria contígua' );
	$no_op = $people->updateOwn( $ids['alfa'], $updated->self_service, 2 );
	$assert( $no_op === $updated || ( 2 === $no_op->revision && 2 === count( $people->history( $ids['alfa'] ) ) ), 'no-op sem evento' );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], $updated->self_service, 1 ) );
	$assert( $before_grants === $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY id', $prefix . 'arn_authz_grants' ), ARRAY_A ) && $before_accounts === $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY user_id', $prefix . 'arn_authz_accounts' ), ARRAY_A ) && get_user_meta( $ids['alfa'], 'session_tokens', true ) === $before_sessions, 'contacto não altera autorização ou sessão' );
	$failure = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, $prefix . 'arn_people_audit' ) ? 'INVALID PEOPLE AUDIT SQL' : $sql;
	add_filter( 'query', $failure );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 999', 'Sala Á', '' ), 2 ) );
	remove_filter( 'query', $failure );
	$failure = null;
	$assert( 2 === $people->find( $ids['alfa'] )->revision && 2 === count( $people->history( $ids['alfa'] ) ), 'falha tardia reverte perfil e auditoria' );
	$other = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$other->set_prefix( $prefix );
	$other->query( 'START TRANSACTION' );
	$other->get_results( $other->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $prefix . 'arn_authz_accounts', $ids['alfa'] ) );
	$db->query( 'SET SESSION innodb_lock_wait_timeout=1' );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 222', 'Sala Á', '' ), 2 ) );
	$other->query( 'ROLLBACK' );
	$assert( 2 === $people->find( $ids['alfa'] )->revision && 2 === count( $people->history( $ids['alfa'] ) ), 'bloqueio concorrente sem escrita' );
	$winner = $people->updateOwn( $ids['alfa'], new SelfService( '+245 222', 'Sala Á', '' ), 2 );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 444', 'Sala Á', '' ), 2 ) );
	$assert( 3 === $winner->revision && 3 === $people->find( $ids['alfa'] )->revision && 3 === count( $people->history( $ids['alfa'] ) ), 'um escritor confirma e CAS antigo recusa' );
	$service       = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
	$beta_snapshot = $auth->find( $ids['beta'] );
	$service->changeState( $ids['beta'], AccountState::Suspended, $beta_snapshot->revision, new ChangeContext( get_current_user_id(), 'Suspensão fictícia de Beta', 'LAB-PEOPLE', time() ) );
	$current_id      = $ids['manager'];
	$current_session = $session( $current_id );
	$assert( 2 === $directory->contactList( $query )['total'] && 2 === $directory->team( $query )['total'], 'Beta suspenso excluído sem fechar A' );
	$current_id      = $ids['beta'];
	$current_session = array(
		'arn_authz_local'    => true,
		'arn_authz_user_id'  => $ids['beta'],
		'arn_authz_revision' => $beta_snapshot->revision,
	);
	$assert( null === $directory->own(), 'Beta suspenso recusado' );
	$current_id      = $ids['alfa'];
	$current_session = $session( $current_id );
	$snapshot        = $auth->find( $ids['alfa'] );
	$service->revokeGrant( $ids['alfa'], $snapshot->stored_grants[0]->id, $snapshot->revision, new ChangeContext( get_current_user_id(), 'Revogação fictícia para ensaio', 'LAB-PEOPLE', time() ) );
	$current_session = $session( $current_id );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 333', '', '' ), 3 ) );
	$assert( 3 === $people->find( $ids['alfa'] )->revision, 'concessão revogada bloqueia escrita' );
	$snapshot = $auth->find( $ids['alfa'] );
	$service->changeState( $ids['alfa'], AccountState::Suspended, $snapshot->revision, new ChangeContext( get_current_user_id(), 'Suspensão fictícia para ensaio', 'LAB-PEOPLE', time() ) );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 333', '', '' ), 3 ) );
	$assert( 3 === $people->find( $ids['alfa'] )->revision, 'suspensão bloqueia escrita' );
	$current_id      = $ids['manager'];
	$current_session = $session( $current_id );
	$assert( 1 === $directory->contactList( $query )['total'] && 1 === $directory->team( $query )['total'], 'dois alvos suspensos saem antes da contagem' );
	echo esc_html( 'People SQL: local exclusivo, ' . $checks . ' verificações.' . PHP_EOL );
} finally {
	if ( null !== $failure ) {
		remove_filter( 'query', $failure );
	}
	if ( null !== $other ) {
		$other->query( 'ROLLBACK' );
		$other->close();
	}
	remove_action( 'user_register', $capture, PHP_INT_MIN );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $owned as $fixture_user_id ) {
		$user = get_userdata( $fixture_user_id );
		if ( $user instanceof WP_User && in_array( $user->user_login, $logins, true ) ) {
			wp_delete_user( $fixture_user_id );
		}
	}
	foreach ( array( 'arn_people_audit', 'arn_people', 'arn_people_schema', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema', 'arn_org_audit', 'arn_org_units', 'arn_org_header' ) as $suffix ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) );
	}
	$files = glob( $root . DIRECTORY_SEPARATOR . '*.password' );
	foreach ( false === $files ? array() : $files as $file ) {
		if ( dirname( $file ) === $root && ! is_link( $file ) ) {
			unlink( $file );
		}
	}
	if ( is_dir( $root ) ) {
		rmdir( $root );
	}
	if ( file_exists( $root ) ) {
		throw new RuntimeException( 'Limpeza People de ficheiros incompleta.' );
	}
	$remaining = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) );
	if ( 0 !== $remaining ) {
		throw new RuntimeException( 'Limpeza People de tabelas incompleta.' );
	}
	foreach ( $logins as $login ) {
		if ( false !== get_user_by( 'login', $login ) ) {
			throw new RuntimeException( 'Limpeza People de contas incompleta.' );
		}
	}
	$db->close();
}
