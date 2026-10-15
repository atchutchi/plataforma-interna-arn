<?php
/** Exclusive editorial installer exercise. Never uses normal F1-10 accounts.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\FileSchema;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\News\LocalEditorialInstaller;
use Arn\Intranet\News\LocalNewsInstaller;
use Arn\Intranet\News\NewsSchema;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.Security.EscapeOutput.ExceptionNotEscaped
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.WP.AlternativeFunctions

if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo indisponível.' );
}
$prefix = 'arn_ed_' . bin2hex( random_bytes( 5 ) ) . '_';
$labels = array( 'author', 'employee_a', 'employee_b', 'alternate', 'editor' );
$logins = array_map( static fn( string $label ): string => $prefix . $label, $labels );
$root   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_ed_private_' . bin2hex( random_bytes( 8 ) );
if ( ! mkdir( $root, 0700 ) ) {
	throw new RuntimeException( 'Raiz privada indisponível.' );
}
$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$db->suppress_errors( true );
$schema  = new NewsSchema( $db );
$files   = new FileSchema( $db );
$owned   = array();
$capture = static function ( int $id ) use ( &$owned, $logins ): void {
	$user = get_userdata( $id );
	if ( $user instanceof WP_User && in_array( $user->user_login, $logins, true ) && $user->user_email === $user->user_login . '@example.test' ) {
		$owned[ $id ] = $user->user_login;
	}
};
add_action( 'user_register', $capture, PHP_INT_MIN, 1 );
$checks        = 0;
$assert        = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou: ' . $label );
	}
	++$checks;
};
$prefix_tables = static fn(): int => (int) $GLOBALS['wpdb']->get_var(
	$GLOBALS['wpdb']->prepare(
		'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE %s',
		$GLOBALS['wpdb']->esc_like( $prefix ) . '%'
	)
);
try {
	if ( 'local' !== wp_get_environment_type() ) {
		foreach ( array( 'ARN_ALLOW_LOCAL_NEWSROOM', 'ARN_ALLOW_LOCAL_PRIVATE_FILES', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW' ) as $flag ) {
			if ( ! defined( $flag ) ) {
				define( $flag, true );
			}
			$assert( true === constant( $flag ), 'flag isolada disponível: ' . $flag );
		}
		global $wpdb;
		$identity_and_grants = static function () use ( $wpdb ): array {
			$tables = array(
				$wpdb->users                         => 'ID',
				$wpdb->usermeta                      => 'umeta_id',
				$wpdb->prefix . 'arn_authz_accounts' => 'user_id',
				$wpdb->prefix . 'arn_authz_grants'   => 'id',
			);
			$result = array();
			foreach ( $tables as $table => $key ) {
				$exists           = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
				$result[ $table ] = 1 === $exists ? $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY %i', $table, $key ), ARRAY_A ) : null;
			}
			return $result;
		};
		$before              = $identity_and_grants();
		$assert( 0 === $prefix_tables() && array() === $owned, 'prefixo exclusivo vazio antes do gate' );
		$rejected = false;
		try {
			( new LocalEditorialInstaller( $prefix, $root ) )->install();
		} catch ( RuntimeException ) {
			$rejected = true;
		}
		$absent = true;
		foreach ( $logins as $login ) {
			$absent = $absent && false === get_user_by( 'login', $login );
		}
		$assert( $rejected && $absent && array() === $owned, 'entrada nova recusa desenvolvimento sem criar contas' );
		$assert( $before === $identity_and_grants(), 'identidades e concessões sem alterações' );
		$assert( 0 === $prefix_tables(), 'nenhuma tabela do prefixo criada' );
		echo esc_html( 'Instalador editorial: gate de desenvolvimento, ' . $checks . ' verificações.' . PHP_EOL );
		return;
	}
	$rejected = false;
	try {
		( new LocalEditorialInstaller( $prefix, ABSPATH ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && array() === $owned && 0 === $prefix_tables(), 'raiz pública recusada antes das contas e tabelas' );
	$rejected = false;
	try {
		( new LocalEditorialInstaller( 'arn_news_', $root ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && array() === $owned && 0 === $prefix_tables(), 'prefixo não exclusivo recusado antes das contas e tabelas' );
	$rejected = false;
	try {
		( new LocalNewsInstaller( $prefix, $root ) )->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && array() === $owned && 0 === $prefix_tables(), 'instalador legado fechado sem tabelas do prefixo' );
	if ( ! defined( 'ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW' ) ) {
		define( 'ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW', true );
	}
	if ( true !== ARN_ALLOW_LOCAL_EDITORIAL_WORKFLOW ) {
		$assert( array() === $owned, 'ambiente não autorizado não cria contas' );
		echo 'Instalador editorial: gates verificados.' . PHP_EOL;
		return;
	}
	$db->query( $db->prepare( 'CREATE TABLE %i LIKE %i', $schema->table( 'posts' ), $GLOBALS['wpdb']->posts ) );
	$installer = new LocalEditorialInstaller( $prefix, $root );
	$fail_next = static function ( string $login ) use ( $logins ): string {
		if ( $login === $logins[1] ) {
			throw new RuntimeException( 'Falha injectada na segunda conta.' );
		}
		return $login;
	};
	add_filter( 'pre_user_login', $fail_next, 10, 1 );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	} finally {
		remove_filter( 'pre_user_login', $fail_next, 10 );
	}
	$assert( $rejected && 1 === count( $owned ), 'falha parcial após primeiro actor' );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && 1 === count( $owned ), 'repetição parcial fechada' );
	foreach ( $owned as $fixture_id => $login ) {
		if ( get_userdata( $fixture_id )?->user_login !== $login || ! wp_delete_user( $fixture_id ) ) {
			throw new RuntimeException( 'Limpeza parcial recusada.' );
		}
		unset( $owned[ $fixture_id ] );
	}
	$file = $root . DIRECTORY_SEPARATOR . str_replace( '.', '-', $logins[0] ) . '.password';
	if ( is_file( $file ) ) {
		unlink( $file );
	}
	$first = $installer->install();
	$assert( 5 === count( $first ) && 5 === count( $owned ), 'cinco contas exclusivas criadas' );
	$repo = new WpdbAuthorizationRepository( $db );
	foreach ( $first as $index => $entry ) {
		$snapshot = $repo->find( $entry['user_id'] );
		$assert( $entry['created'] && AccountState::Active === $snapshot->state && count( $snapshot->stored_grants ) === ( $index > 2 ? 2 : 1 ), 'estado e concessões exactos ' . $index );
		$first_grant     = $snapshot->stored_grants[0]->grant;
		$correct_primary = 2 < $index
			? Role::Editorial === $first_grant->role && Scope::queues( array( 'editorial-ficticia' ) )->type === $first_grant->scope->type
			: Role::Employee === $first_grant->role && Scope::own()->type === $first_grant->scope->type;
		$assert( $correct_primary, 'função e âmbito principais ' . $index );
		if ( $index > 2 ) {
			$audience_targets = $snapshot->stored_grants[1]->grant->scope->targets;
			$expected         = array( 'news_all', 'news_person_' . $first[0]['user_id'], 'news_person_' . $first[1]['user_id'], 'news_person_' . $first[2]['user_id'], 'news_unit_LAB-A', 'news_unit_LAB-B' );
			sort( $expected, SORT_STRING );
			$assert( Role::Editorial === $snapshot->stored_grants[1]->grant->role && $audience_targets === $expected, 'audiência explícita ' . $index );
		}
		$filename = $root . DIRECTORY_SEPARATOR . str_replace( '.', '-', $logins[ $index ] ) . '.password';
		$assert( is_file( $filename ) && 48 === strlen( file_get_contents( $filename ) ), 'password privada ' . $index );
	}
	$audit_before = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	$second       = $installer->install();
	$audit_after  = (int) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $prefix . 'arn_authz_audit' ) );
	$assert( 5 === count( $second ) && $audit_before === $audit_after && ! $second[0]['created'], 'repetição exacta sem auditoria nova' );
	$editor = $repo->find( $first[4]['user_id'] );
	$repo->changeState( $editor->user_id, AccountState::Suspended, $editor->revision, new ChangeContext( get_current_user_id(), 'Suspensão exclusiva', 'LAB-NEWS', time() ) );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && AccountState::Suspended === $repo->find( $editor->user_id )->state, 'suspensão não restaurada' );
	$editor = $repo->find( $editor->user_id );
	$repo->changeState( $editor->user_id, AccountState::Active, $editor->revision, new ChangeContext( get_current_user_id(), 'Reposição exclusiva para ensaio', 'LAB-NEWS', time() ) );
	$author = $repo->find( $first[0]['user_id'] );
	$repo->revokeGrant( $author->user_id, $author->stored_grants[0]->id, $author->revision, new ChangeContext( get_current_user_id(), 'Revogação exclusiva', 'LAB-NEWS', time() ) );
	$rejected = false;
	try {
		$installer->install();
	} catch ( RuntimeException ) {
		$rejected = true;
	}
	$assert( $rejected && $repo->find( $author->user_id )->stored_grants[0]->grant->revoked, 'revogação não restaurada' );
	echo esc_html( 'Instalador editorial: ' . $checks . ' verificações.' . PHP_EOL );
} finally {
	remove_action( 'user_register', $capture, PHP_INT_MIN );
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
	foreach ( $owned as $fixture_id => $login ) {
		if ( get_userdata( $fixture_id )?->user_login === $login ) {
			wp_delete_user( $fixture_id );
		}
	}
	foreach ( $logins as $login ) {
		$file = $root . DIRECTORY_SEPARATOR . str_replace( '.', '-', $login ) . '.password';
		if ( is_file( $file ) ) {
			unlink( $file );
		}
	}
	rmdir( $root );
}
