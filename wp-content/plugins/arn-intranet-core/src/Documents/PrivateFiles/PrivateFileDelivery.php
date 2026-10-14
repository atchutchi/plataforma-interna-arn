<?php
/**
 * Autorização por pedido antes de abrir um binário.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\PrivateFiles;

use Closure;
use Throwable;

/** Executa autorização por pedido antes de qualquer leitura do binário. */
final class PrivateFileDelivery {
	/**
	 * Recebe resolvedores exclusivos do servidor.
	 *
	 * @param PrivateBlobStore $store Armazenamento privado.
	 * @param Closure          $find_record Resolve ID para referência.
	 * @param Closure          $authorize Autoriza a referência.
	 */
	public function __construct( private readonly PrivateBlobStore $store, private readonly Closure $find_record, private readonly Closure $authorize ) {}

	/**
	 * Autoriza o objecto antes de abrir o binário.
	 *
	 * @param mixed $id Identificador vindo do pedido.
	 * @return array{record:FileRecord,handle:resource}|null
	 */
	public function open( mixed $id ): ?array {
		if ( ! is_string( $id ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
			return null;
		}
		try {
			$record = ( $this->find_record )( $id );
			if ( ! $record instanceof FileRecord || $record->blob->id !== $id || true !== ( $this->authorize )( $record ) ) {
				return null;
			}
			return array(
				'record' => $record,
				'handle' => $this->store->open( $record->blob ),
			);
		} catch ( Throwable ) {
			return null;
		}
	}
}
