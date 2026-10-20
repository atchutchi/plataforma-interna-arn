<?php
/** Exclusive native-session CAS worker for the task fixture.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Tasks\{CurrentActor, LocalRoster, TaskRepository};

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_TASKS_SQL_HARNESS' ) || true !== ARN_TASKS_SQL_HARNESS || 'local' !== wp_get_environment_type() || ! current_user_can( 'manage_options' ) || 3 !== count( $args ) ) {
	throw new RuntimeException( 'Worker de tarefas recusado.' ); }
[$payload_path, $ready_path, $result_path] = $args;
$directory                                 = realpath( dirname( $payload_path ) );
if ( false === $directory || 1 !== preg_match( '/\Aarn_tasks_worker_[a-f0-9]{16}\z/D', basename( $directory ) ) || realpath( dirname( $ready_path ) ) !== $directory || realpath( dirname( $result_path ) ) !== $directory || is_link( $payload_path ) ) {
	throw new RuntimeException( 'Directório de worker recusado.' ); }
$database  = null;
$result    = array(
	'result' => 'technical_failure',
	'phase'  => 'payload',
);
$exit_code = 5;
try {
	$data = json_decode( file_get_contents( $payload_path ), true, 32, JSON_THROW_ON_ERROR );
	if ( ! is_array( $data ) || ! is_string( $data['prefix'] ?? null ) || ! LocalRoster::validPrefix( $data['prefix'] ) || ! is_array( $data['roster'] ?? null ) || ! is_int( $data['actor'] ?? null ) || ! is_string( $data['cookie'] ?? null ) || ! is_int( $data['now'] ?? null ) || ! is_int( $data['id'] ?? null ) || ! is_int( $data['expected'] ?? null ) ) {
		throw new RuntimeException( 'Payload de worker inválido.' ); }
	foreach ( array( 'ARN_ALLOW_LOCAL_TASKS', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
		if ( ! defined( $flag ) ) {
			define( $flag, true );
		} if ( true !== constant( $flag ) ) {
			throw new RuntimeException( 'Flag de worker inválida.' ); }
	}
	$result['phase'] = 'connection';
	$database        = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
	$database->set_prefix( $data['prefix'] );
	$database->suppress_errors( true );
	$roster = LocalRoster::normalize( $data['roster'], $data['prefix'] );
	if ( ! isset( $roster[ $data['actor'] ] ) ) {
		throw new RuntimeException( 'Actor do worker fora do elenco.' ); }
	wp_set_current_user( $data['actor'] );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $data['cookie'];
	$clock                       = static fn(): int => $data['now'];
	$actors                      = new CurrentActor( $database, $roster, $clock );
	$repo                        = new TaskRepository( $database, $actors, $clock );
	$connection                  = (int) $database->get_var( 'SELECT CONNECTION_ID()' );
	if ( $connection < 1 ) {
		throw new RuntimeException( 'Ligação de worker indisponível.' ); }
	if ( false === file_put_contents( $ready_path . '.tmp', json_encode( array( 'connection_id' => $connection ), JSON_THROW_ON_ERROR ), LOCK_EX ) || ! rename( $ready_path . '.tmp', $ready_path ) ) {
		throw new RuntimeException( 'Sinal de worker indisponível.' ); }
	$result['phase'] = 'change';
	try {
		$task   = $repo->change( $data['id'], 'start', $data['expected'], array( 'reason' => 'Escritor concorrente sintético.' ) );
		$result = array(
			'result'   => 'committed',
			'phase'    => 'finished',
			'revision' => $task->revision,
		);
	} catch ( DomainException ) {
		$result = array(
			'result' => 'conflict',
			'phase'  => 'finished',
		);
	}
	$exit_code = 0;
} catch ( Throwable $error ) {
	$result['exception_type'] = $error::class;
} finally {
	if ( $database instanceof IdentityWpdb ) {
		$database->query( 'ROLLBACK' );
		$database->close(); }
}
if ( false === file_put_contents( $result_path . '.tmp', json_encode( $result, JSON_THROW_ON_ERROR ), LOCK_EX ) || ! rename( $result_path . '.tmp', $result_path ) ) {
	WP_CLI::halt( 6 ); }
WP_CLI::halt( $exit_code );
