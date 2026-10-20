<?php
/**
 * Classificação da fronteira SQL sem motor.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Identity\SqlBoundary;
use PHPUnit\Framework\TestCase;

/**
 * Contratos do classificador por tokens e interpretações de comentários.
 */
final class SqlBoundaryTest extends TestCase {

	/** O modo de leitura da sessão inclui o alias MariaDB. */
	public function testRefusesReadOnlyVariableAssignments(): void {
		foreach ( array( '8.0.44', '8.4.0', '11.8.3-MariaDB' ) as $server ) {
			foreach ( array( 'transaction_read_only', 'tx_read_only' ) as $name ) {
				foreach ( array( '', 'SESSION ', 'LOCAL ', '@@session.', '@@local.', '@@', '@marker=1, ', '@marker=1, @@session.' ) as $prefix ) {
					$sql = 'SET ' . $prefix . '`' . $name . '`=ON';
					self::assertFalse( SqlBoundary::classify( $sql, $server )['trusted'], $server . ': ' . $sql );
				}
			}
		}
	}

	/** O isolamento de sessão não se confunde com SET TRANSACTION. */
	public function testRefusesIsolationVariableAssignments(): void {
		foreach ( array( '8.0.44', '8.4.0', '11.8.3-MariaDB' ) as $server ) {
			foreach ( array( 'transaction_isolation', 'tx_isolation' ) as $name ) {
				foreach ( array( '', 'SESSION ', 'LOCAL ', '@@session.', '@@local.', '@@', '@marker=1, ', '@marker=1, @@session.' ) as $prefix ) {
					$sql = 'SET ' . $prefix . '`' . $name . "`='READ-UNCOMMITTED'";
					self::assertFalse( SqlBoundary::classify( $sql, $server )['trusted'], $server . ': ' . $sql );
				}
			}
		}
	}

	/** O âmbito de sessão não pode alterar as transacções seguintes. */
	public function testRefusesScopedTransactionCharacteristics(): void {
		foreach ( array( '8.0.44', '8.4.0', '11.8.3-MariaDB' ) as $server ) {
			foreach ( array( 'SESSION', 'LOCAL', 'GLOBAL' ) as $scope ) {
				foreach ( array( 'ISOLATION LEVEL READ UNCOMMITTED', 'ISOLATION LEVEL SERIALIZABLE', 'READ ONLY', 'READ WRITE' ) as $characteristic ) {
					$sql = 'SET /* âmbito */ ' . $scope . ' TRANSACTION ' . $characteristic;
					self::assertFalse( SqlBoundary::classify( $sql, $server )['trusted'], $server . ': ' . $sql );
				}
			}
			self::assertFalse( SqlBoundary::classify( 'SET /*!80000 SESSION */ TRANSACTION ISOLATION LEVEL READ UNCOMMITTED', $server )['trusted'] );
		}
	}

	/** Âmbitos globais e persistentes nunca são atribuições da sessão dedicada. */
	public function testRefusesGlobalAndPersistentAssignments(): void {
		foreach ( array( '8.0.44', '8.4.0', '11.8.3-MariaDB' ) as $server ) {
			foreach ( array( 'GLOBAL', 'PERSIST', 'PERSIST_ONLY' ) as $scope ) {
				foreach ( array( 'SET ' . $scope . ' transaction_read_only=ON', 'SET ' . $scope . ' `max_connections`=100', 'SET @marker=1, ' . $scope . ' max_connections=100', 'SET @@' . $scope . '.max_connections=100', 'SET @marker=1, @@' . $scope . '.`max_connections`=100', 'SET /*!80000 ' . $scope . ' */ max_connections=100' ) as $sql ) {
					self::assertFalse( SqlBoundary::classify( $sql, $server )['trusted'], $server . ': ' . $sql );
				}
			}
		}
	}

	/** Conserva o isolamento da próxima transacção e atribuições de sessão/utilizador. */
	public function testKeepsSupportedTransactionAndSessionAssignments(): void {
		foreach ( array( '8.0.44', '8.4.0', '11.8.3-MariaDB' ) as $server ) {
			foreach ( array( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED', 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ', 'SET SESSION wait_timeout=60', 'SET LOCAL wait_timeout=60', 'SET @@session.wait_timeout=60', 'SET @@local.wait_timeout=60', 'SET @global=1, @persist_only=2', 'SET @transaction_read_only=1, @tx_isolation=2', "SET @text='GLOBAL TRANSACTION', @marker=1" ) as $sql ) {
				self::assertTrue( SqlBoundary::classify( $sql, $server )['trusted'], $server . ': ' . $sql );
			}
		}
	}

	/** A gestão de credenciais não pertence à ligação de dados do módulo. */
	public function testRefusesPasswordStatementsWithoutExecutingThem(): void {
		foreach ( array( "SET PASSWORD='synthetic-placeholder'", "SET PASSWORD FOR 'fictional'@'localhost'='synthetic-placeholder'", "SET /* comentário */ PASSWORD='synthetic-placeholder'" ) as $sql ) {
			self::assertFalse( SqlBoundary::classify( $sql, '8.4.0' )['trusted'], $sql );
		}
	}

	/** Uma expressão ou um literal não é um bloco de controlo SQL. */
	public function testAcceptsCaseExpressionsAndThenLiterals(): void {
		foreach ( array( "SELECT 'THEN'", 'SELECT `THEN` FROM t', 'SELECT CASE WHEN 1=1 THEN 1 ELSE 0 END', 'UPDATE t SET value=CASE WHEN id=1 THEN 2 ELSE 3 END' ) as $sql ) {
			self::assertTrue( SqlBoundary::classify( $sql, '8.0.44' )['trusted'], $sql );
		}
	}

