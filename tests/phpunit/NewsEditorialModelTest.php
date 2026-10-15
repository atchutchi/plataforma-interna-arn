<?php
/**
 * Editorial aggregate contract tests.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Tests;

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Audience;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\EditorialTransition;
use Arn\Intranet\News\NewsState;
use Arn\Intranet\News\Version;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

	/** Verify editorial state transitions and audience invariants. */
final class NewsEditorialModelTest extends TestCase {
	/** Build a complete immutable version.
	 *
	 * @param int           $number Version number.
	 * @param Audience|null $audience Explicit audience.
	 * @param string        $body Article text.
	 * @param int|null      $at Creation timestamp.
	 */
	private function version( int $number, ?Audience $audience, string $body = 'Corpo', ?int $at = null ): Version {
		$asset = new Asset( 'featured', new BlobDescriptor( str_repeat( (string) $number, 32 ), str_repeat( 'a', 64 ), 1, 'image/png' ), 'Imagem' );
		return new Version( $number, new Content( 'Título', $body, 'circular', $asset, array(), array(), $audience ), 2, $at ?? 10 + $number );
	}

	/** Build the initial draft with an explicit unit audience. */
	private function draft(): Article {
		return Article::create( 7, 2, Scope::queues( array( 'editorial-ficticia' ) ), $this->version( 1, Audience::units( array( 'LAB-A' ) ) ), 'Criação' );
	}

	/** Prior publication survives private replacement attempts. */
	public function test_decision_cycle_keeps_prior_publication_until_new_approval(): void {
		$initial = $this->draft();
		$first   = $initial->submit( 1, 12, 'Envio' )->reject( 2, 1, 3, 13, 'Corrigir' );
		self::assertSame( NewsState::Rejected, $first->state );
		$second = $first->revise( $this->version( 2, Audience::units( array( 'LAB-A' ) ), 'Corpo', 14 ), 3, 'Revisão' )->submit( 4, 15, 'Reenvio' )->approve( 5, 2, 3, 16, 'Aprovação' );
		self::assertSame( NewsState::Published, $second->state );
		self::assertSame( 2, $second->published_version );
		self::assertSame( 16, $second->published_at );
		self::assertSame( 3, $second->approved_by );
		$next = $second->revise( $this->version( 3, Audience::people( array( 9 ) ), 'Novo texto', 17 ), 6, 'Nova revisão' );
		self::assertSame( 2, $next->published_version );
		self::assertSame( NewsState::Draft, $next->state );
		$pending = $next->submit( 7, 18, 'Novo envio' );
		self::assertSame( 2, $pending->published_version );
		$rejected = $pending->reject( 8, 3, 3, 19, 'De novo' );
		self::assertSame( 2, $rejected->published_version );
		self::assertSame( 3, $rejected->submitted_version );
		self::assertSame( 18, $rejected->submitted_at );
		self::assertSame( 3, $rejected->transitions[8]->version );
		self::assertSame( 'De novo', $rejected->transitions[8]->reason );
		self::assertSame( 'De novo', $rejected->lastDecision()->reason );
		self::assertSame( 9, $rejected->revision );
		self::assertSame( 1, $initial->revision );
	}

	/** Returning a version requires a new one before re-submission. */
	public function test_return_requires_new_version_and_archive_is_terminal(): void {
		$pending  = $this->draft()->submit( 1, 12 );
		$returned = $pending->returnForEditing( 2, 1, 3, 13, 'Rever' );
		self::assertSame( NewsState::Draft, $returned->state );
		$this->expectException( InvalidArgumentException::class );
		$returned->submit( 3, 14 );
	}

	/** Archiving retains the audit and rejects further author edits. */
	public function test_archive_preserves_history_and_blocks_changes(): void {
		$published = $this->draft()->submit( 1, 12 )->approve( 2, 1, 3, 13, 'Aprovado' );
		$archived  = $published->archive( 3, 1, 3, 14, 'Retirado' );
		self::assertSame( NewsState::Archived, $archived->state );
		self::assertSame( 1, $archived->published_version );
		self::assertCount( 4, $archived->transitions );
		$this->expectException( InvalidArgumentException::class );
		$archived->revise( $this->version( 2, Audience::all() ), 4 );
	}

