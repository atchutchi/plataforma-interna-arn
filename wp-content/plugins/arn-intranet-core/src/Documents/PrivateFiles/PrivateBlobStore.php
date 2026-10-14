<?php
/**
 * Armazém de binários privados imutáveis.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

// phpcs:disable WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions

use RuntimeException;
use Throwable;

/** Copia ficheiros para fora das raízes públicas e valida cada leitura. */
final class PrivateBlobStore {
	/**
	 * Raiz canónica privada.
	 *
	 * @var string
	 */
	private readonly string $root;
	/**
	 * Raízes públicas canónicas.
	 *
	 * @var array
	 */
	private readonly array $public_roots;

	/**
	 * Fixa a política de admissão do servidor.
	 *
	 * @param string $root Raiz privada existente.
	 * @param array  $public_roots Raízes públicas existentes.
	 * @param array  $allowed_mimes MIME admitidos.
	 * @param int    $max_bytes Limite efectivo em bytes.
	 * @throws RuntimeException Quando a configuração é insegura.
	 */
	public function __construct( string $root, array $public_roots, private readonly array $allowed_mimes, private readonly int $max_bytes ) {
		if ( $max_bytes < 1 || array() === $public_roots || array() === $allowed_mimes || array_diff( $allowed_mimes, array( 'application/pdf', 'image/jpeg', 'image/png' ) ) ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		$this->root = self::canonicalDirectory( $root );
		$public     = array();
		foreach ( $public_roots as $candidate ) {
			if ( ! is_string( $candidate ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			$public[] = self::canonicalDirectory( $candidate );
		}
		$this->public_roots = $public;
		$this->assertRoot();
	}

	/**
	 * Copia uma fonte regular para um novo ID sem substituir dados existentes.
	 *
	 * @param string $source Caminho da fonte no servidor.
	 * @return BlobDescriptor Descritor do conteúdo copiado.
	 * @throws RuntimeException Quando a fonte ou cópia não é válida.
	 */
	public function importFile( string $source ): BlobDescriptor {
		$this->assertRoot();
		self::assertNoLinks( $source );
		if ( ! is_file( $source ) || ! is_readable( $source ) ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		$input = @fopen( $source, 'rb' );
		if ( false === $input ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		$initial = fstat( $input );
		if ( false === $initial || $initial['size'] < 1 || $initial['size'] > $this->max_bytes ) {
			fclose( $input );
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		$id      = bin2hex( random_bytes( 16 ) );
		$path    = $this->root . DIRECTORY_SEPARATOR . $id;
		$output  = false;
		$created = false;
		try {
			$output = @fopen( $path, 'xb' );
			if ( false === $output ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			$created = true;
			$hash    = hash_init( 'sha256' );
			$size    = 0;
			while ( ! feof( $input ) ) {
				$chunk = fread( $input, 8192 );
				if ( false === $chunk ) {
					throw new RuntimeException( 'Ficheiro privado indisponível.' );
				}
				$size += strlen( $chunk );
				if ( $size > $this->max_bytes || ( '' === $chunk && ! feof( $input ) ) || strlen( $chunk ) !== fwrite( $output, $chunk ) ) {
					throw new RuntimeException( 'Ficheiro privado indisponível.' );
				}
				hash_update( $hash, $chunk );
			}
			$final = fstat( $input );
			if ( $size !== $initial['size'] || false === $final || $final['size'] !== $initial['size'] || ! fflush( $output ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			fclose( $output );
			$output = false;
			$copy   = @fopen( $path, 'rb' );
			if ( false === $copy ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			try {
				$verified = $this->inspectHandle( $copy );
			} finally {
				fclose( $copy );
			}
			if ( $size !== $verified['size'] || ! hash_equals( hash_final( $hash ), $verified['hash'] ) || ! in_array( $verified['mime'], $this->allowed_mimes, true ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			return new BlobDescriptor( $id, $verified['hash'], $size, $verified['mime'] );
		} catch ( Throwable ) {
			if ( is_resource( $output ) ) {
				fclose( $output );
			}
			if ( $created ) {
				@unlink( $path );
			}
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		} finally {
			fclose( $input );
		}
	}

	/** Duplicate verified bytes from the same locked handle into a new opaque blob.
	 *
	 * @param BlobDescriptor $source Verified source descriptor.
	 * @return BlobDescriptor Independent copy.
	 * @throws RuntimeException When the copy cannot be verified.
	 */
	public function duplicate( BlobDescriptor $source ): BlobDescriptor {
		$input   = $this->open( $source );
		$id      = bin2hex( random_bytes( 16 ) );
		$path    = $this->root . DIRECTORY_SEPARATOR . $id;
		$output  = false;
		$created = false;
		try {
			$output = @fopen( $path, 'xb' );
			if ( false === $output ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			$created = true;
			$size    = 0;
			$hash    = hash_init( 'sha256' );
			while ( ! feof( $input ) ) {
				$chunk = fread( $input, 8192 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $input ) ) ) {
					throw new RuntimeException( 'Ficheiro privado indisponível.' );
				}
				$size += strlen( $chunk );
				if ( $size > $this->max_bytes || strlen( $chunk ) !== fwrite( $output, $chunk ) ) {
					throw new RuntimeException( 'Ficheiro privado indisponível.' );
				}
				hash_update( $hash, $chunk );
			}
			if ( $size !== $source->size || ! hash_equals( $source->sha256, hash_final( $hash ) ) || ! fflush( $output ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			fclose( $output );
			$output = false;
			$copy   = new BlobDescriptor( $id, $source->sha256, $source->size, $source->mime );
			$check  = $this->open( $copy );
			fclose( $check );
			return $copy;
		} catch ( Throwable ) {
			if ( is_resource( $output ) ) {
				fclose( $output );
			}
			if ( $created ) {
				@unlink( $path );
			}
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		} finally {
			fclose( $input );
		}
	}

	/**
	 * Abre e verifica o mesmo handle que será entregue.
	 *
	 * @param BlobDescriptor $blob Descritor esperado.
	 * @return resource
	 * @throws RuntimeException Quando o binário falha a verificação.
	 */
	public function open( BlobDescriptor $blob ) {
		$this->assertRoot();
		$path = $this->root . DIRECTORY_SEPARATOR . $blob->id;
		self::assertNoLinks( $path );
		$handle = @fopen( $path, 'rb' );
		if ( false === $handle ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		try {
			if ( ! flock( $handle, LOCK_SH ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			$verified = $this->inspectHandle( $handle );
			if ( $verified['size'] !== $blob->size || ! hash_equals( $blob->sha256, $verified['hash'] ) || $verified['mime'] !== $blob->mime ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			rewind( $handle );
			return $handle;
		} catch ( Throwable ) {
			fclose( $handle );
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
	}

	/**
	 * Calcula tamanho, hash e MIME por blocos no mesmo handle.
	 *
	 * @param resource $handle Handle aberto.
	 * @return array Dados verificados.
	 * @throws RuntimeException Quando o conteúdo não é admitido.
	 */
	private function inspectHandle( $handle ): array {
		$hash = hash_init( 'sha256' );
		$size = 0;
		$head = '';
		$tail = '';
		while ( ! feof( $handle ) ) {
			$chunk = fread( $handle, 8192 );
			if ( false === $chunk || ( '' === $chunk && ! feof( $handle ) ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			$size += strlen( $chunk );
			if ( $size > $this->max_bytes ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
			$head = substr( $head . $chunk, 0, 4096 );
			$tail = substr( $tail . $chunk, -1024 );
			hash_update( $hash, $chunk );
		}
		$mime      = ( new \finfo( FILEINFO_MIME_TYPE ) )->buffer( $head );
		$signature = match ( $mime ) {
			'application/pdf' => str_starts_with( $head, '%PDF-' ) && str_contains( $tail, '%%EOF' ),
			'image/png' => str_starts_with( $head, "\x89PNG\r\n\x1a\n" ) && str_ends_with( $tail, "IEND\xaeB`\x82" ),
			'image/jpeg' => str_starts_with( $head, "\xff\xd8\xff" ) && str_ends_with( $tail, "\xff\xd9" ),
			default => false,
		};
		if ( $size < 1 || ! $signature ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		return array(
			'size' => $size,
			'hash' => hash_final( $hash ),
			'mime' => $mime,
		);
	}

	/**
	 * Revalida a fronteira privada em cada operação.
	 *
	 * @throws RuntimeException Quando a raiz deixa de ser privada.
	 */
	private function assertRoot(): void {
		if ( self::canonicalDirectory( $this->root ) !== $this->root ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		foreach ( $this->public_roots as $public ) {
			if ( self::overlap( $this->root, $public ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
		}
	}

	/**
	 * Compara caminhos com fronteiras de componentes.
	 *
	 * @param string $a Primeira raiz.
	 * @param string $b Segunda raiz.
	 * @return bool Verdadeiro se forem sobrepostas.
	 */
	private static function overlap( string $a, string $b ): bool {
		$a = strtolower( str_replace( '\\', '/', rtrim( $a, '\\/' ) ) );
		$b = strtolower( str_replace( '\\', '/', rtrim( $b, '\\/' ) ) );
		return $a === $b || str_starts_with( $a, $b . '/' ) || str_starts_with( $b, $a . '/' );
	}

	/**
	 * Resolve uma pasta existente sem aceitar links ancestrais.
	 *
	 * @param string $path Pasta original.
	 * @return string Pasta canónica.
	 * @throws RuntimeException Quando a pasta é inválida.
	 */
	private static function canonicalDirectory( string $path ): string {
		self::assertNoLinks( $path );
		$real = realpath( $path );
		if ( false === $real || ! is_dir( $real ) ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		return $real;
	}

	/**
	 * Recusa links em cada componente ancestral e no próprio alvo.
	 *
	 * @param string $path Caminho a verificar.
	 * @throws RuntimeException Quando existe um link.
	 */
	private static function assertNoLinks( string $path ): void {
		if ( '' === $path || str_contains( $path, "\0" ) ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		$path   = str_replace( '\\', '/', $path );
		$prefix = str_starts_with( $path, '/' ) ? '/' : '';
		foreach ( explode( '/', $path ) as $component ) {
			if ( '' === $component || '.' === $component ) {
				continue; }
			$prefix = rtrim( $prefix, '/' ) . ( '' === $prefix ? '' : '/' ) . $component;
			if ( is_link( $prefix ) ) {
				throw new RuntimeException( 'Ficheiro privado indisponível.' );
			}
		}
	}
}