	/** DDL de teste fora de transacção não passa a ser DML seguro. */
	public function testClassifiesTriggerBodyAsDdl(): void {
		$result = SqlBoundary::classify( "CREATE TRIGGER t BEFORE INSERT ON audit FOR EACH ROW BEGIN IF NEW.id=1 THEN SIGNAL SQLSTATE '45000'; END IF; END", '11.8.3-MariaDB' );
		self::assertTrue( $result['trusted'] );
		self::assertTrue( $result['implicit_commit'] );
	}

	/** Todas as interpretações têm de terminar no mesmo estado transaccional. */
	public function testRefusesDivergentConditionalChain(): void {
		foreach ( array( 'COMMIT /*!99999 AND CHAIN */', 'ROLLBACK /*!99999 AND CHAIN */', 'COMMIT AND /*!40101 NO */ CHAIN' ) as $sql ) {
			self::assertFalse( SqlBoundary::classify( $sql, '8.0.44' )['trusted'], $sql );
		}
	}

	/** O modo lexical não é uma preferência editável da ligação dedicada. */
	public function testRefusesSqlModeChanges(): void {
		foreach ( array( "SET SESSION sql_mode='NO_BACKSLASH_ESCAPES'", "SET @@session.`sql_mode`=''", "SET @text='x', sql_mode='ANSI_QUOTES'" ) as $sql ) {
			self::assertFalse( SqlBoundary::classify( $sql, '8.0.44' )['trusted'], $sql );
		}
	}

	/** A interpretação de aspas fica limitada ao UTF-8 usado pelo WordPress. */
	public function testRefusesNonUtf8LexicalChanges(): void {
		foreach ( array( 'SET NAMES gbk', "SET CHARACTER SET 'latin1'", "SET SESSION character_set_client='gbk'", "SET @@session.character_set_connection='binary'" ) as $sql ) {
			self::assertFalse( SqlBoundary::classify( $sql, '8.0.44' )['trusted'], $sql );
		}
		self::assertTrue( SqlBoundary::classify( "SET NAMES 'utf8mb4' COLLATE utf8mb4_unicode_ci", '8.0.44' )['trusted'] );
	}

	/** Charset da base e collation não podem mudar implicitamente a análise. */
	public function testRefusesIndirectConnectionCharsetChanges(): void {
		foreach ( array( "SET SESSION collation_connection='latin1_swedish_ci'", 'SET @@session.collation_connection=8', 'SET CHARACTER SET utf8mb4', 'SET CHARSET utf8mb4' ) as $sql ) {
			self::assertFalse( SqlBoundary::classify( $sql, '8.4.0' )['trusted'], $sql );
		}
	}

	/**
	 * Comentários inline, literais e SET citado não confundem a fronteira.
	 */
	public function testClassifiesInlineCommentsLiteralsAndQuotedSet(): void {
		$mysql = '8.0.44';
		self::assertSame( 'begin', SqlBoundary::classify( 'START TRANSACTION# inline', $mysql )['command'] );
		self::assertSame( 'begin', SqlBoundary::classify( "BEGIN -- comentário\n", $mysql )['command'] );
		self::assertTrue( SqlBoundary::classify( "ANALYZE# inline\nTABLE t", $mysql )['implicit_commit'] );
		self::assertTrue( SqlBoundary::classify( 'START /*meio*/ TRANSACTION', $mysql )['trusted'] );
		self::assertTrue( SqlBoundary::classify( '/*!99999 SELECT */ CREATE TABLE t (id INT)', $mysql )['implicit_commit'] );
		self::assertTrue( SqlBoundary::classify( 'SET `autocommit`=0', $mysql )['autocommit'] );
		self::assertTrue( SqlBoundary::classify( 'SET @@session.`autocommit`=0', $mysql )['autocommit'] );
		self::assertTrue( SqlBoundary::classify( 'SET `completion_type`=1', $mysql )['completion_type'] );
		self::assertTrue( SqlBoundary::classify( "SET @text='/*', autocommit=0, @text2='*/'", $mysql )['autocommit'] );
		self::assertFalse( SqlBoundary::classify( 'SET @marcador=1', $mysql )['autocommit'] );
		self::assertTrue( SqlBoundary::classify( 'SET @marcador=1', $mysql )['trusted'] );
	}

	/**
	 * SQL composto e comentários MariaDB só executáveis nesse motor.
	 */
	public function testRefusesCompoundAndKeepsMariaCommentsInactiveOnMysql(): void {
		$mysql  = '8.0.44';
		$maria  = '11.8.3-MariaDB';
		$create = 'CREATE TABLE t (id INT)';
		self::assertTrue( SqlBoundary::classify( 'SET STATEMENT max_statement_time=1 FOR ' . $create, $maria )['implicit_commit'] );
		self::assertFalse( SqlBoundary::classify( 'IF 1 THEN ' . $create . '; END IF', $maria )['trusted'] );
		$maria_comment = '/*M!100000 CREATE TABLE t (id INT) */';
		self::assertTrue( SqlBoundary::classify( $maria_comment, $maria )['implicit_commit'] );
		$mysql_view = SqlBoundary::classify( $maria_comment, $mysql );
		self::assertTrue( $mysql_view['trusted'] );
		self::assertFalse( $mysql_view['implicit_commit'] );
	}
}
