<?php
/** Status, account suspension and evidence share a single transaction.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Lifecycle;

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Register\{Authority, Repository as RegisterRepository};
use Arn\Intranet\Organization\Storage\WpdbLocalUnitRepository;
use Arn\Intranet\Authorization\Storage\{AuthorizationSchema, WpdbAuthorizationRepository, ChangeContext};
use Closure;
use RuntimeException;
use Throwable;
// phpcs:disable WordPress.PHP.YodaConditions -- Structured record comparisons.
// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
final class Repository {
	private readonly Schema $schema;
	public function __construct( private readonly IdentityWpdb $db, private readonly Authority $authority, private readonly RegisterRepository $register, private readonly Closure $target, private readonly Closure $clock ) {
		$this->schema = new Schema( $db );
	}
	private function transaction( Closure $operation ): mixed {
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
			throw new RuntimeException( 'Estado profissional indisponível.' ); }
		$state  = State::decode( WpdbLocalUnitRepository::revision( $rows[0]->revision ), $rows[0]->payload );
		$latest = $this->rows( $this->db->prepare( 'SELECT reason, revision FROM %i ORDER BY revision DESC LIMIT 1', $this->schema->table( 'audit' ) ) );
		if ( 0 === $state->revision ) {
			if ( array() !== $latest || array() !== $this->rows( $this->db->prepare( 'SELECT id FROM %i LIMIT 1', $this->schema->table( 'batches' ) ) ) ) {
				throw new RuntimeException( 'Histórico divergente.' ); }
		} else {
			if ( count( $latest ) !== 1 || (string) $state->revision !== (string) $latest[0]->revision ) {
				throw new RuntimeException( 'Histórico divergente.' ); }
			$r = $this->receipt( $latest[0]->reason );
			if ( null === $r || $r['revision'] !== $state->revision || $r['after'] !== hash( 'sha256', $state->encode() ) ) {
				throw new RuntimeException( 'Histórico divergente.' ); }
		}
		return $state;
	}
	public function listing(): array {
		return $this->transaction(
			function ( int $actor ): array {
				$catalog = $this->register->catalogInTransaction();
				$state   = $this->snapshot();
				if ( array_diff_key( $state->people, $catalog->people ) ) {
					throw new RuntimeException( 'Cadastro divergente.' ); }
				$history = array();
				foreach ( $this->rows( $this->db->prepare( 'SELECT reason FROM %i ORDER BY revision DESC LIMIT 20', $this->schema->table( 'audit' ) ) ) as $row ) {
					$r = $this->receipt( $row->reason );
					if ( null === $r ) {
						throw new RuntimeException( 'Recibo ausente.' ); }
					$history[] = $r;
				}
				$accounts = ( $this->target )( $this->db, 0, $actor );
				return array(
					'catalog'  => $catalog,
					'state'    => $state,
					'history'  => $history,
					'accounts' => $accounts,
				);
			}
		);
	}
	public function apply( Command $command ): array {
		return $this->transaction(
			function ( int $actor ) use ( $command ): array {
				$catalog = $this->register->catalogInTransaction();
				$before  = $this->snapshot();
				$id      = hash( 'sha256', $actor . ':' . $command->request_id );
				$prior   = $this->receipt( $id );
				if ( null !== $prior ) {
					if ( $prior['actor'] !== $actor || ! hash_equals( $prior['command'], $command->hash() ) ) {
						throw new RuntimeException( 'Reenvio divergente.' ); }
					return $prior;
				}
				if ( $catalog->revision !== $command->catalog_revision || ! isset( $catalog->people[ $command->person ] ) ) {
					throw new RuntimeException( 'Cadastro alterado ou inexistente.' ); }
				$after       = $before->apply( $command );
				$p           = $after->person( $command->person );
				$auth_before = 0;
				$auth_after  = 0;
				if ( $p['account'] > 0 ) {
					if ( $p['account'] === $actor || true !== ( $this->target )( $this->db, $p['account'], $actor ) ) {
						throw new RuntimeException( 'Conta fictícia indisponível.' ); }
					$auth = new WpdbAuthorizationRepository( $this->db );
					$a    = $auth->findInTransaction( $p['account'] );
					if ( null === $a || $a->revision !== $command->account_revision ) {
						throw new RuntimeException( 'Revisão de conta divergente.' ); }
					$auth_before = $a->revision;
					if ( in_array( $p['state'], array( 'Suspended', 'Closed' ), true ) ) {
						$auth->suspendInTransaction( $p['account'], $a->revision, new ChangeContext( $actor, $command->reason, 'lifecycle/' . $id, ( $this->clock )() ) ); }
					$auth_after = $auth->findInTransaction( $p['account'] )->revision;
				}
				$r    = array(
					'id'               => $id,
					'actor'            => $actor,
					'command'          => $command->hash(),
					'request'          => $command->request_id,
					'action'           => $command->action,
					'person'           => $command->person,
					'state'            => $p['state'],
					'account'          => $p['account'],
					'account_before'   => $auth_before,
					'account_after'    => $auth_after,
					'revision'         => $after->revision,
					'catalog_revision' => $catalog->revision,
					'reason'           => $command->reason,
					'at'               => ( $this->clock )(),
					'before'           => hash( 'sha256', $before->encode() ),
					'after'            => hash( 'sha256', $after->encode() ),
				);
				$json = json_encode( $r, JSON_THROW_ON_ERROR );
				self::decodeReceipt( $json );
				$this->execute( $this->db->prepare( 'UPDATE %i SET revision = %d, payload = CONVERT(UNHEX(%s) USING utf8mb4) WHERE id = 1 AND revision = %d', $this->schema->table( 'header' ), $after->revision, bin2hex( $after->encode() ), $before->revision ) );
				if ( 1 !== $this->db->rows_affected ) {
					throw new RuntimeException( 'Revisão divergente.' ); }
				$this->execute( $this->db->prepare( 'INSERT INTO %i (id, payload) VALUES (%s, CONVERT(UNHEX(%s) USING utf8mb4))', $this->schema->table( 'batches' ), $id, bin2hex( $json ) ) );
				$this->execute( $this->db->prepare( 'INSERT INTO %i (actor_id, event, revision, previous_json, new_json, reason, occurred_at) VALUES (%d, %s, %d, CONVERT(UNHEX(%s) USING utf8mb4), CONVERT(UNHEX(%s) USING utf8mb4), %s, %d)', $this->schema->table( 'audit' ), $actor, $command->action, $after->revision, bin2hex( $before->encode() ), bin2hex( $after->encode() ), $id, $r['at'] ) );
				return $r;
			}
		);
	}
	private function receipt( string $id ): ?array {
		$rows = $this->rows( $this->db->prepare( 'SELECT payload FROM %i WHERE id = %s', $this->schema->table( 'batches' ), $id ) );
		if ( array() === $rows ) {
			return null; }
		if ( count( $rows ) !== 1 ) {
			throw new RuntimeException( 'Recibo divergente.' ); }
		$r = self::decodeReceipt( $rows[0]->payload );
		$a = $this->rows( $this->db->prepare( 'SELECT * FROM %i WHERE revision = %d', $this->schema->table( 'audit' ), $r['revision'] ) );
		if ( count( $a ) !== 1 || $id !== $r['id'] || (string) $r['actor'] !== (string) $a[0]->actor_id || $r['action'] !== $a[0]->event || $id !== $a[0]->reason || (string) $r['at'] !== (string) $a[0]->occurred_at ) {
			throw new RuntimeException( 'Auditoria divergente.' ); }
		$before  = State::decode( $r['revision'] - 1, $a[0]->previous_json );
		$after   = State::decode( $r['revision'], $a[0]->new_json );
		$command = new Command( $r['action'], $r['person'], 'link' === $r['action'] ? '' : $r['state'], $r['account'], $r['revision'] - 1, $r['catalog_revision'], $r['account_before'], $r['reason'], $r['request'] );
		if ( $command->hash() !== $r['command'] || $before->apply( $command )->encode() !== $after->encode() ) {
			throw new RuntimeException( 'Decisão e auditoria divergentes.' ); }
		if ( $r['before'] !== hash( 'sha256', $before->encode() ) || $r['after'] !== hash( 'sha256', $after->encode() ) || $after->person( $r['person'] ) !== array(
			'state'   => $r['state'],
			'account' => $r['account'],
		) ) {
			throw new RuntimeException( 'Auditoria divergente.' ); }
		return $r;
	}
	public static function decodeReceipt( string $json ): array {
		if ( strlen( $json ) > 4096 ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		$r = json_decode( $json, true, 8, JSON_THROW_ON_ERROR );
		if ( ! is_array( $r ) || array_keys( $r ) !== array( 'id', 'actor', 'command', 'request', 'action', 'person', 'state', 'account', 'account_before', 'account_after', 'revision', 'catalog_revision', 'reason', 'at', 'before', 'after' ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		foreach ( array( 'id', 'command', 'request', 'before', 'after' ) as $key ) {
			if ( ! is_string( $r[ $key ] ) || 1 !== preg_match( '/^[a-f0-9]{64}$/D', $r[ $key ] ) ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
		}
		foreach ( array( 'actor', 'account', 'account_before', 'account_after', 'revision', 'catalog_revision', 'at' ) as $key ) {
			if ( ! is_int( $r[ $key ] ) || $r[ $key ] < ( in_array( $key, array( 'account', 'account_before', 'account_after' ), true ) ? 0 : 1 ) ) {
				throw new RuntimeException( 'Recibo inválido.' ); }
		}
		if ( ! is_string( $r['reason'] ) || ! is_string( $r['person'] ) || ! is_string( $r['action'] ) || ! in_array( $r['action'], array( 'link', 'state' ), true ) || $r['id'] !== hash( 'sha256', $r['actor'] . ':' . $r['request'] ) || $r['account_after'] < $r['account_before'] || $r['account_after'] > $r['account_before'] + 1 || ( ( 0 === $r['account'] ) !== ( 0 === $r['account_before'] ) ) ) {
			throw new RuntimeException( 'Recibo inválido.' ); }
		new State(
			$r['revision'],
			array(
				$r['person'] => array(
					'state'   => $r['state'],
					'account' => $r['account'],
				),
			)
		);
		new ChangeContext( $r['actor'], $r['reason'], 'lifecycle/' . $r['id'], $r['at'] );
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
