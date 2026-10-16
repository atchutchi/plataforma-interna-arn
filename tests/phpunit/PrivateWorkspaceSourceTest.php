<?php
/**
 * Leitura de origem privada com hashes dos mesmos bytes.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\Identity\PreRegistration\PrivateWorkspaceSource;
use Arn\Intranet\Tests\Doubles\WorkspaceInventoryFixture;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/doubles/WorkspaceInventoryFixture.php';
// phpcs:disable WordPress.WP.AlternativeFunctions -- Ficheiros temporários próprios dos ensaios puros.
/**
 * Nunca usa a fonte privada verdadeira nos testes.
 */
final class PrivateWorkspaceSourceTest extends TestCase {
	/**
	 * Raiz temporária exclusiva.
	 *
	 * @var string
	 */
	private string $root;
	/**
	 * Fonte fictícia gerada.
	 *
	 * @var array
	 */
	private array $fixture;

	/** Prepara uma pasta privada e duas fontes fictícias. */
	protected function setUp(): void {
		$this->root = sys_get_temp_dir() . '/arn_workspace_' . bin2hex( random_bytes( 6 ) );
		foreach ( array( '', '/private', '/public', '/repo' ) as $suffix ) {
			mkdir( $this->root . $suffix, 0700 ); }
		WorkspaceInventoryFixture::protect( $this->root . '/private' );
		$this->fixture = WorkspaceInventoryFixture::make();
		foreach ( array( 'inventory', 'source' ) as $kind ) {
			file_put_contents( $this->root . '/private/' . $kind . '.json', json_encode( $this->fixture[ $kind ], JSON_THROW_ON_ERROR ) );
			chmod( $this->root . '/private/' . $kind . '.json', 0600 );
		}
	}

	/** Remove exclusivamente as entradas conhecidas da fixture. */
	protected function tearDown(): void {
		foreach ( array( '/private/inventory.json', '/private/source.json', '/public/source.json' ) as $suffix ) {
			if ( file_exists( $this->root . $suffix ) ) {
				unlink( $this->root . $suffix ); }
		}
		foreach ( array( '/private', '/public', '/repo', '' ) as $suffix ) {
			rmdir( $this->root . $suffix ); }
	}
	/**
	 * As duas fontes provadas geram a selecção exacta.
	 */
	public function testReadsOnlyBoundedPrivateFilesWithMatchingDigests(): void {
		$reader = new PrivateWorkspaceSource( $this->root . '/private', $this->root . '/public', array( $this->root . '/repo' ) );
		$batch  = $reader->read( $this->root . '/private/inventory.json', $this->root . '/private/source.json', $this->fixture['inventory_hash'], $this->fixture['source_hash'], 'example.invalid', 73, 1894705200 );
		self::assertCount( 73, $batch->entries );
	}
	/**
	 * Um hash adverso não revela o conteúdo privado.
	 */
	public function testWrongDigestHasOnlyGenericError(): void {
		$reader = new PrivateWorkspaceSource( $this->root . '/private', $this->root . '/public', array( $this->root . '/repo' ) );
		$this->expectExceptionMessage( 'Fonte privada Workspace indisponível.' );
		$reader->read( $this->root . '/private/inventory.json', $this->root . '/private/source.json', str_repeat( '0', 64 ), $this->fixture['source_hash'], 'example.invalid', 73, 1894705200 );
	}

	/** Nem uma selecção válida pode residir dentro de um checkout. */
	public function testGitMarkerInsidePrivateRootIsRejected(): void {
		file_put_contents( $this->root . '/private/.git', 'fixture-only' );
		try {
			$reader = new PrivateWorkspaceSource( $this->root . '/private', $this->root . '/public', array( $this->root . '/repo' ) );
			$this->expectExceptionMessage( 'Fonte privada Workspace indisponível.' );
			$reader->read( $this->root . '/private/inventory.json', $this->root . '/private/source.json', $this->fixture['inventory_hash'], $this->fixture['source_hash'], 'example.invalid', 73, 1894705200 );
		} finally {
			unlink( $this->root . '/private/.git' );
		}
	}

	/** Uma raiz privada configurada dentro do document root é recusada. */
	public function testPrivateRootMayNotBeThePublicRoot(): void {
		$reader = new PrivateWorkspaceSource( $this->root . '/private', $this->root . '/private', array( $this->root . '/repo' ) );
		$this->expectExceptionMessage( 'Fonte privada Workspace indisponível.' );
		$reader->read( $this->root . '/private/inventory.json', $this->root . '/private/source.json', $this->fixture['inventory_hash'], $this->fixture['source_hash'], 'example.invalid', 73, 1894705200 );
	}

	/** O ponto estático não escolhe uma pasta privada por defeito. */
	public function testLoadRequiresExplicitPrivateRootConfiguration(): void {
		$this->expectExceptionMessage( 'Fonte privada Workspace indisponível.' );
		PrivateWorkspaceSource::load( $this->root . '/private/inventory.json', $this->root . '/private/source.json', $this->fixture['inventory_hash'], $this->fixture['source_hash'], 'example.invalid', 73, 1894705200 );
	}
	/**
	 * Recusa fonte fora da raiz privada e ficheiro excessivo.
	 */
	public function testOutsidePrivateRootAndOversizedFileAreRejected(): void {
		$reader = new PrivateWorkspaceSource( $this->root . '/private', $this->root . '/public', array( $this->root . '/repo' ) );
		copy( $this->root . '/private/source.json', $this->root . '/public/source.json' );
		foreach ( array( $this->root . '/public/source.json', $this->root . '/private/source.json' ) as $file ) {
			if ( str_contains( $file, '/private/' ) ) {
				file_put_contents( $file, str_repeat( 'x', 524289 ) ); }
			try {
				$reader->read( $this->root . '/private/inventory.json', $file, $this->fixture['inventory_hash'], $this->fixture['source_hash'], 'example.invalid', 73, 1894705200 );
				self::fail( 'Fonte fora do contrato aceite.' );
			} catch ( RuntimeException $error ) {
				self::assertSame( 'Fonte privada Workspace indisponível.', $error->getMessage() ); }
		}
	}
}
