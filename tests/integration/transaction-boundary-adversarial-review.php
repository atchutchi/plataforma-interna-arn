<?php
/** Reprodutor de revisão. Código candidato por argumento, só SQL sintético local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals, Squiz.Commenting

if ( 'cli' !== PHP_SAPI || '1' !== getenv( 'ARN_TX_ADVERSARIAL_REVIEW' ) || 1 !== preg_match( '/\A(?:127\.0\.0\.1|localhost)(?::[0-9]{1,5})?\z/D', (string) getenv( 'ARN_TX_TEST_HOST' ) ) ) {
	throw new RuntimeException( 'Reprodutor exclusivo do laboratório local, com activação explícita.' );
}
$candidate = isset( $argv[2] ) && is_string( $argv[2] ) ? realpath( $argv[2] ) : false;
if ( false === $candidate || ! is_file( $candidate . '/tests/integration/mysql-transaction-bootstrap.php' ) ) {
	throw new RuntimeException( 'Indica o checkout candidato com o bootstrap transaccional.' );
}
require $candidate . '/tests/integration/mysql-transaction-bootstrap.php';
mysqli_report( MYSQLI_REPORT_OFF ); // phpcs:ignore WordPress.DB.RestrictedFunctions -- Só afecta este processo efémero.

$prefix      = 'arn_adv_' . bin2hex( random_bytes( 6 ) );
$table       = $prefix . '_data';
$cases       = array(
	'control_plain_ddl'          => static fn( $db, $ddl ) => $db->query( 'CREATE TABLE `' . $ddl . '` (id INT) ENGINE=InnoDB' ),
	'control_plain_autocommit'   => static fn( $db ) => $db->query( 'SET autocommit=0' ),
	'inline_nested_start'        => static fn( $db ) => $db->query( 'START TRANSACTION# inline' ),
	'inline_analyze'             => static fn( $db ) => $db->query( "ANALYZE# inline\nTABLE `" . $table . '`' ),
	'ignored_version_comment'    => static fn( $db, $ddl ) => $db->query( '/*!99999 SELECT */ CREATE TABLE `' . $ddl . '` (id INT) ENGINE=InnoDB' ),
	'quoted_autocommit'          => static fn( $db ) => $db->query( 'SET `autocommit`=0' ),
	'comment_markers_in_literal' => static fn( $db ) => $db->query( "SET @text='/*', autocommit=0, @text2='*/'" ),
	'query_filter_commit'        => static function ( $db ) {
		add_filter( 'query', static fn( $sql ) => 'SELECT 1' === $sql ? 'COMMIT' : $sql );
		try {
			return $db->query( 'SELECT 1' );
		} finally {
			$GLOBALS['arn_tx_filters']['query'] = array();
		}
	},
	'commit_failure_rollback'    => static function ( $db ) {
		add_filter( 'query', static fn( $sql ) => 'COMMIT' === $sql ? 'INVALID COMMIT SQL' : $sql );
		try {
			return $db->query( 'COMMIT' );
		} finally {
			$GLOBALS['arn_tx_filters']['query'] = array();
		}
	},
);
$owner       = arn_tx_connect();
$read_scalar = static function ( mysqli $connection, string $sql ): string {
	$result = $connection->query( $sql );
	if ( ! $result instanceof mysqli_result || 0 !== $connection->errno || 1 !== $result->num_rows ) {
		throw new RuntimeException( 'Observação SQL indisponível.' );
	}
	$row = $result->fetch_row();
	$result->free();
	if ( ! is_array( $row ) || 1 !== count( $row ) || ! is_string( $row[0] ) ) {
		throw new RuntimeException( 'Resultado de observação inválido.' );
	}
	return $row[0];
};
$read_count  = static function ( string $sql ) use ( $owner, $read_scalar ): int {
	$value = $read_scalar( $owner->dbh, $sql );
	if ( 1 !== preg_match( '/\A[0-9]+\z/D', $value ) ) {
		throw new RuntimeException( 'Contagem de observação inválida.' );
	}
	return (int) $value;
};
$engine      = $read_scalar( $owner->dbh, 'SELECT VERSION()' );
if ( str_contains( $engine, 'MariaDB' ) ) {
	$cases['set_statement_ddl'] = static fn( $db, $ddl ) => $db->query( 'SET STATEMENT max_statement_time=1 FOR CREATE TABLE `' . $ddl . '` (id INT) ENGINE=InnoDB' );
	$cases['compound_if_ddl']   = static fn( $db, $ddl ) => $db->query( 'IF 1 THEN CREATE TABLE `' . $ddl . '` (id INT) ENGINE=InnoDB; END IF' );
}
$tables = array( $table );
foreach ( array_keys( $cases ) as $index => $unused ) {
	$tables[] = $prefix . '_ddl' . $index;
}
$failures = 0;
$cleanup  = false;
try {
	if ( false === $owner->query( 'CREATE TABLE `' . $table . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ) ) {
		throw new RuntimeException( 'Preparação exclusiva indisponível.' );
	}
	echo json_encode(
		array(
			'engine'         => $engine,
			'synthetic_only' => true,
		),
		JSON_THROW_ON_ERROR
	) . PHP_EOL;
	$index = 0;
	foreach ( $cases as $name => $callback ) {
		$ddl         = $prefix . '_ddl' . $index;
		$sentinel_id = ++$index;
		$db          = arn_tx_connect();
		try {
			if ( false === $db->query( 'START TRANSACTION' ) || ! $db->inTransaction() || false === $db->query( 'INSERT INTO `' . $table . '` VALUES (' . $sentinel_id . ')' ) ) {
				throw new RuntimeException( 'Preparação da sentinela recusada.' );
			}
			$accepted      = false !== $callback( $db, $ddl );
			$rolled_back   = false !== $db->query( 'ROLLBACK' );
			$probe         = $db->dbh->query( 'SET TRANSACTION READ ONLY' );
			$server_active = true === $probe ? false : ( 1568 === $db->dbh->errno ? true : null );
			$autocommit    = (int) $read_scalar( $db->dbh, 'SELECT @@session.autocommit' );
			$db->dbh->query( 'ROLLBACK' );
			$persisted = 1 === $read_count( 'SELECT COUNT(*) FROM `' . $table . '` WHERE id=' . $sentinel_id );
			$created   = 0 < $read_count( "SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $ddl . "'" );
			$passed    = ! $accepted && $rolled_back && false === $server_active && 1 === $autocommit && ! $persisted && ! $created;
			$failures += $passed ? 0 : 1;
			echo json_encode(
				array(
					'case'                         => $name,
					'passed'                       => $passed,
					'query_accepted'               => $accepted,
					'wrapper_rollback_accepted'    => $rolled_back,
					'server_active_after_rollback' => $server_active,
					'autocommit'                   => $autocommit,
					'sentinel_persisted'           => $persisted,
					'ddl_created'                  => $created,
				),
				JSON_THROW_ON_ERROR
			) . PHP_EOL;
		} finally {
			$GLOBALS['arn_tx_filters']['query'] = array();
			if ( $db->dbh instanceof mysqli ) {
				$db->dbh->query( 'ROLLBACK' );
				$db->dbh->query( 'SET autocommit=1' );
			}
			$db->close();
		}
	}
	$db = arn_tx_connect();
	try {
		if ( false === $db->query( 'START TRANSACTION' ) || '8124' !== (string) $db->get_var( 'SELECT 8124' ) ) {
			throw new RuntimeException( 'Controlo da leitura recusado.' );
		}
		$stale     = null !== $db->get_var( 'SAVEPOINT external_caller' );
		$refused   = '' !== $db->last_error;
		$owned     = $db->inTransaction();
		$passed    = $refused && ! $stale && $owned;
		$failures += $passed ? 0 : 1;
		echo json_encode(
			array(
				'case'                  => 'stale_get_var_after_refusal',
				'passed'                => $passed,
				'query_refused'         => $refused,
				'owned_transaction'     => $owned,
				'stale_result_returned' => $stale,
			),
			JSON_THROW_ON_ERROR
		) . PHP_EOL;
	} finally {
		$db->dbh->query( 'ROLLBACK' );
		$db->close();
	}
} finally {
	foreach ( array_reverse( $tables ) as $owned ) {
		if ( ! str_starts_with( $owned, $prefix . '_' ) ) {
			throw new RuntimeException( 'Limpeza não exclusiva recusada.' );
		}
		$owner->dbh->query( 'DROP TABLE IF EXISTS `' . $owned . '`' );
	}
	$remaining = $read_count( 'SELECT COUNT(*) FROM information_schema.tables WHERE TABLE_SCHEMA=DATABASE() AND LEFT(TABLE_NAME,' . strlen( $prefix . '_' ) . ")='" . $prefix . "_'" );
	$cleanup   = 0 === $remaining;
	echo json_encode(
		array(
			'cases'             => count( $cases ) + 1,
			'failures'          => $failures,
			'cleanup_remaining' => $remaining,
		),
		JSON_THROW_ON_ERROR
	) . PHP_EOL;
	$owner->close();
}
exit( 0 === $failures && $cleanup ? 0 : 1 );
