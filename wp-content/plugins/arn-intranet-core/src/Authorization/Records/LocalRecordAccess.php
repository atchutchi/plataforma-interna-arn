<?php
/** Leitura dedicada da autorização e sessão correntes.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Records;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationRepository;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;
use WP_User;
use WP_Session_Tokens;

/** Nunca aceita autoridade a partir do browser e nunca escreve. */
final class LocalRecordAccess {
	/** Fábrica diferida.
	 *
	 * @var Closure
	 */
	private readonly Closure $repository_factory;
	/** Titular autorizado pela composição interna.
	 *
	 * @var Closure
	 */
	private readonly Closure $principal;
	/** A composição alternativa existe apenas para ensaios exclusivos.
	 *
	 * @param Closure|null $repository_factory Repositório dedicado.
	 * @param Closure|null $principal Resolve o titular efectivo ou zero.
	 */
	public function __construct( ?Closure $repository_factory = null, ?Closure $principal = null ) {
		global $wpdb;
		$prefix                   = $wpdb->prefix ?? '';
		$this->repository_factory = $repository_factory ?? static function () use ( $prefix ): AuthorizationRepository {
			$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$database->set_prefix( $prefix );
			$database->suppress_errors( true );
			return new WpdbAuthorizationRepository( $database );
		};
		$this->principal          = $principal ?? static function (): int {
			if ( 'local' !== wp_get_environment_type() || ! defined( 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) || true !== ARN_ALLOW_FICTIONAL_LOCAL_ACCESS || ! defined( 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE' ) || true !== ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE || ! current_user_can( 'read' ) ) {
				return 0;
			}
			$id   = get_current_user_id();
			$user = get_userdata( $id );
			return $user instanceof WP_User && $id > 0 && $id === $user->ID && LocalFixtureUser::LOGIN === $user->user_login && '1' === get_user_meta( $id, LocalFixtureUser::META_KEY, true ) ? $id : 0;
		};
	}
	/** Revalida gates efectivos sem abrir ligação SQL. */
	public function allowed(): bool {
		return ( $this->principal )() > 0;
	}
	/** Null significa ausência de prova, array vazio significa zero objectos. */
	public function read(): ?array {
		try {
			$user_id = ( $this->principal )();
			if ( $user_id <= 0 ) {
				return null;
			}
			$snapshot = ( $this->repository_factory )()->find( $user_id );
			$token    = wp_get_session_token();
			$session  = '' === $token ? array() : ( WP_Session_Tokens::get_instance( $user_id )->get( $token ) ?? array() );
			if ( ! LocalAccountSessionPolicy::valid( $snapshot, $user_id, $session, true ) ) {
				return null;
			}
			return LocalRecordSelection::visible( $snapshot, $user_id, $session, time() );
		} catch ( Throwable ) {
			return null;
		}
	}
}
