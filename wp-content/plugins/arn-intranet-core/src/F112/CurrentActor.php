<?php
/** Current F1-12 authority on one dedicated connection.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\F112;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Closure;
use DomainException;
use RuntimeException;
use Throwable;
use WP_User;

// phpcs:disable Squiz.Commenting -- Internal locked roster/session contract is documented in the F1-12 plan.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
/** Sorted account/grant locks, native session revalidation, then unit locks. */
final class CurrentActor {
	private readonly array $roster;
	private ?int $current_id = null;

	public function __construct( private readonly IdentityWpdb $database, public readonly string $module, array $roster, private readonly Closure $clock, private readonly ?Closure $resolver = null ) {
		if ( ! in_array( $module, array( 'communications', 'calendar' ), true ) || 6 !== count( $roster ) ) {
			throw new RuntimeException( 'Contexto F112 inválido.' );
		}
		$logins = array();
		foreach ( $roster as $id => $person ) {
			if ( ! is_int( $id ) || $id < 1 || ! is_array( $person ) || 2 !== count( $person ) || array_diff( array_keys( $person ), array( 'login', 'units' ) ) || ! is_string( $person['login'] ) || ! is_array( $person['units'] ) || ! array_is_list( $person['units'] ) || in_array( $person['login'], $logins, true ) ) {
				throw new RuntimeException( 'Elenco F112 inválido.' );
			}
			$normal = array( 'f112.colaborador.a', 'f112.colaborador.b', 'f112.responsavel.a', 'f112.autor.institucional', 'f112.revisor.institucional', 'f112.gestor.eventos' );
			if ( ! in_array( $person['login'], $normal, true ) && ( 1 !== preg_match( '/\Aarn_f112_[a-f0-9]{10}_\z/', $database->prefix ) || ! in_array( $person['login'], array_map( fn( string $label ): string => $database->prefix . $label, array( 'a', 'b', 'manager', 'author', 'reviewer', 'events' ) ), true ) ) ) {
				throw new RuntimeException( 'Login F112 inválido.' );
			}
			if ( count( array_unique( $person['units'], SORT_REGULAR ) ) !== count( $person['units'] ) ) {
				throw new RuntimeException( 'Pertenças F112 inválidas.' );
			}
			foreach ( $person['units'] as $unit ) {
				if ( ! is_string( $unit ) || ! in_array( $unit, array( 'LAB-A', 'LAB-B' ), true ) ) {
					throw new RuntimeException( 'Unidade F112 inválida.' );
				}
			}
			$logins[] = $person['login'];
		}
		ksort( $roster, SORT_NUMERIC );
		$this->roster = $roster;
	}

	public function connection(): IdentityWpdb {
		return $this->database;
	}

	public function currentId(): ?int {
		return $this->current_id;
	}

	public function read(): ?ActorContext {
		if ( ! Configuration::current( $this->module ) ) {
			return null;
		}
		$resolved = $this->resolve();
		if ( null === $resolved || ! isset( $this->roster[ $resolved[0] ] ) ) {
			return null;
		}
		$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
		$this->execute( 'START TRANSACTION' );
		try {
			$contexts = $this->locked();
			$actor    = $contexts[ $this->current_id ] ?? null;
			$this->execute( 'COMMIT' );
			$now = ( $this->clock )();
			return $actor instanceof ActorContext && is_int( $now ) && $actor->valid( $now ) ? $actor : null;
		} catch ( DomainException ) {
			$this->database->query( 'ROLLBACK' );
			return null;
		} catch ( Throwable ) {
			$this->database->query( 'ROLLBACK' );
			throw new RuntimeException( 'Autoridade F112 indisponível.' );
		}
	}

	public function locked(): array {
		if ( ! Configuration::current( $this->module ) || ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'Transacção F112 ausente.' );
		}
		$resolved = $this->resolve();
		if ( null === $resolved || ! isset( $this->roster[ $resolved[0] ] ) ) {
			throw new DomainException( 'Operação F112 recusada.' );
		}
		$this->current_id = $resolved[0];
		$accounts         = new WpdbAuthorizationRepository( $this->database );
		$snapshots        = array();
		foreach ( $this->roster as $id => $person ) {
			$snapshot = $accounts->findInTransaction( $id );
			$user     = get_userdata( $id );
			if ( null === $snapshot || ! $user instanceof WP_User || $user->user_login !== $person['login'] || $user->user_email !== $person['login'] . '@example.invalid' || array( 'subscriber' ) !== array_values( $user->roles ) || '1' !== get_user_meta( $id, LocalFixtureUser::META_KEY, true ) || get_user_meta( $id, '_arn_f112_fixture', true ) !== $this->database->prefix ) {
				throw new RuntimeException( 'Elenco F112 incompleto ou incompatível.' );
			}
			$snapshots[ $id ] = $snapshot;
		}
		$session = null === $this->resolver ? $this->nativeSession( $resolved[0], $resolved[1] ) : $resolved[1];
		$units   = ( new WpdbLocalUnitRepository( $this->database ) )->snapshotInTransaction();
		if ( $units->revision < 1 || 3 !== count( $units->units ) ) {
			throw new RuntimeException( 'Unidades F112 ainda não preparadas.' );
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
		return $contexts;
	}

	private function resolve(): ?array {
		$value = null !== $this->resolver ? ( $this->resolver )() : array( get_current_user_id(), array( 'native_token' => wp_get_session_token() ) );
		return is_array( $value ) && array_is_list( $value ) && 2 === count( $value ) && is_int( $value[0] ) && $value[0] > 0 && is_array( $value[1] ) ? $value : null;
	}

	private function nativeSession( int $id, array $proof ): array {
		$token = $proof['native_token'] ?? null;
		if ( ! is_string( $token ) || 1 !== preg_match( '/\A[A-Za-z0-9]{32,256}\z/', $token ) ) {
			return array();
		}
		$table = $GLOBALS['wpdb']->usermeta;
		$rows  = $this->database->get_results( $this->database->prepare( 'SELECT meta_value FROM %i WHERE user_id=%d AND meta_key=%s FOR UPDATE', $table, $id, 'session_tokens' ) );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Sessão F112 indisponível.' );
		}
		if ( 1 !== count( $rows ) ) {
			return array();
		}
		$invalid = false;
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Confines malformed stored-session warnings and restores the previous handler in finally.
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
		if ( $invalid ) {
			return array();
		}
		$session = is_array( $all ) ? ( $all[ hash( 'sha256', $token ) ] ?? null ) : null;
		$now     = ( $this->clock )();
		return is_array( $session ) && is_int( $session['expiration'] ?? null ) && is_int( $now ) && $session['expiration'] > $now ? $session : array();
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Ligação F112 indisponível.' );
		}
	}
}
