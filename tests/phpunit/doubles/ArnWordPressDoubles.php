<?php
/**
 * Adaptadores em memória para testar os callbacks sem WordPress nem base de dados.
 * Não substituem a verificação integrada no wp-env.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);

/**
 * Estado descartável de cada teste.
 */
final class ArnWordPressDoubles {

	/**
	 * Contas simuladas.
	 *
	 * @var array<int, WP_User>
	 */
	public static array $users = array();

	/**
	 * Metadados simulados.
	 *
	 * @var array<int, array<string, string>>
	 */
	public static array $meta = array();

	/**
	 * Tokens de sessão por conta.
	 *
	 * @var array<int, string[]>
	 */
	public static array $sessions = array();

	/**
	 * Chamadas para limpar os cookies do pedido.
	 *
	 * @var int
	 */
	public static int $cookies_cleared = 0;

	/**
	 * Registos dos callbacks.
	 *
	 * @var array<string, array{callback: callable, priority: int, accepted_args: int}>
	 */
	public static array $filters = array();

	/**
	 * Estado de autenticação depois dos validadores do pedido.
	 *
	 * @var bool
	 */
	public static bool $logged_in = false;

	/**
	 * Traduções simuladas, agrupadas por domínio e mensagem original.
	 *
	 * @var array<string, array<string, string>>
	 */
	public static array $translations = array();

	/**
	 * Limpa o estado entre cenários.
	 */
	public static function reset(): void {
		self::$users           = array();
		self::$meta            = array();
		self::$sessions        = array();
		self::$cookies_cleared = 0;
		self::$filters         = array();
		self::$logged_in       = false;
		self::$translations    = array();
	}
}
