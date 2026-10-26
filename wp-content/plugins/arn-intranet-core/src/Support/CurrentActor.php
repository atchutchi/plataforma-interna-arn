<?php
/** Task session, grants and units on the caller-owned dedicated connection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Authorization\Storage\AuthorizationSchema;
use Arn\Intranet\F112\ActorContext;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Closure;
use DomainException;
use RuntimeException;
use Throwable;
use WP_User;

// phpcs:disable Squiz.Commenting -- The locked roster/session composition is documented in the F2-04 plan.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
/** Lock accounts in ascending order, then native session and unit evidence. */
final class CurrentActor {
	private readonly array $roster;
	private ?int $current_id = null;

	/** Optional resolver is a server-side test seam, never request input. */
	public function __construct( private readonly IdentityWpdb $database, array $roster, private readonly Closure $clock, private readonly ?Closure $resolver = null ) {
		$this->roster = LocalRoster::normalize( $roster, $database->prefix );
	}

	public function connection(): IdentityWpdb {
		return $this->database;
	}

	public function currentId(): ?int {
		return $this->current_id;
	}

	public function read(): ?ActorContext {
		$this->current_id = null;
		if ( ! Configuration::current() ) {
			return null;
		}
		$resolved = $this->resolve();
		if ( null === $resolved || ! isset( $this->roster[ $resolved[0] ] ) ) {
			return null;
		}
		if ( $this->database->inTransaction() ) {
			throw new RuntimeException( 'A leitura de tickets exige ligação livre.' );
		}
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$contexts = $this->locked();
			$actor    = $contexts[ $this->current_id ] ?? null;
			$this->execute( 'COMMIT' );
			$now = $this->now();
			if ( ! $actor instanceof ActorContext || ! $actor->valid( $now ) ) {
				$this->current_id = null;
				return null;
			}
			return $actor;
		} catch ( DomainException ) {
			$this->database->query( 'ROLLBACK' );
			$this->current_id = null;
			return null;
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			$this->current_id = null;
			throw new RuntimeException( 'Autoridade de tickets indisponível.' );
		}
	}

	/** Caller must own the transaction. Offline contexts are only recipient data. */
	public function locked(): array {
		$this->current_id = null;
		if ( ! Configuration::current() || ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'Transacção de tickets ausente.' );
		}
		$resolved = $this->resolve();
		if ( null === $resolved || ! isset( $this->roster[ $resolved[0] ] ) ) {
			throw new DomainException( 'Operação de tickets recusada.' );
		}
		( new AuthorizationSchema( $this->database ) )->validate();
		$accounts  = new WpdbAuthorizationRepository( $this->database );
		$snapshots = array();
		foreach ( $this->roster as $id => $person ) {
			$snapshot = $accounts->findInTransaction( $id );
			$user     = get_userdata( $id );
			if ( null === $snapshot || ! $user instanceof WP_User || $user->user_login !== $person['login'] || $user->user_email !== $person['login'] . '@example.invalid' || array( 'subscriber' ) !== array_values( $user->roles ) || '1' !== get_user_meta( $id, LocalFixtureUser::META_KEY, true ) || get_user_meta( $id, '_arn_tickets_fixture', true ) !== $this->database->prefix ) {
				throw new RuntimeException( 'Elenco de tickets incompleto ou incompatível.' );
			}
			$snapshots[ $id ] = $snapshot;
		}
		$session = null === $this->resolver ? $this->nativeSession( $resolved[0], $resolved[1] ) : $resolved[1];
		$units   = ( new WpdbLocalUnitRepository( $this->database ) )->snapshotInTransaction();
		if ( $units->revision < 1 || 3 !== count( $units->units ) ) {
			throw new RuntimeException( 'Unidades de tickets indisponíveis.' );
		}
		$active = array();
		foreach ( $units->units as $unit ) {
			if ( $unit->active && in_array( $unit->id, array( 'LAB-A', 'LAB-B' ), true ) ) {
				$active[] = $unit->id;
			}
		}
		$contexts = array();
		foreach ( $snapshots as $id => $snapshot ) {
			$contexts[ $id ] = new ActorContext( $snapshot, $id === $resolved[0] ? $session : array(), $this->roster[ $id ]['units'], $active );
		}
		if ( ! $contexts[ $resolved[0] ]->valid( $this->now() ) ) {
			throw new DomainException( 'Sessão de tickets indisponível.' );
		}
		$this->current_id = $resolved[0];
		return $contexts;
	}

	private function resolve(): ?array {
		$value = null !== $this->resolver ? ( $this->resolver )() : array( get_current_user_id(), array( 'native_token' => wp_get_session_token() ) );
		return is_array( $value ) && array_is_list( $value ) && 2 === count( $value ) && is_int( $value[0] ) && $value[0] > 0 && is_array( $value[1] ) ? $value : null;
	}

	private function nativeSession( int $id, array $proof ): array {
		$token = $proof['native_token'] ?? null;
		if ( ! is_string( $token ) || 1 !== preg_match( '/\A[A-Za-z0-9]{32,256}\z/D', $token ) ) {
			return array();
		}
		$table = $GLOBALS['wpdb']->usermeta;
		$rows  = $this->database->get_results( $this->database->prepare( 'SELECT meta_value FROM %i WHERE user_id=%d AND meta_key=%s FOR UPDATE', $table, $id, 'session_tokens' ) );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Sessão de tickets indisponível.' );
		}
		if ( 1 !== count( $rows ) || ! is_string( $rows[0]->meta_value ) ) {
			return array();
		}
		$invalid = false;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Stored session warnings stay private and the previous handler is restored.
		set_error_handler(
			static function () use ( &$invalid ): bool {
				$invalid = true;
				return true;
			}
		);
		try {
			$all = unserialize( $rows[0]->meta_value, array( 'allowed_classes' => false ) );
		} catch ( Throwable ) {
			$invalid = true;
			$all     = false;
		} finally {
			restore_error_handler();
		}
		$session = ! $invalid && is_array( $all ) ? ( $all[ hash( 'sha256', $token ) ] ?? null ) : null;
		return is_array( $session ) && is_int( $session['expiration'] ?? null ) && $session['expiration'] > $this->now() ? $session : array();
	}

	private function now(): int {
		$now = ( $this->clock )();
		if ( ! is_int( $now ) || $now < 0 ) {
			throw new RuntimeException( 'Relógio de tickets indisponível.' );
		}
		return $now;
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Ligação de tickets indisponível.' );
		}
	}
}
