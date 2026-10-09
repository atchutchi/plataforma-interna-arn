<?php
/**
 * Adaptadores em memória para testar os callbacks sem WordPress nem base de dados.
 * Não substituem a verificação integrada no wp-env.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

/**
 * Erro opaco devolvido ao chamador.
 */
class WP_Error {

	/**
	 * Código do erro.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Dados associados ao erro.
	 *
	 * @var mixed
	 */
	private mixed $data;

	/**
	 * Retém o código que o chamador recebe.
	 *
	 * @param string $code    Código.
	 * @param string $message Mensagem traduzida.
	 * @param mixed  $data    Dados associados.
	 */
	public function __construct( string $code, string $message = '', mixed $data = null ) {
		$this->code = $code;
		$this->data = $data;
	}

	/**
	 * Obtém o código.
	 */
	public function get_error_code(): string {
		return $this->code;
	}

	/**
	 * Obtém os dados.
	 */
	public function get_error_data(): mixed {
		return $this->data;
	}
}
