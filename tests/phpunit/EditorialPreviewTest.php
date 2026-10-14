<?php
/**
 * Testes da fonte editorial sintética.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

use Arn\Intranet\EditorialPreview\Manifest;
use Arn\Intranet\EditorialPreview\Selection;
use PHPUnit\Framework\TestCase;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Testes puros sem WordPress criam fixtures temporárias.

/** Exercita manifesto, seleção e integridade. */
final class EditorialPreviewTest extends TestCase {
	/**
	 * Diretório temporário do ensaio.
	 *
	 * @var string
	 */
	private string $directory;

	/** Prepara uma fonte exclusiva. */
	protected function setUp(): void {
		$this->directory = sys_get_temp_dir() . '/arn-editorial-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->directory . '/files', 0700, true );
	}

	/** Apaga apenas a fonte exclusiva. */
	protected function tearDown(): void {
		$files = glob( $this->directory . '/files/*' );
		foreach ( false === $files ? array() : $files as $file ) {
			unlink( $file );
		}
		if ( is_file( $this->directory . '/manifest.json' ) ) {
			unlink( $this->directory . '/manifest.json' );
		}
		rmdir( $this->directory . '/files' );
		rmdir( $this->directory );
	}

	/**
	 * Cria um item PDF sintético.
	 *
	 * @param string      $id Identificador.
	 * @param string|null $date Data ou ausência.
	 * @return array<string,mixed> Item de teste.
	 */
	private function item( string $id = 'first', ?string $date = '2026-10-01' ): array {
		file_put_contents( $this->directory . '/files/' . $id . '.pdf', '%PDF-1.7 fixture' );
		return array(
			'id'           => $id,
			'title'        => 'Título',
			'category'     => 'circular',
			'date'         => $date,
			'reference'    => 'Ref',
			'summary'      => 'Resumo',
			'body'         => array( 'Parágrafo' ),
			'source_label' => 'Original',
			'file'         => $id . '.pdf',
			'sha256'       => hash_file( 'sha256', $this->directory . '/files/' . $id . '.pdf' ),
			'format'       => 'PDF',
			'note'         => '',
		);
	}

	/**
	 * Escreve o manifesto temporário.
	 *
	 * @param array<int,array<string,mixed>> $items Itens.
	 */
	private function write( array $items ): void {
		file_put_contents(
			$this->directory . '/manifest.json',
			json_encode(
				array(
					'version' => 1,
					'items'   => $items,
				),
				JSON_THROW_ON_ERROR
			)
		);
	}

	/** Confirma ordenação, filtros e exclusão de regulamentos. */
	public function test_valid_manifest_and_selection_order(): void {
		$this->write( array( $this->item( 'b', null ), $this->item( 'c', '2026-10-01' ), $this->item( 'a', '2026-10-01' ) ) );
		$items = ( new Manifest( $this->directory ) )->items();
		$this->assertSame( array( 'a', 'c', 'b' ), array_column( Selection::filter( $items, 'documents', array() ), 'id' ) );
		$this->assertCount( 3, Selection::filter( $items, 'news', array() ) );
		$this->assertSame( array( 'a', 'c' ), array_column( Selection::filter( $items, 'documents', array( 'year' => '2026' ) ), 'id' ) );
	}

	/** Recusa IDs repetidos. */
	public function test_rejects_duplicate_id_and_unsafe_file(): void {
		$item = $this->item();
		$this->write( array( $item, $item ) );
		$this->expectException( RuntimeException::class );
		( new Manifest( $this->directory ) )->items();
	}

	/** Recusa filtros estruturados. */
	public function test_rejects_unbounded_or_array_query(): void {
		$this->expectException( InvalidArgumentException::class );
		Selection::query( array( 'q' => array( 'x' ) ) );
	}

	/** Confirma a integridade antes de servir o ficheiro. */
	public function test_file_integrity_and_size(): void {
		$item = $this->item();
		$this->write( array( $item ) );
		$manifest = new Manifest( $this->directory );
		$this->assertSame( str_replace( '\\', '/', $this->directory . '/files/first.pdf' ), str_replace( '\\', '/', $manifest->verifiedFile( $item )['path'] ) );
		file_put_contents( $this->directory . '/files/first.pdf', 'tampered' );
		$this->expectException( RuntimeException::class );
		$manifest->verifiedFile( $item );
	}

	/** Recusa percurso e extensão fora do catálogo. */
	public function test_rejects_unsafe_basename(): void {
		$item         = $this->item();
		$item['file'] = '../other.pdf';
		$this->write( array( $item ) );
		$this->expectException( RuntimeException::class );
		( new Manifest( $this->directory ) )->items();
	}

	/** Recusa mais de duzentos itens. */
	public function test_rejects_manifest_item_limit(): void {
		$item = $this->item();
		$this->write( array_fill( 0, 201, $item ) );
		$this->expectException( RuntimeException::class );
		( new Manifest( $this->directory ) )->items();
	}

	/** Recusa filtros acima dos limites definidos. */
	public function test_rejects_query_limits(): void {
		foreach ( array( array( 'q' => str_repeat( 'a', 161 ) ), array( 'paged' => '1000' ), array( 'year' => '26' ), array( 'category' => 'unknown' ) ) as $input ) {
			try {
				Selection::query( $input );
				$this->fail( 'Filtro devia ser recusado.' );
			} catch ( InvalidArgumentException $error ) {
				$this->assertSame( 'Filtro inválido.', $error->getMessage() );
			}
		}
	}

	/** O detalhe conserva apenas o contexto de lista validado. */
	public function test_detail_query_preserves_validated_list_context(): void {
		$query = Selection::query(
			array(
				'q'        => 'pesquisa',
				'category' => 'circular',
				'year'     => '2026',
				'paged'    => '3',
				'item'     => 'anterior',
				'unsafe'   => 'ignorar',
			)
		);
		$this->assertSame(
			array(
				'q'        => 'pesquisa',
				'category' => 'circular',
				'year'     => '2026',
				'paged'    => 3,
				'item'     => 'novo',
			),
			Selection::detailQuery( $query, 'novo' )
		);
		$this->assertSame( array( 'item' => 'novo' ), Selection::detailQuery( Selection::query( array() ), 'novo' ) );
	}
}
