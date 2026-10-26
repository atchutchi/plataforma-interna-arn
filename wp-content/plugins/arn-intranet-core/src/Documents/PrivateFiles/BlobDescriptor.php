<?php
/**
 * Descritor imutável de um binário privado.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use RuntimeException;

/** Binário privado identificado por conteúdo e ID aleatório. */
final readonly class BlobDescriptor {
	/**
	 * Confirma a forma do descritor.
	 *
	 * @param string $id Identificador opaco.
	 * @param string $sha256 Resumo SHA-256.
	 * @param int    $size Tamanho em bytes.
	 * @param string $mime Tipo MIME verificado.
	 * @param array  $derivatives Variantes imutáveis preparadas para registo atómico.
	 * @throws RuntimeException Quando algum atributo é inválido.
	 */
	public function __construct( public string $id, public string $sha256, public int $size, public string $mime, public array $derivatives = array() ) {
		if ( 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $sha256 ) || $size < 1 || ! in_array( $mime, array( 'application/pdf', 'image/jpeg', 'image/png' ), true ) ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
		foreach ( $derivatives as $variant => $derived ) {
			if ( ! in_array( $variant, array( 'thumbnail', 'preview' ), true ) || ! $derived instanceof self || array() !== $derived->derivatives || 'image/png' !== $derived->mime || $derived->id === $id ) {
				throw new RuntimeException( 'Derivado privado inválido.' );
			}
		}
		if ( count( array_unique( array_map( static fn( self $blob ): string => $blob->id, $derivatives ) ) ) !== count( $derivatives ) ) {
			throw new RuntimeException( 'Derivados privados repetidos.' );
		}
	}
}
