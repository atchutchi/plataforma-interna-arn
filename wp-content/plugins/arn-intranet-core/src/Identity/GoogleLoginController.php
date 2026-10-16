<?php
/**
 * Entrada Google local e associação administrativa explícita no WordPress.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Identity;

use Closure;
use RuntimeException;
use Throwable;
use WP_Session_Tokens;
use Arn\Intranet\Identity\PreRegistration\CurrentPreRegistration;

/** Mantém o protocolo no cliente mantido e as decisões locais no serviço auditado. */
final class GoogleLoginController {

	/**
	 * Cliente mantido que executa e valida o protocolo OIDC.
	 *
	 * @var MaintainedGoogleClient
	 */
	private readonly MaintainedGoogleClient $client;

	/**
	 * Transformação opcional do endereço para ensaios isolados.
	 *
	 * @var Closure|null
	 */
	private readonly ?Closure $authorization_transform;

	/**
	 * O transporte e a transformação servem os ensaios isolados por injecção PHP.
	 * A instalação normal não fornece estes argumentos e usa os endpoints Google.
	 *
	 * @param MaintainedGoogleClient|null $client Cliente mantido ou transporte de ensaio.
	 * @param callable|null               $authorization_transform Transformação exclusiva do ensaio.
	 */
	public function __construct( ?MaintainedGoogleClient $client = null, ?callable $authorization_transform = null ) {
		$this->client                  = $client ?? new MaintainedGoogleClient();
		$this->authorization_transform = null === $authorization_transform ? null : Closure::fromCallable( $authorization_transform );
	}
	/** Regista as acções públicas estritamente necessárias e ferramentas privadas. */
	public function registerHooks(): void {
		add_action( 'login_form_arn_google', array( $this, 'startLogin' ) );
		add_action( 'login_form_arn_google_callback', array( $this, 'callback' ) );
		add_action( 'login_form', array( $this, 'loginButton' ) );
		add_action( 'admin_menu', array( $this, 'adminMenu' ) );
		add_action( 'admin_post_arn_google_link', array( $this, 'startLink' ) );
		add_action( 'admin_post_arn_google_revoke', array( $this, 'revokeLink' ) );
	}
	/**
	 * Consulta protegida, limitada ao laboratório autorizado nesta entrega.
	 *
	 * @throws RuntimeException Quando a configuração ou o callback é inválido.
	 */
	public static function configuration(): ?OidcConfiguration {
		if ( 'local' !== wp_get_environment_type() ) {
			return null;
		}
		$file = defined( 'ARN_OIDC_CONFIG_FILE' ) ? ARN_OIDC_CONFIG_FILE : getenv( 'ARN_OIDC_CONFIG_FILE' );
		if ( ! is_string( $file ) || '' === $file ) {
			return null;
		}
		$config = ProtectedOidcConfiguration::load( $file, ABSPATH, dirname( __DIR__, 5 ), wp_get_environment_type() );
		if ( null !== $config && site_url( '/wp-login.php?action=arn_google_callback' ) !== $config->redirect_uri ) {
			throw new RuntimeException( 'O callback não corresponde a esta instalação.' );
		}
		return $config;
	}
	/** Serviço com ligação dedicada, sem cache ou permissões por email. */
	public static function links(): IdentityLinkService {
		return new IdentityLinkService( self::repository(), new WordPressIdentityLinkAuthority() );
	}

	/**
	 * Consulta vínculos persistidos mesmo sem marcador de instalação.
	 *
	 * @param int $id Conta local.
	 * @throws RuntimeException Quando desaparece um schema já instalado.
	 */
	public static function linkedUser( int $id ): ?LocalIdentityLink {
		$repository = self::repository();
		if ( ! $repository->hasSchema() ) {
			if ( '1' === get_option( 'arn_oidc_schema_ready' ) ) {
				throw new RuntimeException();
			}
			return null;
		}
		return ( new IdentityLinkService( $repository, new WordPressIdentityLinkAuthority() ) )->findForUser( $id );
	}

