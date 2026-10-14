<?php
/**
 * Fonte privada do portal editorial.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\EditorialPreview;

use RuntimeException;

// phpcs:disable WordPress.WP.AlternativeFunctions -- A fonte privada usa caminhos locais do servidor, fora de WP_Filesystem.

/** Valida o manifesto e os originais privados. */
final class Manifest {
	public const CATEGORIES = array(
		'circular'             => 'Circular',
		'nota-interna'         => 'Nota interna',
		'despacho'             => 'Despacho',
		'regulamento'          => 'Regulamento',
		'comunicacao-sindical' => 'Comunicação sindical',
	);

	/**
	 * Guarda o diretório da fonte.
	 *
	 * @param string $directory Diretório externo ao document root.
	 */
	public function __construct( private readonly string $directory ) {}

	/**
	 * Lê e valida a fonte completa.
	 *
	 * @return array<int, array<string, mixed>> Itens válidos.
	 * @throws RuntimeException Fonte inválida.
	 */
	public function items(): array {
		$path = $this->directory . '/manifest.json';
		if ( is_link( $path ) || ! is_file( $path ) || filesize( $path ) > 1048576 ) {
			throw new RuntimeException( 'Fonte editorial indisponível.' );
		}
		$raw = file_get_contents( $path, false, null, 0, 1048577 );
		if ( ! is_string( $raw ) || strlen( $raw ) > 1048576 ) {
			throw new RuntimeException( 'Fonte editorial indisponível.' );
		}
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) || count( $data ) !== 2 || ! array_key_exists( 'version', $data ) || ! array_key_exists( 'items', $data ) || 1 !== $data['version'] || ! is_array( $data['items'] ) || ! array_is_list( $data['items'] ) || count( $data['items'] ) > 200 ) {
			throw new RuntimeException( 'Fonte editorial inválida.' );
		}
		$seen = array();
		foreach ( $data['items'] as $item ) {
			$this->validateItem( $item );
			if ( isset( $seen[ $item['id'] ] ) ) {
				throw new RuntimeException( 'Fonte editorial inválida.' );
			}
			$seen[ $item['id'] ] = true;
		}
		return $data['items'];
	}

	/**
	 * Valida um item sem abrir o original.
	 *
	 * @param mixed $item Item de entrada.
	 * @throws RuntimeException Item inválido.
	 */
	private function validateItem( mixed $item ): void {
		$keys = array( 'id', 'title', 'category', 'date', 'reference', 'summary', 'body', 'source_label', 'file', 'sha256', 'format', 'note' );
		if ( ! is_array( $item ) || array_diff( $keys, array_keys( $item ) ) || array_diff( array_keys( $item ), $keys ) ) {
			throw new RuntimeException( 'Fonte editorial inválida.' );
		}
		foreach ( array( 'id', 'title', 'category', 'reference', 'summary', 'source_label', 'file', 'sha256', 'format', 'note' ) as $key ) {
			if ( ! is_string( $item[ $key ] ) || strlen( $item[ $key ] ) > 2000 ) {
				throw new RuntimeException( 'Fonte editorial inválida.' );
			}
		}
		if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,79}$/D', $item['id'] ) || ! isset( self::CATEGORIES[ $item['category'] ] ) || ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9._-]{0,159}\.(?:pdf|jpe?g)$/D', $item['file'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $item['sha256'] ) || ! in_array( $item['format'], array( 'PDF', 'JPEG' ), true ) ) {
			throw new RuntimeException( 'Fonte editorial inválida.' );
		}
		$extension = strtolower( pathinfo( $item['file'], PATHINFO_EXTENSION ) );
		if ( ( 'PDF' === $item['format'] && 'pdf' !== $extension ) || ( 'JPEG' === $item['format'] && ! in_array( $extension, array( 'jpg', 'jpeg' ), true ) ) ) {
			throw new RuntimeException( 'Fonte editorial inválida.' );
		}
		if ( null !== $item['date'] && ( ! is_string( $item['date'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/D', $item['date'] ) || false === strtotime( $item['date'] ) || gmdate( 'Y-m-d', (int) strtotime( $item['date'] ) ) !== $item['date'] ) ) {
			throw new RuntimeException( 'Fonte editorial inválida.' );
		}
		if ( ! is_array( $item['body'] ) || ! array_is_list( $item['body'] ) || count( $item['body'] ) > 100 ) {
			throw new RuntimeException( 'Fonte editorial inválida.' );
		}
		foreach ( $item['body'] as $paragraph ) {
			if ( ! is_string( $paragraph ) || strlen( $paragraph ) > 10000 ) {
				throw new RuntimeException( 'Fonte editorial inválida.' );
			}
		}
	}

	/**
	 * Confirma identidade, tamanho e formato do original.
	 *
	 * @param array<string,mixed> $item Item validado.
	 * @return array{path:string,mime:string,size:int} Original confirmado.
	 * @throws RuntimeException Original inválido.
	 */
	public function verifiedFile( array $item ): array {
		$this->validateItem( $item );
		if ( is_link( $this->directory . '/files' ) ) {
			throw new RuntimeException( 'Original indisponível.' );
		}
		$base = realpath( $this->directory . '/files' );
		$path = realpath( $this->directory . '/files/' . $item['file'] );
		if ( false === $base || false === $path || ! str_starts_with( $path, $base . DIRECTORY_SEPARATOR ) || ! is_file( $path ) ) {
			throw new RuntimeException( 'Original indisponível.' );
		}
		$size      = filesize( $path );
		$expected  = 'PDF' === $item['format'] ? 'application/pdf' : 'image/jpeg';
		$signature = file_get_contents( $path, false, null, 0, 5 );
		$matches   = 'PDF' === $item['format'] ? str_starts_with( (string) $signature, '%PDF-' ) : str_starts_with( (string) $signature, "\xFF\xD8\xFF" );
		$mime      = class_exists( \finfo::class ) ? ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $path ) : $expected;
		if ( false === $size || $size < 1 || $size > 20971520 || ! $matches || $expected !== $mime ) {
			throw new RuntimeException( 'Original indisponível.' );
		}
		$digest = hash_file( 'sha256', $path );
		if ( ! is_string( $digest ) || ! hash_equals( $item['sha256'], $digest ) ) {
			throw new RuntimeException( 'Original indisponível.' );
		}
		return array(
			'path' => $path,
			'mime' => $expected,
			'size' => $size,
		);
	}
}
