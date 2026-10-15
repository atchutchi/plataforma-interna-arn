<?php
/** Audited queue-only editor upgrade on an isolated five-person roster.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\LocalEditorialInstaller;
use Arn\Intranet\News\NewsSchema;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo indisponível.' );
}
$prefix = 'arn_ed_' . bin2hex( random_bytes( 5 ) ) . '_';
$labels = array( 'author', 'employee_a', 'employee_b', 'alternate', 'editor' );
$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_ed_upgrade_' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Raiz privada indisponível.' );
}
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$schema = new NewsSchema( $db );
$files  = new FileSchema( $db );
$owned  = array();
try {
	if ( ! defined( 'ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW' ) ) {
		define( 'ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW', true );
	}
	if ( 'local' !== wp_get_environment_type() || true !== ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW ) {
		echo "Upgrade editorial: gate fechado.\n";
		return;
	}
	$db->query( $db->prepare( 'CREATE TABLE %i LIKE %i', $schema->table( 'posts' ), $GLOBALS['wpdb']->posts ) );
	$repository = new WpdbAuthorizationRepository( $db );
	$service    = new AuthorizationStorageService( $repository, new LocalAuthorizationStorageAuthority() );
	$context    = static fn( string $reason ): ChangeContext => new ChangeContext( get_current_user_id(), $reason, 'LAB-NEWS', time() );
	$service->installSchema( $context( 'Esquema exclusivo' ) );
	$files->install();
	$schema->install();
	foreach ( $labels as $label ) {
		$login  = $prefix . $label;
		$result = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password( 48, true, true ),
				'user_email' => $login . '@example.test',
				'role'       => 'subscriber',
			)
		);
		if ( is_wp_error( $result ) || ! is_int( $result ) ) {
			throw new RuntimeException( 'Fixture exclusiva indisponível.' );
		}
		$owned[ $label ] = $result;
		update_user_meta( $result, LocalFixtureUser::META_KEY, '1' );
		$service->createAccount( $result, $context( 'Conta exclusiva' ) );
		$service->changeState( $result, AccountState::Active, 1, $context( 'Activação exclusiva' ) );
	}
	$people = array();
	foreach ( array( 'author', 'employee_a', 'employee_b' ) as $label ) {
		$people[] = 'news_person_' . $owned[ $label ];
	}
	$audience_targets = array_merge( array( 'news_all', 'news_unit_LAB-A', 'news_unit_LAB-B' ), $people );
	sort( $audience_targets, SORT_STRING );
	foreach ( $labels as $label ) {
		$user_id      = $owned[ $label ];
		$queue        = in_array( $label, array( 'editor', 'alternate' ), true );
		$fixture_role = $queue ? Role::Editorial : Role::Employee;
		$scope        = $queue ? Scope::queues( array( 'editorial-ficticia' ) ) : Scope::own();
		$service->createGrant( new Grant( $user_id, $fixture_role, $scope, 0, null ), $repository->find( $user_id )->revision, $context( 'Concessão exclusiva' ) );
		if ( 'alternate' === $label ) {
			$service->createGrant( new Grant( $user_id, Role::Editorial, Scope::audiences( $audience_targets ), 0, null ), $repository->find( $user_id )->revision, $context( 'Audiência alternativa' ) );
		}
	}
	$editor_id    = $owned['editor'];
	$before       = $repository->find( $editor_id );
	$audit_before = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	$installed    = ( new LocalEditorialInstaller( $prefix, $root ) )->install();
	$after        = $repository->find( $editor_id );
	$audit_after  = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	if ( 5 !== count( $installed ) || $after->revision !== $before->revision + 1 || 2 !== count( $after->stored_grants ) || $audit_after !== $audit_before + 1 ) {
		throw new RuntimeException( 'Ampliação auditada inválida.' );
	}
	$again = ( new LocalEditorialInstaller( $prefix, $root ) )->install();
	if ( 5 !== count( $again ) || $after->revision !== $repository->find( $editor_id )->revision || $audit_after !== (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) ) ) {
		throw new RuntimeException( 'Repetição alterou concessões.' );
	}
	echo "Upgrade editorial: CAS, auditoria e idempotência verificados.\n";
} finally {
	foreach ( array( 'notifications', 'audit', 'assets', 'versions', 'news', 'schema_history', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( $name ) ) );
	}
	foreach ( array( 'audit', 'files', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $files->table( $name ) ) );
	}
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_authz_' . $name ) );
	}
	$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( 'posts' ) ) );
	foreach ( $owned as $label => $fixture_id ) {
		$user = get_userdata( $fixture_id );
		if ( $user instanceof WP_User && $user->user_login === $prefix . $label && $user->user_email === $prefix . $label . '@example.test' ) {
			wp_delete_user( $fixture_id );
		}
	}
	rmdir( $root );
}
