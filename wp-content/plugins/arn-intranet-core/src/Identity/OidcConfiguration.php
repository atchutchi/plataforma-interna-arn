<?php
/**
 * Configuração externa de OIDC sem opções persistidas ou segredos no Git.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use InvalidArgumentException;
use LogicException;

/**
 * A configuração validada não activa o cliente nem demonstra homologação.
 */
final readonly class OidcConfiguration {

	public const SCOPES = array( 'openid', 'email' );

	/**
	 * Usa a fábrica para exigir a configuração completa e validar o callback.
	 *
	 * @param string $client_id        ID externo aprovado.
	 * @param string $client_secret    Segredo externo apenas em memória.
	 * @param string $redirect_uri     Callback exacto a registar institucionalmente.
	 * @param string $workspace_domain Domínio Google Workspace aprovado.
	 */
	private function __construct(
		public string $client_id,
		private string $client_secret,
		public string $redirect_uri,
		public string $workspace_domain,
	) {}

	/**
	 * O leitor pode consultar getenv ou configuração protegida do ambiente.
	 * A flag 1 é deliberada. Ausência e outros valores não lêem os segredos.
	 *
	 * @param callable(string): (string|false) $read             Leitor de variáveis externas.
	 * @param string                           $environment_type Tipo explícito de ambiente WordPress.
	 *
	 * @throws InvalidArgumentException Em configuração activa incompleta ou inválida.
	 */
	public static function fromEnvironment( callable $read, string $environment_type = 'production' ): ?self {
		if ( '1' !== $read( 'ARN_OIDC_ENABLED' ) ) {
			return null;
		}

		$values = array();
		foreach ( array( 'CLIENT_ID', 'CLIENT_SECRET', 'REDIRECT_URI', 'WORKSPACE_DOMAIN' ) as $field ) {
			$value = $read( 'ARN_OIDC_' . $field );
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				throw new InvalidArgumentException( 'Configuração OIDC activa incompleta.' );
			}
			$values[ $field ] = $value;
		}

		$allow_local_http = 'local' === $environment_type && '1' === $read( 'ARN_OIDC_ALLOW_LOCAL_HTTP' );
		if ( ! self::validDomain( $values['WORKSPACE_DOMAIN'] ) || ! self::validRedirect( $values['REDIRECT_URI'], $allow_local_http ) ) {
			throw new InvalidArgumentException( 'Domínio ou callback OIDC inválido.' );
		}

		return new self( $values['CLIENT_ID'], $values['CLIENT_SECRET'], $values['REDIRECT_URI'], $values['WORKSPACE_DOMAIN'] );
	}

	/**
	 * Apenas o futuro adaptador OIDC deve consumir este valor, nunca logs.
	 */
	public function clientSecret(): string {
		return $this->client_secret;
	}

	/**
	 * Redige o segredo nas inspecções de debug normais.
	 *
	 * @return array<string, string>
	 */
	public function __debugInfo(): array {
		return array(
			'client_id'        => $this->client_id,
			'client_secret'    => '[oculto]',
			'redirect_uri'     => $this->redirect_uri,
			'workspace_domain' => $this->workspace_domain,
		);
	}

	/**
	 * Não colocar esta configuração em cache ou opções serializadas.
	 *
	 * @return array Nunca devolvido.
	 * @throws LogicException Sempre, para impedir persistência acidental do segredo.
	 */
	public function __serialize(): array {
		return throw new LogicException( 'A configuração OIDC não pode ser serializada.' );
	}

	/**
	 * Domínio DNS explícito e canónico. Não prova posse ou aprovação Google.
	 *
	 * @param string $domain Nome configurado.
	 */
	public static function validDomain( string $domain ): bool {
		$label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
		return strlen( $domain ) <= 253 &&
			1 === preg_match( '/^(?:' . $label . '\.)+[a-z]{2,63}$/D', $domain ) &&
			! str_ends_with( $domain, '.local' );
	}

	/**
	 * Callback HTTPS exacto. Query estática é admitida para clientes WordPress.
	 *
	 * @param string $uri              URL do callback.
	 * @param bool   $allow_local_http Opt-in reservado ao ambiente local.
	 */
	private static function validRedirect( string $uri, bool $allow_local_http ): bool {
		if ( false === filter_var( $uri, FILTER_VALIDATE_URL ) || 1 === preg_match( '/[\x00-\x20\x7f\\\\]/', $uri ) ||
			1 === preg_match( '/%(?![a-f0-9]{2})/i', $uri ) ) {
			return false;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Regra pura executável sem WordPress.
		$parts = parse_url( $uri );
		if ( ! is_array( $parts ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ||
			( isset( $parts['port'] ) && ( $parts['port'] < 1 || $parts['port'] > 65535 ) ) ) {
			return false;
		}
		$decoded = $uri;
		$path    = $parts['path'] ?? '';
		for ( $depth = 0; $depth < 8; ++$depth ) {
			if ( 1 === preg_match( '/[\x00-\x1f\x7f\\\\]/', $decoded ) || 1 === preg_match( '~(?:^|/)\.{1,2}(?:/|$)~', $path ) ) {
				return false;
			}
			$next = rawurldecode( $decoded );
			if ( $next === $decoded ) {
				break;
			}
			$decoded = $next;
			$path    = rawurldecode( $path );
		}
		if ( 8 === $depth ) {
			return false;
		}
		if ( 'https' === ( $parts['scheme'] ?? '' ) ) {
			return self::validDomain( $parts['host'] ?? '' );
		}
		return $allow_local_http && 'http' === ( $parts['scheme'] ?? '' ) && 'localhost' === ( $parts['host'] ?? '' ) &&
			'/wp-login.php' === ( $parts['path'] ?? '' ) && 'action=arn_google_callback' === ( $parts['query'] ?? '' );
	}
}
