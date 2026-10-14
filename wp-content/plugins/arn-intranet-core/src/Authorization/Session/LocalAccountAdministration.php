<?php
/** Fronteiras comuns das ferramentas administrativas da fixture local.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Session;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationStorageService;
use Arn\Intranet\Authorization\Storage\ChangeContext;
use Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;
use WP_User;

/** Fronteira administrativa comum, sempre fechada fora do laboratório. */
final class LocalAccountAdministration {
	/** Fábrica diferida da persistência.
	 *
	 * @var Closure():AuthorizationRepository
	 */
	private readonly Closure $repository_factory;
	/**
	 * Não abre ligações antes dos gates efectivos.
	 *
	 * @param Closure|null $repository_factory Composição interna de testes.
	 */
	public function __construct( ?Closure $repository_factory = null ) {
		global $wpdb;
		$prefix                   = $wpdb->prefix ?? '';
		$this->repository_factory = $repository_factory ?? static function () use ( $prefix ): AuthorizationRepository {
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $prefix );
			return new WpdbAuthorizationRepository( $database );
		};
	}
	/** Exige ambas as flags e a autoridade administrativa efectiva. */
	public function allowed(): bool {
		return defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) && true === ARN_ALLOW_FICTIONAL_LOCAL_ACCESS
			&& ( new LocalAuthorizationStorageAuthority() )->canManage( get_current_user_id() );
	}
	/** Resolve exclusivamente Ana Teste existente e marcada. */
	public function fixture(): ?WP_User {
		$fixture = get_user_by( 'login', LocalFixtureUser::LOGIN );
		return $fixture instanceof WP_User && LocalFixtureUser::LOGIN === $fixture->user_login && ( new LocalAuthorizationStorageAuthority() )->canUseAccount( $fixture->ID ) ? $fixture : null;
	}
	/** Cria o serviço auditado depois da autorização. */
	public function service(): AuthorizationStorageService {
		if ( ! $this->allowed() ) {
			wp_die( 'Operação local não autorizada.', '', array( 'response' => 403 ) );
		}
		return new AuthorizationStorageService( ( $this->repository_factory )(), new LocalAuthorizationStorageAuthority() );
	}
	/** Valida método e nonce próprio antes de ler dados.
	 *
	 * @param string $action Despacho fixo da ferramenta.
	 */
	public function requirePost( string $action ): void {
		if ( ! $this->allowed() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			wp_die( 'Operação local não autorizada.', '', array( 'response' => 403 ) );
		}
		if ( ( $_POST['action'] ?? null ) !== $action || ! isset( $_POST['_wpnonce'] ) || ! is_string( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), $action ) ) {
			wp_die( 'Pedido local inválido.', '', array( 'response' => 403 ) );
		}
	}
	/** Exige decimal positivo canónico e representável neste servidor.
	 *
	 * @param mixed $value Entrada recebida.
	 */
	public function positive( mixed $value ): int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/\A[1-9][0-9]*\z/', $value ) || (string) (int) $value !== $value ) {
			wp_die( 'Dados do pedido local inválidos.', '', array( 'response' => 400 ) );
		}
		return (int) $value;
	}
	/** Resolve actor e instante internamente e valida a justificação.
	 *
	 * @param mixed  $reason Justificação recebida.
	 * @param string $reference Referência fixa da ferramenta.
	 */
	public function context( mixed $reason, string $reference ): ChangeContext {
		if ( ! is_string( $reason ) ) {
			wp_die( 'Dados do pedido local inválidos.', '', array( 'response' => 400 ) );
		}
		try {
			return new ChangeContext( get_current_user_id(), wp_unslash( $reason ), $reference, time() );
		} catch ( Throwable ) {
			wp_die( 'Justificação inválida. Usa uma frase curta numa só linha, sem espaços no início ou no fim.', '', array( 'response' => 400 ) );
		}
	}
}
