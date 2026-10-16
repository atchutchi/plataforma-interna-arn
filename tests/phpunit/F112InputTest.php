<?php
/**
 * Strict F1-12 request boundaries.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Boundary contract tests.
declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\F112\Input;
use PHPUnit\Framework\TestCase;

final class F112InputTest extends TestCase {
	public function test_form_errors_identify_own_fields_without_rewriting_the_payload(): void {
		$post   = array(
			'operation'   => 'create',
			'nonce'       => 'proof',
			'reason'      => '',
			'title'       => str_repeat( 'á', 161 ),
			'body'        => 'Texto',
			'audience'    => 'LAB-A',
			'circuit'     => 'team-direct',
			'valid_until' => '2030-02-30T10:00:00Z',
		);
		$errors = Input::validation( 'communications', $post );
		self::assertArrayHasKey( 'title', $errors );
		self::assertArrayHasKey( 'reason', $errors );
		self::assertArrayHasKey( 'valid_until', $errors );
		self::assertSame( str_repeat( 'á', 161 ), $post['title'] );
	}
	public function test_closed_query_refuses_arrays_overflow_and_private_workflow_filters(): void {
		self::assertSame(
			21,
			Input::query(
				'communications',
				array(
					'entry' => '21',
					'q'     => 'Texto',
				)
			)['id']
		);
		foreach ( array(
			array( 'entry' => '01' ),
			array( 'entry' => array() ),
			array( 'entry' => str_repeat( '9', 100 ) ),
			array(
				'entry'   => '1',
				'notices' => '1',
			),
			array( 'actor' => '1' ),
			array( 'state' => 'Draft' ),
			array( 'q' => str_repeat( 'á', 101 ) ),
		) as $bad ) {
			self::assertNull( Input::query( 'communications', $bad ) );
		}
		self::assertNull( Input::query( 'calendar', array( 'state' => 'Draft' ) ) );
		self::assertNotNull( Input::query( 'calendar', array( 'state' => 'Draft' ), true ) );
	}

	public function test_mutation_shape_never_accepts_authority_from_the_browser(): void {
		$valid = array(
			'operation' => 'publish',
			'id'        => '7',
			'revision'  => '2',
			'reason'    => 'Publicar teste',
			'nonce'     => 'proof',
		);
		self::assertTrue( Input::shape( 'communications', $valid, true ) );
		foreach ( array( 'author', 'organizer', 'unit', 'actor', 'audience' ) as $extra ) {
			self::assertFalse( Input::shape( 'communications', $valid + array( $extra => 'LAB-B' ), true ) );
		}
		$valid['reason'] = array();
		self::assertFalse( Input::shape( 'communications', $valid, true ) );
		self::assertFalse( Input::shape( 'communications', array( 'operation' => 'publish' ), false ) );
	}
}
