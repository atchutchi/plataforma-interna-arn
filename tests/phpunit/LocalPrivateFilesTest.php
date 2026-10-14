<?php
/** Ensaios da composição local de ficheiros privados.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Documents\PrivateFiles;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Fixtures temporárias locais exigem operações PHP directas nos testes puros.

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Authorization\Scope;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;
use Arn\Intranet\Authorization\Storage\StoredGrant;
use PHPUnit\Framework\TestCase;

/** Verifica as fronteiras de autorização e bytes reais. */
final class LocalPrivateFilesTest extends TestCase {
	/**
	 * Constrói uma referência fechada.
	 *
	 * @param int    $object_id Identificador sintético.
	 * @param int    $version Versão.
	 * @param string $variant Variante.
	 * @param string $type Tipo do objecto.
	 */
	private function record( int $object_id, int $version = 2, string $variant = 'original', string $type = 'local_document' ): FileRecord {
		return new FileRecord( new BlobDescriptor( str_repeat( 'a', 32 ), str_repeat( 'b', 64 ), 10, 'application/pdf' ), $type, $object_id, $version, $variant, 99, 100 );
	}

	/**
	 * Constrói um snapshot sintético.
	 *
	 * @param array        $grants Concessões de ensaio.
	 * @param AccountState $state Estado da conta.
	 */
	private function snapshot( array $grants, AccountState $state = AccountState::Active ): AuthorizationSnapshot {
		return new AuthorizationSnapshot( 7, $state, 3, array_map( static fn( Grant $grant, int $index ): StoredGrant => new StoredGrant( $index + 1, $grant ), $grants, array_keys( $grants ) ) );
	}

	/** Metadados de uma sessão fictícia válida. */
	private function session(): array {
		return array(
			'arn_authz_local'    => true,
			'arn_authz_user_id'  => 7,
			'arn_authz_revision' => 3,
		);
	}

	/** A flag textual e produção não activam o adaptador. */
	public function testGateRequiresBooleanTrueAndLocalEnvironment(): void {
		self::assertTrue( LocalPrivateFiles::enabled( 'local', true ) );
		self::assertFalse( LocalPrivateFiles::enabled( 'local', 'true' ) );
		self::assertFalse( LocalPrivateFiles::enabled( 'production', true ) );
	}

	/** Objectos fora do catálogo e versões antigas são recusados. */
	public function testCatalogRejectsUnknownObjectsAndOldVersions(): void {
		foreach ( array( $this->record( 4 ), $this->record( 1, 1 ), $this->record( 1, 2, 'preview', 'post' ) ) as $record ) {
			self::assertNull( LocalFileCatalog::request( $record, 7 ) );
		}
		self::assertSame( 7, LocalFileCatalog::request( $this->record( 1 ), 7 )->owner_id );
		self::assertNull( LocalFileCatalog::request( $this->record( 1, 2, 'preview' ), 7 ) );
	}

	/** Employee com âmbito exacto e sessão válida é obrigatório. */
	public function testAuthorizationRequiresEmployeeAndMatchingScopeAndSession(): void {
		$own          = $this->record( 1 );
		$unit         = $this->record( 2 );
		$employee_own = new Grant( 7, Role::Employee, Scope::own(), 0, null );
		$employee_a   = new Grant( 7, Role::Employee, Scope::units( array( 'unidade-ficticia-a' ) ), 0, null );
		$manager_a    = new Grant( 7, Role::UnitManager, Scope::units( array( 'unidade-ficticia-a' ) ), 0, null );
		self::assertTrue( LocalFileCatalog::allows( $own, $this->snapshot( array( $employee_own ) ), 7, $this->session(), 100 ) );
		self::assertFalse( LocalFileCatalog::allows( $unit, $this->snapshot( array( $employee_own, $manager_a ) ), 7, $this->session(), 100 ) );
		self::assertTrue( LocalFileCatalog::allows( $unit, $this->snapshot( array( $employee_a ) ), 7, $this->session(), 100 ) );
		self::assertFalse( LocalFileCatalog::allows( $this->record( 3 ), $this->snapshot( array( $employee_a ) ), 7, $this->session(), 100 ) );
		self::assertFalse( LocalFileCatalog::allows( $own, $this->snapshot( array( $employee_own ) ), 7, array_replace( $this->session(), array( 'arn_authz_revision' => 2 ) ), 100 ) );
		self::assertFalse( LocalFileCatalog::allows( $own, $this->snapshot( array( $employee_own ), AccountState::Suspended ), 7, $this->session(), 100 ) );
		self::assertFalse( LocalFileCatalog::allows( $own, $this->snapshot( array( $employee_own ) ), 99, $this->session(), 100 ) );
	}

