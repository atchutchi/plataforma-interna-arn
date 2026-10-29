<?php
/** Delegation journal and canonical grants commit together.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Delegations;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Authorization\DelegationPolicy;
use Arn\Intranet\Authorization\Storage\{AuthorizationSchema, WpdbAuthorizationRepository, ChangeContext};
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Closure;
use RuntimeException;
use Throwable;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class Repository {
	private readonly Schema $schema;
	public function __construct( private readonly IdentityWpdb $db, private readonly Authority $authority ) {
		$this->schema = new Schema( $db );
	}
	private function transaction( Closure $operation, ?Closure $final_check = null ): mixed {
		if ( $this->db->inTransaction() ) {
			throw new RuntimeException( 'Transacção externa recusada.' ); }
		$old = $this->db->suppress_errors( true );
		try {
			$this->schema->validate();
			( new AuthorizationSchema( $this->db ) )->validate();
			$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
			$this->execute( 'START TRANSACTION' );
			$actor  = $this->authority->lock();
			$result = $operation( $actor );
			$this->authority->assertCurrent();
			if ( null !== $final_check ) {
				$final_check();
			}
			$this->execute( 'COMMIT' );
			return $result;
		} catch ( Throwable $e ) {
			$this->db->query( 'ROLLBACK' );
			throw new RuntimeException( 'Operação não confirmada. Consulta o histórico antes de repetir.', 0, $e );
		} finally {
			$this->db->suppress_errors( $old ); }
	}
	private function snapshot(): State {
		$rows = $this->rows( $this->db->prepare( 'SELECT id, revision, payload FROM %i FOR UPDATE', $this->schema->table( 'header' ) ) );
		if ( count( $rows ) !== 1 || '1' !== (string) $rows[0]->id ) {
			throw new RuntimeException( 'Delegações indisponíveis.' ); }
		$s      = State::decode( WpdbLocalUnitRepository::revision( $rows[0]->revision ), $rows[0]->payload );
		$roster = $this->authority->roster();
		foreach ( $s->records as $record ) {
			foreach ( array( 'issuer', 'delegate', 'target' ) as $key ) {
				if ( $record[ $key ] !== $roster[ $key ] ) {
					throw new RuntimeException( 'Elenco de delegações divergente.' ); }
			}
		}
		$history  = $this->rows( $this->db->prepare( 'SELECT a.*, b.payload AS receipt_payload FROM %i a LEFT JOIN %i b ON a.reason = b.id ORDER BY a.revision', $this->schema->table( 'audit' ), $this->schema->table( 'batches' ) ) );
		$receipts = $this->rows( $this->db->prepare( 'SELECT COUNT(*) AS total FROM %i', $this->schema->table( 'batches' ) ) );
		if ( count( $history ) !== $s->revision || count( $receipts ) !== 1 || (string) $s->revision !== (string) $receipts[0]->total ) {
			throw new RuntimeException( 'Histórico incompleto.' );
		}
		$previous = hash( 'sha256', ( new State( 0, array() ) )->encode() );
		foreach ( $history as $index => $row ) {
			if ( ! is_string( $row->receipt_payload ) ) {
				throw new RuntimeException( 'Recibo ausente.' );
			}
			$r = self::validateReceipt( $row->reason, $row->receipt_payload, $row );
			if ( $r['revision'] !== $index + 1 || $r['before'] !== $previous ) {
				throw new RuntimeException( 'Cadeia de decisões divergente.' );
			}
			$previous = $r['after'];
		}
		if ( $previous !== hash( 'sha256', $s->encode() ) ) {
			throw new RuntimeException( 'Estado e histórico divergentes.' );
		}
		return $s;
	}
	public function listing(): array {
		return $this->transaction(
			function ( int $actor ): array {
				$s       = $this->snapshot();
				$history = array();
				foreach ( $this->rows( $this->db->prepare( 'SELECT reason FROM %i ORDER BY revision DESC LIMIT 20', $this->schema->table( 'audit' ) ) ) as $row ) {
						$r = $this->receipt( $row->reason );
					if ( null === $r ) {
						throw new RuntimeException( 'Recibo ausente.' ); }
					$history[] = $r;
				}
				return array(
					'state'   => $s,
					'history' => $history,
					'actor'   => $actor,
					'roster'  => $this->authority->roster(),
					'target'  => $this->authority->targetInfo(),
					'now'     => $this->authority->now(),
				);
			}
		);
	}
	private function authorize( Command $c, State $s, int $actor ): void {
		$this->authority->assertCurrent();
		$roster = $this->authority->roster();
		if ( $c->delegate !== $roster['delegate'] || $c->target !== $roster['target'] ) {
			throw new RuntimeException( 'Destinatários fora do circuito.' ); }
		if ( in_array( $c->action, array( 'issue', 'revoke' ), true ) ) {
			if ( $actor !== $roster['issuer'] ) {
				throw new RuntimeException( 'Designação explícita requerida.' ); }
			if ( 'revoke' === $c->action ) {
				return; }
			if ( ! $this->authority->delegateActive() || $c->expires <= $this->authority->now() ) {
				throw new RuntimeException( 'Delegação sem vigência futura.' ); }
			$this->authority->target();
		} else {
			$r = $s->records[ $c->delegation ] ?? null;
			$a = $this->authority->actor();
			if ( $actor !== $roster['delegate'] || null === $r || $r['issuer'] !== $roster['issuer'] || $c->expires <= $this->authority->now() || ! ( new DelegationPolicy() )->canGrant( $a->toAccount( true ), $a->policyGrants(), array( State::delegation( $r ) ), $c->target, $c->roleValue(), $c->scopeValue(), $this->authority->now(), true ) ) {
				throw new RuntimeException( 'Delegação ou limites indisponíveis.' ); }
			$this->authority->target();
		}
		if ( 'own' !== $c->scope ) {
			$units  = ( new WpdbLocalUnitRepository( $this->db ) )->snapshotInTransaction();
			$active = false;
			foreach ( $units->units as $unit ) {
				if ( $unit->id === $c->scope && $unit->active ) {
					$active = true; }
			}
			if ( ! $active ) {
				throw new RuntimeException( 'Unidade indisponível.' ); }
		}
	}
	public function apply( Command $c ): array {
		$decision_check = null;
		return $this->transaction(
			function ( int $actor ) use ( $c, &$decision_check ): array {
				$before = $this->snapshot();
				$id     = hash( 'sha256', $actor . ':' . $c->request_id );
				$old    = $this->receipt( $id );
				if ( null !== $old ) {
					if ( $old['actor'] !== $actor || $old['command'] !== $c->values() ) {
						throw new RuntimeException( 'Reenvio divergente.' ); }
					return $old;
				}
				$this->authorize( $c, $before, $actor );
				$grant_id       = 0;
				$account_before = 0;
				$account_after  = 0;
				// Pure validation before any write. A temporary ID is only used for this validation.
				$before->apply( $c, $actor, 'grant' === $c->action ? PHP_INT_MAX : 0 );
				if ( 'grant' === $c->action ) {
					$t = $this->authority->target();
					if ( $t->revision !== $c->target_revision ) {
						throw new RuntimeException( 'Revisão da conta divergente.' ); }
					$account_before = $t->revision;
					$grant_id       = ( new WpdbAuthorizationRepository( $this->db ) )->createGrantInTransaction( $c->grant(), $t->revision, new ChangeContext( $actor, $c->reason, 'delegation/' . $id, $this->authority->now() ) );
					$account_after  = $t->revision + 1;
				}
				$after = $before->apply( $c, $actor, $grant_id );
				$r     = array(
					'id'             => $id,
					'actor'          => $actor,
					'command'        => $c->values(),
					'revision'       => $after->revision,
					'grant_id'       => $grant_id,
					'account_before' => $account_before,
					'account_after'  => $account_after,
					'at'             => $this->authority->now(),
					'before'         => hash( 'sha256', $before->encode() ),
					'after'          => hash( 'sha256', $after->encode() ),
				);
				$json  = json_encode( $r, JSON_THROW_ON_ERROR );
				self::decodeReceipt( $json );
				$this->execute( $this->db->prepare( 'UPDATE %i SET revision = %d, payload = CONVERT(UNHEX(%s) USING utf8mb4) WHERE id = 1 AND revision = %d', $this->schema->table( 'header' ), $after->revision, bin2hex( $after->encode() ), $before->revision ) );
				if ( 1 !== $this->db->rows_affected ) {
					throw new RuntimeException( 'Revisão divergente.' ); }
				$this->execute( $this->db->prepare( 'INSERT INTO %i (id, payload) VALUES (%s, CONVERT(UNHEX(%s) USING utf8mb4))', $this->schema->table( 'batches' ), $id, bin2hex( $json ) ) );
				$this->execute( $this->db->prepare( 'INSERT INTO %i (actor_id, event, revision, previous_json, new_json, reason, occurred_at) VALUES (%d, %s, %d, CONVERT(UNHEX(%s) USING utf8mb4), CONVERT(UNHEX(%s) USING utf8mb4), %s, %d)', $this->schema->table( 'audit' ), $actor, $c->action, $after->revision, bin2hex( $before->encode() ), bin2hex( $after->encode() ), $id, $r['at'] ) );
				$this->authorize( $c, $before, $actor );
				$decision_check = function () use ( $c, $before ): void {
					// Last pure time check follows every SQL and session read before COMMIT.
					$now = $this->authority->now();
					if ( 'revoke' !== $c->action && ( $c->expires <= $now || ( 'grant' === $c->action && ! State::delegation( $before->records[ $c->delegation ] )->validAt( $now ) ) ) ) {
						throw new RuntimeException( 'Prazo terminado antes da confirmação.' );
					}
				};
				return $r;
			},
			static function () use ( &$decision_check ): void {
				// A historical receipt is read-only, so it does not repeat temporal authorization.
				if ( null !== $decision_check ) {
					$decision_check();
				}
			}
		);
	}
	private function receipt( string $id ): ?array {
		$rows = $this->rows( $this->db->prepare( 'SELECT payload FROM %i WHERE id = %s', $this->schema->table( 'batches' ), $id ) );
		if ( array() === $rows ) {
			return null; }
		$r = self::decodeReceipt( $rows[0]->payload );
		$a = $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE revision = %d', $this->schema->table( 'audit' ), $r['revision'] ) );
		if ( count( $a ) !== 1 ) {
			throw new RuntimeException( 'Auditoria ausente.' );
		}
		return self::validateReceipt( $id, $rows[0]->payload, $a[0] );
	}
	private static function validateReceipt( string $id, string $payload, object $audit ): array {
		$r = self::decodeReceipt( $payload );
		$c = Command::from( $r['command'] );
		if ( (string) $audit->revision !== (string) $r['revision'] || $r['id'] !== $id || $audit->reason !== $id || $audit->event !== $c->action || (string) $audit->actor_id !== (string) $r['actor'] || (string) $audit->occurred_at !== (string) $r['at'] ) {
			throw new RuntimeException( 'Auditoria divergente.' ); }
		$before = State::decode( $r['revision'] - 1, $audit->previous_json );
		$after  = State::decode( $r['revision'], $audit->new_json );
		if ( $before->apply( $c, $r['actor'], $r['grant_id'] )->encode() !== $after->encode() || hash( 'sha256', $before->encode() ) !== $r['before'] || hash( 'sha256', $after->encode() ) !== $r['after'] ) {
			throw new RuntimeException( 'Decisão divergente.' ); }
		return $r;
	}
	public static function decodeReceipt( string $json ): array {
		if ( strlen( $json ) > 8192 ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		$r = json_decode( $json, true, 12, JSON_THROW_ON_ERROR );
		if ( ! is_array( $r ) || array_keys( $r ) !== array( 'id', 'actor', 'command', 'revision', 'grant_id', 'account_before', 'account_after', 'at', 'before', 'after' ) || ! is_array( $r['command'] ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		$c = Command::from( $r['command'] );
		foreach ( array( 'id', 'before', 'after' ) as $key ) {
			if ( ! is_string( $r[ $key ] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $r[ $key ] ) ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
		}
		foreach ( array( 'actor', 'revision', 'grant_id', 'account_before', 'account_after', 'at' ) as $key ) {
			if ( ! is_int( $r[ $key ] ) || $r[ $key ] < ( in_array( $key, array( 'grant_id', 'account_before', 'account_after' ), true ) ? 0 : 1 ) ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
		}
		if ( $r['revision'] !== $c->revision + 1 || $r['id'] !== hash( 'sha256', $r['actor'] . ':' . $c->request_id ) || ( 'grant' === $c->action ? ( $r['grant_id'] < 1 || $r['account_before'] !== $c->target_revision || $r['account_after'] !== $r['account_before'] + 1 ) : ( 0 !== $r['grant_id'] || 0 !== $r['account_before'] || 0 !== $r['account_after'] ) ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		return $r;
	}
	private function execute( string $sql ): void {
		if ( false === $this->db->query( $sql ) || '' !== $this->db->last_error ) {
			throw new RuntimeException( 'Persistência indisponível.' ); }
	}
	private function rows( string $sql ): array {
		$r = $this->db->get_results( $sql );
		if ( ! is_array( $r ) || '' !== $this->db->last_error ) {
			throw new RuntimeException( 'Leitura indisponível.' ); }
		return $r;
	}
}
