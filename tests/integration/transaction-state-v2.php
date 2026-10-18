<?php
/**
 * Prova RED/GREEN do estado transaccional real em MySQL e MariaDB.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, WordPress.DB.RestrictedFunctions, WordPress.Security.EscapeOutput, WordPress.WP.GlobalVariablesOverride, Squiz.Commenting, Generic.Formatting.MultipleStatementAlignment

require __DIR__ . '/transaction-state-v2-bootstrap.php';

$checks = 0;
$assert = static function ( bool $condition, string $name ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou transaction-state-v2: ' . $name );
	}
	++$checks;
};
$deny = static function ( callable $operation, string $name ) use ( $assert ): void {
	try {
		$operation();
	} catch ( Throwable ) {
		$assert( true, $name );
		return;
	}
	$assert( false, $name );
};

$db     = arn_tx_v2_connect();
$prefix = 'arn_txv2_' . bin2hex( random_bytes( 4 ) ) . '_';
$probe  = $prefix . 'probe';
$ddl    = $prefix . 'ddl';
$exec   = $prefix . 'exec';
$direct = $prefix . 'direct';
$tables = array( $probe, $ddl, $exec, $direct );

try {
	$assert( method_exists( $db, 'inTransaction' ), 'contrato IdentityWpdb::inTransaction existe' );
	$assert( false === $db->check_connection( true ) && false === $db->check_connection( false ), 'reconnect continua recusado' );
	$assert( false === $db->inTransaction(), 'sessão inicial sem transacção' );

	$assert( false !== $db->query( 'CREATE TABLE `' . $probe . '` (id INT PRIMARY KEY, value_int INT NOT NULL) ENGINE=InnoDB' ), 'tabela de prova criada' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START abre transacção' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (1, 10)' ) && $db->inTransaction(), 'DML conserva transacção' );
	$assert( false !== $db->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'ROLLBACK fecha transacção' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 1' ), 'ROLLBACK reverte DML' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START para COMMIT' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (2, 20)' ), 'DML antes de COMMIT' );
	$assert( false !== $db->query( 'COMMIT' ) && ! $db->inTransaction(), 'COMMIT fecha transacção' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 2' ), 'COMMIT confirma DML' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START antes de COMMIT AND CHAIN' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (3, 30)' ), 'DML antes de CHAIN' );
	$assert( false !== $db->query( 'COMMIT AND CHAIN' ) && $db->inTransaction(), 'COMMIT AND CHAIN mantém nova transacção' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (4, 40)' ), 'DML na transacção encadeada' );
	$assert( false !== $db->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'ROLLBACK fecha CHAIN' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 3' ), 'primeira parte do CHAIN confirmada' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 4' ), 'segunda parte do CHAIN revertida' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START antes de ROLLBACK AND CHAIN' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (5, 50)' ), 'DML antes de rollback encadeado' );
	$assert( false !== $db->query( 'ROLLBACK AND CHAIN' ) && $db->inTransaction(), 'ROLLBACK AND CHAIN mantém nova transacção' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (6, 60)' ), 'DML depois do rollback encadeado' );
	$assert( false !== $db->query( 'COMMIT' ) && ! $db->inTransaction(), 'COMMIT fecha rollback encadeado' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 5' ), 'primeira parte do rollback encadeado revertida' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 6' ), 'segunda parte do rollback encadeado confirmada' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START antes da tabela temporária' );
	$assert( false !== $db->query( 'CREATE TEMPORARY TABLE `' . $prefix . 'temporary` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'tabela temporária criada' );
	$assert( $db->inTransaction(), 'CREATE TEMPORARY TABLE não confirma a transacção' );
	$assert( false !== $db->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'rollback depois da tabela temporária' );
	$db->query( 'DROP TEMPORARY TABLE IF EXISTS `' . $prefix . 'temporary`' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START antes de DDL' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (7, 70)' ), 'sentinela antes de DDL' );
	$assert( false !== $db->query( 'CREATE TABLE `' . $ddl . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'DDL executado' );
	$assert( ! $db->inTransaction(), 'DDL reflecte commit implícito' );
	$db->query( 'ROLLBACK' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 7' ), 'sentinela DDL permaneceu confirmada' );

	$assert( false !== $db->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START antes de comentário executável' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (8, 80)' ), 'sentinela antes do comentário executável' );
	$assert( false !== $db->query( '/*!50000 CREATE TABLE `' . $exec . '` (id INT PRIMARY KEY) ENGINE=InnoDB */' ), 'comentário executável DDL' );
	$assert( ! $db->inTransaction(), 'comentário executável reflecte commit implícito' );
	$db->query( 'ROLLBACK' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 8' ), 'sentinela do comentário permaneceu confirmada' );

	$assert( false !== $db->query( 'SET autocommit=0' ) && ! $db->inTransaction(), 'autocommit=0 sem DML não inventa transacção' );
	$assert( false !== $db->query( 'INSERT INTO `' . $probe . '` VALUES (9, 90)' ) && $db->inTransaction(), 'DML com autocommit=0 abre transacção' );
	$assert( false !== $db->query( 'COMMIT' ) && ! $db->inTransaction(), 'COMMIT com autocommit=0 fecha a transacção corrente' );
	$assert( false !== $db->query( 'SET autocommit=1' ) && ! $db->inTransaction(), 'autocommit restaurado' );

	$handle = arn_tx_v2_handle( $db );
	$assert( true === $handle->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START directo no mysqli é observado' );
	$assert( true === $handle->query( 'ROLLBACK' ) && ! $db->inTransaction(), 'ROLLBACK directo no mysqli é observado' );

	$assert( true === $handle->query( 'START TRANSACTION' ) && $db->inTransaction(), 'START directo antes de DDL directo' );
	$assert( true === $handle->query( 'INSERT INTO `' . $probe . '` VALUES (10, 100)' ), 'sentinela directa' );
	$assert( true === $handle->query( 'CREATE TABLE `' . $direct . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'DDL directo no mysqli' );
	$assert( ! $db->inTransaction(), 'DDL directo reflecte commit implícito' );
	$handle->query( 'ROLLBACK' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $probe . '` WHERE id = 10' ), 'sentinela directa permaneceu confirmada' );

	$closed = arn_tx_v2_connect();
	$closed->close();
	$deny( static fn() => $closed->inTransaction(), 'ligação principal fechada recusa' );

	echo 'PASS: transaction-state-v2, ' . $checks . ' verificações, motor=' . (string) $db->get_var( 'SELECT VERSION()' ) . PHP_EOL;
} finally {
	foreach ( array_reverse( $tables ) as $table ) {
		$db->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	}
	$db->close();
}
