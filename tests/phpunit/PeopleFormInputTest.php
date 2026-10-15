<?php
/** Own-profile form boundary tests.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Test cases are self-describing.
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\People\PeopleFormInput;
use PHPUnit\Framework\TestCase;

final class PeopleFormInputTest extends TestCase {
	public function test_exact_own_fields_and_line_endings(): void {
		$form = PeopleFormInput::parse(
			array(
				'operation'         => 'update_own',
				'nonce'             => 'token',
				'expected_revision' => '2',
				'phone'             => '+245 111',
				'office'            => 'Sala A',
				'bio'               => "Linha 1\r\nLinha 2",
			)
		);
		self::assertNotNull( $form );
		self::assertSame( 2, $form['revision'] );
		self::assertSame( "Linha 1\nLinha 2", $form['self_service']->bio );
	}

	public function test_forbidden_fields_and_invalid_revisions_are_rejected(): void {
		$base = array(
			'operation'         => 'update_own',
			'nonce'             => 'token',
			'expected_revision' => '2',
			'phone'             => '',
			'office'            => '',
			'bio'               => '',
		);
		foreach ( array( 'actor', 'unit', 'job', 'email', 'state', 'role', 'page_id' ) as $field ) {
			self::assertNull( PeopleFormInput::parse( $base + array( $field => 'x' ) ), $field );
		}
		self::assertNull( PeopleFormInput::parse( array_replace( $base, array( 'expected_revision' => '02' ) ) ) );
		self::assertNull( PeopleFormInput::parse( array_replace( $base, array( 'bio' => "linha\rsolta" ) ) ) );
		self::assertNull( PeopleFormInput::parse( array_replace( $base, array( 'phone' => array( 'x' ) ) ) ) );
	}
}
