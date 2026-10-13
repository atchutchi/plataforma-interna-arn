<?php
/**
 * Tentativa OIDC de uso único numa sessão PHP privada e dedicada.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Mensagem constante sem dados externos, escapada pelo chamador se a apresentar.
// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged -- Cada resultado nativo é verificado e convertido em falha genérica, sem divulgar caminhos ou IDs.
// phpcs:disable WordPress.WP.AlternativeFunctions -- Armazenamento PHP puro sem dependência de WordPress.
// phpcs:disable WordPress.PHP.IniSet.Risky -- Define deliberadamente os controlos de segurança da sessão dedicada antes do arranque.

use Closure;
use RuntimeException;

/** O chamador valida que a pasta fica fora da raiz pública do WordPress. */
final class PhpOidcAttemptStore {

	private const FAILURE = 'Não foi possível validar a tentativa de autenticação.';
	private const TTL     = 300;
	/**
	 * Pasta privada validada e resolvida.
	 *
	 * @var string
	 */
	private readonly string $private_directory;
	/**
	 * Relógio local em segundos Unix.
	 *
	 * @var Closure
	 */
	private readonly Closure $clock;

	/**
	 * Valida o destino privado e configura o relógio da tentativa.
	 *
	 * @param string        $private_directory Pasta privada exclusiva de sessões.
	 * @param bool          $secure_cookie    Origem HTTPS, excepto localhost explicitamente autorizado.
	 * @param callable|null $clock            Relógio que devolve segundos Unix.
	 * @throws RuntimeException Se o armazenamento privado não estiver disponível.
	 */
	public function __construct( string $private_directory, private readonly bool $secure_cookie, ?callable $clock = null ) {
		if ( '' === $private_directory || str_contains( $private_directory, "\0" ) ||
			1 !== preg_match( '~^(?:[a-zA-Z]:[/\\\\]|/)~', $private_directory ) ||
			1 === preg_match( '~(?:^|[/\\\\])\.{1,2}(?:[/\\\\]|$)~', $private_directory ) ) {
			throw new RuntimeException( self::FAILURE );
		}
		$path = rtrim( $private_directory, '/\\' );
		for ( $ancestor = $path; ; $ancestor = dirname( $ancestor ) ) {
			if ( is_link( $ancestor ) ) {
				throw new RuntimeException( self::FAILURE );
			}
			if ( dirname( $ancestor ) === $ancestor ) {
				break;
			}
		}
		if ( ! is_dir( $path ) && ! @mkdir( $path, 0700 ) ) {
			throw new RuntimeException( self::FAILURE );
		}
		$resolved = realpath( $path );
		if ( false === $resolved || ! is_dir( $resolved ) || ! is_writable( $resolved ) ||
			( '\\' !== DIRECTORY_SEPARATOR && ( false === fileperms( $resolved ) || 0 !== ( fileperms( $resolved ) & 0077 ) ) ) ) {
			throw new RuntimeException( self::FAILURE );
		}
		$this->private_directory = $resolved;
		$this->clock             = null === $clock ? static fn(): int => time() : Closure::fromCallable( $clock );
	}

	/**
	 * Guarda só os campos autorizados e substitui a tentativa anterior.
	 *
	 * @param array<string,mixed> $context Contexto PKCE, nonce e autorização local.
	 * @throws RuntimeException Se a tentativa ou sessão forem inválidas.
	 */
	public function put( array $context ): void {
		$this->validateContext( $context );
		$this->start();
		if ( ! @session_regenerate_id( true ) ) {
			@session_write_close();
			throw new RuntimeException( self::FAILURE );
		}
		$_SESSION = array( 'arn_oidc_attempt' => $context );
		if ( ! @session_write_close() ) {
			throw new RuntimeException( self::FAILURE );
		}
	}

