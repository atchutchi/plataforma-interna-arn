<?php
/** CMS editorial contracts.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Audience;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\EditorialTransition;
use Arn\Intranet\News\NewsDecisionForm;
use Arn\Intranet\News\NewsAudiencePresets;
use Arn\Intranet\News\NewsFormInput;
use Arn\Intranet\News\LocalNewsRoster;
use Arn\Intranet\News\NewsroomFilters;
use Arn\Intranet\News\NewsroomProjection;
use Arn\Intranet\News\Version;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use PHPUnit\Framework\TestCase;

/** Assert the browser sees only authorised editorial projections. */
final class NewsEditorialCmsTest extends TestCase {
	/** Submitted version with a later private draft. */
	private function article(): Article {
		$featured   = new Asset( 'featured', new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 10, 'image/png' ), 'Imagem' );
		$first      = new Version( 1, new Content( 'Versão enviada', 'Texto enviado', 'circular', $featured, array(), array(), Audience::all() ), 7, 10 );
		$next_asset = new Asset( 'featured', new BlobDescriptor( str_repeat( 'c', 32 ), str_repeat( 'd', 64 ), 10, 'image/png' ), 'Imagem privada' );
		$second     = new Version( 2, new Content( 'Título privado', 'Texto privado', 'despacho', $next_asset, array(), array(), Audience::units( array( 'LAB-B' ) ) ), 7, 40 );
		return new Article(
			31,
			7,
			Scope::queues( array( 'editorial-ficticia' ) ),
			4,
			array( $first, $second ),
			1,
			20,
			array(
				new EditorialTransition( 1, 'created', 1, 7, 10, 'Início' ),
				new EditorialTransition( 2, 'submitted', 1, 7, 20, 'Envio' ),
				new EditorialTransition( 3, 'approved', 1, 8, 30, 'Aprovação' ),
				new EditorialTransition( 4, 'revised', 2, 7, 40, 'Correcção' ),
			)
		);
	}

	/** A reviewer cannot infer later draft details through rows or filters. */
	public function testEditorProjectionAndFiltersNeverExposePrivateDraft(): void {
		$article = $this->article();
		$editor  = NewsroomProjection::article( $article, false );
		$author  = NewsroomProjection::article( $article, true );
		self::assertSame( 'Versão enviada', $editor['title'] );
		self::assertSame( 'published', $editor['state'] );
		self::assertSame( 1, $editor['version'] );
		self::assertStringNotContainsString( 'Título privado', (string) json_encode( $editor ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Plain PHPUnit process without WordPress.
		self::assertArrayNotHasKey( 'revision', $editor );
		self::assertSame( 'Título privado', $author['title'] );
		self::assertSame( 'draft', $author['state'] );
		$filters = NewsroomFilters::parse(
			array(
				'arn_author' => '7',
				'arn_state'  => 'published',
				'arn_from'   => '1970-01-01',
				'arn_to'     => '1970-01-01',
				'arn_paged'  => '1',
			)
		);
		self::assertCount( 1, NewsroomFilters::apply( array( $editor ), $filters ) );
		self::assertCount( 0, NewsroomFilters::apply( array( $author ), $filters ) );
	}

	/** Archiving the current publication remains visible despite a later private draft. */
	public function testArchiveStateComesFromVisiblePublicationEvent(): void {
		$archived = $this->article()->archive( 4, 1, 8, 50, 'Retirada' );
		$editor   = NewsroomProjection::article( $archived, false );
		self::assertSame( 'archived', $editor['state'] );
		self::assertSame( 'Versão enviada', $editor['title'] );
		self::assertSame( 1, $editor['version'] );
	}

	/** Archival of the older live publication wins over a later rejected submission. */
	public function testArchivalStateWinsAfterDifferentSubmittedVersion(): void {
		$article = $this->article()->submit( 4, 50, 'Reenvio' )->reject( 5, 2, 8, 60, 'Rejeição' )->archive( 6, 1, 8, 70, 'Retirada' );
		$editor  = NewsroomProjection::article( $article, false );
		self::assertSame( 2, $editor['version'] );
		self::assertSame( 'archived', $editor['state'] );
	}

	/** Invalid dates and structured values close the query. */
	public function testFiltersRejectMalformedInput(): void {
		foreach ( array(
			array( 'arn_from' => '2026-02-30' ),
			array( 'arn_to' => array( '2026-01-01' ) ),
			array( 'arn_author' => '0' ),
			array( 'arn_state' => 'private' ),
			array( 'arn_paged' => '0' ),
			array(
				'arn_from' => '2026-02-02',
				'arn_to'   => '2026-02-01',
			),
		) as $get ) {
			self::assertNull( NewsroomFilters::parse( $get ) );
		}
	}

	/** A token cannot be replayed across decisions, actors or revisions. */
	public function testDecisionTokenBindsActorObjectOperationVersionAndRevision(): void {
		$article = $this->article();
		$token   = NewsDecisionForm::token( $article, 8, 'archive', 1, 'test-key' );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{64}$/D', $token );
		self::assertTrue( NewsDecisionForm::verify( $token, $article, 8, 'archive', 1, 'test-key' ) );
		self::assertFalse( NewsDecisionForm::verify( $token, $article, 9, 'archive', 1, 'test-key' ) );
		self::assertFalse( NewsDecisionForm::verify( $token, $article, 8, 'approve', 1, 'test-key' ) );
		self::assertFalse( NewsDecisionForm::verify( $token, $article, 8, 'archive', 2, 'test-key' ) );
		self::assertFalse( NewsDecisionForm::verify( $token, $article->revise( new Version( 3, new Content( 'Outro', '', 'circular', null, array(), array() ), 7, 50 ), 4, 'Outra' ), 8, 'archive', 1, 'test-key' ) );
	}

	/** The draft receives a server-resolved audience only. */
	public function testAudiencePresetIsServerResolvedAndUnknownIsFieldError(): void {
		$base     = array(
			'title'    => 'Título',
			'body'     => '',
			'category' => 'circular',
			'reason'   => 'Criar',
		);
		$resolver = static fn( string $value ): ?Audience => 'lab-all' === $value ? Audience::all() : null;
		self::assertNull( NewsFormInput::validate( $base, array(), $resolver )['draft']->audience );
		self::assertSame( 'all', NewsFormInput::validate( $base + array( 'audience_preset' => 'lab-all' ), array(), $resolver )['draft']->audience->kind );
		self::assertArrayHasKey( 'audience_preset', NewsFormInput::validate( $base + array( 'audience_preset' => 'forjado' ), array(), $resolver )['errors'] );
		self::assertArrayHasKey( 'form', NewsFormInput::validate( $base + array( 'audience_ids' => array( 8 ) ), array(), $resolver )['errors'] );
	}

	/** Presets match the signed-off four HTTP names, with a trusted roster ID. */
	public function testClosedPresetNamesAndServerResolvedPersonA(): void {
		$expected = array( '', 'lab-all', 'lab-unit-a', 'lab-unit-b', 'lab-person-a' );
		self::assertSame( $expected, array_keys( NewsAudiencePresets::labels() ) );
		self::assertSame( 'all', NewsAudiencePresets::resolve( 'lab-all' )->kind );
		self::assertSame( array( 'LAB-A' ), NewsAudiencePresets::resolve( 'lab-unit-a' )->targets );
		$ids    = array(
			'autor.noticias.teste'         => 7,
			'editor.noticias.teste'        => 8,
			'editor.suplente.teste'        => 9,
			'colaborador.noticias.a.teste' => 10,
			'colaborador.noticias.b.teste' => 11,
		);
		$roster = new LocalNewsRoster(
			static fn( string $login ): ?array => isset( $ids[ $login ] ) ? array(
				'id'     => $ids[ $login ],
				'login'  => $login,
				'marked' => true,
			) : null
		);
		self::assertSame( 10, $roster->personAId() );
		self::assertSame( array( 10 ), NewsAudiencePresets::resolve( 'lab-person-a', $roster )->targets );
		self::assertSame( 'lab-person-a', NewsAudiencePresets::selected( Audience::people( array( 10 ) ), $roster ) );
		self::assertNull( NewsAudiencePresets::resolve( 'other', $roster ) );
	}

	/** Audit rows from the real repository are stdClass and contain a private revision. */
	public function testEditorHistoryOmitsPrivateEventAndRevisionCounter(): void {
		$article = $this->article();
		$rows    = array(
			(object) array(
				'revision'    => 2,
				'version'     => 1,
				'event'       => 'submitted',
				'actor_id'    => 7,
				'occurred_at' => 20,
				'reason'      => 'Envio',
			),
			(object) array(
				'revision'    => 3,
				'version'     => 1,
				'event'       => 'approved',
				'actor_id'    => 8,
				'occurred_at' => 30,
				'reason'      => 'Aprovada',
			),
			(object) array(
				'revision'    => 4,
				'version'     => 2,
				'event'       => 'revised',
				'actor_id'    => 7,
				'occurred_at' => 40,
				'reason'      => 'Motivo privado',
			),
		);
		$history = NewsroomProjection::history( $rows, $article, false );
		self::assertCount( 2, $history );
		self::assertSame( 'approved', $history[1]->event );
		self::assertObjectNotHasProperty( 'revision', $history[1] );
		self::assertSame( array( 'Envio', 'Aprovada' ), array_map( static fn( object $row ): string => $row->reason, $history ) );
	}
}
