<?php
/**
 * Read the current local documentary actor from WordPress.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Documents\Catalog;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;
use WP_Session_Tokens;
use WP_User;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment

/** No principal, snapshot or session data is accepted from HTTP arguments. */
final class CurrentDocumentActor {
	private readonly Closure $repository_factory;
	private readonly Closure $principal;
	private readonly Closure $session_reader;

	public function __construct( ?Closure $repository_factory = null, ?Closure $principal = null, ?Closure $session_reader = null ) {
		global $wpdb;
		$prefix                   = $wpdb->prefix ?? '';
		$this->repository_factory = $repository_factory ?? static function () use ( $prefix ): WpdbAuthorizationRepository {
			$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$db->set_prefix( $prefix );
			return new WpdbAuthorizationRepository( $db );
		};
		$this->principal          = $principal ?? static function (): int {
			if ( ! LocalDocumentCatalog::configured() || ! current_user_can( 'read' ) ) {
				return 0;
			}
			$id   = get_current_user_id();
			$user = get_userdata( $id );
			return $user instanceof WP_User && $id > 0 && $id === $user->ID
				&& in_array( $user->user_login, array( LocalFixtureUser::LOGIN, 'rh.documentos.teste' ), true )
				&& '1' === get_user_meta( $id, LocalFixtureUser::META_KEY, true ) ? $id : 0;
		};
		$this->session_reader     = $session_reader ?? static fn( int $id ): array => self::session( $id );
	}

	/** Returns [snapshot, id, server session] for an allowed fixture only. */
	public function read(): ?array {
		try {
			$id = ( $this->principal )();
			if ( ! is_int( $id ) || $id < 1 ) {
				return null;
			}
			$snapshot = ( $this->repository_factory )()->find( $id );
			$session  = $this->sessionFor( $id );
			return LocalAccountSessionPolicy::valid( $snapshot, $id, $session, true ) ? array( $snapshot, $id, $session ) : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	/** Fetch fresh server token metadata with the same trusted provider. */
	public function sessionFor( int $user_id ): array {
		return ( $this->session_reader )( $user_id );
	}

	/** Current server principal only, without a new authorization transaction. */
	public function principalId(): ?int {
		$id = ( $this->principal )();
		return is_int( $id ) && $id > 0 ? $id : null;
	}

	/** Re-read token metadata after SQL locks rather than trusting a prior copy. */
	public static function session( int $user_id ): array {
		$token = wp_get_session_token();
		return '' === $token ? array() : ( WP_Session_Tokens::get_instance( $user_id )->get( $token ) ?? array() );
	}
}
