<?php
/**
 * Gravação People, CAS, no-op, rollback de auditoria e instalador no MySQL exclusivo.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, WordPress.NamingConventions.PrefixAllGlobals, WordPress.WP.GlobalVariablesOverride, WordPress.Security.EscapeOutput, Squiz.Commenting, Generic.Commenting.DocComment, Generic.Formatting.MultipleStatementAlignment

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Organization\Storage\LocalUnitContext;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Arn\Intranet\People\LocalPeopleRoster;
use Arn\Intranet\People\PeopleSchema;
use Arn\Intranet\People\ProfessionalDetails;
use Arn\Intranet\People\Profile;
use Arn\Intranet\People\SelfService;
use Arn\Intranet\People\WpdbPeopleAuthority;
use Arn\Intranet\People\WpdbPeopleRepository;

require __DIR__ . '/mysql-transaction-bootstrap.php';

if ( ! defined( 'WP_CLI' ) ) {
	define( 'WP_CLI', true );
}
if ( ! defined( 'ARN_ALLOW_LOCAL_PEOPLE' ) ) {
	define( 'ARN_ALLOW_LOCAL_PEOPLE', true );
}
if ( ! defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) ) {
	define( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', true );
}
if ( ! defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) ) {
	define( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', true );
}
if ( ! defined( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) ) {
	define( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE', true );
}

$GLOBALS['arn_people_users'] = array();
$GLOBALS['arn_people_meta']  = array();

if ( ! class_exists( 'WP_User' ) ) {
	require dirname( __DIR__ ) . '/phpunit/doubles/WP_User.php';
}
if ( ! function_exists( 'wp_get_environment_type' ) ) {
	/** Ambiente local do ensaio exclusivo. */
	function wp_get_environment_type(): string {
		return 'local';
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Administrador técnico do ensaio.
	 *
	 * @param string $capability Capacidade.
	 */
	function current_user_can( $capability ): bool {
		return 'manage_options' === $capability;
	}
}
if ( ! function_exists( 'get_current_user_id' ) ) {
	/** Actor administrativo fictício. */
	function get_current_user_id(): int {
		return 1;
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	/**
	 * @param int $user_id Identificador.
	 * @return WP_User|false
	 */
	function get_userdata( $user_id ) {
		return $GLOBALS['arn_people_users'][ (int) $user_id ] ?? false;
	}
}
if ( ! function_exists( 'get_user_by' ) ) {
	/**
	 * @param string     $field Campo.
	 * @param string|int $value Valor.
	 * @return WP_User|false
	 */
	function get_user_by( $field, $value ) {
		foreach ( $GLOBALS['arn_people_users'] as $user ) {
			if ( 'login' === $field && $user->user_login === $value ) {
				return $user;
			}
			if ( 'email' === $field && $user->user_email === $value ) {
				return $user;
			}
			if ( 'id' === $field && $user->ID === (int) $value ) {
				return $user;
			}
		}
		return false;
	}
}
if ( ! function_exists( 'get_user_meta' ) ) {
	/**
	 * @param int    $user_id Identificador.
	 * @param string $key Chave.
	 * @param bool   $single Valor único.
	 */
	function get_user_meta( $user_id, $key = '', $single = false ) {
		$value = $GLOBALS['arn_people_meta'][ (int) $user_id ][ (string) $key ] ?? null;
		if ( $single ) {
			return $value ?? '';
		}
		return null === $value ? array() : array( $value );
	}
}
if ( ! function_exists( 'update_user_meta' ) ) {
	/**
	 * @param int    $user_id Identificador.
	 * @param string $key Chave.
	 * @param mixed  $value Valor.
	 */
	function update_user_meta( $user_id, $key, $value ): bool {
		$GLOBALS['arn_people_meta'][ (int) $user_id ][ (string) $key ] = (string) $value;
		return true;
	}
}

$checks = 0;
$assert = static function ( bool $condition, string $name ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou People MySQL: ' . $name );
	}
	++$checks;
};
$deny   = static function ( callable $operation, string $name ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, $name );
		return;
	}
	$assert( false, $name );
};

$db     = arn_tx_connect();
$prefix = 'arn_peo_' . bin2hex( random_bytes( 5 ) ) . '_';
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$tables = array();