	/**
	 * Retira a tentativa sob o lock nativo antes de validar ou devolver valores.
	 *
	 * @return array<string,mixed> Contexto consumido para o cliente OIDC mantido.
	 * @throws RuntimeException Se estiver ausente, inválido ou expirado.
	 */
	public function consume(): array {
		$this->start();
		$context = $_SESSION['arn_oidc_attempt'] ?? null;
		unset( $_SESSION['arn_oidc_attempt'] );
		if ( ! @session_write_close() || ! is_array( $context ) ) {
			throw new RuntimeException( self::FAILURE );
		}
		$this->validateContext( $context );
		return $context;
	}

	/**
	 * Abre exclusivamente o motor files para manter leitura e remoção atómicas.
	 *
	 * @throws RuntimeException Se os cabeçalhos ou outra sessão impedirem o isolamento.
	 */
	private function start(): void {
		if ( PHP_SESSION_NONE !== session_status() || headers_sent() ) {
			throw new RuntimeException( self::FAILURE );
		}
		foreach ( array(
			'session.use_strict_mode'   => '1',
			'session.use_only_cookies'  => '1',
			'session.use_cookies'       => '1',
			'session.use_trans_sid'     => '0',
			'session.serialize_handler' => 'php',
			'session.gc_maxlifetime'    => (string) self::TTL,
		) as $setting => $value ) {
			if ( false === @ini_set( $setting, $value ) ) {
				throw new RuntimeException( self::FAILURE );
			}
		}
		if ( false === @session_module_name( 'files' ) || false === @session_save_path( $this->private_directory ) ||
			false === @session_name( 'ARN_OIDC_FLOW' ) || ! @session_set_cookie_params(
				array(
					'lifetime' => 0,
					'path'     => '/wp-login.php',
					'domain'   => '',
					'secure'   => $this->secure_cookie,
					'httponly' => true,
					'samesite' => 'Lax',
				)
			) || ! @session_start() ) {
			throw new RuntimeException( self::FAILURE );
		}
	}

	/**
	 * Não guarda tokens, segredos ou dados livres fornecidos pelo callback.
	 *
	 * @param array<string,mixed> $context Contexto a verificar.
	 * @throws RuntimeException Se faltar um campo ou se o prazo não for válido.
	 */
	private function validateContext( array $context ): void {
		$fields = array( 'state', 'nonce', 'code_verifier', 'issued_at', 'purpose', 'target_user_id', 'actor_id' );
		$link   = 'link' === ( $context['purpose'] ?? null );
		if ( $link ) {
			$fields[] = 'actor_token_hash';
		}
		if ( array_diff( array_keys( $context ), $fields ) || array_diff( $fields, array_keys( $context ) ) ) {
			throw new RuntimeException( self::FAILURE );
		}
		foreach ( array( 'state', 'nonce' ) as $field ) {
			if ( ! is_string( $context[ $field ] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $context[ $field ] ) ) {
				throw new RuntimeException( self::FAILURE );
			}
		}
		$now = ( $this->clock )();
		if ( ! is_int( $now ) || ! is_int( $context['issued_at'] ) || $context['issued_at'] < 0 ||
			$context['issued_at'] > $now || $now - $context['issued_at'] > self::TTL ||
			! is_string( $context['code_verifier'] ) || 1 !== preg_match( '/^[A-Za-z0-9._~-]{43,128}$/D', $context['code_verifier'] ) ||
			! in_array( $context['purpose'], array( 'login', 'link' ), true ) ||
			! is_int( $context['target_user_id'] ) || ! is_int( $context['actor_id'] ) ||
			( ! $link && ( 0 !== $context['target_user_id'] || 0 !== $context['actor_id'] ) ) ||
			( $link && ( $context['target_user_id'] < 1 || $context['actor_id'] < 1 ||
				! is_string( $context['actor_token_hash'] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $context['actor_token_hash'] ) ) ) ) {
			throw new RuntimeException( self::FAILURE );
		}
	}
}
