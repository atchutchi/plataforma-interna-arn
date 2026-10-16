<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Server-side directory and own-profile use cases. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use Arn\Intranet\Identity\IdentityWpdb;
use Closure;
use Throwable;

final class LocalPeopleDirectory {
	private readonly Closure $actor_factory;
	private readonly Closure $authority_factory;
	private readonly Closure $profiles_factory;
	private readonly Closure $writer_factory;
	private readonly ?string $exclusive_prefix;

	/** Factories allow isolated regression tests without changing authority. */
	public function __construct( ?Closure $actor_factory = null, ?Closure $authority_factory = null, ?Closure $profiles_factory = null, ?Closure $writer_factory = null, ?string $exclusive_prefix = null ) {
		$this->exclusive_prefix  = $exclusive_prefix;
		$this->actor_factory     = $actor_factory ?? CurrentPeopleActor::resolve( ... );
		$this->authority_factory = $authority_factory ?? static function (): PeopleAuthority {
			global $wpdb;
			$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$db->set_prefix( $wpdb->prefix );
			return new WpdbPeopleAuthority( $db );
		};
		$this->profiles_factory  = $profiles_factory ?? static function (): WpdbPeopleRepository {
			global $wpdb;
			$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
			$db->set_prefix( $wpdb->prefix );
			return new WpdbPeopleRepository( $db, new WpdbPeopleAuthority( $db ) );
		};
		$this->writer_factory    = $writer_factory ?? $this->profiles_factory;
	}

	/** Closed list model: items, total, page and pages. */
	public function contactList( PeopleQuery $query ): ?array {
		$context = $this->reader();
		return null === $context ? null : $this->listing( $context, $query );
	}

	/** Closed contact projection or null. */
	public function contact( int $user_id ): ?array {
		if ( $user_id <= 0 ) {
			return null;
		}
		$context = $this->reader();
		if ( null === $context ) {
			return null;
		}
		[$actor_id, $session, $authority, $units, $own, $repository] = $context;
		try {
			$profile = $repository->find( $user_id );
			$target  = null === $profile ? null : $authority->account( $user_id );
			return null !== $profile && null !== $target && PeopleEligibility::active( $profile, $target, $this->activeIds( $units ) ) ? PeopleProjection::contact( $profile, $this->label( $units, $profile->professional->unit_id ) ) : null;
		} catch ( Throwable ) {
			return null;
		}
	}

	/** Own projection adds revision only. */
	public function own(): ?array {
		$context = $this->reader();
		if ( null === $context ) {
			return null;
		}
		$profile = $context[4];
		return PeopleProjection::own( $profile, $this->label( $context[3], $profile->professional->unit_id ) );
	}

	/** Team is strictly limited to a manager grant on the requested unit. */
	public function team( PeopleQuery $query ): ?array {
		$context = $this->reader();
		if ( null === $context ) {
			return null;
		}
		[$actor_id, $session, $authority, $units, $own, $repository, $account] = $context;
		$unit_id = '' === $query->unit ? $own->professional->unit_id : $query->unit;
		if ( ! PeoplePolicy::canReadTeam( $unit_id, $account, $session, time() ) || ! in_array( $unit_id, $this->activeIds( $units ), true ) ) {
			return null;
		}
		return $this->listing( $context, $query, $unit_id );
	}

	/** Revalidation and commit happen in the repository's own transaction. */
	public function updateOwn( SelfService $self_service, int $expected_revision ): Profile {
		if ( ! PeopleConfiguration::current() ) {
			throw new \RuntimeException( 'People indisponível.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant exception.
		}
		$actor = ( $this->actor_factory )();
		if ( ! is_array( $actor ) || ! isset( $actor[0] ) || ! is_int( $actor[0] ) || $actor[0] <= 0 ) {
			throw new \RuntimeException( 'Actor People inválido.' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Constant exception.
		}
		return ( $this->writer_factory )()->updateOwn( $actor[0], $self_service, $expected_revision );
	}

	/** Internal own history projection: occurred_at and changed_fields only. */
	public function ownHistory(): ?array {
		$context = $this->reader();
		if ( null === $context ) {
			return null;
		}
		try {
			return array_map(
				static fn( array $event ): array => array(
					'occurred_at'    => $event['occurred_at'],
					'changed_fields' => $event['changed_fields'],
				),
				$context[5]->history( $context[0] )
			);
		} catch ( Throwable ) {
			return null;
		}
	}

	private function reader(): ?array {
		if ( ! PeopleConfiguration::current() ) {
			return null;
		}
		try {
			$actor = ( $this->actor_factory )();
			if ( ! is_array( $actor ) || ! array_is_list( $actor ) || 2 !== count( $actor ) || ! is_int( $actor[0] ) || ! is_array( $actor[1] ) || ! in_array( $actor[0], LocalPeopleRoster::ids( $this->exclusive_prefix ), true ) ) {
				return null;
			}
			[$user_id, $session] = $actor;
			$authority           = ( $this->authority_factory )();
			$account             = $authority->account( $user_id );
			if ( null === $account || ! PeoplePolicy::canReadDirectory( $account, $session, time() ) ) {
				return null;
			}
			$units      = $authority->units();
			$repository = ( $this->profiles_factory )();
			$own        = $repository->find( $user_id );
			if ( null === $own || ! PeopleEligibility::active( $own, $account, $this->activeIds( $units ) ) ) {
				return null;
			}
			return array( $user_id, $session, $authority, $units, $own, $repository, $account );
		} catch ( Throwable ) {
			return null;
		}
	}

	private function listing( array $context, PeopleQuery $query, ?string $team_unit = null ): ?array {
		[$actor_id, $session, $authority, $units, $own, $repository] = $context;
		try {
			$active             = $this->activeIds( $units );
			$selection          = PeopleSelection::select(
				$repository->all(),
				$query,
				static function ( Profile $profile ) use ( $authority, $active, $team_unit ): bool {
					if ( null !== $team_unit && $profile->professional->unit_id !== $team_unit ) {
						return false;
					}
					$target = $authority->account( $profile->user_id );
					return null !== $target && PeopleEligibility::active( $profile, $target, $active );
				}
			);
			$selection['items'] = array_map( fn( Profile $profile ): array => PeopleProjection::contact( $profile, $this->label( $units, $profile->professional->unit_id ) ), $selection['items'] );
			return $selection;
		} catch ( Throwable ) {
			return null;
		}
	}

	private function activeIds( \Arn\Intranet\Organization\Storage\LocalUnitSnapshot $units ): array {
		return array_values( array_map( static fn( $unit ): string => $unit->id, array_filter( $units->units, static fn( $unit ): bool => $unit->active ) ) );
	}

	private function label( \Arn\Intranet\Organization\Storage\LocalUnitSnapshot $units, string $id ): string {
		foreach ( $units->units as $unit ) {
			if ( $unit->id === $id ) {
				return $unit->name ?? $unit->acronym;
			}
		}
		return '';
	}
}
