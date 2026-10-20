<?php
/**
 * Contratos da fronteira nativa, sem substituir o ensaio WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Tests;

use Arn\Intranet\Documents\PrivateFiles\NativeMediaBoundary;
use PHPUnit\Framework\TestCase;
use WP_Error;

/** Apenas os caminhos privados dos módulos podem aceitar uploads. */
final class NativeMediaBoundaryTest extends TestCase {

	/** A recusa conserva os caminhos e não inventa uma pasta pública alternativa. */
	public function testUploadsFailWithoutRewritingPaths(): void {
		$input  = array(
			'basedir'  => '/public/uploads',
			'tmp_name' => '/tmp/fixture',
			'error'    => 0,
		);
		$output = NativeMediaBoundary::refuseUpload( $input );
		self::assertSame( NativeMediaBoundary::MESSAGE, $output['error'] );
		unset( $input['error'], $output['error'] );
		self::assertSame( $input, $output );
	}

	/** Não bloquear os CPT privados, páginas ou decisões de recusa anteriores. */
	public function testOnlyAttachmentsAreRefusedAtInsertion(): void {
		self::assertTrue( NativeMediaBoundary::refuseAttachment( false, array( 'post_type' => 'attachment' ) ) );
		foreach ( array( 'post', 'page', 'arn_noticia', 'arn_documento' ) as $type ) {
			self::assertFalse( NativeMediaBoundary::refuseAttachment( false, array( 'post_type' => $type ) ) );
			self::assertTrue( NativeMediaBoundary::refuseAttachment( true, array( 'post_type' => $type ) ) );
		}
	}

	/** Nem as capacidades de superadministrador abrem os uploads. */
	public function testNoUploadCapabilityException(): void {
		foreach ( array( 'upload_files', 'unfiltered_upload', 'export' ) as $cap ) {
			foreach ( array( 0, 1, 99 ) as $id ) {
				self::assertSame( array( 'do_not_allow' ), NativeMediaBoundary::capabilities( array( 'manage_options' ), $cap, $id, array() ) );
			}
		}
		self::assertSame( array( 'read' ), NativeMediaBoundary::capabilities( array( 'read' ), 'read', 1, array() ) );
	}

	/** Índice, regex de detalhe e variações de maiúsculas fecham o mesmo recurso. */
	public function testRestBoundaryDoesNotCloseOtherModules(): void {
		$blocked = array( '/wp/v2/media', '/wp/v2/media/', '/wp/v2/media/(?P<id>[\d]+)', '/WP/v2/MEDIA/5/edit', '/wp/v2/types/attachment' );
		$allowed = array( '/wp/v2/pages', '/wp/v2/media-library', '/arn/v1/files', '/wp/v2/users/me' );
		foreach ( $blocked as $route ) {
			self::assertTrue( NativeMediaBoundary::isMediaRoute( $route ) );
		}
		foreach ( $allowed as $route ) {
			self::assertFalse( NativeMediaBoundary::isMediaRoute( $route ) );
		}
		self::assertSame( array_fill_keys( $allowed, array() ), NativeMediaBoundary::restEndpoints( array_fill_keys( array_merge( $blocked, $allowed ), array() ) ) );
	}

	/** Rest dispatch interno recebe a mesma recusa sem conhecer o objecto. */
	public function testInternalRestDispatchIsClosed(): void {
		$request = new class() {
			/** Rota de fixture. */
			public function get_route(): string {
				return '/wp/v2/media/123';
			}
		};
		$result  = NativeMediaBoundary::restRequest( null, new \stdClass(), $request );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( array( 'status' => 404 ), $result->get_error_data() );
	}

	/** Não deixar get_posts omitir o filtro de attachments nem alterar consultas normais. */
	public function testMixedQueriesCannotSuppressTheBoundary(): void {
		foreach ( array( '', 'any', 'attachment', array( 'post', 'attachment' ), 'page', array( 'arn_noticia' ) ) as $type ) {
			$query = new class($type) {
				/** Estado do filtro da consulta fictícia.
				 *
				 * @var bool
				 */
				public bool $suppressed = true;
				/**
				 * Prepara a consulta fictícia.
				 *
				 * @param mixed $type Tipo explícito ou implícito.
				 */
				public function __construct( private mixed $type ) {}
				/**
				 * Lê um argumento.
				 *
				 * @param string $key Chave da consulta.
				 * @return mixed Valor fictício.
				 */
				public function get( string $key ): mixed {
					return 'post_type' === $key ? $this->type : null;
				}
				/**
				 * Regista a alteração do filtro.
				 *
				 * @param string $key Chave da consulta.
				 * @param bool   $value Novo estado.
				 */
				public function set( string $key, bool $value ): void {
					$this->suppressed = $value;
				}
			};
			NativeMediaBoundary::filterQueries( $query );
			self::assertSame( 'page' === $type || array( 'arn_noticia' ) === $type, $query->suppressed );
		}
	}

	/** A recusa de oEmbed cobre o objecto resolvido directamente. */
	public function testEmbeddingDoesNotDiscloseAttachments(): void {
		self::assertFalse( NativeMediaBoundary::embeddable( true, (object) array( 'post_type' => 'attachment' ) ) );
		self::assertTrue( NativeMediaBoundary::embeddable( true, (object) array( 'post_type' => 'page' ) ) );
		self::assertFalse( NativeMediaBoundary::embeddable( false, (object) array( 'post_type' => 'page' ) ) );
	}
}
