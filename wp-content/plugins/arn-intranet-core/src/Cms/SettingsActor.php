<?php
/** Detached authority for CMS settings in the local laboratory.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Cms;

use Arn\Intranet\Authorization\{Action, AuthorizationPolicy, Request, Scope};
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Authorization\Storage\AuthorizationSnapshot;

// phpcs:disable Squiz.Commenting -- Server-resolved immutable proof, not HTTP payload.
/** A native administrator role does not establish this authority. */
final readonly class SettingsActor {
	private array $session;

	public function __construct( public AuthorizationSnapshot $snapshot, array $session ) {
		$proof = array();
		foreach ( array( 'arn_authz_local', 'arn_authz_user_id', 'arn_authz_revision', 'expiration' ) as $key ) {
			if ( array_key_exists( $key, $session ) ) {
				$proof[ $key ] = $session[ $key ];
			}
		}
		$this->session = $proof;
	}

	public function id(): int {
		return $this->snapshot->user_id;
	}

	public function allows( int $now ): bool {
		return $now >= 0 && is_int( $this->session['expiration'] ?? null ) && $now < $this->session['expiration']
			&& LocalAccountSessionPolicy::valid( $this->snapshot, $this->id(), $this->session, true )
			&& ( new AuthorizationPolicy() )->allows( $this->snapshot->toAccount( true ), new Request( Action::ManageCmsSettings, Scope::institutional(), 0, 0, true, true, true, true ), $this->snapshot->policyGrants(), $now );
	}
}
