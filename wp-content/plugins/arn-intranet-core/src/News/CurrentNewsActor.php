<?php
/** Server-side current fictitious news actor.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\News;

use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;
use WP_Session_Tokens;
use WP_User;

// phpcs:disable Squiz.Commenting.FunctionComment, Squiz.Commenting.VariableComment

/** Current marked fixture and its server token metadata. */
final class CurrentNewsActor {
	private readonly Closure $repository_factory;
	private readonly Closure $principal;
	private readonly Closure $session_reader;
	private readonly Closure $reviewer_provider;
	private readonly Closure $participant_provider;

	public function __construct( ?Closure $repository_factory = null, ?Closure $principal = null, ?Closure $session_reader = null, ?Closure $reviewer_provider = null, ?Closure $participant_provider = null ) {
		global $wpdb;
		$roster                     = new LocalNewsRoster();
		$prefix                     = $wpdb->prefix ?? '';
		$this->repository_factory   = $repository_factory ?? static function () use ( $prefix ): WpdbAuthorizationRepository {
			$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$db->set_prefix( $prefix );
			return new WpdbAuthorizationRepository( $db );
		};
		$this->principal            = $principal ?? static function () use ( $roster ): int {
			if ( ! NewsConfiguration::configured() || ! current_user_can( 'read' ) ) {
				return 0;
			}
			$id   = get_current_user_id();
			$user = get_userdata( $id );
			return $user instanceof WP_User && $id > 0 && $id === $user->ID && null !== $roster->unitsFor( $id ) ? $id : 0;
		};
		$this->session_reader       = $session_reader ?? static fn( int $id ): array => self::session( $id );
		$this->reviewer_provider    = $reviewer_provider ?? static fn(): array => $roster->reviewerIds();
		$this->participant_provider = $participant_provider ?? static fn(): array => $roster->participantUnits();
	}

	/** Returns snapshot, account ID and server session for the current marked actor. */
	public function read(): ?array {
		try {
			$id = ( $this->principal )();
			if ( ! is_int( $id ) || $id < 1 ) {
				return null;
			}
			if ( null === $this->unitsFor( $id ) ) {
				return null;
			}
			$snapshot = ( $this->repository_factory )()->find( $id );
			$session  = $this->sessionFor( $id );
			return LocalAccountSessionPolicy::valid( $snapshot, $id, $session, true ) ? array( $snapshot, $id, $session ) : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	public function sessionFor( int $user_id ): array {
		return ( $this->session_reader )( $user_id );
	}

	/** Trusted server roster; grants and account state are checked after locks. */
	public function reviewerIds(): array {
		return ( $this->reviewer_provider )();
	}

	public function participantUnits(): array {
		$people = ( $this->participant_provider )();
		return is_array( $people ) ? $people : array();
	}

	public function unitsFor( int $user_id ): ?array {
		$people = $this->participantUnits();
		return isset( $people[ $user_id ] ) && is_array( $people[ $user_id ] ) ? $people[ $user_id ] : null;
	}

	public static function session( int $user_id ): array {
		$token = wp_get_session_token();
		return '' === $token ? array() : ( WP_Session_Tokens::get_instance( $user_id )->get( $token ) ?? array() );
	}
}