	/** Legacy pending content cannot acquire invented recipients. */
	public function test_legacy_pending_without_audience_can_be_returned_but_not_approved(): void {
		$legacy = new Article( 7, 2, Scope::queues( array( 'editorial-ficticia' ) ), 2, array( $this->version( 1, null ) ), 1, 12 );
		self::assertSame( NewsState::Pending, $legacy->state );
		self::assertNull( $legacy->version( 1 )->content->audience );
		self::assertSame( NewsState::Draft, $legacy->returnForEditing( 2, 1, 3, 13, 'Definir audiência' )->state );
		$this->expectException( InvalidArgumentException::class );
		$legacy->approve( 2, 1, 3, 13, 'Aprovar' );
	}

	/** Contradictory audit and stale revisions are rejected. */
	public function test_rejects_forged_history_and_wrong_cas(): void {
		$draft = $this->draft();
		$bad   = array( new EditorialTransition( 1, 'created', 1, 2, 11, '' ), new EditorialTransition( 2, 'approved', 1, 3, 12, '' ) );
		try {
			new Article( 7, 2, Scope::queues( array( 'editorial-ficticia' ) ), 2, array( $this->version( 1, Audience::all() ) ), null, null, $bad );
			self::fail( 'Histórico contraditório aceite.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertNotSame( '', $exception->getMessage() );
		}
		$this->expectException( InvalidArgumentException::class );
		$draft->submit( 0, 12 );
	}

	/** Audience targets remain exact despite caller reference mutation. */
	public function test_audience_validates_lists_and_detaches_php_references(): void {
		$id     = 9;
		$people = Audience::people( array( &$id ) );
		$id     = 10;
		self::assertTrue( $people->includes( 9, array() ) );
		self::assertFalse( $people->includes( 10, array() ) );
		self::assertSame( array( 'news_person_9' ), $people->permissionScope()->targets );
		self::assertSame( array( 'news_all' ), Audience::all()->permissionScope()->targets );
		self::assertTrue( Audience::units( array( 'LAB-A' ) )->includes( 8, array( 'LAB-A' ) ) );
		foreach ( array( array( 1 => 9 ), array( 9, 9 ), array( '9' ), array( 0 ) ) as $invalid ) {
			try {
				Audience::people( $invalid );
				self::fail( 'Lista inválida aceite.' );
			} catch ( InvalidArgumentException $exception ) {
				self::assertNotSame( '', $exception->getMessage() );
			}
		}
	}

	/** Hydrated arrays cannot retain caller element references. */
	public function test_hydrated_version_and_transition_arrays_detach_references(): void {
		$original    = $this->version( 1, Audience::all() );
		$replacement = $this->version( 1, Audience::people( array( 9 ) ) );
		$versions    = array( &$original );
		$created     = new EditorialTransition( 1, 'created', 1, 2, 11, 'Criação' );
		$transition  = $created;
		$history     = array( &$transition );
		$article     = new Article( 7, 2, Scope::queues( array( 'editorial-ficticia' ) ), 1, $versions, null, null, $history );
		$original    = $replacement;
		$transition  = new EditorialTransition( 1, 'created', 1, 2, 11, 'Alterado' );
		self::assertSame( array( 'news_all' ), $article->version( 1 )->content->audience->permissionScope()->targets );
		self::assertSame( 'Criação', $article->transitions[0]->reason );
	}

	/** An approval cannot skip a prior submission or use the author's account. */
	public function test_invalid_actor_and_timestamp_cannot_be_hydrated(): void {
		$version = $this->version( 1, Audience::all() );
		$bad     = array(
			new EditorialTransition( 1, 'created', 1, 2, 11, '' ),
			new EditorialTransition( 2, 'submitted', 1, 2, 12, '' ),
			new EditorialTransition( 3, 'approved', 1, 2, 13, '' ),
		);
		try {
			new Article( 7, 2, Scope::queues( array( 'editorial-ficticia' ) ), 3, array( $version ), 1, 12, $bad );
			self::fail( 'Auto-decisão aceite.' );
		} catch ( InvalidArgumentException $exception ) {
			self::assertNotSame( '', $exception->getMessage() );
		}
		$pending = $this->draft()->submit( 1, 12 );
		$this->expectException( InvalidArgumentException::class );
		$pending->approve( 2, 1, 3, 11, 'Tempo anterior' );
	}

	/** An explicitly supplied empty audit is never treated as legacy input. */
	public function test_explicit_empty_history_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		new Article( 7, 2, Scope::queues( array( 'editorial-ficticia' ) ), 1, array( $this->version( 1, Audience::all() ) ), null, null, array() );
	}
}
