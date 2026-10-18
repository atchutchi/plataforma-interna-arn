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

$db            = arn_tx_connect();
$suffix        = bin2hex( random_bytes( 4 ) );
$base          = 'arn_review_' . $suffix;
$mariadb_table = 'arn_review_maria_' . $suffix;
$version       = (string) $db->get_var( 'SELECT VERSION()' );
$is_mariadb    = str_contains( strtoupper( $version ), 'MARIADB' );

try {
	$assert( false !== $db->query( 'CREATE TABLE `' . $base . '` (id INT PRIMARY KEY) ENGINE=InnoDB' ), 'tabela exclusiva criada' );

	$assert( false !== $db->query( 'START TRANSACTION;' ), 'START com ponto e vírgula aceite' );
	$assert( $db->inTransaction(), 'START com ponto e vírgula recebe guarda' );
	$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (1)' ), 'sentinela semicolon criada' );
	$assert( false !== $db->query( 'ROLLBACK;' ), 'ROLLBACK com ponto e vírgula aceite' );
	$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 1' ), 'ROLLBACK semicolon reverte sentinela' );

	$assert( false === $db->query( 'SET @@autocommit=0' ), 'autocommit sem escopo recusado' );
	$assert( '1' === (string) $db->get_var( 'SELECT @@session.autocommit' ), 'autocommit sem escopo não altera sessão' );

	$assert( $db->dbh instanceof mysqli && true === $db->dbh->query( 'SET @arn_review_marker=NULL' ), 'marcador limpo' );
	$assert( false === $db->query( 'SET @arn_review_marker=1, @@session.autocommit=0' ), 'autocommit em atribuição múltipla recusado' );
	$assert( null === $db->get_var( 'SELECT @arn_review_marker' ), 'atribuição anterior ao autocommit não executada' );
	$assert( '1' === (string) $db->get_var( 'SELECT @@session.autocommit' ), 'atribuição múltipla não altera autocommit' );

	$assert( false === $db->query( 'SET @arn_review_marker=2, @@session.completion_type=1' ), 'completion type em atribuição múltipla recusado' );
	$assert( null === $db->get_var( 'SELECT @arn_review_marker' ), 'atribuição anterior ao completion type não executada' );

	if ( $is_mariadb ) {
		$assert( false !== $db->query( 'START TRANSACTION' ), 'START antes do comentário MariaDB' );
		$assert( false !== $db->query( 'INSERT INTO `' . $base . '` VALUES (2)' ), 'sentinela MariaDB criada' );
		$assert( false === $db->query( '/*M!100000 CREATE TABLE `' . $mariadb_table . '` (id INT PRIMARY KEY) ENGINE=InnoDB */' ), 'comentário executável MariaDB recusado' );
		$assert( $db->inTransaction(), 'comentário MariaDB recusado conserva guarda' );
		$assert( false !== $db->query( 'ROLLBACK' ), 'ROLLBACK depois do comentário MariaDB' );
		$assert( '0' === (string) $db->get_var( 'SELECT COUNT(*) FROM `' . $base . '` WHERE id = 2' ), 'comentário MariaDB não confirma sentinela' );
		$assert( null === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $mariadb_table ) ), 'comentário MariaDB não cria tabela' );
	}

	echo 'PASS: regressões de revisão, ' . $checks . ' verificações, motor=' . $version . PHP_EOL;
} finally {
	if ( $db->dbh instanceof mysqli ) {
		$db->dbh->query( 'ROLLBACK' );
		$db->dbh->query( 'SET autocommit=1' );
		$db->dbh->query( 'SET SESSION completion_type=0' );
		$db->dbh->query( 'SET @arn_review_marker=NULL' );
		$db->dbh->query( 'DROP TABLE IF EXISTS `' . $mariadb_table . '`' );
		$db->dbh->query( 'DROP TABLE IF EXISTS `' . $base . '`' );
	}
	$db->close();
}
