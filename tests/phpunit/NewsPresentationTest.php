<?php
/** News HTTP gates and reduced presentation.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\News\Article;
use Arn\Intranet\News\Asset;
use Arn\Intranet\News\Content;
use Arn\Intranet\News\NewsPortal;
use Arn\Intranet\News\NewsroomPage;
use Arn\Intranet\News\Version;
use PHPUnit\Framework\TestCase;

/** Server gates and reduced preview models. */
final class NewsPresentationTest extends TestCase {
	/** CSRF and malformed article claims stop before writes. */
	public function test_post_gate_rejects_csrf_and_forged_identifiers(): void {
		$base   = array(
			'operation'  => 'revise',
			'article_id' => '23',
			'_wpnonce'   => 'good',
		);
		$verify = static fn( string $nonce, string $action ): bool => 'good' === $nonce && 'arn_news_revise_23' === $action;
		$this->assertTrue( NewsroomPage::postGate( $base, 'POST', 'arn-newsroom', $verify ) );
		$this->assertFalse( NewsroomPage::postGate( array_replace( $base, array( '_wpnonce' => 'bad' ) ), 'POST', 'arn-newsroom', $verify ) );
		$this->assertFalse( NewsroomPage::postGate( array_replace( $base, array( 'article_id' => array( '23' ) ) ), 'POST', 'arn-newsroom', $verify ) );
		$this->assertFalse( NewsroomPage::postGate( $base, 'GET', 'arn-newsroom', $verify ) );
	}

	/** Pending editorial view carries only the submitted version. */
	public function test_editor_projection_contains_only_submitted_version(): void {
		$blob    = new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 100, 'image/png' );
		$asset   = new Asset( 'featured', $blob, 'Imagem de teste' );
		$v1      = new Version( 1, new Content( 'Texto anterior reservado', 'Corpo anterior', 'circular', null, array(), array() ), 7, 10 );
		$v2      = new Version( 2, new Content( 'Texto enviado', 'Corpo enviado', 'circular', $asset, array(), array() ), 7, 20 );
		$article = new Article( 33, 7, Scope::queues( array( 'editorial-ficticia' ) ), 3, array( $v1, $v2 ), 2, 30 );
		$model   = NewsPortal::project( $article, false, static fn( string $id ): string => '/media/' . $id );
		$this->assertSame( 'Texto enviado', $model['title'] );
		$this->assertSame( 2, $model['version'] );
		$this->assertSame( '/media/' . str_repeat( 'a', 32 ), $model['featured']['url'] );
		$this->assertSame( 'Corpo enviado', $model['body'] );
		$this->assertArrayNotHasKey( 'versions', $model );
		$draft = Article::create( 34, 7, Scope::queues( array( 'editorial-ficticia' ) ), $v1 );
		$this->assertNull( NewsPortal::project( $draft, false, static fn( string $id ): string => '/media/' . $id ) );
		$this->assertSame( 'Texto anterior reservado', NewsPortal::project( $draft, true, static fn( string $id ): string => '/media/' . $id )['title'] );
	}
}
