<?php
/** Exclusive real WordPress and SQL installer exercise.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\LocalNewsInstaller;
use Arn\Intranet\News\NewsSchema;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' );
}
if ( 'local' !== wp_get_environment_type() ) {
	$gate_prefix  = 'arn_ni_gate_' . bin2hex( random_bytes( 5 ) ) . '_';
	$identities   = static function (): array {
		$found = array();
		foreach ( array( 'autor.noticias.teste', 'editor.noticias.teste' ) as $login ) {
			$user    = get_user_by( 'login', $login );
			$found[] = $user instanceof WP_User ? $user->ID : null;
		}
		return $found;
	};
	$before_users = $identities();
	$rejected     = false;
	try {
		( new LocalNewsInstaller( $gate_prefix ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$after_users = $identities();
	global $wpdb;
	$tables = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $gate_prefix . 'arn_news' ) );
	if ( ! $rejected || $before_users !== $after_users || 0 !== $tables ) {
		throw new RuntimeException( 'Falhou: instalador não recusou gate antes de escritas.' );
	}
	echo "Instalador de notícias: recusa fora de local verificada.\n";
	return;
}
if ( ! defined( 'ARN_ALLOW_LOCAL_NEWSROOM' ) ) {
	define( 'ARN_ALLOW_LOCAL_NEWSROOM', true );
}
if ( true !== ARN_ALLOW_LOCAL_NEWSROOM ) {
	throw new RuntimeException( 'Ensaio local indisponível.' );
}
$logins = array( 'autor.noticias.teste', 'editor.noticias.teste' );
foreach ( $logins as $login ) {
	if ( false !== get_user_by( 'login', $login ) ) {
		throw new RuntimeException( 'Conta de ensaio preexistente, sem alterações.' );
	}
}
$checks       = 0;
$assert       = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix       = 'arn_ni_' . bin2hex( random_bytes( 5 ) ) . '_';
$private_root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_news_install_' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $private_root, 0700 ) ) {
	throw new RuntimeException( 'Raiz de ensaio indisponível.' );
}
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$schema  = new NewsSchema( $db );
$files   = new FileSchema( $db );
$created = array();
$capture = static function ( int $user_id ) use ( &$created ): void {
	$user = get_userdata( $user_id );
	if ( ! $user instanceof WP_User ) {
		return;
	}
	$expected = array(
		'autor.noticias.teste'  => 'autor.noticias.teste@example.test',
		'editor.noticias.teste' => 'editor.noticias.teste@example.test',
	);
	if ( isset( $expected[ $user->user_login ] ) && $expected[ $user->user_login ] === $user->user_email ) {
		$created[ $user_id ] = array( $user->user_login, $user->user_email );
	}
};
add_action( 'user_register', $capture, PHP_INT_MIN, 1 );
try {
	$rejected = false;
	try {
		( new LocalNewsInstaller( $prefix, ABSPATH ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && array() === $created, 'raiz pública recusada antes de criar conta' );
	$working_root = realpath( dirname( __DIR__, 2 ) . '/tests' );
	$rejected     = false;
	try {
		( new LocalNewsInstaller( $prefix, $working_root ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$working_tables = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $schema->table( 'news' ) ) );
	$assert( $rejected && 0 === $working_tables && array() === $created, 'raiz versionável recusada antes de SQL e contas' );
	$db->query( $db->prepare( 'CREATE TABLE %i LIKE %i', $schema->table( 'posts' ), $GLOBALS['wpdb']->posts ) );
	$installer   = new LocalNewsInstaller( $prefix, $private_root );
	$fail_second = static function ( string $login ): string {
		if ( 'editor.noticias.teste' === $login ) {
			throw new RuntimeException( 'Falha de ensaio após a primeira conta.' );
		}
		return $login;
	};
	add_filter( 'pre_user_login', $fail_second, 10, 1 );
	$partial_rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$partial_rejected = true;
	} finally {
		remove_filter( 'pre_user_login', $fail_second, 10 );
	}
	$partial_ids = array_keys( $created );
	$assert( $partial_rejected && 1 === count( $partial_ids ) && false === get_user_by( 'login', 'editor.noticias.teste' ), 'falha injectada após primeira criação registada no hook' );
	$assert( ! is_file( $private_root . DIRECTORY_SEPARATOR . 'editor-noticias-local.password' ), 'falha não conserva password da segunda conta' );
	$partial_id   = $partial_ids[0];
	$partial_user = get_userdata( $partial_id );
	if ( ! $partial_user instanceof WP_User || array( $partial_user->user_login, $partial_user->user_email ) !== $created[ $partial_id ] ) {
		throw new RuntimeException( 'Identidade parcial inesperada.' );
	}
	if ( ! wp_delete_user( $partial_id ) ) {
		throw new RuntimeException( 'Limpeza parcial indisponível.' );
	}
	unset( $created[ $partial_id ] );
	$partial_password = $private_root . DIRECTORY_SEPARATOR . 'autor-noticias-local.password';
	if ( is_file( $partial_password ) ) {
		unlink( $partial_password );
	}
	$assert( false === get_user_by( 'login', 'autor.noticias.teste' ) && array() === $created, 'limpeza só da primeira conta criada pelo ensaio' );
	$first    = $installer->install();
	$user_ids = array_column( $first, 'user_id' );
	$assert( 2 === count( $first ) && $first[0]['created'] && $first[1]['created'], 'duas contas novas exactas' );
	foreach ( $logins as $index => $login ) {
		$user = get_user_by( 'login', $login );
		$assert( $user && (int) $user->ID === $user_ids[ $index ] && isset( $created[ $user_ids[ $index ] ] ) && array( 'subscriber' ) === array_values( $user->roles ), 'identidade Subscriber ' . $index );
	}
	foreach ( array( 'autor-noticias-local.password', 'editor-noticias-local.password' ) as $filename ) {
		$assert( 48 === strlen( file_get_contents( $private_root . DIRECTORY_SEPARATOR . $filename ) ), 'credencial de 48 caracteres só na raiz privada' );
	}
	$authorization = new WpdbAuthorizationRepository( $db );
	$author        = $authorization->find( $user_ids[0] );
	$editor        = $authorization->find( $user_ids[1] );
	$assert( AccountState::Active === $author->state && AccountState::Active === $editor->state && 1 === count( $author->stored_grants ) && 1 === count( $editor->stored_grants ), 'estado e concessões exactos' );
	$audit_before = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	$second       = $installer->install();
	$audit_after  = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	$assert( ! $second[0]['created'] && ! $second[1]['created'] && $audit_before === $audit_after, 'repetição sem eventos' );
	$context  = static fn( string $reason ): ChangeContext => new ChangeContext( get_current_user_id(), $reason, 'LAB-NEWS', time() );
	$authorization->changeState( $user_ids[1], AccountState::Suspended, $editor->revision, $context( 'Suspensão exclusiva' ) );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && AccountState::Suspended === $authorization->find( $user_ids[1] )->state, 'suspensão não restaurada' );
	$authorization->changeState( $user_ids[1], AccountState::Active, $authorization->find( $user_ids[1] )->revision, $context( 'Reposição exclusiva para ensaio' ) );
	$authorization->revokeGrant( $user_ids[0], $author->stored_grants[0]->id, $author->revision, $context( 'Revogação exclusiva' ) );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && $authorization->find( $user_ids[0] )->stored_grants[0]->grant->revoked, 'revogação não restaurada' );
	echo esc_html( 'Instalador de notícias: ' . $checks . " verificações.\n" );
} finally {
	remove_action( 'user_register', $capture, PHP_INT_MIN );
	foreach ( array( 'notifications', 'audit', 'assets', 'versions', 'news', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( $name ) ) );
	}
	foreach ( array( 'audit', 'files', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $files->table( $name ) ) );
	}
	foreach ( array( 'audit', 'grants', 'accounts', 'schema' ) as $name ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_authz_' . $name ) );
	}
	$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $schema->table( 'posts' ) ) );
	foreach ( $created as $fixture_user_id => $identity ) {
		$user = get_userdata( $fixture_user_id );
		if ( $user instanceof WP_User && array( $user->user_login, $user->user_email ) === $identity ) {
			wp_delete_user( $fixture_user_id );
		}
	}
	foreach ( array( 'autor-noticias-local.password', 'editor-noticias-local.password' ) as $filename ) {
		$file = $private_root . DIRECTORY_SEPARATOR . $filename;
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $private_root );
}
