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
use Arn\Intranet\People\PeoplePages;
use Arn\Intranet\People\SelfService;
use Arn\Intranet\People\WpdbPeopleAuthority;
use Arn\Intranet\People\WpdbPeopleRepository;

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) {
	throw new RuntimeException( 'Ensaio People indisponível.' );
}
$launcher = realpath( (string) ( $_SERVER['argv'][0] ?? '' ) );
if ( false === $launcher || ! is_file( $launcher ) || ! is_readable( $launcher ) ) {
	throw new RuntimeException( 'Launcher WP-CLI indisponível.' );
}
$prefix = 'arn_peo_' . bin2hex( random_bytes( 5 ) ) . '_';
$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_people_private_' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Raiz privada indisponível.' );
}
$logins       = LocalPeopleRoster::logins( $prefix );
$owned        = array();
$owned_pages  = array();
$capture_page = static function ( int $id ) use ( &$owned_pages, $prefix ): void {
	$post = get_post( $id );
	if ( $post instanceof WP_Post && 'page' === $post->post_type && str_starts_with( $post->post_name, rtrim( $prefix, '_' ) . '-' ) ) {
		$owned_pages[ $id ] = $id;
	}
};
add_action( 'save_post_page', $capture_page, PHP_INT_MIN, 1 );
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
$initial_post_count = (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts );
$other              = null;
$failure            = null;
$workers            = array();
$checks             = 0;
$assert             = static function ( bool $value, string $name ) use ( &$checks ): void {
	if ( ! $value ) {
		throw new RuntimeException( 'Falhou People: ' . esc_html( $name ) );
	}
	++$checks;
};
$deny               = static function ( callable $operation ) use ( $assert ): void {
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
		$before = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) );
		$deny( static fn() => ( new LocalPeopleInstaller( $prefix, $root ) )->install() );
		$after = array( (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->users ), (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts ), (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->options ) );
		$assert( $before === $after && array() === $owned && array() === $owned_pages, 'development negativo sem efeitos' );
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
	foreach ( $installed['pages'] as $page_id ) {
		$owned_pages[ $page_id ] = $page_id; }
	$ids   = $installed['user_ids'];
	$auth  = new WpdbAuthorizationRepository( $db );
	$units = new WpdbLocalUnitRepository( $db );
	$assert( 3 === count( $ids ), 'três perfis próprios' );
	$deny( static fn() => $units->snapshotInTransaction() );
	$original_b      = array_column( $units->snapshot()->units, null, 'id' )['LAB-B'];
	$payload         = $db->get_var( $db->prepare( 'SELECT payload FROM %i WHERE id=%s', $prefix . 'arn_org_units', 'LAB-B' ) );
	$marked          = json_decode( $payload, true, 32, JSON_THROW_ON_ERROR );
	$marked['name'] .= ' marcador';
	$db->query( 'START TRANSACTION' );
	$db->query( $db->prepare( 'UPDATE %i SET payload=%s WHERE id=%s', $prefix . 'arn_org_units', json_encode( $marked, JSON_THROW_ON_ERROR ), 'LAB-B' ) );
	$locked    = $units->snapshotInTransaction();
	$active_tx = $db->get_var( 'SELECT @@session.in_transaction' );
	$db->query( 'ROLLBACK' );
	$rolled_b = array_column( $units->snapshot()->units, null, 'id' )['LAB-B'];
	$locked_b = array_column( $locked->units, null, 'id' )['LAB-B'];
	$assert( 1 === $locked->revision && '1' === (string) $active_tx && 1 === $units->snapshot()->revision && $locked_b->name === $marked['name'] && get_object_vars( $rolled_b ) === get_object_vars( $original_b ), 'snapshotInTransaction não confirma marcador e rollback do chamador reverte-o' );
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
	$alfa_auth_revision = $auth->find( $ids['alfa'] )->revision;
	$other->query( 'START TRANSACTION' );
	$other->get_results( $other->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $prefix . 'arn_authz_accounts', $ids['alfa'] ) );
	$db->query( 'SET SESSION innodb_lock_wait_timeout=1' );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 222', 'Sala Á', '' ), 2 ) );
	$other->query( 'ROLLBACK' );
	$assert( 2 === $people->find( $ids['alfa'] )->revision && 2 === count( $people->history( $ids['alfa'] ) ), 'bloqueio concorrente sem escrita' );
	$other->query( 'START TRANSACTION' );
	$other->get_results( $other->prepare( 'SELECT user_id FROM %i WHERE user_id=%d FOR UPDATE', $prefix . 'arn_authz_accounts', $ids['alfa'] ) );
	$command = array(
		PHP_BINARY,
		$launcher,
		'--path=' . ABSPATH,
		'--user=' . wp_get_current_user()->user_login,
		'eval-file',
		__DIR__ . '/people-worker.php',
	);
	foreach ( array( 'a', 'b' ) as $label ) {
		$environment = array_merge(
			getenv(),
			array(
				'ARN_PEOPLE_TEST_PREFIX'        => $prefix,
				'ARN_PEOPLE_TEST_ROOT'          => $root,
				'ARN_PEOPLE_TEST_LABEL'         => $label,
				'ARN_PEOPLE_TEST_USER_ID'       => (string) $ids['alfa'],
				'ARN_PEOPLE_TEST_AUTH_REVISION' => (string) $alfa_auth_revision,
			)
		);
		$descriptors = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open -- Local-only two-process integration test.
		$process = proc_open( $command, $descriptors, $pipes, dirname( __DIR__, 2 ), $environment );
		$assert( is_resource( $process ), 'worker PHP exclusivo iniciou' );
		fclose( $pipes[0] );
		$workers[] = array( $process, $pipes, $label );
	}
	$ready = false;
	for ( $attempt = 0; $attempt < 200; ++$attempt ) {
		if ( is_file( $root . DIRECTORY_SEPARATOR . '.people-worker-a.ready' ) && is_file( $root . DIRECTORY_SEPARATOR . '.people-worker-b.ready' ) ) {
			$ready = true;
			break;
		}
		if ( ! proc_get_status( $workers[0][0] )['running'] || ! proc_get_status( $workers[1][0] )['running'] ) {
			break;
		}
		usleep( 50000 );
	}
	$assert( $ready && proc_get_status( $workers[0][0] )['running'] && proc_get_status( $workers[1][0] )['running'], 'dois escritores simultâneos aguardam o mesmo bloqueio' );
	$other->query( 'ROLLBACK' );
	$finished = false;
	for ( $attempt = 0; $attempt < 200; ++$attempt ) {
		if ( is_file( $root . DIRECTORY_SEPARATOR . '.people-worker-a.result' ) && is_file( $root . DIRECTORY_SEPARATOR . '.people-worker-b.result' ) ) {
			$finished = true;
			break;
		}
		usleep( 50000 );
	}
	$assert( $finished, 'dois escritores concluíram dentro do limite' );
	$statuses = array();
	foreach ( $workers as [$process, $pipes, $label] ) {
		stream_get_contents( $pipes[1] );
		stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$assert( 0 === proc_close( $process ), 'worker terminou sem falha' );
		$statuses[] = file_get_contents( $root . DIRECTORY_SEPARATOR . '.people-worker-' . $label . '.result' );
	}
	$workers = array();
	sort( $statuses );
	$after_race = $people->find( $ids['alfa'] );
	$assert( array( 'committed', 'rejected' ) === $statuses && 3 === $after_race->revision && 3 === count( $people->history( $ids['alfa'] ) ) && in_array( $after_race->self_service->phone, array( '+245 222', '+245 444' ), true ), 'dois escritores: um commit, um CAS recusado e um evento' );
	foreach ( array( 'a', 'b' ) as $label ) {
		unlink( $root . DIRECTORY_SEPARATOR . '.people-worker-' . $label . '.ready' );
		unlink( $root . DIRECTORY_SEPARATOR . '.people-worker-' . $label . '.result' );
	}
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
	remove_action( 'save_post_page', $capture_page, PHP_INT_MIN );
	foreach ( $owned_pages as $page_id ) {
		$owned_post = get_post( $page_id );
		if ( $owned_post instanceof WP_Post && 'page' === $owned_post->post_type && str_starts_with( $owned_post->post_name, rtrim( $prefix, '_' ) . '-' ) ) {
			wp_delete_post( $page_id, true );
		}
	}
	foreach ( array( 'directory', 'profile', 'team' ) as $view ) {
		if ( get_page_by_path( ( new PeoplePages( $prefix ) )->slug( $view ), OBJECT, 'page' ) instanceof WP_Post ) {
			throw new RuntimeException( 'Limpeza People de páginas incompleta.' );
		}
	}
	if ( null !== $failure ) {
		remove_filter( 'query', $failure );
	}
	if ( null !== $other ) {
		$other->query( 'ROLLBACK' );
		$other->close();
	}
	foreach ( $workers as [$process, $pipes] ) {
		if ( proc_get_status( $process )['running'] ) {
			proc_terminate( $process );
		}
		foreach ( $pipes as $pipe ) {
			if ( is_resource( $pipe ) ) {
				fclose( $pipe );
			}
		}
		proc_close( $process );
	}
	foreach ( array( 'a', 'b' ) as $label ) {
		foreach ( array( 'ready', 'result' ) as $kind ) {
			$file = $root . DIRECTORY_SEPARATOR . '.people-worker-' . $label . '.' . $kind;
			if ( is_file( $file ) && ! is_link( $file ) ) {
				unlink( $file );
			}
		}
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
	if ( $initial_post_count !== (int) $GLOBALS['wpdb']->get_var( 'SELECT COUNT(*) FROM ' . $GLOBALS['wpdb']->posts ) ) {
		throw new RuntimeException( 'Limpeza People de posts incompleta.' );
	}
	$db->close();
}
