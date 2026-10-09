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
	 * Limpa o estado entre cenários.
	 */
	public static function reset(): void {
		self::$users           = array();
		self::$meta            = array();
		self::$sessions        = array();
		self::$cookies_cleared = 0;
		self::$filters         = array();
		self::$logged_in       = false;
	}
}

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
	 * Elimina todos os tokens da conta, sem afetar as restantes.
	 */
	public function destroy_all(): void {
		ArnWordPressDoubles::$sessions[ $this->user_id ] = array();
	}
}

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
 * Devolve um metadado.
 *
 * @param int    $user_id Identificador.
 * @param string $key     Chave.
 * @param bool   $single  Devolução de um valor único.
 */
function get_user_meta( int $user_id, string $key, bool $single ): string {
	return ArnWordPressDoubles::$meta[ $user_id ][ $key ] ?? '';
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
 * Mantém a mensagem sem iniciar os catálogos WordPress.
 *
 * @param string $message Texto.
 * @param string $domain  Domínio de tradução.
 */
function __( string $message, string $domain ): string {
	return $message;
}
