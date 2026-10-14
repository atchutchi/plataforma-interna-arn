<?php
/**
 * Referência auditável e imutável.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use RuntimeException;

/** Associação imutável entre binário e versão de objecto. */
final readonly class FileRecord {
	/**
	 * Confirma os identificadores e a variante fechada.
	 *
	 * @param BlobDescriptor $blob Descritor do binário.
	 * @param string         $object_type Tipo de objecto.
	 * @param int            $object_id Identificador do objecto.
	 * @param int            $version Versão.
	 * @param string         $variant Variante fechada.
	 * @param int            $actor_id Actor de registo.
	 * @param int            $created_at Instante UTC Unix.
	 * @throws RuntimeException Quando algum atributo é inválido.
	 */
	public function __construct( public BlobDescriptor $blob, public string $object_type, public int $object_id, public int $version, public string $variant, public int $actor_id, public int $created_at ) {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]{0,63}$/D', $object_type ) || $object_id < 1 || $version < 1 || ! in_array( $variant, array( 'original', 'thumbnail', 'preview' ), true ) || $actor_id < 1 || $created_at < 1 ) {
			throw new RuntimeException( 'Ficheiro privado indisponível.' );
		}
	}
}
