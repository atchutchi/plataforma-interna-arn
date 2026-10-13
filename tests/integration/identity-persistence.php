<?php
/**
 * Ensaio real de persistência. Executar com wp eval-file e um administrador local.
 * Usa contas fictícias e tabelas exclusivas, eliminadas no final.
 *
 * @package ArnIntranetCore
 */

use Arn\Intranet\Identity\GoogleAccountPolicy;
use Arn\Intranet\Identity\IdentityLinkService;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Identity\WordPressIdentityLinkAuthority;
use Arn\Intranet\Identity\WpdbIdentityLinkRepository;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio permitido apenas por administrador num laboratório local.' );
}

$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$prefix   = 'arn_it_' . bin2hex( random_bytes( 6 ) ) . '_';
$database->set_prefix( $prefix );
$database->suppress_errors( true );
$service  = new IdentityLinkService( new WpdbIdentityLinkRepository( $database ), new WordPressIdentityLinkAuthority() );
$actor_id = get_current_user_id();
$users    = array();
$checks   = 0;
$assert   = static function ( bool $condition ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Asserção de persistência falhou.' );
	}
	++$checks;
};
$refused  = static function ( callable $operation ) use ( $assert ): void {
	$failed = false;
	try {
		$operation();
	} catch ( Throwable ) {
		$failed = true;
	}
	$assert( $failed );
};

try {
	$service->installSchema( $actor_id );
	foreach ( array( 'a', 'b' ) as $suffix ) {
		$user_id = wp_insert_user(
			array(
				'user_login' => $prefix . $suffix,
				'user_email' => $prefix . $suffix . '@example.test',
				'user_pass'  => wp_generate_password( 32 ),
				'role'       => 'subscriber',
			)
		);
		$assert( is_int( $user_id ) );
		$users[] = $user_id;
	}
	$subject = 'fixture-sub-' . $prefix;
	$service->create( $actor_id, $users[0], 'accounts.google.com', $subject );
	$identity_link = $service->find( 'https://accounts.google.com', $subject );
	$assert( null !== $identity_link && ! $identity_link->authorized && ! $identity_link->active );
	$assert( null === $service->find( 'https://accounts.google.com', strtoupper( $subject ) ) );
	$refused( static fn() => $service->create( $actor_id, $users[1], 'https://accounts.google.com', $subject ) );
	$refused( static fn() => $service->create( $actor_id, $users[0], 'https://accounts.google.com', $subject . '-different' ) );
	$refused( static fn() => $service->activate( $users[0], $users[0] ) );
	$service->activate( $actor_id, $users[0] );
	$identity_link = $service->find( 'https://accounts.google.com', $subject );
	$claims        = array(
		'iss'            => 'https://accounts.google.com',
		'sub'            => $subject,
		'hd'             => 'example.test',
		'email_verified' => true,
	);
	$assert( GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $identity_link ) ) === $users[0] );
	$service->activate( $actor_id, $users[0] );
	$audit_table          = $prefix . 'arn_identity_audit';
	$identity_links_table = $prefix . 'arn_identity_links';
	$assert( '2' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM %i', $audit_table ) ) );
	$service->revoke( $actor_id, $users[0] );
	$assert( null === GoogleAccountPolicy::localUserId( $claims, 'example.test', array( $service->find( 'https://accounts.google.com', $subject ) ) ) );
	$assert( '3' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM %i', $audit_table ) ) );
	$assert( 'create,activate,revoke' === $database->get_var( $database->prepare( 'SELECT GROUP_CONCAT(action ORDER BY id) FROM %i', $audit_table ) ) );

	// O schema continua válido, mas qualquer escrita na auditoria falha.
	$trigger = $prefix . 'deny_audit';
	$assert( false !== $database->query( $database->prepare( "CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture audit failure'", $trigger, $audit_table ) ) );
	$refused( static fn() => $service->activate( $actor_id, $users[0] ) );
	$assert( false === $service->find( 'https://accounts.google.com', $subject )->active );
	$refused( static fn() => $service->create( $actor_id, $users[1], 'https://accounts.google.com', $subject . '-second' ) );
	$assert( null === $service->find( 'https://accounts.google.com', $subject . '-second' ) );
	$assert( '3' === (string) $database->get_var( $database->prepare( 'SELECT COUNT(*) FROM %i', $audit_table ) ) );
	$database->query( $database->prepare( 'DROP TRIGGER %i', $trigger ) );
	$database->query( 'START TRANSACTION' );
	$refused( static fn() => $service->activate( $actor_id, $users[0] ) );
	$database->query( 'ROLLBACK' );
	$service->activate( $actor_id, $users[0] );
	$assert( true === $service->find( 'https://accounts.google.com', $subject )->active );
	$assert( array( 'subscriber' ) === get_userdata( $users[0] )->roles );
	WP_CLI::success( 'Persistência real: ' . $checks . ' asserções, constraints, auditoria, rollback e recusa de actor sem competência.' );
} finally {
	$database->query( $database->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_identity_audit' ) );
	$database->query( $database->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_identity_links' ) );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $user_id ) {
		wp_delete_user( $user_id );
	}
}