	/** O ID é estrito e um PDF real é entregue pelo handle verificado. */
	public function testDeliveryUsesRealBlobAndRejectsInvalidIdsBeforeRegistry(): void {
		$base = sys_get_temp_dir() . '/arn-local-pf-' . bin2hex( random_bytes( 6 ) );
		mkdir( $base );
		mkdir( $base . '/private' );
		mkdir( $base . '/public' );
		file_put_contents( $base . '/public/source.pdf', "%PDF-1.4\n1 0 obj <<>> endobj\n%%EOF\n" );
		try {
			$store    = new PrivateBlobStore( $base . '/private', array( $base . '/public' ), array( 'application/pdf' ), 1024 );
			$blob     = $store->importFile( $base . '/public/source.pdf' );
			$record   = new FileRecord( $blob, 'local_document', 1, 2, 'original', 99, 100 );
			$registry = new class( $record ) {
				/**
				 * Número de consultas por ID.
				 *
				 * @var int
				 */
				public int $finds = 0;
				/**
				 * Recebe a referência de ensaio.
				 *
				 * @param FileRecord $record Referência de ensaio.
				 */
				public function __construct( private FileRecord $record ) {}
				/**
				 * Procura um ID opaco.
				 *
				 * @param string $id ID opaco.
				 */
				public function find( string $id ): ?FileRecord {
					++$this->finds;
					return $id === $this->record->blob->id ? $this->record : null; }
				/**
				 * Consulta singular simulada.
				 *
				 * @param string $type Tipo.
				 * @param int    $id Objecto.
				 * @param int    $version Versão.
				 * @param string $variant Variante.
				 */
				public function findForObject( string $type, int $id, int $version, string $variant ): ?FileRecord {
					return $this->record; }
			};
			$reader   = fn(): array => array( $this->snapshot( array( new Grant( 7, Role::Employee, Scope::own(), 0, null ) ) ), 7, $this->session() );
			$service  = new LocalPrivateFiles( $reader, static fn() => $registry, static fn() => $store, static fn(): bool => true );
			foreach ( array( array(), 'A' . substr( $blob->id, 1 ), $blob->id . 'x' ) as $bad ) {
				self::assertNull( $service->open( $bad ) );
			}
			self::assertSame( 0, $registry->finds );
			$opened = $service->open( $blob->id );
			self::assertSame( file_get_contents( $base . '/public/source.pdf' ), stream_get_contents( $opened['handle'] ) );
			fclose( $opened['handle'] );
			self::assertSame( 1, $registry->finds );
			$closed = new LocalPrivateFiles( $reader, static fn() => $registry, static fn() => $store, static fn(): bool => false );
			self::assertNull( $closed->open( $blob->id ) );
			self::assertSame( 1, $registry->finds );
		} finally {
			$entries = glob( $base . '/private/*' );
			foreach ( false === $entries ? array() : $entries as $path ) {
				unlink( $path ); }
			unlink( $base . '/public/source.pdf' );
			rmdir( $base . '/private' );
			rmdir( $base . '/public' );
			rmdir( $base );
		}
	}

	/** Sessões inválidas param antes das fontes de registo e binários. */
	public function testInvalidSessionStopsBeforeRegistryOrStore(): void {
		$called  = 0;
		$reader  = fn(): array => array( $this->snapshot( array( new Grant( 7, Role::Employee, Scope::own(), 0, null ) ) ), 7, array_replace( $this->session(), array( 'arn_authz_revision' => 2 ) ) );
		$factory = static function () use ( &$called ) {
			++$called;
			throw new \RuntimeException( 'unexpected' );
		};
		$service = new LocalPrivateFiles( $reader, $factory, $factory, static fn(): bool => true );
		self::assertNull( $service->open( str_repeat( 'a', 32 ) ) );
		self::assertNull( $service->listVisible() );
		self::assertSame( 0, $called );
	}

	/** Só uma invocação CLI local administradora instala. */
	public function testInstallerRequiresExplicitCliAndBooleanFlags(): void {
		self::assertTrue( LocalFileInstaller::eligible( 'local', true, true, true, true, true ) );
		self::assertFalse( LocalFileInstaller::eligible( 'local', 'true', true, true, true, true ) );
		self::assertFalse( LocalFileInstaller::eligible( 'production', true, true, true, true, true ) );
		self::assertFalse( LocalFileInstaller::eligible( 'local', true, true, true, false, true ) );
		self::assertFalse( LocalFileInstaller::eligible( 'local', true, true, true, true, false ) );
	}

	/** A ferramenta usa exactamente os três rótulos correntes. */
	public function testToolShowsOnlyThreeCurrentVariants(): void {
		self::assertSame(
			array(
				'original'  => 'Original (PDF)',
				'thumbnail' => 'Miniatura (PNG)',
				'preview'   => 'Pré-visualização (JPEG)',
			),
			LocalPrivateFilesPage::labels()
		);
	}

	/** O original sintético contém uma página e xref com offsets correctos. */
	public function testSyntheticPdfContainsACompletePageAndCrossReference(): void {
		$pdf = ( new \ReflectionClass( LocalFileInstaller::class ) )->getMethod( 'content' )->invoke( null, 1, 2, 'original' );
		self::assertStringContainsString( '/Type /Page ', $pdf );
		self::assertStringContainsString( '/Count 1', $pdf );
		self::assertMatchesRegularExpression( '/startxref\n([0-9]+)\n%%EOF\n\z/', $pdf );
		preg_match( '/startxref\n([0-9]+)\n%%EOF\n\z/', $pdf, $matches );
		self::assertStringStartsWith( 'xref', substr( $pdf, (int) $matches[1] ) );
	}
}
