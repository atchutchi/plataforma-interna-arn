<?php
/**
 * F1-07 People local.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting -- Internal contracts are documented in the F1-07 plan and tests.
/** Dedicated, audited People persistence. */
declare(strict_types=1);
namespace Arn\Intranet\People;

use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class WpdbPeopleRepository {
	private readonly PeopleSchema $schema;
	private readonly ?string $exclusive_prefix;

	public function __construct( private readonly IdentityWpdb $database, private readonly PeopleAuthority $authority ) {
		if ( $authority instanceof WpdbPeopleAuthority && $authority->connection() !== $database ) {
			throw new RuntimeException( 'Ligação People divergente.' );
		}
		$this->schema           = new PeopleSchema( $database );
		$this->exclusive_prefix = LocalPeopleRoster::validPrefix( $database->prefix ) ? $database->prefix : null;
	}

	public function installSchema(): void {
		$this->gate();
		if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) ) {
			throw new RuntimeException( 'Instalação People recusada.' );
		}
		$this->silently( fn() => $this->schema->install() );
	}

	public function find( int $user_id ): ?Profile {
		$this->gate();
		if ( ! in_array( $user_id, LocalPeopleRoster::ids( $this->exclusive_prefix ), true ) ) {
			return null;
		}
		return $this->silently(
			function () use ( $user_id ): ?Profile {
				$this->schema->validate();
				$this->assertRosterRows();
				return $this->read( $user_id );
			}
		);
	}

	public function all(): array {
		$this->gate();
		$ids = LocalPeopleRoster::ids( $this->exclusive_prefix );
		return $this->silently(
			function () use ( $ids ): array {
				$this->schema->validate();
				$this->assertRosterRows();
				$profiles = array();
				foreach ( $ids as $id ) {
						$profile = $this->read( $id );
					if ( null === $profile ) {
						throw new RuntimeException( 'Elenco People incompleto.' );
					}
					$profiles[] = $profile;
				}
				return $profiles;
			}
		);
	}

	public function seed( Profile $profile, int $actor_id, int $now ): void {
		$this->gate();
		if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! current_user_can( 'manage_options' ) || 0 >= $actor_id || 0 > $now || 1 !== $profile->revision || $profile->updated_at !== $now || ! LocalPeopleRoster::contains( $profile->user_id, $this->exclusive_prefix ) ) {
			throw new RuntimeException( 'Criação People recusada.' );
		}
		$this->silently(
			function () use ( $profile, $actor_id, $now ): void {
				$this->schema->validate();
				$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
				$this->execute( 'START TRANSACTION' );
				try {
					if ( null !== $this->read( $profile->user_id, true ) ) {
						throw new RuntimeException( 'Perfil People existente.' );
					}
					$this->writeProfile( $profile, false );
					$this->writeAudit( $profile, $actor_id, $now, 'created', array(), str_repeat( '0', 64 ), 'Criação de perfil fictício' );
					$this->execute( 'COMMIT' );
				} catch ( Throwable ) {
					$this->database->query( 'ROLLBACK' );
					throw new RuntimeException( 'Criação People recusada.' );
				}
			}
		);
	}

	public function updateOwn( int $user_id, SelfService $replacement, int $expected_revision ): Profile {
		$this->gate();
		$actor = $this->authority->actor();
		if ( null === $actor || $user_id <= 0 || $actor[0] !== $user_id || ! in_array( $user_id, LocalPeopleRoster::ids( $this->exclusive_prefix ), true ) ) {
			throw new RuntimeException( 'Alteração People recusada.' );
		}
		return $this->silently(
			function () use ( $user_id, $replacement, $expected_revision, $actor ): Profile {
				$this->schema->validate();
				$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
				$this->execute( 'START TRANSACTION' );
				try {
						$account = $this->authority->account( $user_id, true );
						$now     = time();
					if ( null === $account || ! PeoplePolicy::canUpdateOwn( $user_id, $account, $actor[1], $now ) ) {
						throw new RuntimeException( 'Alteração People recusada.' );
					}
						$units = $this->authority->units( true );
						$this->assertRosterRows();
						$before = $this->read( $user_id, true );
					if ( null === $before || ! in_array( $before->professional->unit_id, $this->activeIds( $units ), true ) ) {
						throw new RuntimeException( 'Alteração People recusada.' );
					}
					$after = $before->revise( $replacement, $expected_revision, $user_id, $now );
					if ( $after !== $before ) {
						$this->writeProfile( $after, true, $before->revision );
						$this->writeAudit( $after, $user_id, $now, 'self_updated', $before->changedFields( $replacement ), $before->digest(), 'Actualização do perfil próprio' );
					}
					$this->execute( 'COMMIT' );
					return $after;
				} catch ( Throwable ) {
					$this->database->query( 'ROLLBACK' );
					throw new RuntimeException( 'Alteração People recusada. Recarrega a página.' );
				}
			}
		);
	}

	/** Internal history. The caller enforces owner access. */
	public function history( int $user_id ): array {
		$this->gate();
		if ( ! in_array( $user_id, LocalPeopleRoster::ids( $this->exclusive_prefix ), true ) ) {
			return array();
		}
		return $this->silently(
			function () use ( $user_id ): array {
				$this->schema->validate();
				$this->assertRosterRows();
				$profile = $this->read( $user_id );
				return null === $profile ? array() : $this->events( $user_id );
			}
		);
	}

	private function read( int $user_id, bool $locked = false ): ?Profile {
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE user_id=%d', $this->schema->table( 'people' ), $user_id ) . ( $locked ? ' FOR UPDATE' : '' ) );
		if ( array() === $rows ) {
			return null;
		}
		if ( 1 !== count( $rows ) ) {
			throw new RuntimeException( 'Perfil People inválido.' );
		}
		$r       = $rows[0];
		$profile = new Profile(
			self::integer( $r->user_id ),
			new ProfessionalDetails( $r->code, $r->name, $r->email, $r->job, $r->unit_id ),
			new SelfService( $r->phone, $r->office, $r->bio ),
			self::integer( $r->revision ),
			self::integer( $r->updated_at )
		);
		$user    = get_userdata( $user_id );
		$logins  = LocalPeopleRoster::logins( $this->exclusive_prefix );
		$label   = $user instanceof \WP_User ? array_search( $user->user_login, $logins, true ) : false;
		if ( ! is_string( $label ) ) {
			throw new RuntimeException( 'Elenco People inválido.' );
		}
		$base     = LocalPeopleRoster::details( $label );
		$expected = new ProfessionalDetails( $base->code, $base->name, $user->user_email, $base->job, $base->unit_id );
		if ( get_object_vars( $profile->professional ) !== get_object_vars( $expected ) ) {
			throw new RuntimeException( 'Dados profissionais People inválidos.' );
		}
		$events = $this->events( $user_id );
		$last   = end( $events );
		if ( $profile->user_id !== $user_id || $profile->digest() !== $r->digest || false === $last || $profile->revision !== $last['revision'] || $profile->updated_at !== $last['occurred_at'] || $profile->digest() !== $last['after_hash'] ) {
			throw new RuntimeException( 'Integridade People inválida.' );
		}
		return $profile;
	}

	private function events( int $user_id ): array {
		$rows       = $this->rows( $this->database->prepare( 'SELECT actor_id, event, revision, occurred_at, changed_fields, before_hash, after_hash, reason FROM %i WHERE user_id=%d ORDER BY revision', $this->schema->table( 'audit' ), $user_id ) );
		$events     = array();
		$prior      = str_repeat( '0', 64 );
		$prior_time = 0;
		foreach ( $rows as $index => $row ) {
			$revision    = self::integer( $row->revision );
			$actor_id    = self::integer( $row->actor_id );
			$occurred_at = self::integer( $row->occurred_at );
			$fields      = json_decode( $row->changed_fields, true, 8, JSON_THROW_ON_ERROR );
			if ( $actor_id <= 0 || $occurred_at < $prior_time || $revision !== $index + 1 || $row->before_hash !== $prior || 1 !== preg_match( '/\A[a-f0-9]{64}\z/D', $row->after_hash ) || ( 0 === $index && ( 'created' !== $row->event || array() !== $fields || 'Criação de perfil fictício' !== $row->reason ) ) || ( 0 < $index && ( 'self_updated' !== $row->event || $actor_id !== $user_id || 'Actualização do perfil próprio' !== $row->reason || ! is_array( $fields ) || array() === $fields || array_values( array_intersect( array( 'phone', 'office', 'bio' ), $fields ) ) !== $fields ) ) ) {
				throw new RuntimeException( 'Auditoria People inválida.' );
			}
			$events[]   = array(
				'actor_id'       => $actor_id,
				'event'          => $row->event,
				'revision'       => $revision,
				'occurred_at'    => $occurred_at,
				'changed_fields' => $fields,
				'before_hash'    => $row->before_hash,
				'after_hash'     => $row->after_hash,
				'reason'         => $row->reason,
			);
			$prior      = $row->after_hash;
			$prior_time = $occurred_at;
		}
		return $events;
	}

	private function assertRosterRows(): void {
		$expected = array_values( LocalPeopleRoster::ids( $this->exclusive_prefix ) );
		sort( $expected, SORT_NUMERIC );
		$actual = array_map( self::integer( ... ), array_map( static fn( $row ) => $row->user_id, $this->rows( $this->database->prepare( 'SELECT user_id FROM %i ORDER BY user_id', $this->schema->table( 'people' ) ) ) ) );
		if ( $actual !== $expected ) {
			throw new RuntimeException( 'Elenco People inválido.' );
		}
	}

	private function writeProfile( Profile $p, bool $replace, int $expected = 0 ): void {
		if ( $replace ) {
			$sql = $this->database->prepare( 'UPDATE %i SET phone=CONVERT(UNHEX(%s) USING utf8mb4), office=CONVERT(UNHEX(%s) USING utf8mb4), bio=CONVERT(UNHEX(%s) USING utf8mb4), revision=%d, updated_at=%d, digest=%s WHERE user_id=%d AND revision=%d', $this->schema->table( 'people' ), bin2hex( $p->self_service->phone ), bin2hex( $p->self_service->office ), bin2hex( $p->self_service->bio ), $p->revision, $p->updated_at, $p->digest(), $p->user_id, $expected );
		} else {
			$sql = $this->database->prepare( 'INSERT INTO %i (user_id, code, name, email, job, unit_id, phone, office, bio, revision, updated_at, digest) VALUES (%d,%s,CONVERT(UNHEX(%s) USING utf8mb4),%s,CONVERT(UNHEX(%s) USING utf8mb4),%s,CONVERT(UNHEX(%s) USING utf8mb4),CONVERT(UNHEX(%s) USING utf8mb4),CONVERT(UNHEX(%s) USING utf8mb4),%d,%d,%s)', $this->schema->table( 'people' ), $p->user_id, $p->professional->code, bin2hex( $p->professional->name ), $p->professional->email, bin2hex( $p->professional->job ), $p->professional->unit_id, bin2hex( $p->self_service->phone ), bin2hex( $p->self_service->office ), bin2hex( $p->self_service->bio ), $p->revision, $p->updated_at, $p->digest() );
		}
		if ( 1 !== $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Escrita People recusada.' );
		}
	}

	private function writeAudit( Profile $p, int $actor, int $now, string $event, array $fields, string $before, string $reason ): void {
		$this->execute( $this->database->prepare( 'INSERT INTO %i (user_id, actor_id, event, revision, occurred_at, changed_fields, before_hash, after_hash, reason) VALUES (%d,%d,%s,%d,%d,%s,%s,%s,CONVERT(UNHEX(%s) USING utf8mb4))', $this->schema->table( 'audit' ), $p->user_id, $actor, $event, $p->revision, $now, json_encode( $fields, JSON_THROW_ON_ERROR ), $before, $p->digest(), bin2hex( $reason ) ) );
	}

	private function activeIds( \Arn\Intranet\Organization\Storage\LocalUnitSnapshot $snapshot ): array {
		return array_values( array_map( static fn( $unit ): string => $unit->id, array_filter( $snapshot->units, static fn( $unit ): bool => $unit->active ) ) );
	}

	private function gate(): void {
		if ( ! PeopleConfiguration::current() ) {
			throw new RuntimeException( 'People indisponível.' );
		}
	}

	private static function integer( mixed $value ): int {
		if ( ! is_string( $value ) || 1 !== preg_match( '/\A(0|[1-9][0-9]*)\z/D', $value ) || (string) (int) $value !== $value ) {
			throw new RuntimeException( 'Número People inválido.' );
		}
		return (int) $value;
	}

	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura People recusada.' );
		}
		return $rows;
	}

	private function execute( string $sql ): void {
		if ( false === $this->database->query( $sql ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Escrita People recusada.' );
		}
	}

	private function silently( callable $operation ): mixed {
		$previous = $this->database->suppress_errors( true );
		try {
			return $operation();
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}
}
