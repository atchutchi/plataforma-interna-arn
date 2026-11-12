<?php
/** Migrações interrompidas e retomadas exclusivamente em tabelas fictícias.
 *
 * @package ArnIntranet
 */

// phpcs:disable WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput.ExceptionNotEscaped, Squiz.Commenting
use Arn\Intranet\Identity\{IdentityWpdb, LocalIdentityLink, WpdbIdentityLinkRepository};
use Arn\Intranet\Identity\Institutional\{MaintenanceGate, MigrationReceipts, MigrationRunner};
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Apenas laboratório.' ); }
$checks = 0;
$assert = static function ( bool $ok ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Asserção migrações ' . ( $checks + 1 ) );
	} ++$checks;
};
$refuse = static function ( callable $call ) use ( $assert ): void {
	try {
		$call();
	} catch ( Throwable ) {
		$assert( true );
		return;
	} $assert( false );
};
global $wpdb;
$users_before = $wpdb->get_results( "SELECT ID,user_login FROM {$wpdb->users} ORDER BY ID", ARRAY_A );
for ( $case = 1; $case <= 9; ++$case ) {
	$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$prefix = 'arn_mig_' . bin2hex( random_bytes( 6 ) ) . '_';
	$db->set_prefix( $prefix );
	$db->suppress_errors( true );
	$dir = sys_get_temp_dir() . '/' . $prefix;
	mkdir( $dir, 0700 );
	$gate   = null;
	$filter = null;
	try {
		$gate = new MaintenanceGate( $dir, ABSPATH, true );
		$gate->exclusive();
		$binding  = hash( 'sha256', $prefix );
		$receipts = new MigrationReceipts( $gate, $binding );
		$runner   = new MigrationRunner( $db, $gate, $receipts );
		if ( 1 === $case ) {
			$other     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$lock_name = 'arn_migration_' . substr( hash( 'sha256', $db->get_var( 'SELECT DATABASE()' ) . '|' . $prefix ), 0, 40 );
			try {
				$assert( '1' === (string) $other->get_var( $other->prepare( 'SELECT GET_LOCK(%s, 0)', $lock_name ) ) );
				$refuse( static fn() => $runner->apply( get_current_user_id(), 'synthetic-backup' ) );
				$assert( array() === $receipts->all() );
			} finally {
				$other->get_var( $other->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
				$other->close(); }
		}
		$refuse( static fn() => $runner->release( get_current_user_id() ) );
		$history = array();
		if ( $case > 6 ) {
			$links = new WpdbIdentityLinkRepository( $db );
			$links->installSchema();
			$links->create( new LocalIdentityLink( 987654321, 'accounts.google.com', 'synthetic-migration', false, false ), get_current_user_id() );
			$db->query( "ALTER TABLE {$prefix}arn_identity_links DROP COLUMN generation" );
			$db->query( "ALTER TABLE {$prefix}arn_identity_audit DROP COLUMN previous_generation, DROP COLUMN new_generation" );
			$history = $db->get_results( "SELECT * FROM {$prefix}arn_identity_audit ORDER BY id", ARRAY_A );
		}
		$count  = 0;
		$filter = static function ( string $sql ) use ( &$count, $case, $prefix ): string {
			$verb = $case > 6 ? 'ALTER TABLE' : 'CREATE TABLE';
			if ( str_starts_with( $sql, $verb ) && str_contains( $sql, $prefix ) && ( $case > 6 ? $case - 6 : $case ) === ++$count ) {
				return 'INVALID SYNTHETIC INTERRUPTED MIGRATION'; }
			return $sql;
		};
		add_filter( 'query', $filter );
		$refuse( static fn() => $runner->apply( get_current_user_id(), 'synthetic-backup' ) );
		remove_filter( 'query', $filter );
		$filter = null;
		$assert( file_exists( $dir . '/maintenance' ) );
		$gate->close();
		// Nova instância simula um novo processo depois da interrupção.
		$gate = new MaintenanceGate( $dir, ABSPATH, true );
		$gate->exclusive();
		$receipts = new MigrationReceipts( $gate, $binding );
		$runner   = new MigrationRunner( $db, $gate, $receipts );
		$refuse( static fn() => $runner->release( get_current_user_id() ) );
		$runner->apply( get_current_user_id(), 'synthetic-backup' );
		$runner->apply( get_current_user_id(), 'synthetic-backup' );
		$records = $receipts->all();
		$assert( 'complete' === end( $records )['phase'] );
		$assert( '0' === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_authz_accounts" ) );
		$assert( '0' === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_authz_grants" ) );
		$assert( '0' === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_authz_audit" ) );
		$assert( ( $case > 6 ? '1' : '0' ) === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_identity_links" ) );
		if ( $case > 6 ) {
			$after = $db->get_results( "SELECT id,user_id,actor_id,action,previous_authorized,previous_active,new_authorized,new_active,occurred_at FROM {$prefix}arn_identity_audit ORDER BY id", ARRAY_A );
			$assert( $history === $after );
			$assert( '0' === (string) $db->get_var( "SELECT COUNT(*) FROM {$prefix}arn_identity_audit WHERE new_generation IS NOT NULL" ) );
		}
		$refuse( static fn() => ( new MigrationReceipts( $gate, hash( 'sha256', 'different' ) ) )->all() );
		$runner->release( get_current_user_id() );
		$assert( ! file_exists( $dir . '/maintenance' ) );
	} finally {
		if ( null !== $filter ) {
			remove_filter( 'query', $filter ); }
		$gate?->close();
		foreach ( array( 'arn_authz_grants', 'arn_authz_audit', 'arn_authz_accounts', 'arn_authz_schema', 'arn_identity_audit', 'arn_identity_links' ) as $suffix ) {
			$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) ); }
		$db->close();
		foreach ( glob( $dir . '/*' ) as $file ) {
			unlink( $file );
		} rmdir( $dir );
	}
}
$assert( $users_before === $wpdb->get_results( "SELECT ID,user_login FROM {$wpdb->users} ORDER BY ID", ARRAY_A ) );
WP_CLI::success( 'Migrações institucionais: ' . $checks . ' verificações. Fixtures removidas.' );
