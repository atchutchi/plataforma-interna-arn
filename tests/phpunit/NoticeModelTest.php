<?php
/** Pure notice lifecycle, audit and version tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Communications\DecisionToken;
use Arn\Intranet\Communications\Notice;
use Arn\Intranet\Communications\NoticeContent;
use Arn\Intranet\Communications\NoticeState;
use Arn\Intranet\Communications\NoticeVersion;
use Arn\Intranet\F112\Audience;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Old publication survives edits, but not expiry or archive. */
final class NoticeModelTest extends TestCase {

	/** Readonly arrays must not retain references owned by an external caller. */
	public function test_constructor_detaches_version_and_audit_references(): void {
		$draft                = Notice::draft( 1, 7, new NoticeContent( 'Original', 'B', Audience::unit( 'LAB-A' ), 'team-direct', null ), 90 );
		$reason               = 'Criação de rascunho.';
		$history              = $draft->history;
		$history[0]['reason'] = &$reason;
		$version              = $draft->versions[0];
		$versions             = array( &$version );
		$copy                 = new Notice( 1, 7, 'LAB-A', 1, NoticeState::Draft, $versions, null, null, null, null, null, $history );
		$reason               = 'Mutação externa.';
		$version              = new NoticeVersion( 1, new NoticeContent( 'Injectado', 'B', Audience::unit( 'LAB-A' ), 'team-direct', null ), 7, 90 );
		self::assertSame( 'Original', $copy->latest()->content->title );
		self::assertSame( 'Criação de rascunho.', $copy->history[0]['reason'] );
	}

	/** Publication is immediate with an exclusive validity endpoint. */
	public function test_team_publication_expiry_cas_noop_and_archive(): void {
		$content = new NoticeContent( 'Equipa', 'Texto', Audience::unit( 'LAB-A' ), 'team-direct', 110 );
		$draft   = Notice::draft( 1, 7, $content, 90 );
		$public  = $draft->publish( 8, 1, 100, 'Publicar para a equipa.' );
		self::assertSame( 'LAB-A', $public->unit_id );
		self::assertSame( 7, $public->author_id );
		self::assertNull( $public->published( 99 ) );
		self::assertSame( 'Equipa', $public->published( 109 )->content->title );
		self::assertNull( $public->published( 110 ) );
		self::assertSame( $public, $public->revise( $content, 8, 2, 101, 'Sem alteração.' ) );
		$copy = Notice::fromArray( $public->toArray() );
		self::assertEquals( $public, $copy );
		self::assertSame( 2, count( $copy->history ) );
		$archived = $public->archive( 8, 2, 101, 'Retirar publicação.' );
		self::assertSame( NoticeState::Archived, $archived->state );
		self::assertNull( $archived->published( 105 ) );
		foreach ( array(
			static fn() => $public->revise( $content, 8, 1, 101, 'CAS antigo.' ),
			static fn() => $archived->revise( $content, 7, 3, 102, 'Terminal.' ),
			static fn() => $draft->publish( 7, 1, 110, 'Expirado.' ),
			static fn() => $public->archive( 8, 2, 99, 'Relógio anterior.' ),
		) as $invalid ) {
			$this->refused( $invalid );
		}
	}

	/** A second actor decides a frozen version and cannot silently broaden the current audience. */
	public function test_institutional_version_freeze_resubmission_and_audience_replacement(): void {
		$one = new NoticeContent( 'Uma', 'V1', Audience::all(), 'institutional-review', null );
		$two = new NoticeContent( 'Duas', 'V2', Audience::unit( 'LAB-B' ), 'institutional-review', null );
		$n   = Notice::draft( 2, 7, $one, 90 )->submit( 7, 1, 91, 'Submeter.' );
		$this->refused( static fn() => $n->decide( 'approve', 1, 7, 2, 92, 'Auto aprovação.' ) );
		$this->refused( static fn() => $n->revise( $two, 7, 2, 92, 'Alterar pendente.' ) );
		$n = $n->decide( 'approve', 1, 8, 2, 92, 'Aprovar.' )->revise( $two, 7, 3, 93, 'Nova audiência.' )->submit( 7, 4, 94, 'Submeter V2.' );
		self::assertSame( 1, $n->published( 95 )->number );
		self::assertSame( 'all', $n->published( 95 )->content->audience->type );
		$this->refused( static fn() => $n->decide( 'approve', 1, 8, 5, 95, 'Versão antiga.' ) );
		$token = DecisionToken::make( $n, 8, str_repeat( 'x', 32 ) );
		self::assertTrue( DecisionToken::valid( $n, 8, str_repeat( 'x', 32 ), $token ) );
		self::assertFalse( DecisionToken::valid( $n, 9, str_repeat( 'x', 32 ), $token ) );
		$n = $n->decide( 'reject', 2, 8, 5, 95, 'Rejeitar.' );
		self::assertFalse( DecisionToken::valid( $n, 8, str_repeat( 'x', 32 ), $token ) );
		self::assertSame( NoticeState::Rejected, $n->state );
		$this->refused( static fn() => $n->submit( 7, 6, 96, 'Sem versão nova.' ) );
		$n = $n->revise( new NoticeContent( 'Corrigida', 'V3', Audience::unit( 'LAB-B' ), 'institutional-review', null ), 7, 6, 96, 'Corrigir.' )->submit( 7, 7, 97, 'Submeter V3.' )->decide( 'return', 3, 8, 8, 98, 'Devolver.' );
		$this->refused( static fn() => $n->submit( 7, 9, 99, 'Sem versão nova.' ) );
		$n = $n->revise( $two, 7, 9, 99, 'Nova versão.' )->submit( 7, 10, 100, 'Submeter V4.' )->decide( 'approve', 4, 8, 11, 101, 'Aprovar V4.' );
		self::assertSame( 'LAB-B', $n->published( 102 )->content->audience->unit_id );
		self::assertSame( 4, $n->published_version );
		self::assertSame( 12, $n->published_revision );
		self::assertEquals( $n, Notice::fromArray( $n->toArray() ) );
	}

	/** Persistence cannot invent a state or silently cast revision fields. */
	public function test_hydration_replays_history_and_rejects_forged_fields(): void {
		$n    = Notice::draft( 1, 7, new NoticeContent( 'A', 'B', Audience::all(), 'institutional-review', null ), 90 );
		$data = $n->toArray();
		foreach ( array( array( 'state', 'Published' ), array( 'revision', '1' ), array( 'revision', PHP_INT_MAX ), array( 'extra', true ), array( 'published_version', 1 ), array( 'author_id', 8 ) ) as $change ) {
			$bad               = $data;
			$bad[ $change[0] ] = $change[1];
			$this->refused( static fn() => Notice::fromArray( $bad ) );
		}
		$this->refused( static fn() => new NoticeContent( 'A', 'B', Audience::all(), 'team-direct', null ) );
	}

	/** A refused operation has no mutable effect.
	 *
	 * @param callable $operation Invalid operation.
	 */
	private function refused( callable $operation ): void {
		try {
			$operation();
			self::fail( 'Invalid transition accepted.' );
		} catch ( InvalidArgumentException ) {
			self::assertTrue( true );
		}
	}
}
