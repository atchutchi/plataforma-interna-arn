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
