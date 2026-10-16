<?php
/** F1-12 input boundaries.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\F112\Audience;
use Arn\Intranet\F112\PlainText;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Rejects invalid Unicode and structural inputs before persistence. */
final class F112ValuesTest extends TestCase {

	/** C1 controls are forbidden even when they are valid multi-byte UTF-8. */
	public function test_unicode_control_characters_are_not_treated_as_whitespace(): void {
		foreach ( array( false, true ) as $multiline ) {
			foreach ( array( "A\u{0085}B", "A\u{009f}B" ) as $value ) {
				try {
					PlainText::value( $value, 100, $multiline );
					self::fail( 'Unicode control accepted.' );
				} catch ( InvalidArgumentException ) {
					self::assertTrue( true );
				}
			}
		}
	}

	/** Text remains literal and bodies normalise only CRLF. */
	public function test_text_is_unicode_bounded_and_literal(): void {
		self::assertSame( 'Olá', PlainText::value( " \u{00a0}Olá ", 3, false ) );
		self::assertSame( "<script>\ntexto", PlainText::value( "<script>\r\ntexto", 100, true ) );
		self::assertSame( '', PlainText::value( ' ', 10, false, false ) );
		foreach ( array( '', 'Áááá', "a\nb", "a\rb", "a\0b", "a\tb", "a\u{2028}b", "\xff" ) as $value ) {
			try {
				PlainText::value( $value, 3, false );
				self::fail( 'Invalid text accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
		$this->expectException( InvalidArgumentException::class );
		PlainText::value( "body\r", 10, true );
	}

	/** Scope must survive roundtrip without accepting arbitrary units or keys. */
	public function test_audience_is_closed_and_strictly_hydrated(): void {
		self::assertSame(
			array(
				'type'    => 'all',
				'unit_id' => null,
			),
			Audience::all()->toArray()
		);
		self::assertSame( array( 'f112_unit_LAB-A' ), Audience::unit( 'LAB-A' )->scope()->targets );
		self::assertEquals(
			Audience::unit( 'LAB-B' ),
			Audience::fromArray(
				array(
					'type'    => 'unit',
					'unit_id' => 'LAB-B',
				)
			)
		);
		foreach ( array(
			array(
				'type'    => 'all',
				'unit_id' => 'LAB-A',
			),
			array(
				'type'    => 'unit',
				'unit_id' => 'LAB-ROOT',
			),
			array(
				'type'    => 'all',
				'unit_id' => null,
				'extra'   => true,
			),
		) as $data ) {
			try {
				Audience::fromArray( $data );
				self::fail( 'Invalid audience accepted.' );
			} catch ( InvalidArgumentException ) {
				self::assertTrue( true );
			}
		}
	}
}
