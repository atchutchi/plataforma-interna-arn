<?php
/**
 * Adaptadores em memória para testar os callbacks sem WordPress nem base de dados.
 * Não substituem a verificação integrada no wp-env.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

/**
 * Dados públicos que os callbacks consultam em WP_User.
 */
class WP_User {

	/**
	 * Identificador.
	 *
	 * @var int
	 */
	public int $ID;

	/**
	 * Login.
	 *
	 * @var string
	 */
	public string $user_login;

	/**
	 * Email.
	 *
	 * @var string
	 */
	public string $user_email;

	/**
	 * Cria uma conta em memória.
	 *
	 * @param int    $user_id Identificador.
	 * @param string $login   Login.
	 * @param string $email   Email.
	 */
	public function __construct( int $user_id, string $login, string $email ) {
		$this->ID         = $user_id;
		$this->user_login = $login;
		$this->user_email = $email;
	}
}
