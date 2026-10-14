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
	 * Acrescenta um erro sem substituir o primeiro código.
	 *
	 * @param string $code Código novo.
	 * @param string $message Mensagem pública.
	 */
	public function add( string $code, string $message ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Assinatura nativa, sem renderização no double.
		if ( '' === $this->code ) {
			$this->code = $code;
		}
	}

	/**
	 * Obtém os dados.
	 */
	public function get_error_data(): mixed {
		return $this->data;
	}
}
