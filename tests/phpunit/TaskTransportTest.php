<?php
/** Closed task filters and transport, without WordPress or SQL.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Tasks\{Input, Task, TaskContent, TaskSelection};
use PHPUnit\Framework\TestCase;

/** Invalid browser claims must fail before consulting a source. */
final class TaskTransportTest extends TestCase {

	/** Structured input, authority claims and impossible IDs are refused. */
	public function testClosedQuery(): void {
		self::assertNotNull( Input::query( 'own', array() ) );
		self::assertNotNull(
			Input::query(
				'team',
				array(
					'assignee'       => '7',
					'state'          => 'open',
					'q'              => 'Prazo',
					'arn_tasks_page' => '2',
				)
			)
		);
		foreach ( array(
			array( 'q' => array() ),
			array( 'actor_id' => '7' ),
			array( 'entry' => '01' ),
			array( 'arn_tasks_page' => str_repeat( '9', 40 ) ),
			array( 'q' => '<b>x</b>' ),
			array( 'q' => str_repeat( 'á', 121 ) ),
			array(
				'entry' => '1',
				'new'   => '1',
			),
			array(
				'notices' => '1',
				'state'   => 'open',
			),
		) as $query ) {
			self::assertNull( Input::query( 'own', $query ) );
		}
		self::assertNull( Input::query( 'own', array( 'assignee' => '7' ) ) );
		self::assertNull( Input::query( 'own', array( 'new' => '1' ) ) );
		self::assertNull( Input::query( 'team', array( 'notices' => '1' ) ) );
		self::assertNotNull( Input::query( 'team', array( 'new' => '1' ) ) );
	}

	/** Creation identity has no object ID and is bound to the nonce action. */
	public function testCreateShapeAndNonceBinding(): void {
		$post = array(
			'operation'         => 'create',
			'nonce'             => 'nonce',
			'create_request_id' => str_repeat( 'a', 32 ),
			'title'             => 'Título',
			'description'       => '',
			'priority'          => 'normal',
			'due'               => '',
			'assignee_id'       => '7',
		);
		self::assertTrue( Input::shape( $post ) );
		foreach ( array( array( 'id' => '1' ), array( 'unit_id' => 'LAB-B' ), array( 'author_id' => '7' ) ) as $extra ) {
			self::assertFalse( Input::shape( $post + $extra ) );
		}
		self::assertFalse( Input::shape( array_replace( $post, array( 'create_request_id' => str_repeat( 'A', 32 ) ) ) ) );
		self::assertFalse( Input::shape( array_replace( $post, array( 'assignee_id' => '0' ) ) ) );
		self::assertNotSame( Input::nonce( 7, 'team', 'create', 0, str_repeat( 'a', 32 ) ), Input::nonce( 7, 'team', 'create', 0, str_repeat( 'b', 32 ) ) );
		self::assertNotSame( Input::nonce( 7, 'own', 'start', 1 ), Input::nonce( 8, 'own', 'start', 1 ) );
		self::assertNotSame( Input::nonce( 7, 'own', 'start', 1 ), Input::nonce( 7, 'team', 'start', 1 ) );
		self::assertSame( null, Input::content( $post )->due_at );
	}

	/** Mutation fields are exact and comments cannot carry files or edit IDs. */
	public function testMutationShape(): void {
		$base = array(
			'operation'         => 'comment',
			'nonce'             => 'nonce',
			'id'                => '1',
			'expected_revision' => '2',
			'body'              => 'Texto',
		);
		self::assertTrue( Input::shape( $base ) );
		self::assertFalse( Input::shape( $base + array( 'comment_id' => '1' ) ) );
		self::assertFalse( Input::shape( $base + array( 'attachment' => 'x' ) ) );
		self::assertFalse( Input::shape( array_replace( $base, array( 'expected_revision' => '02' ) ) ) );
		self::assertSame( array( 'body' => 'Texto' ), Input::fields( $base ) );
		self::assertTrue(
			Input::shape(
				array(
					'operation' => 'mark-read',
					'nonce'     => 'nonce',
					'id'        => '1',
				)
			)
		);
		self::assertSame(
			array(),
			Input::validation(
				array(
					'operation' => 'change_due',
					'due'       => '',
					'reason'    => '',
				)
			)
		);
		self::assertArrayHasKey(
			'reason',
			Input::validation(
				array(
					'operation' => 'cancel',
					'reason'    => '',
				)
			)
		);
		self::assertArrayHasKey(
			'body',
			Input::validation(
				array(
					'operation' => 'comment',
					'body'      => '<script>x</script>',
				)
			)
		);
	}

	/** Already-authorised rows are ordered before counting and pagination. */
	public function testStableSelection(): void {
		$tasks = array();
		foreach ( range( 1, 21 ) as $id ) {
			$tasks[] = Task::create( $id, 9, 7, 'LAB-A', new TaskContent( 'Título ' . $id, 'Descrição', 'normal', 21 === $id ? '2030-01-15T10:00:00Z' : null ), 100, str_pad( dechex( $id ), 32, '0', STR_PAD_LEFT ) );
		}
		$first = TaskSelection::parse( array() )->apply( $tasks );
		self::assertSame( 21, $first['total'] );
		self::assertCount( 20, $first['items'] );
		self::assertSame( 21, $first['items'][0]->id );
		self::assertSame( 20, TaskSelection::parse( array( 'page' => '2' ) )->apply( $tasks )['items'][0]->id );
		self::assertSame( array(), TaskSelection::parse( array( 'page' => (string) PHP_INT_MAX ) )->apply( $tasks )['items'] );
		self::assertSame(
			1,
			TaskSelection::parse(
				array(
					'q'        => 'Título 21',
					'assignee' => '7',
				)
			)->apply( $tasks )['total']
		);
		self::assertSame( 0, TaskSelection::parse( array( 'assignee' => '8' ) )->apply( $tasks )['total'] );
	}
}
