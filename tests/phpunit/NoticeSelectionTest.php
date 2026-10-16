<?php
/** Authorised notice pagination.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Communications\Notice;
use Arn\Intranet\Communications\NoticeContent;
use Arn\Intranet\Communications\NoticeSelection;
use Arn\Intranet\F112\Audience;
use PHPUnit\Framework\TestCase;

/** Filters never widen a malformed request. */
final class NoticeSelectionTest extends TestCase {

	/** Twenty per page, literal search and beyond-end emptiness. */
	public function test_pagination_search_and_closed_parameters(): void {
		$notices = array();
		for ( $id = 1; $id <= 21; ++$id ) {
			$notices[] = Notice::draft( $id, 7, new NoticeContent( 'Aviso [A] ' . $id, 'Corpo', Audience::unit( 'LAB-A' ), 'team-direct', null ), 10 )->publish( 7, 1, 11, 'Publicar.' );
		}
		$first = NoticeSelection::parse( array() )->apply( $notices, 12 );
		self::assertSame( 21, $first['total'] );
		self::assertCount( 20, $first['items'] );
		self::assertSame( 21, $first['items'][0]->id );
		self::assertCount(
			1,
			NoticeSelection::parse(
				array(
					'page' => '2',
					'q'    => '[A]',
				)
			)->apply(
				$notices,
				12
			)['items']
		);
		self::assertSame( array(), NoticeSelection::parse( array( 'page' => '3' ) )->apply( $notices, 12 )['items'] );
		self::assertSame( 0, NoticeSelection::parse( array( 'q' => '.*' ) )->apply( $notices, 12 )['total'] );
		self::assertSame( 0, NoticeSelection::parse( array( 'unit' => 'LAB-B' ) )->apply( $notices, 12 )['total'] );
		foreach ( array( array( 'page' => '01' ), array( 'page' => str_repeat( '9', 100 ) ), array( 'q' => array() ), array( 'q' => str_repeat( 'é', 101 ) ), array( 'unit' => 'LAB-ROOT' ), array( 'state' => 'private' ), array( 'unknown' => '1' ) ) as $bad ) {
			self::assertNull( NoticeSelection::parse( $bad ) );
		}
	}
}
