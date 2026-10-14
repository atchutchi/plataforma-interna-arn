<?php
/**
 * Adaptadores em memória para testar os callbacks sem WordPress nem base de dados.
 * Não substituem a verificação integrada no wp-env.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

/**
 * Armazenamento de sessões por utilizador em memória.
 */
class WP_Session_Tokens {

	/**
	 * Conta do gestor de sessões.
	 *
	 * @var int
	 */
	private int $user_id;

	/**
	 * Seleciona a conta.
	 *
	 * @param int $user_id Identificador.
	 */
	private function __construct( int $user_id ) {
		$this->user_id = $user_id;
	}

	/**
	 * Obtém o gestor da conta indicada.
	 *
	 * @param int $user_id Identificador.
	 */
	public static function get_instance( int $user_id ): self {
		return new self( $user_id );
	}

	/**
	 * Obtém metadados ou null quando o token não existe.
	 *
	 * @param string $token Token fictício.
	 * @return array|null
	 */
	public function get( string $token ): ?array {
		return ArnWordPressDoubles::$sessions[ $this->user_id ][ $token ] ?? null;
	}

	/**
	 * Elimina só o token indicado.
	 *
	 * @param string $token Token fictício.
	 */
	public function destroy( string $token ): void {
		unset( ArnWordPressDoubles::$sessions[ $this->user_id ][ $token ] );
	}

	/**
	 * Elimina todos os tokens da conta, sem afetar as restantes.
	 */
	public function destroy_all(): void {
		ArnWordPressDoubles::$sessions[ $this->user_id ] = array();
	}
}
