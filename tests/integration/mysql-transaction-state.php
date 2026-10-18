<?php
/**
 * Regressões reais das fronteiras transaccionais no MySQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
// phpcs:disable WordPress.PHP.DevelopmentFunctions, WordPress.PHP.NoSilencedErrors.Discouraged
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
// phpcs:disable WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals

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

$db       = arn_tx_connect();
$observer = arn_tx_connect_root();
$prefix   = 'arn_tx2_' . bin2hex( random_bytes( 5 ) ) . '_';
$table    = $prefix . 'probe';
$barrier  = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arn_tx2_' . bin2hex( random_bytes( 6 ) );

try {
	$db->set_prefix( $prefix );
	$assert(
		false === $db->check_connection( true ) && false === $db->check_connection( false ),
		'ausência de reconnect'
	);
	$assert( ! $db->inTransaction(), 'sessão inicial ociosa' );
	$assert( false === isset( $db->dbh ), 'handle mysqli não exposto' );
	$deny( static fn() => $db->dbh, 'leitura directa do handle recusada' );
	$deny(
		static function () use ( $db ): void {
			unset( $db->dbh );
		},
		'remoção directa do handle recusada'
	);
	$deny( static fn() => $db->db_connect( false ), 'reconexão manual recusada' );
	$deny( static fn() => $db->set_sql_mode(), 'mudança directa de sql_mode recusada' );
	$deny( static fn() => $db->set_charset( null ), 'mudança directa de charset recusada' );
	$deny( static fn() => $db->select( 'outra' ), 'mudança directa de base recusada' );
	$assert(
		! str_contains(
			strtoupper( (string) $db->get_var( 'SELECT @@SESSION.sql_mode' ) ),
			'NO_BACKSLASH_ESCAPES'
		),
		'sql_mode normalizado'
	);
	$assert( false === $db->query( 'CALL unsafe_procedure()' ), 'CALL recusado fora da transacção' );
	$assert( false === $db->query( "PREPARE stmt FROM 'START TRANSACTION'" ), 'PREPARE recusado fora da transacção' );

	$assert(
		true === $db->query(
			'CREATE TABLE `' . $table . '` (id INT PRIMARY KEY, v INT NOT NULL) ENGINE=InnoDB'
		),
		'tabela exclusiva criada'
	);
	$assert( 1 === $db->query( 'INSERT INTO `' . $table . '` (id, v) VALUES (1, 0)' ), 'linha exclusiva criada' );

	foreach (
		array(
			'SET autocommit=1'                           => 'autocommit redundante recusado',
			'COMMIT AND CHAIN'                           => 'chain recusado',
			'CREATE TEMPORARY TABLE unsafe_tmp (id INT)' => 'DDL temporário recusado',
			'/*!80000 CREATE TABLE unsafe_exec (id INT) */' => 'comentário executável recusado',
		) as $unsafe => $label
	) {
		$assert( 0 === $db->query( 'START TRANSACTION' ), 'START para ' . $label );
		$assert( 1 === $db->query( 'UPDATE `' . $table . '` SET v = v + 1 WHERE id = 1' ), 'sentinela para ' . $label );
		$assert( false === $db->query( $unsafe ), $label );
		$assert( $db->inTransaction(), $label . ' preserva estado controlado' );
		$assert( 0 === $db->query( 'ROLLBACK' ), 'rollback de ' . $label );
		$assert(
			'0' === (string) $db->get_var( 'SELECT v FROM `' . $table . '` WHERE id = 1' ),
			$label . ' não confirma sentinela'
		);
	}

	$assert( 0 === $db->query( 'START TRANSACTION' ), 'START antes de nested' );
	$assert( 1 === $db->query( 'UPDATE `' . $table . '` SET v = 4 WHERE id = 1' ), 'sentinela nested' );
	$assert( false === $db->query( 'START TRANSACTION' ), 'START aninhado recusado' );
	$assert( $db->inTransaction(), 'START aninhado conserva a transacção exterior' );
	$assert( 0 === $db->query( 'ROLLBACK' ), 'rollback nested' );
	$assert(
		'0' === (string) $db->get_var( 'SELECT v FROM `' . $table . '` WHERE id = 1' ),
		'nested não confirma dados'
	);

	$mutator = static fn( $sql ) => str_starts_with(
		(string) $sql,
		'UPDATE `' . $table . '`'
	) ? 'CREATE TABLE escaped_filter (id INT)' : $sql;
	$assert( 0 === $db->query( 'START TRANSACTION' ), 'START antes do filtro' );
	add_filter( 'query', $mutator, 10, 1 );
	$assert(
		false === $db->query( 'UPDATE `' . $table . '` SET v = 8 WHERE id = 1' ),
		'filtro mutante recusado antes do motor'
	);
	remove_filter( 'query', $mutator, 10 );
	$assert( $db->inTransaction(), 'recusa pré-execução conserva a transacção' );
	$assert( 0 === $db->query( 'ROLLBACK' ), 'rollback depois do filtro' );
	$assert(
		'0' === (string) $db->get_var(
			'SELECT COUNT(*) FROM information_schema.TABLES ' .
			"WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'escaped_filter'"
		),
		'filtro não criou DDL'
	);

	$assert( 0 === $db->query( 'START TRANSACTION' ), 'START antes do erro DML' );
	$assert(
		false === $db->query( 'UPDATE `' . $table . '` SET coluna_inexistente = 1 WHERE id = 1' ),
		'erro DML observado'
	);
	$deny( static fn() => $db->inTransaction(), 'erro DML exige rollback' );
	$assert( false === $db->query( 'COMMIT' ), 'commit recusado depois de erro' );
	$assert( 0 === $db->query( 'ROLLBACK' ), 'rollback recupera o estado' );
	$assert( ! $db->inTransaction(), 'estado ocioso depois do rollback' );

	$assert( 0 === $db->query( 'START TRANSACTION' ), 'START antes do commit' );
	$assert( 1 === $db->query( 'UPDATE `' . $table . '` SET v = 1 WHERE id = 1' ), 'escrita confirmável' );
	$assert( 0 === $db->query( 'COMMIT' ), 'commit canónico' );
	$assert( ! $db->inTransaction(), 'commit não inicia chain' );
	$assert( '1' === (string) $db->get_var( 'SELECT v FROM `' . $table . '` WHERE id = 1' ), 'commit preserva a escrita' );

	$php       = PHP_BINARY;
	$core      = $argv[1];
	$worker    = __DIR__ . DIRECTORY_SEPARATOR . 'mysql-transaction-worker.php';
	$env       = array_merge(
		is_array( getenv() ) ? getenv() : $_ENV,
		array(
			'ARN_TX_TEST_USER'      => (string) getenv( 'ARN_TX_TEST_USER' ),
			'ARN_TX_TEST_PASSWORD'  => (string) getenv( 'ARN_TX_TEST_PASSWORD' ),
			'ARN_TX_TEST_NAME'      => (string) getenv( 'ARN_TX_TEST_NAME' ),
			'ARN_TX_TEST_HOST'      => (string) getenv( 'ARN_TX_TEST_HOST' ),
			'ARN_TX_WPDB'           => $core,
			'ARN_TX_WORKER_TABLE'   => $table,
			'ARN_TX_WORKER_BARRIER' => $barrier,
		)
	);
	$spawn     = static function ( string $role ) use ( $php, $worker, $core, $env ): array {
		$process = proc_open(
			array( $php, $worker, $core ),
			array(
				0 => array( 'pipe', 'r' ),
				1 => array( 'pipe', 'w' ),
				2 => array( 'pipe', 'w' ),
			),
			$pipes,
			null,
			array_merge( $env, array( 'ARN_TX_WORKER_ROLE' => $role ) )
		);
		if ( ! is_resource( $process ) ) {
			throw new RuntimeException( 'Não foi possível abrir o worker ' . $role . '.' );
		}
		fclose( $pipes[0] );
		return array( $process, $pipes );
	};
	$wait_file = static function ( string $path, float $seconds = 20.0 ): void {
		$deadline = microtime( true ) + $seconds;
		while ( ! is_file( $path ) ) {
			if ( microtime( true ) > $deadline ) {
				throw new RuntimeException( 'Timeout na barreira ' . basename( $path ) . '.' );
			}
			usleep( 25000 );
		}
	};
	$finish    = static function ( array $running ): array {
		[ $process, $pipes ] = $running;
		$stdout              = stream_get_contents( $pipes[1] );
		$stderr              = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$exit = proc_close( $process );
		return array( $exit, $stdout, $stderr );
	};

	$holder = $spawn( 'holder' );
	$wait_file( $barrier . '.locked' );
	$waiter = $spawn( 'waiter' );
	$wait_file( $barrier . '.waiter-attempting' );

	$lock_sql      = $observer->prepare(
		'SELECT COUNT(*) FROM performance_schema.data_lock_waits AS waits '
		. 'INNER JOIN performance_schema.data_locks AS requested '
		. 'ON requested.ENGINE_LOCK_ID = waits.REQUESTING_ENGINE_LOCK_ID '
		. 'WHERE requested.OBJECT_SCHEMA = DATABASE() AND requested.OBJECT_NAME = %s',
		$table
	);
	$lock_deadline = microtime( true ) + 20.0;
	$lock_count    = 0;
	while ( microtime( true ) <= $lock_deadline ) {
		$lock_count = (int) $observer->get_var( $lock_sql );
		if ( $lock_count > 0 ) {
			break;
		}
		usleep( 25000 );
	}
	$assert( $lock_count > 0, 'performance_schema confirma espera pelo lock real' );
	$assert( ! is_file( $barrier . '.waiter-updated' ), 'waiter continua bloqueado antes da libertação' );
	file_put_contents( $barrier . '.overlap-observed', '1', LOCK_EX );
	file_put_contents( $barrier . '.release', '1', LOCK_EX );
	[ $holder_exit, , $holder_error ] = $finish( $holder );
	[ $waiter_exit, , $waiter_error ] = $finish( $waiter );
	$assert(
		0 === $holder_exit && 0 === $waiter_exit,
		'dois processos terminaram: ' . trim( $holder_error . ' ' . $waiter_error )
	);
	$assert( 'committed' === (string) file_get_contents( $barrier . '.holder.result' ), 'holder confirmou' );
	$assert( 'committed' === (string) file_get_contents( $barrier . '.waiter.result' ), 'waiter confirmou' );
	$assert(
		'12' === (string) $db->get_var( 'SELECT v FROM `' . $table . '` WHERE id = 1' ),
		'escritores sobrepostos somam 1 e 10 à base 1'
	);

	echo 'PASS: ' . $checks . ' verificações, motor=' . (string) $db->get_var( 'SELECT VERSION()' ) . PHP_EOL;
} finally {
	try {
		$db->query( 'ROLLBACK' );
	} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Limpeza após perda deliberada da ligação.
		// A ligação pode já estar indisponível durante a limpeza.
	}
	$barrier_files = glob( $barrier . '*' );
	foreach ( is_array( $barrier_files ) ? $barrier_files : array() as $file ) {
		@unlink( $file );
	}
	$db->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	$db->query( 'DROP TABLE IF EXISTS escaped_filter' );
	$observer->close();
	$db->close();
}
