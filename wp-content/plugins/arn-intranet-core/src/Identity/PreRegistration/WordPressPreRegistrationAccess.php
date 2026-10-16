<?php
/**
 * Impede autenticação nativa de contas pré-cadastradas.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Closure;
use Throwable;
use WP_Error;
use WP_User;
use WP_Session_Tokens;

// phpcs:disable Squiz.Commenting -- Adaptador de hooks com contrato fechado e testes de todos os canais nativos.
/** Não depende da activação do cliente para conservar as guardas. */
final class WordPressPreRegistrationAccess {
	private readonly Closure $lookup;
	private readonly Closure $enabled;
	public function __construct( ?callable $lookup = null, ?callable $enabled = null ) {
		$this->lookup  = Closure::fromCallable( $lookup ?? array( CurrentPreRegistration::class, 'lookup' ) );
		$this->enabled = Closure::fromCallable( $enabled ?? array( CurrentPreRegistration::class, 'enabled' ) );}
	public function register(): void {
		add_filter( 'authenticate', array( $this, 'filterAuthentication' ), PHP_INT_MAX );
		add_filter( 'determine_current_user', array( $this, 'filterCurrentUser' ), PHP_INT_MAX );
		add_filter( 'allow_password_reset', array( $this, 'filterPasswordReset' ), PHP_INT_MAX, 2 );
		add_action( 'validate_password_reset', array( $this, 'validatePasswordReset' ), PHP_INT_MAX, 2 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( $this, 'filterApplicationPasswords' ), PHP_INT_MAX, 2 );
		add_filter( 'send_auth_cookies', array( $this, 'filterSendAuthCookies' ), PHP_INT_MAX, 6 );
	}
	public function filterAuthentication( $user ) {
		if ( ! $user instanceof WP_User ) {
			return $user;
		}try {
			if ( null === ( $this->lookup )( $user->ID ) ) {
				return $user;
			}
		} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- A falha termina na recusa constante abaixo.
			/* A falha segue para a recusa constante. */}return new WP_Error( 'arn_workspace_google_required', __( 'Esta conta exige entrada Google autorizada.', 'arn-intranet-core' ) );}
	public function filterPasswordReset( $allow, int $user_id ) {
		if ( ! $allow || $allow instanceof WP_Error ) {
			return $allow;
		}try {
			return null === ( $this->lookup )( $user_id );
		} catch ( Throwable ) {
			return false;}}
	public function validatePasswordReset( WP_Error $errors, $user ): void {
		if ( $user instanceof WP_User && true !== $this->filterPasswordReset( true, $user->ID ) ) {
			$errors->add( 'arn_workspace_google_required', __( 'Esta conta exige entrada Google autorizada.', 'arn-intranet-core' ) );}}
	public function filterApplicationPasswords( bool $available, WP_User $user ): bool {
		return $available && true === $this->filterPasswordReset( true, $user->ID );}
	public function filterCurrentUser( $user_id ) {
		if ( ! $user_id ) {
			return $user_id;
		}$manager   = WP_Session_Tokens::get_instance( (int) $user_id );
		$token      = wp_get_session_token();
		$session    = '' === $token ? array() : ( $manager->get( $token ) ?? array() );
		$revoke_all = true;
		try {
			$record = ( $this->lookup )( (int) $user_id );
			if ( null === $record && ! array_key_exists( 'arn_workspace_revision', $session ) ) {
				return $user_id;
			}$enabled   = ( $this->enabled )();
			$revoke_all = null === $record || PreRegistrationState::Linked !== $record->state || true !== $enabled;
			if ( $this->valid( $record, $session, $enabled ) ) {
				return $user_id;
			}
		} catch ( Throwable ) {
			$revoke_all = true;}
		if ( $revoke_all ) {
			$manager->destroy_all();
		} else {
			$manager->destroy( $token );
		}wp_clear_auth_cookie();
		return false;
	}
	public function filterSendAuthCookies( bool $send, int $expire, int $expiration, int $user_id, string $scheme, string $token ): bool {
		if ( false === $send ) {
			return false;
		}try {
			$record = ( $this->lookup )( $user_id );
			if ( null === $record ) {
				return true;
			}$session = WP_Session_Tokens::get_instance( $user_id )->get( $token ) ?? array();
			return $this->valid( $record, $session, ( $this->enabled )() );
		} catch ( Throwable ) {
			return false;}}
	private function valid( ?PreRegistration $record, array $session, mixed $enabled ): bool {
		return null !== $record && PreRegistrationState::Linked === $record->state && true === $enabled && true === ( $session['arn_oidc'] ?? null ) && ( $session['arn_workspace_revision'] ?? null ) === $record->revision;}
}
