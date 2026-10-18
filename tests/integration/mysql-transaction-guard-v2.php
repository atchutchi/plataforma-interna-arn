<?php
/**
 * Regressões da guarda transaccional portátil em MySQL real.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, WordPress.DB.RestrictedFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/mysql-transaction-bootstrap.php';

$checks = 0;
$assert = static function ( bool $condition, string $name ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou guarda transaccional: ' . $name );
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

$db       = arn_tx_connect();
$suffix   = bin2hex( random_bytes( 4 ) );
$base     = 'arn_guard_' . $suffix;
$ddl      = 'arn_guard_ddl_' . $suffix;
$comment  = 'arn_guard_comment_' . $suffix;
$direct   = 'arn_guard_direct_' . $suffix;
$temp     = 'arn_guard_temp_' . $suffix;
$tables   = array( $base, $ddl, $comment, $direct );
$clean_id = static fn( int $id ): string => 'DELETE FROM `' . $base . '` WHERE id = ' . $id;

try {
	$assert( method_exists( $db, 'inTransaction' ), 'API inTransaction existe' );
	$assert( ! $db->inTransaction(), 'sessão inicial fora de transacção' );
	$assert( false !== $db->query( 'CREATE TABLE `' . $base . '` (id INT PRIMARY KEY, value_int INT NOT NULL) ENGINE=InnoDB' ), 'tabela exclusiva criada' );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START aceite' );
	$assert( $db->inTransaction(), 'START observado' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (1, 1)' ), 'sentinela nested criada' );
	$assert( false === $db->query( 'START TRANSACTION' ), 'START aninhado recusado antes do servidor' );
	$assert( $db->inTransaction(), 'recusa nested conserva transacção' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK nested aceite' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 1' ), 'nested não confirmou sentinela' );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de DDL' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (2, 2)' ), 'sentinela DDL criada' );
	$assert( false === $db->query( 'CREATE TABLE `' . $ddl . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'DDL recusado dentro da transacção' );
	$assert( $db->inTransaction(), 'DDL recusado conserva guarda' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK depois de DDL recusado' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 2' ), 'DDL recusado não confirmou sentinela' );
	$assert( null === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $ddl ) ), 'DDL recusado não criou tabela' );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de comentário executável' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (3, 3)' ), 'sentinela comentário criada' );
	$assert( false === $db->query( '/*!40101 CREATE TABLE `' . $comment . '` (id INT PRIMARY KEY) ENGINE=InnoDB */' ), 'DDL em comentário executável recusado' );
	$assert( $db->inTransaction(), 'comentário recusado conserva guarda' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK comentário' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 3' ), 'comentário não confirmou sentinela' );
	$assert( null === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $comment ) ), 'comentário não criou tabela' );

	$assert( false === $db->query( 'SET autocommit=0' ), 'autocommit off recusado fora de transacção' );
	$assert( '1' === (string) $db->get_var( 'SELECT @@session.autocommit' ), 'autocommit permanece activo' );
	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de autocommit redundante' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (4, 4)' ), 'sentinela autocommit criada' );
	$assert( false === $db->query( 'SET autocommit=1' ), 'autocommit redundante recusado na transacção' );
	$assert( $db->inTransaction(), 'autocommit recusado conserva guarda' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK autocommit' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 4' ), 'autocommit não confirmou sentinela' );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de temporária' );
	$assert( false !== $db->query( 'CREATE TEMPORARY TABLE `' . $temp . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'tabela temporária permitida' );
	$assert( $db->inTransaction(), 'tabela temporária conserva transacção' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK temporária' );
	$assert( false !== $db->query( 'DROP TEMPORARY TABLE IF EXISTS `' . $temp . '`' ), 'temporária removida' );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de COMMIT AND CHAIN' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (5, 5)' ), 'primeira sentinela chain' );
	$assert( false !== $db->query( 'COMMIT AND CHAIN' ), 'COMMIT AND CHAIN aceite' );
	$assert( $db->inTransaction(), 'COMMIT AND CHAIN instala nova guarda' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (6, 6)' ), 'segunda sentinela chain' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK segunda transacção chain' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 5' ), 'primeira chain confirmada' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 6' ), 'segunda chain revertida' );
	$db->query( $clean_id( 5 ) );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de ROLLBACK AND CHAIN' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (7, 7)' ), 'primeira sentinela rollback chain' );
	$assert( false !== $db->query( 'ROLLBACK AND CHAIN' ), 'ROLLBACK AND CHAIN aceite' );
	$assert( $db->inTransaction(), 'ROLLBACK AND CHAIN instala nova guarda' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (8, 8)' ), 'segunda sentinela rollback chain' );
	$assert( false !== $db->query( 'COMMIT' ), 'COMMIT segunda transacção rollback chain' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 7' ), 'primeira rollback chain revertida' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 8' ), 'segunda rollback chain confirmada' );
	$db->query( $clean_id( 8 ) );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de DDL directo' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (9, 9)' ), 'sentinela DDL directo criada' );
	$assert( $db->dbh instanceof mysqli && true === $db->dbh->query( 'CREATE TABLE `' . $direct . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'DDL directo executado para provar detecção' );
	$deny( static fn() => $db->inTransaction(), 'guarda desaparecida por DDL directo é recusada' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 9' ), 'sentinela prova commit implícito directo' );
	$db->query( $clean_id( 9 ) );

	$assert( $db->dbh instanceof mysqli && true === $db->dbh->query( 'START TRANSACTION' ), 'transacção externa criada' );
	$deny( static fn() => $db->inTransaction(), 'transacção externa não é adoptada' );
	$db->dbh->query( 'ROLLBACK' );
	$assert( ! $db->inTransaction(), 'sessão recupera ausência depois do rollback externo' );

	echo 'PASS: guarda transaccional V2, ' . $checks . ' verificações, motor=' . (string) $db->get_var( 'SELECT VERSION()' ) . PHP_EOL;
} finally {
	if ( $db->dbh instanceof mysqli ) {
		$db->dbh->query( 'ROLLBACK' );
		$db->dbh->query( 'SET autocommit=1' );
	}
	$db->query( 'DROP TEMPORARY TABLE IF EXISTS `' . $temp . '`' );
	foreach ( array_reverse( $tables ) as $table ) {
		$db->query( 'DROP TABLE IF EXISTS `' . $table . '`' );
	}
	$db->close();
}