try {
	$ids    = array(
		'alfa'    => 101,
		'beta'    => 102,
		'manager' => 103,
	);
	$logins = LocalPeopleRoster::logins( $prefix );
	foreach ( $ids as $label => $user_id ) {
		$login                                   = $logins[ $label ];
		$user                                    = new WP_User( $user_id, $login, $login . '@example.invalid' );
		$user->roles                             = array( 'subscriber' );
		$GLOBALS['arn_people_users'][ $user_id ] = $user;
		update_user_meta( $user_id, LocalFixtureUser::META_KEY, '1' );
	}

	$auth  = new WpdbAuthorizationRepository( $db );
	$units = new WpdbLocalUnitRepository( $db );
	$auth->installSchema();
	$auth->installSchema();
	$units->installSchema();
	$units->installSchema();
	$people_schema = new PeopleSchema( $db );
	$people_schema->install();
	$people_schema->install();
	foreach ( array( 'arn_authz_accounts', 'arn_authz_grants', 'arn_authz_audit', 'arn_authz_schema', 'arn_org_header', 'arn_org_units', 'arn_org_audit', 'arn_people', 'arn_people_audit', 'arn_people_schema' ) as $suffix ) {
		$tables[] = $prefix . $suffix;
	}
	$assert( 10 === count( $tables ), 'dez tabelas exclusivas' );

	$units->apply( 'initialize', 0, new LocalUnitContext( 1, 'Preparação fictícia exclusiva People MySQL', time() ) );
	$active = array_values( array_map( static fn( $unit ): string => $unit->id, array_filter( $units->snapshot()->units, static fn( $unit ): bool => $unit->active ) ) );
	$assert( in_array( 'LAB-A', $active, true ) && in_array( 'LAB-B', $active, true ), 'LAB-A e LAB-B activas' );

	$service = new AuthorizationStorageService( $auth, new LocalAuthorizationStorageAuthority() );
	$context = static fn( string $reason ): ChangeContext => new ChangeContext( 1, $reason, 'LAB-PEOPLE', time() );
	foreach ( $ids as $label => $user_id ) {
		$service->createAccount( $user_id, $context( 'Criação fictícia People' ) );
		$snapshot = $auth->find( $user_id );
		$service->changeState( $user_id, AccountState::Active, $snapshot->revision, $context( 'Activação fictícia People' ) );
		$snapshot = $auth->find( $user_id );
		$service->createGrant( new Grant( $user_id, Role::Employee, Scope::own(), time(), null ), $snapshot->revision, $context( 'Concessão fictícia People' ) );
	}

	$session = static function ( int $user_id ) use ( $auth ): array {
		$snapshot = $auth->find( $user_id );
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => $user_id,
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
	$people = new WpdbPeopleRepository( $db, $authority );
	foreach ( $ids as $label => $user_id ) {
		$base         = LocalPeopleRoster::details( $label );
		$professional = new ProfessionalDetails( $base->code, $base->name, $logins[ $label ] . '@example.invalid', $base->job, $base->unit_id );
		$now          = time();
		$people->seed( new Profile( $user_id, $professional, new SelfService( '', '', '' ), 1, $now ), 1, $now );
	}
	$before = $people->find( $ids['alfa'] );
	$assert( null !== $before && 1 === $before->revision && 3 === count( $people->all() ), 'elenco seed' );

	$updated = $people->updateOwn( $ids['alfa'], new SelfService( '+245 123', 'Sala A', "Linha 1\nLinha 2" ), 1 );
	$assert( 2 === $updated->revision && array( 'phone', 'office', 'bio' ) === $people->history( $ids['alfa'] )[1]['changed_fields'], 'gravação autosserviço' );

	$no_op = $people->updateOwn( $ids['alfa'], $updated->self_service, 2 );
	$assert( 2 === $no_op->revision && 2 === count( $people->history( $ids['alfa'] ) ), 'no-op sem evento' );

	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 222', 'Sala A', '' ), 1 ), 'CAS recusa revisão antiga' );
	$assert( 2 === $people->find( $ids['alfa'] )->revision, 'CAS sem escrita' );

	$failure = static function ( string $sql ) use ( $prefix ): string {
		return str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, $prefix . 'arn_people_audit' ) ? 'INVALID PEOPLE AUDIT SQL' : $sql;
	};
	add_filter( 'query', $failure );
	$deny( static fn() => $people->updateOwn( $ids['alfa'], new SelfService( '+245 999', 'Sala A', '' ), 2 ), 'falha de auditoria recusa' );
	remove_filter( 'query', $failure );
	$assert( 2 === $people->find( $ids['alfa'] )->revision && 2 === count( $people->history( $ids['alfa'] ) ), 'rollback por falha de auditoria' );

	$people_schema->install();
	$assert( 2 === $people->find( $ids['alfa'] )->revision, 'instalador schema idempotente' );

	echo 'PASS: People MySQL exclusivo, ' . $checks . ' verificações, prefixo limpo a seguir, motor=' . (string) $db->get_var( 'SELECT VERSION()' ) . PHP_EOL;
} finally {
	foreach ( array_reverse( $tables ) as $table ) {
		$db->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	}
	$remaining = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE %s', $db->esc_like( $prefix ) . '%' ) );
	$db->close();
	if ( 0 !== $remaining ) {
		throw new RuntimeException( 'Limpeza People de prefixo incompleta: ' . $prefix );
	}
}
