<?php
/** Real task schema and authority, only new random tables and fictional users.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\{AccountState, Grant};
use Arn\Intranet\Authorization\Storage\{AuthorizationStorageService, ChangeContext, LocalAuthorizationStorageAuthority, WpdbAuthorizationRepository};
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\{LocalUnitContext, WpdbLocalUnitRepository};
use Arn\Intranet\Tasks\{Configuration, CurrentActor, LocalRoster, TaskPolicy, TaskSchema};

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, WordPress.PHP.DevelopmentFunctions, WordPress.PHP.DiscouragedPHPFunctions
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_TASKS_SQL_HARNESS' ) || true !== ARN_TASKS_SQL_HARNESS || ! current_user_can( 'manage_options' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Ensaio de tarefas exige bootstrap CLI e laboratório.' );
}
if ( 'development' === wp_get_environment_type() ) {
	if ( Configuration::current() ) {
		throw new RuntimeException( 'Gate de tarefas abriu em development.' );
	}
	WP_CLI::success( 'Tasks foundation: development recusado antes dos recursos.' );
	return;
}
foreach ( array( 'ARN_ALLOW_LOCAL_TASKS', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
	if ( ! defined( $flag ) ) {
		define( $flag, true );
	}
	if ( true !== constant( $flag ) ) {
		throw new RuntimeException( 'Flag de ensaio de tarefas recusada.' );
	}
}
global $wpdb;
$admin      = get_current_user_id();
$old_cookie = $_COOKIE[ LOGGED_IN_COOKIE ] ?? null;
$prefix     = 'arn_tsk_' . bin2hex( random_bytes( 5 ) ) . '_';
$db         = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$owned_users  = array();
$owned_tables = array();
$checks       = 0;
$now          = 1894701600;
$clock        = static function () use ( &$now ): int {
	return $now;
};
$assert       = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou Tasks foundation: ' . $label );
	} ++$checks;
};
$unready      = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( RuntimeException ) {
		$assert( true, 'recusa técnica' );
		return;
	} $assert( false, 'falta recusa técnica' );
};
$protected    = array();
foreach ( array( $wpdb->users, $wpdb->usermeta ) as $table ) {
	$rows                = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A );
	$protected[ $table ] = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
	sort( $protected[ $table ] );
}
$schema = new TaskSchema( $db );
foreach ( array( 'tasks', 'comments', 'audit', 'notifications', 'schema' ) as $part ) {
	$owned_tables[] = $schema->table( $part ); }
foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $part ) {
	$owned_tables[] = $prefix . 'arn_authz_' . $part; }
foreach ( array( 'audit', 'units', 'header' ) as $part ) {
	$owned_tables[] = $prefix . 'arn_org_' . $part; }
foreach ( $owned_tables as $table ) {
	$assert( '0' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ), 'nome aleatório livre' );
}
try {
	$unready( $schema->assertReady( ... ) );
	$schema->install();
	$schema->install();
	$schema->assertReady();
	$assert( true, 'instalação exacta e repetição' );
	$assert( false !== $db->query( $db->prepare( 'ALTER TABLE %i DROP INDEX delivery', $schema->table( 'notifications' ) ) ), 'índice alterado só no prefixo próprio' );
	$unready( $schema->assertReady( ... ) );
	$assert( false !== $db->query( $db->prepare( 'ALTER TABLE %i ADD UNIQUE KEY delivery (task_id,revision,recipient_id,kind)', $schema->table( 'notifications' ) ) ), 'índice reposto no ensaio' );
	$schema->assertReady();
	$auth    = new WpdbAuthorizationRepository( $db );
	$service = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
	$context = static function () use ( $admin, &$now ): ChangeContext {
		return new ChangeContext( $admin, 'Ensaio sintético Tasks', 'TASKS-exclusive', $now );
	};
	$service->installSchema( $context() );
	$units = new WpdbLocalUnitRepository( $db );
	$units->installSchema();
	$units->apply( 'initialize', 0, new LocalUnitContext( $admin, 'Unidades sintéticas Tasks', $now ) );
	$roster = array();
	$ids    = array();
	foreach ( LocalRoster::definitions( $prefix ) as $label => $person ) {
		$fixture_id = wp_insert_user(
			array(
				'user_login'   => $person['login'],
				'user_email'   => $person['login'] . '@example.invalid',
				'user_pass'    => bin2hex( random_bytes( 24 ) ),
				'display_name' => 'Tarefa fictícia ' . $label,
				'role'         => 'subscriber',
			)
		);
		$assert( is_int( $fixture_id ) && $fixture_id > 0, 'conta nova fictícia' );
		$owned_users[ $fixture_id ] = $person['login'];
		$ids[ $label ]              = $fixture_id;
		update_user_meta( $fixture_id, LocalFixtureUser::META_KEY, '1' );
		update_user_meta( $fixture_id, '_arn_tasks_fixture', $prefix );
		$service->createAccount( $fixture_id, $context() );
		$service->changeState( $fixture_id, AccountState::Active, 1, $context() );
		foreach ( LocalRoster::grants( $label ) as [$fixture_role, $scope] ) {
			$service->createGrant( new Grant( $fixture_id, $fixture_role, $scope, 0, null ), $auth->find( $fixture_id )->revision, $context() ); }
		$roster[ $fixture_id ] = $person;
	}
	$assert( LocalRoster::normalize( $roster, $prefix ) === LocalRoster::resolve( $prefix ), 'elenco real completo' );
	$login  = static function ( int $id ) use ( $auth, &$now ): string {
		wp_set_current_user( $id );
		$manager                    = WP_Session_Tokens::get_instance( $id );
		$token                      = $manager->create( time() + 3600 );
		$data                       = $manager->get( $token );
		$data['arn_authz_local']    = true;
		$data['arn_authz_user_id']  = $id;
		$data['arn_authz_revision'] = $auth->find( $id )->revision;
		$data['expiration']         = $now + 3600;
		$manager->update( $token, $data );
		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $id, time() + 3600, 'logged_in', $token );
		return $token;
	};
	$actors = new CurrentActor( $db, $roster, $clock );
	$token  = $login( $ids['manager'] );
	$assert( $ids['manager'] === $actors->read()?->id(), 'sessão nativa lida sob lock' );
	$assert( TaskPolicy::create( 'LAB-A', $actors->read(), $now ), 'gestor autorizado no âmbito' );
	WP_Session_Tokens::get_instance( $ids['manager'] )->destroy( $token );
	$assert( null === $actors->read() && null === $actors->currentId(), 'token destruído e identidade antiga limpa' );
	$login( $ids['b'] );
	$assert( $ids['b'] === $actors->read()?->id(), 'controlo positivo da unidade B' );
	wp_set_current_user( $admin );
	++$now;
	$units->apply( 'deactivate-b', 1, new LocalUnitContext( $admin, 'Unidade sintética inactiva', $now ) );
	wp_set_current_user( $ids['b'] );
	$assert( null === $actors->read(), 'unidade inactiva fecha a sessão de tarefas' );
	wp_set_current_user( $admin );
	++$now;
	$units->apply( 'reactivate-b', 2, new LocalUnitContext( $admin, 'Reposição sintética B', $now ) );
	$login( $ids['a'] );
	$assert( $ids['a'] === $actors->read()?->id(), 'controlo positivo antes da suspensão' );
	wp_set_current_user( $admin );
	$service->changeState( $ids['a'], AccountState::Suspended, $auth->find( $ids['a'] )->revision, $context() );
	wp_set_current_user( $ids['a'] );
	$assert( null === $actors->read(), 'suspensão fecha acesso' );
	wp_set_current_user( $admin );
	$service->changeState( $ids['a'], AccountState::Active, $auth->find( $ids['a'] )->revision, $context() );
	wp_set_current_user( $ids['a'] );
	$assert( null === $actors->read(), 'reactivar não recupera sessão antiga' );
	$login( $ids['a'] );
	$assert( $ids['a'] === $actors->read()?->id(), 'nova sessão usa revisão actual' );
	$marker         = $prefix . 'arn_authz_schema';
	$held_marker    = $prefix . 'arn_authz_schema_held';
	$owned_tables[] = $held_marker;
	$assert( false !== $db->query( $db->prepare( 'RENAME TABLE %i TO %i', $marker, $held_marker ) ), 'marcador de autorização isolado' );
	try {
		$unready( $actors->read( ... ) );
		$assert( null === $actors->currentId(), 'fundação parcial limpa a identidade' );
	} finally {
		$assert( false !== $db->query( $db->prepare( 'RENAME TABLE %i TO %i', $held_marker, $marker ) ), 'marcador de autorização reposto' );
	}
	$assert( $ids['a'] === $actors->read()?->id(), 'autoridade recupera só com esquema completo' );
	wp_set_current_user( $admin );
	$assert( null === $actors->read(), 'administrador técnico fora do elenco' );
	if ( defined( 'ARN_TASKS_STORAGE_EXERCISE' ) && true === ARN_TASKS_STORAGE_EXERCISE ) {
		require __DIR__ . '/tasks-storage-cases.php';
	}
} finally {
	$db->query( 'ROLLBACK' );
	wp_set_current_user( $admin );
	if ( null === $old_cookie ) {
		unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	} else {
		$_COOKIE[ LOGGED_IN_COOKIE ] = $old_cookie; }
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $owned_users as $fixture_id => $login_name ) {
		$user = get_userdata( $fixture_id );
		if ( ! $user instanceof WP_User || $user->user_login !== $login_name || $user->user_email !== $login_name . '@example.invalid' || get_user_meta( $fixture_id, '_arn_tasks_fixture', true ) !== $prefix || ! wp_delete_user( $fixture_id ) ) {
			throw new RuntimeException( 'Limpeza de conta fictícia recusada.' ); }
	}
	foreach ( $owned_tables as $table ) {
		if ( ! str_starts_with( $table, $prefix ) || false === $db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $table ) ) ) {
			throw new RuntimeException( 'Limpeza de tabela própria recusada.' ); }
	}
	$db->close();
}
foreach ( $protected as $table => $hashes ) {
	$actual = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i', $table ), ARRAY_A ) );
	sort( $actual );
	$assert( $actual === $hashes, 'contas e sessões anteriores preservadas' );
}
WP_CLI::success( 'Tasks foundation: ' . $checks . ' verificações, prefixo e contas de ensaio removidos.' );
