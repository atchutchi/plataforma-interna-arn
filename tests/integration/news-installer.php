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
try {
	$rejected = false;
	try {
		( new LocalNewsInstaller( $prefix, ABSPATH ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected, 'raiz pública recusada' );
	$db->query( $db->prepare( 'CREATE TABLE %i LIKE %i', $schema->table( 'posts' ), $GLOBALS['wpdb']->posts ) );
	$installer = new LocalNewsInstaller( $prefix, $private_root );
	$first     = $installer->install();
	$created   = array_column( $first, 'user_id' );
	$assert( 2 === count( $first ) && $first[0]['created'] && $first[1]['created'], 'duas contas novas exactas' );
	foreach ( $logins as $index => $login ) {
		$user = get_user_by( 'login', $login );
		$assert( $user && (int) $user->ID === $created[ $index ] && array( 'subscriber' ) === array_values( $user->roles ), 'identidade Subscriber ' . $index );
	}
	foreach ( array( 'autor-noticias-local.password', 'editor-noticias-local.password' ) as $filename ) {
		$assert( 48 === strlen( file_get_contents( $private_root . DIRECTORY_SEPARATOR . $filename ) ), 'credencial de 48 caracteres só na raiz privada' );
	}
	$authorization = new WpdbAuthorizationRepository( $db );
	$author        = $authorization->find( $created[0] );
	$editor        = $authorization->find( $created[1] );
	$assert( AccountState::Active === $author->state && AccountState::Active === $editor->state && 1 === count( $author->stored_grants ) && 1 === count( $editor->stored_grants ), 'estado e concessões exactos' );
	$audit_before = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	$second       = $installer->install();
	$audit_after  = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	$assert( ! $second[0]['created'] && ! $second[1]['created'] && $audit_before === $audit_after, 'repetição sem eventos' );
	$context  = static fn( string $reason ): ChangeContext => new ChangeContext( get_current_user_id(), $reason, 'LAB-NEWS', time() );
	$authorization->changeState( $created[1], AccountState::Suspended, $editor->revision, $context( 'Suspensão exclusiva' ) );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && AccountState::Suspended === $authorization->find( $created[1] )->state, 'suspensão não restaurada' );
	$authorization->changeState( $created[1], AccountState::Active, $authorization->find( $created[1] )->revision, $context( 'Reposição exclusiva para ensaio' ) );
	$authorization->revokeGrant( $created[0], $author->stored_grants[0]->id, $author->revision, $context( 'Revogação exclusiva' ) );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && $authorization->find( $created[0] )->stored_grants[0]->grant->revoked, 'revogação não restaurada' );
	echo esc_html( 'Instalador de notícias: ' . $checks . " verificações.\n" );
} finally {
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
	foreach ( $created as $fixture_user_id ) {
		wp_delete_user( $fixture_user_id );
	}
	foreach ( array( 'autor-noticias-local.password', 'editor-noticias-local.password' ) as $filename ) {
		$file = $private_root . DIRECTORY_SEPARATOR . $filename;
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $private_root );
}
