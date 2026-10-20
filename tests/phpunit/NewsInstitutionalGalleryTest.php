<?php
/** Institutional news and larger private galleries.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\News\{Asset, Content, DraftInput, MediaInput, NewsFormInput, NewsPortal, NewsPublishedPortal};
use PHPUnit\Framework\TestCase;

/** The closed category and gallery limits agree across domain and HTTP. */
final class NewsInstitutionalGalleryTest extends TestCase {

	/** Ten supplied photographs fit as a cover and nine ordered images. */
	public function test_institutional_article_with_large_gallery(): void {
		$asset   = new Asset( 'gallery', new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'image/jpeg' ), 'Imagem de teste' );
		$content = new Content( 'Notícia de teste', 'Texto fornecido', 'noticia-institucional', null, array_fill( 0, 12, $asset ), array() );
		self::assertCount( 12, $content->gallery );
		self::assertSame( 'Notícia institucional', NewsPortal::categories()['noticia-institucional'] );
		self::assertNotNull( NewsPublishedPortal::filters( array( 'category' => 'noticia-institucional' ) ) );
		$media = new MediaInput( str_repeat( 'a', 32 ), null, 'Imagem' );
		self::assertCount( 12, ( new DraftInput( 'Título', '', 'noticia-institucional', null, array_fill( 0, 12, $media ), array() ) )->gallery );
		$this->expectException( InvalidArgumentException::class );
		new Content( 'Título', '', 'noticia-institucional', null, array_fill( 0, 13, $asset ), array() );
	}

	/** HTTP retains the same bounded list and rejects overflow. */
	public function test_http_gallery_limit_and_category(): void {
		$post   = array(
			'title'    => 'Título',
			'body'     => 'Texto',
			'category' => 'noticia-institucional',
			'reason'   => 'Preparação local',
			'gallery'  => array_fill(
				0,
				12,
				array(
					'id'          => str_repeat( 'a', 32 ),
					'description' => 'Imagem',
				)
			),
		);
		$result = NewsFormInput::validate( $post, array() );
		self::assertSame( array(), $result['errors'] );
		self::assertCount( 12, $result['draft']->gallery );
		$post['gallery'][] = $post['gallery'][0];
		self::assertArrayHasKey( 'gallery', NewsFormInput::validate( $post, array() )['errors'] );
	}
}