	/** Cria a persistência dedicada com o prefixo do site corrente. */
	private static function repository(): WpdbIdentityLinkRepository {
		global $wpdb;
		$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$database->set_prefix( $wpdb->prefix );
		return new WpdbIdentityLinkRepository( $database );
	}
	/** Botão de entrada institucional, sem recolher a palavra-passe Google. */
	public function loginButton(): void {
		try {
			if ( null === self::configuration() ) {
				return;
			}
			echo '<p class="arn-google-login"><a class="button button-primary" href="' . esc_url( site_url( '/wp-login.php?action=arn_google' ) ) . '">' . esc_html__( 'Entrar com Google', 'arn-intranet-core' ) . '</a></p>';
		} catch ( Throwable ) {
			echo '<p>' . esc_html__( 'A entrada Google está temporariamente indisponível.', 'arn-intranet-core' ) . '</p>';
		}
	}
	/** Inicia exclusivamente o fluxo de entrada, sem conta alvo fornecida por URL. */
	public function startLogin(): never {
		$this->start( 'login', 0, 0 );
	}
	/**
	 * Inicia uma associação com capacidade local, nonce e confirmação explícita.
	 *
	 * @throws RuntimeException Interceptada internamente para recusar falhas na preparação do esquema.
	 */
	public function startLink(): never {
		$this->requireAdministrator( 'arn_google_link' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- requireAdministrator verifica check_admin_referer antes de ler este pedido.
		$target = isset( $_POST['target_user_id'] ) ? absint( $_POST['target_user_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- requireAdministrator verifica o nonce arn_google_link antes de ler a confirmação.
		if ( ! isset( $_POST['confirm_link'] ) || '1' !== wp_unslash( $_POST['confirm_link'] ) || $target < 1 || false === get_userdata( $target ) ) {
			$this->fail();
		}
		try {
			if ( null !== CurrentPreRegistration::lookup( $target ) ) {
				throw new RuntimeException();
			}
			self::links()->installSchema( get_current_user_id() );
			update_option( 'arn_oidc_schema_ready', '1', false );
			if ( '1' !== get_option( 'arn_oidc_schema_ready' ) ) {
				throw new RuntimeException();
			}
		} catch ( Throwable ) {
			$this->fail();
		}
		$this->start( 'link', $target, get_current_user_id() );
	}
	/**
	 * Liga state/nonce/PKCE ao browser e à sessão do administrador, quando necessário.
	 *
	 * @param string $purpose Finalidade login ou link.
	 * @param int    $target Conta local previamente escolhida.
	 * @param int    $actor Actor local que confirmou a associação.
	 * @throws RuntimeException Interceptada internamente para recusar configuração indisponível.
	 */
	private function start( string $purpose, int $target, int $actor ): never {
		try {
			$config = self::configuration();
			if ( null === $config ) {
				throw new RuntimeException();
			}
			$context = array(
				'state'          => bin2hex( random_bytes( 32 ) ),
				'nonce'          => bin2hex( random_bytes( 32 ) ),
				'code_verifier'  => bin2hex( random_bytes( 32 ) ),
				'issued_at'      => time(),
				'purpose'        => $purpose,
				'target_user_id' => $target,
				'actor_id'       => $actor,
			);
			if ( 'link' === $purpose ) {
				$context['actor_token_hash'] = hash( 'sha256', wp_get_session_token() );
			}
			$this->attempts()->put( $context );
			$url = $this->client->authorizationUrl( $config, $context );
			if ( null !== $this->authorization_transform ) {
				$url = ( $this->authorization_transform )( $url );
			}
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- URL criada pelo cliente fixo Google, nunca por parâmetro de pedido.
			wp_redirect( $url, 302 );
			exit;
		} catch ( Throwable ) {
			$this->fail();
		}
	}
	/**
	 * Callback de uso único, com política local antes de emitir qualquer sessão.
	 *
	 * @throws RuntimeException Interceptada internamente para recusar pedidos, identidades ou vínculos inválidos.
	 */
	public function callback(): never {
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		try {
			$config = self::configuration();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Apenas limita o pedido antes de consumir state/nonce e validar OIDC, sem conceder acesso.
			if ( null === $config || 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || count( $_GET ) > 10 ) {
				throw new RuntimeException();
			}
			$context = $this->attempts()->consume();
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- state/nonce consumíveis verificados pelo cliente mantido, antes de qualquer concessão.
			$params  = wp_unslash( $_GET );
			$claims  = $this->client->complete( $config, $params, $context );
			$service = self::links();
			if ( 'link' === $context['purpose'] ) {
				if ( null !== CurrentPreRegistration::lookup( $context['target_user_id'] ) ) {
					throw new RuntimeException();
				}
				$actor = get_current_user_id();
				if ( $actor !== $context['actor_id'] || ! current_user_can( 'manage_options' ) ||
					! hash_equals( $context['actor_token_hash'], hash( 'sha256', wp_get_session_token() ) ) ) {
					throw new RuntimeException();
				}
				$proof = new LocalIdentityLink( $context['target_user_id'], $claims['iss'], $claims['sub'], true, true );
				if ( GoogleAccountPolicy::localUserId( $claims, $config->workspace_domain, array( $proof ) ) !== $proof->user_id ) {
					throw new RuntimeException();
				}
				$existing = $service->find( $proof->issuer, $proof->subject );
				if ( null === $existing ) {
					$service->create( $actor, $proof->user_id, $proof->issuer, $proof->subject );
				} elseif ( $existing->user_id !== $proof->user_id ) {
					throw new RuntimeException();
				}
				if ( is_wp_error(
					wp_update_user(
						array(
							'ID'                  => $proof->user_id,
							'user_activation_key' => '',
						)
					)
				) ) {
					throw new RuntimeException();
				}
				$service->activate( $actor, $proof->user_id );
				WP_Session_Tokens::get_instance( $proof->user_id )->destroy_all();
				if ( $actor === $proof->user_id ) {
					wp_clear_auth_cookie();
				}
				wp_safe_redirect( wp_login_url(), 302 );
				exit;
			}
			$link         = CurrentPreRegistration::loginResolver()->resolve( $claims, $config, time() );
			$registration = CurrentPreRegistration::lookup( $link->user_id );
			( new WordPressFederatedSession() )->issue( $link, $registration?->revision );
			nocache_headers();
			header( 'Referrer-Policy: no-referrer' );
			wp_safe_redirect( home_url( '/' ), 302 );
			exit;
		} catch ( Throwable ) {
			$this->fail();
		}
	}
	/** Armazenamento privado derivado do ficheiro já validado, fora do webroot/Git. */
	private function attempts(): PhpOidcAttemptStore {
		$file = defined( 'ARN_OIDC_CONFIG_FILE' ) ? ARN_OIDC_CONFIG_FILE : getenv( 'ARN_OIDC_CONFIG_FILE' );
		return new PhpOidcAttemptStore( dirname( (string) realpath( $file ) ) . DIRECTORY_SEPARATOR . 'sessions', is_ssl() );
	}
	/** Página administrativa, acessível apenas com competência local. */
	public function adminMenu(): void {
		add_management_page( __( 'Acesso Google local', 'arn-intranet-core' ), __( 'Acesso Google local', 'arn-intranet-core' ), 'manage_options', 'arn-google-access', array( $this, 'adminPage' ) );
	}
	/**
	 * Interface explícita de associação de contas previamente existentes.
	 *
	 * @throws RuntimeException Interceptada internamente para apresentar a configuração indisponível.
	 */
	public function adminPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->fail();
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Acesso Google local', 'arn-intranet-core' ) . '</h1>';
		try {
			if ( null === self::configuration() ) {
				throw new RuntimeException();
			}
		} catch ( Throwable ) {
			echo '<p>' . esc_html__( 'O cliente Google local ainda não está configurado. A configuração é guardada fora do repositório.', 'arn-intranet-core' ) . '</p></div>';
			return;
		}
		echo '<p>' . esc_html__( 'Ensaio local. Escolhe uma conta WordPress já criada. A identidade Google será validada no callback e a associação ficará auditada. Os papéis existentes não são alterados.', 'arn-intranet-core' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'arn_google_link' );
		echo '<input type="hidden" name="action" value="arn_google_link"><p><label for="arn-target">' . esc_html__( 'Conta local a associar', 'arn-intranet-core' ) . '</label> <select id="arn-target" name="target_user_id">';
		foreach ( get_users( array( 'fields' => array( 'ID', 'user_login' ) ) ) as $account ) {
			try {
				if ( null !== CurrentPreRegistration::lookup( (int) $account->ID ) ) {
					continue;
				}
			} catch ( Throwable ) {
				continue;
			}
			echo '<option value="' . esc_attr( (string) $account->ID ) . '">' . esc_html( $account->user_login ) . '</option>';
		}
		echo '</select></p><p><label><input type="checkbox" name="confirm_link" value="1" required> ' . esc_html__( 'Confirmo a conta alvo e a activação da identidade Google que vou autenticar.', 'arn-intranet-core' ) . '</label></p>';
		submit_button( __( 'Validar e associar com Google', 'arn-intranet-core' ) );
		echo '</form><hr><h2>' . esc_html__( 'Revogar acesso', 'arn-intranet-core' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'arn_google_revoke' );
		echo '<input type="hidden" name="action" value="arn_google_revoke"><p><label for="arn-revoke">' . esc_html__( 'ID da conta local vinculada', 'arn-intranet-core' ) . '</label> <input id="arn-revoke" type="number" min="1" name="target_user_id" required></p>';
		submit_button( __( 'Revogar vínculo e sessões', 'arn-intranet-core' ), 'secondary' );
		echo '</form></div>';
	}
	/** Revogação auditada e destruição imediata das sessões da conta alvo. */
	public function revokeLink(): never {
		$this->requireAdministrator( 'arn_google_revoke' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- requireAdministrator verifica o nonce arn_google_revoke antes de ler a conta alvo.
		$target = isset( $_POST['target_user_id'] ) ? absint( $_POST['target_user_id'] ) : 0;
		try {
			self::links()->revoke( get_current_user_id(), $target );
			WP_Session_Tokens::get_instance( $target )->destroy_all();
			wp_safe_redirect( admin_url( 'tools.php?page=arn-google-access' ), 302 );
			exit;
		} catch ( Throwable ) {
			$this->fail();
		}
	}
	/**
	 * Exige administrador local, pedido POST e nonce da acção.
	 *
	 * @param string $action Finalidade do nonce administrativo.
	 */
	private function requireAdministrator( string $action ): void {
		if ( 'local' !== wp_get_environment_type() || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			$this->fail();
		}
		check_admin_referer( $action );
	}
	/** Resposta constante que não expõe tokens, claims, SQL ou causas. */
	private function fail(): never {
		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		wp_die(
			esc_html__( 'Não foi possível concluir a entrada Google. Volta à página de entrada ou contacta o administrador local.', 'arn-intranet-core' ),
			esc_html__( 'Entrada não concluída', 'arn-intranet-core' ),
			array(
				'response'  => 403,
				'back_link' => true,
			)
		);
	}
}
