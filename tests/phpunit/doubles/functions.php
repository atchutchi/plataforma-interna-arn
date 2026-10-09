<?php
/**
 * Adaptadores em memória para testar os callbacks sem WordPress nem base de dados.
 * Não substituem a verificação integrada no wp-env.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

/**
 * Devolve a conta em memória.
 *
 * @param int $user_id Identificador.
 * @return WP_User|false
 */
function get_userdata( int $user_id ) {
	return ArnWordPressDoubles::$users[ $user_id ] ?? false;
}

/**
 * Devolve um metadado, como valor único ou lista de valores.
 *
 * @param int    $user_id Identificador.
 * @param string $key     Chave.
 * @param bool   $single  Devolução de um valor único.
 * @return string|string[]
 */
function get_user_meta( int $user_id, string $key, bool $single ): string|array {
	$value = ArnWordPressDoubles::$meta[ $user_id ][ $key ] ?? null;

	if ( $single ) {
		return $value ?? '';
	}

	return null === $value ? array() : array( $value );
}

/**
 * Regista a limpeza dos cookies sem enviar cabeçalhos.
 */
function wp_clear_auth_cookie(): void {
	++ArnWordPressDoubles::$cookies_cleared;
}

/**
 * Estado corrente de autenticação em memória.
 */
function is_user_logged_in(): bool {
	return ArnWordPressDoubles::$logged_in;
}

/**
 * Retém a ligação aos mecanismos WordPress para conferir os argumentos.
 *
 * @param string   $hook          Nome do filtro.
 * @param callable $callback      Validador registado.
 * @param int      $priority      Prioridade.
 * @param int      $accepted_args Número de argumentos.
 */
function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	ArnWordPressDoubles::$filters[ $hook ] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

/**
 * Consulta o catálogo em memória ou mantém a mensagem original.
 *
 * @param string $message Texto.
 * @param string $domain  Domínio de tradução.
 */
function __( string $message, string $domain ): string {
	return ArnWordPressDoubles::$translations[ $domain ][ $message ] ?? $message;
}
