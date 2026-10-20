<?php
/**
 * Regressões dos achados da revisão independente da PR #41.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery, WordPress.DB.RestrictedFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals

require __DIR__ . '/mysql-transaction-bootstrap.php';

$checks = 0;
$assert = static function ( bool $condition, string $name ) use ( &$checks ): void {
	if ( ! $condition ) {
		throw new RuntimeException( 'Falhou regressão de revisão: ' . $name );
	}
	++$checks;
};

$db                  = arn_tx_connect();
$suffix              = bin2hex( random_bytes( 4 ) );
$base                = 'arn_review_' . $suffix;
$mariadb_table       = 'arn_review_maria_' . $suffix;
$mysql_comment_table = 'arn_review_mysqlc_' . $suffix;
$version             = (string) $db->get_var( 'SELECT VERSION()' );
$is_mariadb          = str_contains( strtoupper( $version ), 'MARIADB' );

$completion_is_no_chain = static function () use ( $db ): bool {
	$value = strtoupper( (string) $db->get_var( 'SELECT @@session.completion_type' ) );
	return in_array( $value, array( '0', 'NO_CHAIN' ), true );
};

$prove_set_refused = static function ( string $sql, string $label, int $sentinel ) use ( $db, $assert, $base, $completion_is_no_chain ): void {
	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes de ' . $label );
	$assert( $db->inTransaction(), 'guarda activa antes de ' . $label );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (' . $sentinel . ')' ), 'sentinela de ' . $label );
	$assert( $db->dbh instanceof mysqli && true === $db->dbh->query( 'SET @marcador=7' ), 'marcador conhecido antes de ' . $label );
	$assert( '7' === (string) $db->get_var( 'SELECT @marcador' ), 'marcador anterior a ' . $label . ' está 7' );
	$assert( false === $db->query( $sql ), $label . ' recusado antes do servidor' );
	$assert( '7' === (string) $db->get_var( 'SELECT @marcador' ), 'marcador anterior a ' . $label . ' inalterado' );
	$assert( '1' === (string) $db->get_var( 'SELECT @@session.autocommit' ), 'autocommit permanece 1 após ' . $label );
	$assert( $completion_is_no_chain(), 'completion_type permanece NO_CHAIN após ' . $label );
	$assert( $db->inTransaction(), 'transacção controlada continua após ' . $label );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK disponível após ' . $label );
	$assert( ! $db->inTransaction(), 'ROLLBACK após ' . $label . ' não deixa transacção externa' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = ' . $sentinel ), 'ROLLBACK reverte sentinela de ' . $label );
	$assert( false !== $db->query( 'START TRANSACTION' ), 'nova transacção após ' . $label . ' sem lock abandonado' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (' . $sentinel . ')' ), 'mesmo id reutilizável após ' . $label );
	$assert( false !== $db->query( 'ROLLBACK' ), 'limpeza do ensaio de lock de ' . $label );
};

try {
	$assert( false !== $db->query( 'CREATE TABLE `' . $base . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'tabela exclusiva criada' );

	$assert( false !== $db->query( 'START TRANSACTION;' ), 'START com ponto e vírgula aceite' );
	$assert( $db->inTransaction(), 'START com ponto e vírgula recebe guarda' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (1)' ), 'sentinela semicolon criada' );
	$assert( false !== $db->query( 'ROLLBACK;' ), 'ROLLBACK com ponto e vírgula aceite' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 1' ), 'ROLLBACK semicolon reverte sentinela' );
	$assert( ! $db->inTransaction(), 'ROLLBACK semicolon não deixa transacção externa' );

	$assert( false !== $db->query( 'START TRANSACTION;' ), 'START semicolon antes de COMMIT' );
	$assert( $db->inTransaction(), 'guarda antes de COMMIT semicolon' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (2)' ), 'sentinela COMMIT semicolon' );
	$assert( false !== $db->query( 'COMMIT;' ), 'COMMIT com ponto e vírgula aceite' );
	$assert( ! $db->inTransaction(), 'COMMIT semicolon termina a transacção' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 2' ), 'COMMIT semicolon confirma sentinela' );

	$assert( false !== $db->query( 'BEGIN;' ), 'BEGIN com ponto e vírgula aceite' );
	$assert( $db->inTransaction(), 'BEGIN semicolon recebe guarda' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (3)' ), 'sentinela BEGIN semicolon' );
	$assert( false !== $db->query( 'COMMIT AND CHAIN;' ), 'COMMIT AND CHAIN com ponto e vírgula aceite' );
	$assert( $db->inTransaction(), 'AND CHAIN semicolon instala nova guarda' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (4)' ), 'sentinela após chain semicolon' );
	$assert( false !== $db->query( 'ROLLBACK AND CHAIN;' ), 'ROLLBACK AND CHAIN com ponto e vírgula aceite' );
	$assert( $db->inTransaction(), 'ROLLBACK AND CHAIN semicolon instala nova guarda' );
	$assert( '1' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 3' ), 'COMMIT AND CHAIN semicolon confirmou a primeira sentinela' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 4' ), 'ROLLBACK AND CHAIN semicolon reverteu a segunda sentinela' );
	$assert( false !== $db->query( 'ROLLBACK;' ), 'ROLLBACK final chain semicolon' );
	$assert( ! $db->inTransaction(), 'ROLLBACK final chain não deixa transacção externa' );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes do comentário MySQL' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (10)' ), 'sentinela comentário MySQL' );
	$assert( false === $db->query( '/*!40101 CREATE TABLE `' . $mysql_comment_table . '` (id INT PRIMARY KEY) ENGINE=InnoDB */' ), 'comentário executável MySQL recusado' );
	$assert( $db->inTransaction(), 'comentário MySQL recusado conserva guarda' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK depois do comentário MySQL' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 10' ), 'comentário MySQL não confirma sentinela' );
	$assert( null === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $mysql_comment_table ) ), 'comentário MySQL não cria tabela' );

	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes do comentário MariaDB' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (11)' ), 'sentinela MariaDB criada' );
	$maria_comment = '/*M!100000 CREATE TABLE `' . $mariadb_table . '` (id INT PRIMARY KEY) ENGINE=InnoDB */';
	if ( $is_mariadb ) {
		$assert( false === $db->query( $maria_comment ), 'comentário executável MariaDB recusado' );
	} else {
		$db->query( $maria_comment );
		$assert( $db->inTransaction(), 'comentário MariaDB ignorado no MySQL não executa DDL' );
	}
	$assert( $db->inTransaction(), 'guarda após comentário MariaDB' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK depois do comentário MariaDB' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 11' ), 'comentário MariaDB não confirma sentinela' );
	$assert( null === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $mariadb_table ) ), 'comentário MariaDB não cria tabela' );

	$prove_set_refused( 'SET autocommit=0', 'SET autocommit=0', 20 );
	$prove_set_refused( 'SET SESSION autocommit=0', 'SET SESSION autocommit=0', 21 );
	$prove_set_refused( 'SET LOCAL autocommit:=0', 'SET LOCAL autocommit:=0', 22 );
	$prove_set_refused( 'SET GLOBAL autocommit=0', 'SET GLOBAL autocommit=0', 23 );
	$prove_set_refused( 'SET @@autocommit=0', 'SET @@autocommit=0', 24 );
	$prove_set_refused( 'SET @@session.autocommit=0', 'SET @@session.autocommit=0', 25 );
	$prove_set_refused( 'SET @marcador=1, @@session.autocommit=0', 'SET múltiplo com autocommit', 26 );
	$prove_set_refused( 'SET completion_type=1', 'SET completion_type=1', 27 );
	$prove_set_refused( 'SET @@completion_type=1', 'SET @@completion_type=1', 28 );
	$prove_set_refused( 'SET @@session.completion_type=1', 'SET @@session.completion_type=1', 29 );
	$prove_set_refused( 'SET @marcador=2, @@session.completion_type=1', 'SET múltiplo com completion_type', 30 );
	$prove_set_refused( 'SET `autocommit`=0', 'SET autocommit citado', 31 );
	$prove_set_refused( 'SET @@session.`autocommit`=0', 'SET session autocommit citado', 32 );
	$prove_set_refused( 'SET `completion_type`=1', 'SET completion_type citado', 33 );
	$prove_set_refused( "SET @text='/*', autocommit=0, @text2='*/'", 'SET com delimitadores no literal', 34 );

	$assert( false !== $db->query( 'SET @permitido=4' ), 'SET de variável de utilizador continua aceite' );
	$assert( '4' === (string) $db->get_var( 'SELECT @permitido' ), 'variável de utilizador persistiu' );

	$assert( false !== $db->query( 'BEGIN# comentário' ), 'BEGIN com comentário de linha recebe guarda' );
	$assert( $db->inTransaction(), 'BEGIN comentado instala guarda' );
	$assert( false === $db->query( 'START TRANSACTION# inline' ), 'START aninhado com comentário inline recusado' );
	$assert( $db->inTransaction(), 'START aninhado comentado não confirma a transacção' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK após START comentado' );

	add_filter( 'query', static fn( $sql ) => 'SELECT 1' === $sql ? 'CREATE TABLE `' . $mysql_comment_table . '` (id INT) ENGINE=InnoDB' : $sql );
	$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes do filtro DDL' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (40)' ), 'sentinela do filtro DDL' );
	$assert( false === $db->query( 'SELECT 1' ), 'filtro query que injecta DDL recusado' );
	$assert( $db->inTransaction(), 'guarda sobrevive ao filtro DDL' );
	$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK após filtro DDL' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 40' ), 'filtro DDL não confirma sentinela' );
	$GLOBALS['arn_tx_filters']['query'] = array();

	echo 'PASS: regressões de revisão, ' . $checks . ' verificações, motor=' . $version . PHP_EOL;
} finally {
	if ( $db->dbh instanceof mysqli ) {
		$db->dbh->query( 'ROLLBACK' );
		$db->dbh->query( 'SET autocommit=1' );
		$db->dbh->query( 'SET SESSION completion_type=0' );
		$db->dbh->query( 'SET @arn_review_marker=NULL' );
		$db->dbh->query( 'SET @marcador=NULL' );
		$db->dbh->query( 'DROP TABLE IF EXISTS `' . $mariadb_table . '`' );
		$db->dbh->query( 'DROP TABLE IF EXISTS `' . $mysql_comment_table . '`' );
		$db->dbh->query( 'DROP TABLE IF EXISTS `' . $base . '`' );
	}
	$db->close();
}
