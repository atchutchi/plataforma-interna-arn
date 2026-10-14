<?php
/**
 * Testes do armazenamento privado.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

// phpcs:disable Squiz.Commenting.ClassComment, Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment, WordPress.WP.AlternativeFunctions, WordPress.PHP.NoSilencedErrors, Generic.CodeAnalysis.UnusedFunctionParameter

use Arn\Intranet\Documents\PrivateFiles\PrivateBlobStore;
use Arn\Intranet\Documents\PrivateFiles\PrivateFileDelivery;
use Arn\Intranet\Documents\PrivateFiles\BlobDescriptor;
use Arn\Intranet\Documents\PrivateFiles\FileRecord;
use PHPUnit\Framework\TestCase;

final class PrivateFilesTest extends TestCase {
	private string $base;

	protected function setUp(): void {
		$this->base = sys_get_temp_dir() . '/arn-private-' . bin2hex( random_bytes( 5 ) );
		mkdir( $this->base );
		mkdir( $this->base . '/private' );
		mkdir( $this->base . '/public' );
	}

	protected function tearDown(): void {
		if ( is_link( $this->base . '/link' ) ) {
			unlink( $this->base . '/link' ); }
		foreach ( glob( $this->base . '/private/*' ) ? glob( $this->base . '/private/*' ) : array() as $file ) {
			unlink( $file );
		}
		foreach ( glob( $this->base . '/public/*' ) ? glob( $this->base . '/public/*' ) : array() as $file ) {
			unlink( $file );
		}
		rmdir( $this->base . '/private' );
		rmdir( $this->base . '/public' );
		rmdir( $this->base );
	}

	public function testRejectsOverlappingRoots(): void {
		foreach ( array( array( $this->base, $this->base . '/public' ), array( $this->base . '/public', $this->base . '/public' ), array( $this->base . '/private', $this->base ) ) as $paths ) {
			try {
				new PrivateBlobStore( $paths[0], array( $paths[1] ), array( 'application/pdf' ), 100 );
				self::fail( 'Sobreposição aceite.' );
			} catch ( RuntimeException ) {
				self::assertTrue( true );
			}
		}
	}

	public function testRejectsLinkedRoot(): void {
		if ( ! @symlink( $this->base . '/private', $this->base . '/link' ) ) {
			self::markTestSkipped( 'Symlink indisponível.' );
		}
		$this->expectException( RuntimeException::class );
		new PrivateBlobStore( $this->base . '/link', array( $this->base . '/public' ), array( 'application/pdf' ), 100 );
	}

	public function testImportAndDeliveryRejectMutationAndAuthorizeFirst(): void {
		$source = $this->base . '/public/source.pdf';
		file_put_contents( $source, "%PDF-1.4\n%%EOF\n" );
		$store = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'application/pdf' ), 100 );
		$blob  = $store->importFile( $source );
		self::assertMatchesRegularExpression( '/^[a-f0-9]{32}$/', $blob->id );
		$record  = new FileRecord( $blob, 'local_document', 1, 2, 'original', 1, time() );
		$called  = false;
		$service = new PrivateFileDelivery(
			$store,
			static fn ( string $id ): ?FileRecord => $record,
			static function ( FileRecord $candidate ) use ( &$called ): bool {
				$called = true;
				return false;
			}
		);
		self::assertNull( $service->open( $blob->id ) );
		self::assertTrue( $called );
		unlink( $this->base . '/private/' . $blob->id );
		$service = new PrivateFileDelivery(
			$store,
			static fn ( string $id ): ?FileRecord => $record,
			function ( FileRecord $candidate ) use ( $blob ): bool {
				file_put_contents( $this->base . '/private/' . $blob->id, "%PDF-1.4\n%%EOF\n" );
				return true;
			}
		);
		$result  = $service->open( $blob->id );
		self::assertSame( $record, $result['record'] );
		fclose( $result['handle'] );
		file_put_contents( $this->base . '/private/' . $blob->id, 'corrupt' );
		$service = new PrivateFileDelivery( $store, static fn ( string $id ): ?FileRecord => $record, static fn ( FileRecord $candidate ): bool => true );
		self::assertNull( $service->open( $blob->id ) );
	}

	public function testRejectsGrowthBeyondLimit(): void {
		$source = $this->base . '/public/large.pdf';
		file_put_contents( $source, "%PDF-1.4\n%%EOF\n" );
		$store = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'application/pdf' ), 100 );
		file_put_contents( $source, str_repeat( 'a', 101 ), FILE_APPEND );
		$this->expectException( RuntimeException::class );
		$store->importFile( $source );
	}

	public function testRejectsInvalidDescriptorsAndTraversalIds(): void {
		$store = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'application/pdf' ), 100 );
		foreach ( array( '../a', str_repeat( 'A', 32 ), str_repeat( 'a', 31 ) ) as $bad_id ) {
			$delivery = new PrivateFileDelivery(
				$store,
				static function (): never {
					self::fail( 'Resolvedor invocado.' );
				},
				static fn (): bool => true
			);
			self::assertNull( $delivery->open( $bad_id ) );
			try {
				new BlobDescriptor( $bad_id, str_repeat( 'a', 64 ), 1, 'application/pdf' );
				self::fail( 'Descritor inválido aceite.' );
			} catch ( RuntimeException ) {
				self::assertTrue( true );
			}
		}
		$blob = new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'application/pdf' );
		foreach ( array( array( '', 1, 1, 'original', 1, 1 ), array( 'doc', 0, 1, 'original', 1, 1 ), array( 'doc', 1, 0, 'original', 1, 1 ), array( 'doc', 1, 1, 'unknown', 1, 1 ), array( 'doc', 1, 1, 'original', 0, 1 ), array( 'doc', 1, 1, 'original', 1, 0 ) ) as $parts ) {
			try {
				new FileRecord( $blob, ...$parts );
				self::fail( 'Referência inválida aceite.' );
			} catch ( RuntimeException ) {
				self::assertTrue( true );
			}
		}
	}

	public function testDeliveryFailsClosedForResolverAndAuthorization(): void {
		$store  = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'application/pdf' ), 100 );
		$blob   = new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 1, 'application/pdf' );
		$record = new FileRecord( $blob, 'doc', 1, 1, 'original', 1, 1 );
		$cases  = array(
			array(
				static function (): never {
										throw new RuntimeException( 'falha' ); },
				static fn (): bool => true,
			),
			array( static fn (): string => 'incorrecto', static fn (): bool => true ),
			array(
				static fn (): FileRecord => $record,
				static function (): never {
									throw new RuntimeException( 'falha' ); },
			),
			array( static fn (): FileRecord => $record, static fn (): string => 'true' ),
		);
		foreach ( $cases as $case ) {
			self::assertNull( ( new PrivateFileDelivery( $store, ...$case ) )->open( $blob->id ) );
		}
	}

	public function testRecognizesValidPngAndJpeg(): void {
		$fixtures = array(
			'image/png'  => 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGNgYGAAAAAEAAH2FzhVAAAAAElFTkSuQmCC',
			'image/jpeg' => '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD5/ooooA//2Q==',
		);
		$store    = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array_keys( $fixtures ), 1000 );
		foreach ( $fixtures as $mime => $encoded ) {
			$source = $this->base . '/public/fixture-' . str_replace( '/', '-', $mime );
			file_put_contents( $source, base64_decode( $encoded, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Fixtures sintéticas de imagem.
			$blob = $store->importFile( $source );
			self::assertSame( $mime, $blob->mime );
			$handle = $store->open( $blob );
			self::assertSame( $blob->size, strlen( stream_get_contents( $handle ) ) );
			fclose( $handle );
		}
	}

	public function testMimeRequiresRecognitionAndSignature(): void {
		$source = $this->base . '/public/fake.png';
		file_put_contents( $source, "\x89PNG\r\n\x1a\nFAKEIEND\xaeB`\x82" );
		$store = new PrivateBlobStore( $this->base . '/private', array( $this->base . '/public' ), array( 'image/png' ), 1000 );
		try {
			$store->importFile( $source );
			self::fail( 'Assinatura incompleta aceite.' );
		} catch ( RuntimeException ) {
			self::assertSame( array(), glob( $this->base . '/private/*' ) );
		}
	}
}
