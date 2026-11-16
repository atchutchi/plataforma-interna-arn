<?php
/** Institutional publication boundary tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Communications\{InstitutionalPanel, Notice, NoticeContent};
use Arn\Intranet\F112\Audience;
use PHPUnit\Framework\TestCase;
// phpcs:disable Squiz.Commenting.FunctionComment
/** Only the approved version may leave the server. */
final class InstitutionalPanelTest extends TestCase {
	public function testOnlyPublishedInstitutionalAudienceIsProjected(): void {
		$draft = Notice::draft( 1, 7, new NoticeContent( 'Público interno', 'Texto aprovado', Audience::all(), 'institutional-review', 200 ), 90 );
		self::assertNull( InstitutionalPanel::project( $draft, 100 ) );
		$pending = $draft->submit( 7, 1, 91, 'Submeter.' );
		self::assertNull( InstitutionalPanel::project( $pending, 100 ) );
		$public = $pending->decide( 'approve', 1, 8, 2, 92, 'Aprovar.' );
		self::assertSame(
			array(
				'id'      => 1,
				'title'   => 'Público interno',
				'body'    => 'Texto aprovado',
				'version' => 1,
			),
			InstitutionalPanel::project( $public, 100 )
		);
		self::assertNull( InstitutionalPanel::project( $public, 200 ) );
		self::assertNull( InstitutionalPanel::project( $public, 91 ) );
		self::assertNull( InstitutionalPanel::project( $public->archive( 7, 3, 101, 'Arquivo.' ), 102 ) );
		$revised = $public->revise( new NoticeContent( 'Rascunho privado', 'Não publicar', Audience::unit( 'LAB-A' ), 'institutional-review', null ), 7, 3, 101, 'Revisão.' );
		self::assertSame( 'Texto aprovado', InstitutionalPanel::project( $revised, 102 )['body'] );
		$unit = $revised->submit( 7, 4, 103, 'Submeter.' )->decide( 'approve', 2, 8, 5, 104, 'Aprovar unidade.' );
		self::assertNull( InstitutionalPanel::project( $unit, 105 ) );
		$team = Notice::draft( 2, 7, new NoticeContent( 'Equipa', 'Texto', Audience::unit( 'LAB-A' ), 'team-direct', null ), 90 )->publish( 8, 1, 91, 'Publicar.' );
		self::assertNull( InstitutionalPanel::project( $team, 100 ) );
		self::assertFalse( InstitutionalPanel::permitted( null, 100 ) );
	}
}
