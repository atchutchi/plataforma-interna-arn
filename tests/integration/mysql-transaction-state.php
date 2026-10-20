<?php
/**
 * Fronteiras transaccionais da IdentityWpdb em MySQL/MariaDB real.
 * Recusa @@session.in_transaction e privilegio PROCESS.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, WordPress.DB.RestrictedFunctions, WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride, WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open, Universal.Operators.DisallowShortTernary, PEAR.Functions.FunctionCallSignature, Generic.Formatting.MultipleStatementAlignment

use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\PrivateFiles\WpdbFileRegistry;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;

require __DIR__ . '/mysql-transaction-bootstrap.php';

$checks = 0;
$assert = static function ( bool $condition, string $name ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou transacção: ' . $name );
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
$prefix = 'arn_tx_' . bin2hex( random_bytes( 4 ) ) . '_';
$db->set_prefix( $prefix );
$tables = array();
$php    = PHP_BINARY;
$core   = $argv[1];
$worker = __DIR__ . DIRECTORY_SEPARATOR . 'mysql-transaction-worker.php';

try {
	$assert( false === $db->check_connection( true ) && false === $db->check_connection( false ), 'ausência de reconnect' );
	$assert( ! $db->inTransaction(), 'sessão inicial fora de transacção' );

	$db->last_error = '';
	$legacy         = $db->get_var( 'SELECT @@session.in_transaction' );
	$assert( null === $legacy && '' !== $db->last_error && ! str_contains( strtolower( $db->last_error ), 'process' ), 'variável MariaDB recusada sem PROCESS' );
	$assert( ! $db->inTransaction(), 'introspecção continua utilizável após a variável ausente' );

	$assert( false === $db->query( 'START TRANSACTION xyzabc' ), 'START inválido recusado' );
	$assert( ! $db->inTransaction(), 'START falhado não abre transacção' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START via query()' );
	$assert( false !== $db->query( 'SAVEPOINT arn_tx_probe' ) && $db->inTransaction(), 'SAVEPOINT conserva transacção' );
	$assert( false !== $db->query( 'ROLLBACK TO SAVEPOINT arn_tx_probe' ) && $db->inTransaction(), 'ROLLBACK TO conserva transacção' );
	$assert( false !== $db->query( 'COMMIT' ) && ! $db->inTransaction(), 'COMMIT via query()' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'segunda transacção' );
	$assert( false !== $db->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'ROLLBACK via query()' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START antes de nested' );
	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START aninhado continua transacção' );
	$assert( false !== $db->query( 'COMMIT' ) && ! $db->inTransaction(), 'COMMIT após nested' );

	$assert( false !== $db->query( 'COMMIT' ) && ! $db->inTransaction(), 'COMMIT ocioso' );
	$assert( false !== $db->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'ROLLBACK ocioso' );

	$ddl = $prefix . 'probe';
	$tables[] = $ddl;
	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START antes de DDL' );
	$assert( false !== $db->query( 'CREATE TABLE `' . $ddl . '` (id INT PRIMARY KEY, v INT NOT NULL) ENGINE=InnoDB' ) && ! $db->inTransaction(), 'DDL com commit implícito' );

	$assert( false !== $db->query( 'SET autocommit=0' ), 'autocommit off' );
	$deny( static fn() => $db->inTransaction(), 'autocommit=0 sem START recusa' );
	$assert( false !== $db->query( 'SET autocommit=1' ) && ! $db->inTransaction(), 'autocommit restaurado' );

	if ( $db->dbh instanceof mysqli ) {
		$assert( $db->dbh->query( 'START TRANSACTION' ), 'START externo' );
		$deny( static fn() => $db->inTransaction(), 'transacção externa recusada' );
		$db->dbh->query( 'ROLLBACK' );
	}

	$closed = arn_tx_connect();
	$closed->close();
	$deny( static fn() => $closed->inTransaction(), 'ligação fechada recusa' );

	$db = arn_tx_connect();
	$db->set_prefix( $prefix );
	$db->suppress_errors( true );
	$assert( false !== $db->query( 'INSERT INTO `' . $ddl . '` (id, v) VALUES (1, 0)' ), 'linha de concorrência' );

	$auth = new WpdbAuthorizationRepository( $db );
	$auth->installSchema();
	foreach ( array( 'accounts', 'grants', 'audit', 'schema' ) as $suffix ) {
		$tables[] = $prefix . 'arn_authz_' . $suffix;
	}
	$deny( static fn() => $auth->findInTransaction( 1 ), 'findInTransaction sem transacção' );
	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'transacção para autorização' );
	$assert( null === $auth->findInTransaction( 1 ), 'findInTransaction na transacção exterior' );
	$assert( $db->inTransaction(), 'leitura conserva transacção' );
	$assert( false !== $db->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'rollback autorização' );

	$units = new WpdbLocalUnitRepository( $db );
	$units->installSchema();
	foreach ( array( 'header', 'units', 'audit' ) as $suffix ) {
		$tables[] = $prefix . 'arn_org_' . $suffix;
	}
	$deny( static fn() => $units->snapshotInTransaction(), 'snapshotInTransaction sem transacção' );

	$files = new WpdbFileRegistry( $db );
	try {
		$files->registerInTransaction( new \Arn\Intranet\Documents\PrivateFiles\FileRecord( new \Arn\Intranet\Documents\PrivateFiles\BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'image/png' ), 'document', 1, 1, 'original', 1, 1 ) );
		$assert( false, 'registo sem transacção deveria recusar' );
	} catch ( Throwable ) {
		$assert( true, 'registo sem transacção recusa' );
	}

	$barrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_tx_' . bin2hex( random_bytes( 6 ) );
	$inherited = getenv();
	if ( ! is_array( $inherited ) ) {
		$inherited = $_ENV;
	}
	$env     = array_merge( $inherited, array(
		'ARN_TX_TEST_USER'      => (string) getenv( 'ARN_TX_TEST_USER' ),
		'ARN_TX_TEST_PASSWORD'  => (string) getenv( 'ARN_TX_TEST_PASSWORD' ),
		'ARN_TX_TEST_NAME'      => (string) getenv( 'ARN_TX_TEST_NAME' ),
		'ARN_TX_TEST_HOST'      => (string) getenv( 'ARN_TX_TEST_HOST' ),
		'ARN_TX_WPDB'           => $core,
		'ARN_TX_WORKER_TABLE'   => $ddl,
		'ARN_TX_WORKER_BARRIER' => $barrier,
	) );
	$spawn   = static function ( string $role ) use ( $php, $worker, $core, $env ): array {
		$spec    = array(
			0 => array( 'pipe', 'r' ),
			1 => array( 'pipe', 'w' ),
			2 => array( 'pipe', 'w' ),
		);
		$process = proc_open(
			array( $php, $worker, $core ),
			$spec,
			$pipes,
			null,
			$env + array( 'ARN_TX_WORKER_ROLE' => $role )
		);
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'processo ' . $role );
		}
		fclose( $pipes[0] );
		stream_set_blocking( $pipes[1], false );
		stream_set_blocking( $pipes[2], false );
		return array( $process, $pipes );
	};
	$holder = $spawn( 'holder' );
	$deadline = microtime( true ) + 20;
	while ( ! is_file( $barrier . '.ready' ) ) {
		if ( microtime( true ) > $deadline ) {
			throw new RuntimeException( 'barreira holder' );
		}
		usleep( 50000 );
	}
	$waiter = $spawn( 'waiter' );
	while ( ! is_file( $barrier . '.waiter-started' ) ) {
		if ( microtime( true ) > $deadline ) {
			throw new RuntimeException( 'barreira waiter' );
		}
		usleep( 50000 );
	}
	file_put_contents( $barrier . '.release', 'go', LOCK_EX );
	foreach ( array( $holder, $waiter ) as $running ) {
		[ $process, $pipes ] = $running;
		$wait_until          = microtime( true ) + 20;
		while ( true ) {
			$status = proc_get_status( $process );
			if ( ! $status['running'] ) {
				break;
			}
			if ( microtime( true ) > $wait_until ) {
				proc_terminate( $process );
				throw new RuntimeException( 'worker timeout' );
			}
			usleep( 50000 );
		}
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );
	}
	$holder_result = is_file( $barrier . '.holder.result' ) ? (string) file_get_contents( $barrier . '.holder.result' ) : '';
	$waiter_result = is_file( $barrier . '.waiter.result' ) ? (string) file_get_contents( $barrier . '.waiter.result' ) : '';
	$assert( 'committed' === $holder_result && 'committed' === $waiter_result, 'dois processos reais commitiram em sobreposição' );
	$value = $db->get_var( 'SELECT v FROM `' . $ddl . '` WHERE id = 1' );
	$assert( '11' === (string) $value, 'dois escritores reais somaram 1+10' );
	foreach ( glob( $barrier . '*' ) ?: array() as $file ) {
		unlink( $file );
	}

	echo 'PASS: fronteiras transaccionais, ' . $checks . ' verificações, motor=' . (string) $db->get_var( 'SELECT VERSION()' ) . PHP_EOL;
} finally {
	foreach ( array_reverse( $tables ) as $table ) {
		$db->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	}
	$db->close();
}
