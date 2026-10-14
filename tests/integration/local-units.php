<?php
/** Integração exclusiva das unidades sintéticas.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\{LocalUnitContext, LocalUnitInstaller, WpdbLocalUnitRepository};
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' ); }
if ( ! defined( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) ) {
	define( 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE', true ); }
$checks = 0;
$assert = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Apenas mensagem interna do ensaio CLI.
		throw new RuntimeException( 'Falhou: ' . $label ); }
	++$checks;
};
$deny   = static function ( callable $run ) use ( $assert ): void {
	try {
		$run();
	} catch ( RuntimeException ) {
		$assert( true, 'recusa' );
		return; }
	$assert( false, 'operação devia ser recusada' );
};
$prefix = 'arn_units_' . bin2hex( random_bytes( 6 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$repo    = new WpdbLocalUnitRepository( $db );
$other   = null;
$failure = null;
$actor   = get_current_user_id();
$users   = array();
try {
	$deny( static fn() => $repo->snapshot() );
	$repo->installSchema();
	$repo->installSchema();
	$assert( 0 === $repo->snapshot()->revision && array() === $repo->snapshot()->units, 'singleton vazio' );
	$context = new LocalUnitContext( $actor, 'Ensaio exclusivo', time() );
	$repo->apply( 'initialize', 0, $context );
	$repo->installSchema();
	$assert( 1 === $repo->snapshot()->revision && 3 === count( $repo->snapshot()->units ), 'instalação preserva árvore' );
	$deny( static fn() => $repo->apply( 'initialize', 0, $context ) );
	$deny( static fn() => $repo->apply( 'initialize', 1, $context ) );
	$repo->apply( 'rename-a', 1, $context );
	$repo->apply( 'move-b-to-a', 2, $context );
	$units = array_column( $repo->snapshot()->units, null, 'id' );
	$assert( 'Unidade fictícia A revista' === $units['LAB-A']->name && 'LAB-A' === $units['LAB-B']->parent_id, 'identidade estável e superior' );
	$failure                  = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, $prefix . 'arn_org_audit' ) ? 'INVALID LOCAL AUDIT SQL' : $sql;
	add_filter( 'query', $failure );
	$previous_show_errors     = $db->show_errors( true );
	$previous_suppress_errors = $db->suppress_errors( false );
	ob_start();
	try {
		$deny( static fn() => $repo->apply( 'move-b-to-root', 3, $context ) );
		$state_restored = true === $db->show_errors && false === $db->suppress_errors;
	} finally {
		$sql_output = ob_get_clean();
		$db->show_errors( $previous_show_errors );
		$db->suppress_errors( $previous_suppress_errors );
	}
	remove_filter( 'query', $failure );
	$failure = null;
	$assert( '' === $sql_output, 'falha SQL não produz saída' );
	$assert( $state_restored, 'estado de erros da ligação restaurado' );
	$assert( 3 === $repo->snapshot()->revision && 3 === count( $repo->history() ), 'rollback revisão e auditoria' );
	$units = array_column( $repo->snapshot()->units, null, 'id' );
	$assert( 'LAB-A' === $units['LAB-B']->parent_id, 'rollback árvore integral' );
	$other = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$other->set_prefix( $prefix );
	$other->suppress_errors( true );
	$other->query( 'START TRANSACTION' );
	$other->get_results( $other->prepare( 'SELECT id FROM %i WHERE id=1 FOR UPDATE', $prefix . 'arn_org_header' ) );
	$db->query( 'SET SESSION innodb_lock_wait_timeout=1' );
	$deny( static fn() => $repo->apply( 'move-b-to-root', 3, $context ) );
	$other->query( 'ROLLBACK' );
	$assert( 3 === $repo->snapshot()->revision, 'bloqueio concorrente conserva revisão' );
	$repo->apply( 'move-b-to-root', 3, $context );
	$deny( static fn() => $repo->apply( 'move-b-to-a', 3, $context ) );
	$repo->apply( 'deactivate-b', 4, $context );
	$assert( 5 === $repo->snapshot()->revision && 5 === count( $repo->history() ), 'desactivação auditada' );
	$deny( static fn() => $repo->apply( 'deactivate-b', 5, $context ) );
	$deny( static fn() => WpdbLocalUnitRepository::revision( '00' ) );
	$deny( static fn() => new LocalUnitContext( $actor, '', time() ) );
	$user = wp_insert_user(
		array(
			'user_login' => $prefix . 'denied',
			'user_pass'  => wp_generate_password( 32 ),
			'role'       => 'subscriber',
		)
	);
	$assert( is_int( $user ), 'conta exclusiva' );
	$users[] = $user;
	wp_set_current_user( $user );
	$assert( ! LocalUnitInstaller::allowed(), 'actor comum recusado' );
	$deny( static fn() => LocalUnitInstaller::repository() );
	wp_set_current_user( 0 );
	$assert( ! LocalUnitInstaller::allowed(), 'anónimo recusado' );
	wp_set_current_user( $actor );
	$db->query( $db->prepare( 'ALTER TABLE %i ADD unexpected int NULL', $prefix . 'arn_org_units' ) );
	$deny( static fn() => $repo->snapshot() );
	$deny( static fn() => $repo->installSchema() );
	$deny( static fn() => $repo->apply( 'rename-a', 5, $context ) );
	$db->query( $db->prepare( 'ALTER TABLE %i DROP COLUMN unexpected', $prefix . 'arn_org_units' ) );
	$db->query( $db->prepare( 'UPDATE %i SET revision = 0 WHERE id = 1', $prefix . 'arn_org_header' ) );
	$deny( static fn() => $repo->snapshot() );
	$db->query( $db->prepare( 'UPDATE %i SET revision = 5 WHERE id = 1', $prefix . 'arn_org_header' ) );
	$db->query( $db->prepare( 'DELETE FROM %i', $prefix . 'arn_org_units' ) );
	$deny( static fn() => $repo->snapshot() );
	$db->query( $db->prepare( 'DROP TABLE %i', $prefix . 'arn_org_audit' ) );
	$deny( static fn() => $repo->installSchema() );
} finally {
	if ( null !== $failure ) {
		remove_filter( 'query', $failure ); }
	if ( null !== $other ) {
		$other->query( 'ROLLBACK' );
		$other->close(); }
	wp_set_current_user( $actor );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( $users as $fixture_user_id ) {
		wp_delete_user( $fixture_user_id ); }
	foreach ( array( 'audit', 'units', 'header' ) as $suffix ) {
		$db->query( $db->prepare( 'DROP TABLE IF EXISTS %i', $prefix . 'arn_org_' . $suffix ) ); }
	$db->close();
}
WP_CLI::success( 'Unidades locais: ' . $checks . ' verificações.' );
