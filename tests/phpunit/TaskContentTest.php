<?php
/** F1-13 canonical task values.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Tasks\TaskContent;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Input is validated without a database or institutional identities. */
final class TaskContentTest extends TestCase {

	/** Unicode bounds and UTC retain a canonical representation. */
	public function testCanonicalValuesAndRoundTrip(): void {
		$content = new TaskContent( ' Rever parecer ', "Primeira\r\nSegunda", 'high', '2032-02-29T10:00:00Z' );
		self::assertSame( 'Rever parecer', $content->title );
		self::assertSame( "Primeira\nSegunda", $content->description );
		self::assertSame( '2032-02-29T10:00:00Z', gmdate( 'Y-m-d\TH:i:s\Z', $content->due_at ) );
		self::assertSame( $content->toArray(), TaskContent::fromArray( $content->toArray() )->toArray() );
		self::assertSame( '', ( new TaskContent( 'Sem descrição', '', 'normal', null ) )->description );
		self::assertSame( 160, mb_strlen( ( new TaskContent( str_repeat( 'á', 160 ), '', 'low', null ) )->title ) );
	}

	/** Calendar overflow, offsets and controls are not repaired silently. */
	public function testRejectsInvalidDatesAndText(): void {
		$cases = array(
			array( '', '', 'normal', null ),
			array( str_repeat( 'á', 161 ), '', 'normal', null ),
			array( "Linha\r\nseguinte", '', 'normal', null ),
			array( "Linha\u{2028}seguinte", '', 'normal', null ),
			array( 'Título', "Texto\r", 'normal', null ),
			array( 'Título', '<script>conteúdo</script>', 'normal', null ),
			array( 'Título', str_repeat( 'á', 8001 ), 'normal', null ),
			array( 'Título', "\xff", 'normal', null ),
			array( 'Título', '', 'urgent', null ),
			array( 'Título', '', 'normal', '2030-02-29T10:00:00Z' ),
			array( 'Título', '', 'normal', '2030-01-15T10:00:00+00:00' ),
			array( 'Título', '', 'normal', '9999999999999999-01-01T00:00:00Z' ),
		);
		foreach ( $cases as $case ) {
			try {
				new TaskContent( ...$case );
				self::fail( 'Valor de tarefa inválido aceite.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}

	/** Corrupt persisted values cannot become canonical by hydration. */
	public function testRejectsNonCanonicalStorage(): void {
		$value = ( new TaskContent( 'Título', '', 'normal', null ) )->toArray();
		foreach ( array( array_merge( $value, array( 'extra' => true ) ), array_merge( $value, array( 'title' => ' Título ' ) ), array_merge( $value, array( 'due_at' => '2030-01-15T10:00:00Z' ) ) ) as $bad ) {
			try {
				TaskContent::fromArray( $bad );
				self::fail( 'Representação persistida inválida aceite.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}
}
