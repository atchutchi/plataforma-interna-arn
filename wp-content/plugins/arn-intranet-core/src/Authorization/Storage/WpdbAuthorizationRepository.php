<?php
/**
 * Contas, concessões e auditoria na mesma transacção dedicada.
 *
 * @package Arn\Intranet
 */

declare(strict_types=1);

namespace Arn\Intranet\Authorization\Storage;

use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Grant;
use Arn\Intranet\Authorization\Role;
use Arn\Intranet\Identity\IdentityWpdb;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

// SQL preparado e tabelas próprias sem cache. Nenhuma mensagem inclui dados externos.
// JSON_THROW_ON_ERROR recusa erros sem normalizar os dados internos.
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.Security.EscapeOutput.ExceptionNotEscaped

/** Fronteira interna. A autoridade administrativa pertence ao serviço. */
final class WpdbAuthorizationRepository implements AuthorizationRepository {

	/**
	 * Schema da ligação dedicada.
	 *
	 * @var AuthorizationSchema
	 */
	private readonly AuthorizationSchema $schema;

	/**
	 * Recebe configuração já resolvida, nunca a ligação WordPress global.
	 *
	 * @param IdentityWpdb $database Ligação dedicada sem reconnect.
	 * @throws InvalidArgumentException Quando os argumentos estão fora do domínio.
	 */
	public function __construct( private readonly IdentityWpdb $database ) {
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,40}$/D', $database->prefix ) ) {
			throw new InvalidArgumentException( 'Prefixo de autorização inválido.' );
		}
		$this->schema = new AuthorizationSchema( $database );
	}

	/**
	 * Instalação explícita e idempotente da versão conhecida.
	 *
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function installSchema(): void {
		$this->silently( fn() => $this->schema->install() );
	}

	/**
	 * Cria Pending e primeira decisão atomicamente.
	 *
	 * @param int           $user_id Conta.
	 * @param ChangeContext $context Decisão.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function createAccount( int $user_id, ChangeContext $context ): void {
		$this->positive( $user_id );
		$this->operation(
			function () use ( $user_id, $context ): void {
				if ( null !== $this->account( $user_id ) ) {
						throw new RuntimeException( 'Conta de autorização já existente.' );
				}
				$this->execute( $this->database->prepare( 'INSERT INTO %i (user_id, state, revision, created_at, updated_at) VALUES (%d, %s, 1, %d, %d)', $this->schema->table( 'accounts' ), $user_id, AccountState::Pending->name, $context->occurred_at, $context->occurred_at ), 1 );
				$this->audit(
					$user_id,
					1,
					'account_created',
					null,
					array(
						'user_id' => $user_id,
						'state'   => 'Pending',
					),
					$context
				);
			}
		);
	}

	/**
	 * CAS anterior ao no-op e validação da transição sob bloqueio.
	 *
	 * @param int           $user_id Conta.
	 * @param AccountState  $state Estado novo.
	 * @param int           $expected_revision Revisão observada.
	 * @param ChangeContext $context Decisão.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function changeState( int $user_id, AccountState $state, int $expected_revision, ChangeContext $context ): void {
		$this->operation(
			function () use ( $user_id, $state, $expected_revision, $context ): void {
				$account = $this->current( $user_id, $expected_revision );
				if ( $account->state === $state ) {
						return;
				}
				if ( AccountState::Pending === $state ) {
					throw new RuntimeException( 'Transição de autorização inválida.' );
				}
				$this->bump( $account, $state, $context );
				$this->audit(
					$user_id,
					$account->revision + 1,
					'state_changed',
					array(
						'user_id' => $user_id,
						'state'   => $account->state->name,
					),
					array(
						'user_id' => $user_id,
						'state'   => $state->name,
					),
					$context
				);
			}
		);
	}

	/**
	 * Suspend within an owner's transaction, without committing it or granting access.
	 *
	 * @param int           $user_id Target account.
	 * @param int           $expected_revision Observed revision.
	 * @param ChangeContext $context Audited decision.
	 * @throws RuntimeException When no transaction exists or persistence fails.
	 */
	public function suspendInTransaction( int $user_id, int $expected_revision, ChangeContext $context ): void {
		if ( ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'Transacção requerida.' );
		}
		$this->schema->validate();
		$account = $this->current( $user_id, $expected_revision );
		if ( AccountState::Suspended === $account->state ) {
			return;
		}
		$this->bump( $account, AccountState::Suspended, $context );
		$this->audit(
			$user_id,
			$account->revision + 1,
			'state_changed',
			array(
				'user_id' => $user_id,
				'state'   => $account->state->name,
			),
			array(
				'user_id' => $user_id,
				'state'   => AccountState::Suspended->name,
			),
			$context
		);
	}

	/**
	 * Cria concessão apenas em conta activa.
	 *
	 * @param Grant         $grant Concessão.
	 * @param int           $expected_revision Revisão observada.
	 * @param ChangeContext $context Decisão.
	 * @return int ID persistido.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function createGrant( Grant $grant, int $expected_revision, ChangeContext $context ): int {
		return $this->operation( fn(): int => $this->createGrantInTransaction( $grant, $expected_revision, $context ) );
	}

	/**
	 * Create under an owner's transaction. Business authority belongs to that owner.
	 *
	 * @param Grant         $grant Explicit grant.
	 * @param int           $expected_revision Observed account revision.
	 * @param ChangeContext $context Audited decision.
	 * @return int Stored grant ID.
	 * @throws RuntimeException When transaction or storage validation fails.
	 */
	public function createGrantInTransaction( Grant $grant, int $expected_revision, ChangeContext $context ): int {
		if ( ! $this->database->inTransaction() ) {
			throw new RuntimeException( 'Transacção requerida.' );
		}
		$this->schema->validate();
		$account = $this->current( $grant->user_id, $expected_revision );
		$this->active( $account, $grant );
		$stored = $this->insertGrant( $grant );
		$this->bump( $account, $account->state, $context );
		$this->audit( $grant->user_id, $account->revision + 1, 'grant_created', null, $this->payload( $stored ), $context );
		return $stored->id;
	}

	/**
	 * Revogação mantém dados históricos, mesmo em conta suspensa.
	 *
	 * @param int           $user_id Conta.
	 * @param int           $grant_id Concessão.
	 * @param int           $expected_revision Revisão observada.
	 * @param ChangeContext $context Decisão.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function revokeGrant( int $user_id, int $grant_id, int $expected_revision, ChangeContext $context ): void {
		$this->operation(
			function () use ( $user_id, $grant_id, $expected_revision, $context ): void {
				$account  = $this->current( $user_id, $expected_revision );
				$previous = $this->lockedGrant( $user_id, $grant_id );
				if ( $previous->grant->revoked ) {
						return;
				}
				$next = $this->revoke( $previous );
				$this->bump( $account, $account->state, $context );
				$this->audit( $user_id, $account->revision + 1, 'grant_revoked', $this->payload( $previous ), $this->payload( $next ), $context );
			}
		);
	}

	/**
	 * Revoga e substitui numa única revisão, sem reescrever concessão anterior.
	 *
	 * @param int           $user_id Conta.
	 * @param int           $grant_id Concessão anterior.
	 * @param Grant         $replacement Concessão nova.
	 * @param int           $expected_revision Revisão observada.
	 * @param ChangeContext $context Decisão.
	 * @return int ID novo.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function replaceGrant( int $user_id, int $grant_id, Grant $replacement, int $expected_revision, ChangeContext $context ): int {
		return $this->operation(
			function () use ( $user_id, $grant_id, $replacement, $expected_revision, $context ): int {
				$account = $this->current( $user_id, $expected_revision );
				$this->active( $account, $replacement );
				$previous = $this->lockedGrant( $user_id, $grant_id );
				if ( $previous->grant->revoked ) {
						throw new RuntimeException( 'Substituição de autorização inválida.' );
				}
				$old = $this->revoke( $previous );
				$new = $this->insertGrant( $replacement );
				$this->bump( $account, $account->state, $context );
				$this->audit(
					$user_id,
					$account->revision + 1,
					'grant_replaced',
					$this->payload( $previous ),
					array(
						'old_grant' => $this->payload( $old ),
						'new_grant' => $this->payload( $new ),
					),
					$context
				);
				return $new->id;
			}
		);
	}

	/**
	 * Conta e todas as concessões sob a mesma transacção e bloqueio.
	 *
	 * @param int $user_id Conta.
	 * @return AuthorizationSnapshot|null
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	public function find( int $user_id ): ?AuthorizationSnapshot {
		$this->positive( $user_id );
		return $this->operation(
			fn (): ?AuthorizationSnapshot => $this->lockedSnapshot( $user_id )
		);
	}

	/** Read an account under an existing transaction on this exact connection.
	 *
	 * @param int $user_id Account ID.
	 * @return AuthorizationSnapshot|null
	 */
	public function findInTransaction( int $user_id ): ?AuthorizationSnapshot {
		$this->positive( $user_id );
		return $this->silently(
			function () use ( $user_id ): ?AuthorizationSnapshot {
				if ( ! $this->database->inTransaction() ) {
					throw new RuntimeException( 'Transacção de autorização ausente.' );
				}
				return $this->lockedSnapshot( $user_id );
			}
		);
	}

	/** Hydrate account and grants with the established lock order.
	 *
	 * @param int $user_id Account ID.
	 * @return AuthorizationSnapshot|null
	 */
	private function lockedSnapshot( int $user_id ): ?AuthorizationSnapshot {
		$account = $this->account( $user_id );
		if ( null === $account ) {
			return null;
		}
		$grants = array();
		foreach ( $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id FOR UPDATE', $this->schema->table( 'grants' ), $user_id ) ) as $row ) {
			$grants[] = $this->hydrate( $row );
		}
		return new AuthorizationSnapshot( $user_id, $account->state, $account->revision, $grants );
	}

	/**
	 * Envelopes associativos com JSON validado, mais recentes primeiro.
	 *
	 * @param int $user_id Conta.
	 * @param int $limit Limite entre 1 e 100.
	 * @return array
	 * @throws InvalidArgumentException Quando os argumentos estão fora do domínio.
	 */
	public function history( int $user_id, int $limit = 50 ): array {
		$this->positive( $user_id );
		if ( $limit < 1 || $limit > 100 ) {
			throw new InvalidArgumentException( 'Limite de histórico inválido.' );
		}
		return $this->operation(
			function () use ( $user_id, $limit ): array {
				$account  = $this->account( $user_id );
				$rows     = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT %d', $this->schema->table( 'audit' ), $user_id, $limit ) );
				$events   = array();
				$revision = $account?->revision;
				$last_id  = null;
				foreach ( $rows as $row ) {
						$event = (array) $row;
					foreach ( array( 'id', 'user_id', 'actor_id', 'revision', 'occurred_at' ) as $key ) {
						$event[ $key ] = $this->integer( $event[ $key ], 'occurred_at' === $key ? 0 : 1 );
					}
					if ( $event['user_id'] !== $user_id || $event['revision'] !== $revision || ( null !== $last_id && $event['id'] >= $last_id ) ) {
						throw new RuntimeException( 'Envelope de auditoria inválido.' );
					}
					new ChangeContext( $event['actor_id'], $event['reason'], $event['decision_ref'], $event['occurred_at'] );
					$this->validateEvent( $event );
					$events[] = $event;
					--$revision;
					$last_id = $event['id'];
				}
				if ( ( null !== $account && count( $events ) !== min( $limit, $account->revision ) ) || ( null === $account && array() !== $events ) ) {
					throw new RuntimeException( 'Histórico de autorização incompleto.' );
				}
				return $events;
			}
		);
	}

	/**
	 * Lê e valida a conta antes de qualquer concessão.
	 *
	 * @param int $user_id Conta.
	 * @return AuthorizationSnapshot|null
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function account( int $user_id ): ?AuthorizationSnapshot {
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE user_id = %d FOR UPDATE', $this->schema->table( 'accounts' ), $user_id ) );
		if ( array() === $rows ) {
			return null;
		}
		if ( 1 !== count( $rows ) || $this->integer( $rows[0]->user_id ) !== $user_id ) {
			throw new RuntimeException( 'Conta de autorização inválida.' );
		}
		$row = $rows[0];
		$this->integer( $row->created_at, 0 );
		$this->integer( $row->updated_at, 0 );
		return new AuthorizationSnapshot( $user_id, $this->state( $row->state ), $this->integer( $row->revision ), array() );
	}

	/**
	 * Compara a revisão dentro da transacção, incluindo pedidos repetidos.
	 *
	 * @param int $user_id Conta.
	 * @param int $revision Revisão esperada.
	 * @return AuthorizationSnapshot
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function current( int $user_id, int $revision ): AuthorizationSnapshot {
		$this->positive( $user_id );
		$this->positive( $revision );
		$account = $this->account( $user_id );
		if ( null === $account || $revision !== $account->revision ) {
			throw new RuntimeException( 'Revisão de autorização desactualizada.' );
		}
		return $account;
	}

	/**
	 * Protege destinatário e estado de uma concessão nova.
	 *
	 * @param AuthorizationSnapshot $account Conta bloqueada.
	 * @param Grant                 $grant Concessão nova.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function active( AuthorizationSnapshot $account, Grant $grant ): void {
		if ( AccountState::Active !== $account->state || $account->user_id !== $grant->user_id || $grant->revoked ) {
			throw new RuntimeException( 'Criação de concessão não permitida.' );
		}
	}

	/**
	 * Incrementa uma única revisão com CAS adicional no UPDATE.
	 *
	 * @param AuthorizationSnapshot $account Conta bloqueada.
	 * @param AccountState          $state Estado novo.
	 * @param ChangeContext         $context Decisão.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function bump( AuthorizationSnapshot $account, AccountState $state, ChangeContext $context ): void {
		if ( PHP_INT_MAX === $account->revision ) {
			throw new RuntimeException( 'Revisão de autorização esgotada.' );
		}
		$this->execute( $this->database->prepare( 'UPDATE %i SET state = %s, revision = %d, updated_at = %d WHERE user_id = %d AND revision = %d', $this->schema->table( 'accounts' ), $state->name, $account->revision + 1, $context->occurred_at, $account->user_id, $account->revision ), 1 );
	}

	/**
	 * Grava valores de domínio sem aceitar payload SQL externo.
	 *
	 * @param Grant $grant Concessão nova.
	 * @return StoredGrant
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function insertGrant( Grant $grant ): StoredGrant {
		$expiry = null === $grant->expires_at ? 'NULL' : '%d';
		$args   = array( $this->schema->table( 'grants' ), $grant->user_id, $grant->role->name, $grant->scope->type->name, ScopeCodec::encode( $grant->scope ), $grant->starts_at );
		if ( null !== $grant->expires_at ) {
			$args[] = $grant->expires_at;
		}
		$this->execute( $this->database->prepare( "INSERT INTO %i (user_id, role, scope_type, targets_json, starts_at, expires_at, revoked) VALUES (%d, %s, %s, %s, %d, {$expiry}, 0)", ...$args ), 1 );
		return new StoredGrant( $this->integer( $this->database->insert_id ), $grant );
	}

	/**
	 * Só deve ser chamada depois do bloqueio da conta.
	 *
	 * @param int $user_id Conta.
	 * @param int $grant_id Concessão.
	 * @return StoredGrant
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function lockedGrant( int $user_id, int $grant_id ): StoredGrant {
		$this->positive( $grant_id );
		$rows = $this->rows( $this->database->prepare( 'SELECT * FROM %i WHERE user_id = %d AND id = %d FOR UPDATE', $this->schema->table( 'grants' ), $user_id, $grant_id ) );
		if ( 1 !== count( $rows ) ) {
			throw new RuntimeException( 'Concessão de autorização ausente.' );
		}
		$stored = $this->hydrate( $rows[0] );
		if ( $stored->id !== $grant_id || $stored->grant->user_id !== $user_id ) {
			throw new RuntimeException( 'Concessão de autorização inválida.' );
		}
		return $stored;
	}

	/**
	 * Modifica apenas a revogação, preservando o restante histórico.
	 *
	 * @param StoredGrant $previous Concessão anterior.
	 * @return StoredGrant
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function revoke( StoredGrant $previous ): StoredGrant {
		$grant = $previous->grant;
		$this->execute( $this->database->prepare( 'UPDATE %i SET revoked = 1 WHERE id = %d AND user_id = %d AND revoked = 0', $this->schema->table( 'grants' ), $previous->id, $grant->user_id ), 1 );
		return new StoredGrant( $previous->id, new Grant( $grant->user_id, $grant->role, $grant->scope, $grant->starts_at, $grant->expires_at, true ) );
	}

	/**
	 * Roundtrip inteiro sem floats, overflow ou normalização de texto inválido.
	 *
	 * @param mixed $value Valor SQL.
	 * @param int   $minimum Mínimo permitido.
	 * @return int
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function integer( mixed $value, int $minimum = 1 ): int {
		if ( ( ! is_string( $value ) && ! is_int( $value ) ) || (string) (int) $value !== (string) $value || (int) $value < $minimum ) {
			throw new RuntimeException( 'Inteiro de autorização inválido.' );
		}
		return (int) $value;
	}

	/**
	 * Rejeita IDs ou revisões fora do domínio.
	 *
	 * @param int $value Valor positivo.
	 * @throws InvalidArgumentException Quando os argumentos estão fora do domínio.
	 */
	private function positive( int $value ): void {
		if ( $value < 1 ) {
			throw new InvalidArgumentException( 'Identificador de autorização inválido.' );
		}
	}

	/**
	 * Estado exacto, sem fallback permissivo.
	 *
	 * @param string $name Nome persistido.
	 * @return AccountState
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function state( string $name ): AccountState {
		foreach ( AccountState::cases() as $state ) {
			if ( $name === $state->name ) {
				return $state;
			}
		}
		throw new RuntimeException( 'Estado de autorização inválido.' );
	}

	/**
	 * Hidrata cada campo e recusa a linha inteira perante qualquer anomalia.
	 *
	 * @param object $row Linha SQL.
	 * @return StoredGrant
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function hydrate( object $row ): StoredGrant {
		$role = null;
		foreach ( Role::cases() as $candidate ) {
			if ( $candidate->name === $row->role ) {
				$role = $candidate;
			}
		}
		if ( null === $role || ! in_array( $row->revoked, array( '0', '1', 0, 1 ), true ) ) {
			throw new RuntimeException( 'Concessão de autorização inválida.' );
		}
		return new StoredGrant( $this->integer( $row->id ), new Grant( $this->integer( $row->user_id ), $role, ScopeCodec::decode( $row->scope_type, $row->targets_json ), $this->integer( $row->starts_at, 0 ), null === $row->expires_at ? null : $this->integer( $row->expires_at, 0 ), (bool) $row->revoked ) );
	}

	/**
	 * Payload fechado, com listas canónicas e valores inteiros.
	 *
	 * @param StoredGrant $stored Concessão validada.
	 * @return array
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function payload( StoredGrant $stored ): array {
		$grant = $stored->grant;
		return array(
			'id'         => $stored->id,
			'user_id'    => $grant->user_id,
			'role'       => $grant->role->name,
			'scope_type' => $grant->scope->type->name,
			'targets'    => json_decode( ScopeCodec::encode( $grant->scope ), false, 512, JSON_THROW_ON_ERROR ),
			'starts_at'  => $grant->starts_at,
			'expires_at' => $grant->expires_at,
			'revoked'    => $grant->revoked,
		);
	}

	/**
	 * Auditoria anexada na mesma transacção da mudança.
	 *
	 * @param int           $user_id Conta.
	 * @param int           $revision Revisão nova.
	 * @param string        $event Evento fechado.
	 * @param array|null    $previous Dados anteriores.
	 * @param array         $next Dados novos.
	 * @param ChangeContext $context Decisão.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function audit( int $user_id, int $revision, string $event, ?array $previous, array $next, ChangeContext $context ): void {
		$old  = null === $previous ? 'NULL' : '%s';
		$args = array( $this->schema->table( 'audit' ), $user_id, $context->actor_id, $event, $revision );
		if ( null !== $previous ) {
			$args[] = json_encode( $previous, JSON_THROW_ON_ERROR );
		}
		$args[] = json_encode( $next, JSON_THROW_ON_ERROR );
		$args[] = bin2hex( $context->reason );
		$args[] = $context->decision_ref;
		$args[] = $context->occurred_at;
		$this->execute( $this->database->prepare( "INSERT INTO %i (user_id, actor_id, event, revision, previous_json, new_json, reason, decision_ref, occurred_at) VALUES (%d, %d, %s, %d, {$old}, %s, CONVERT(UNHEX(%s) USING utf8mb4), %s, %d)", ...$args ), 1 );
	}

	/**
	 * Reconstitui payload JSON sem aceitar objectos como listas ou campos extra.
	 *
	 * @param mixed $payload Objecto JSON.
	 * @param int   $user_id Conta esperada.
	 * @return array Concessão canónica.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function grantPayload( mixed $payload, int $user_id ): array {
		$this->shape( $payload, array( 'id', 'user_id', 'role', 'scope_type', 'targets', 'starts_at', 'expires_at', 'revoked' ) );
		foreach ( array( 'id', 'user_id', 'starts_at' ) as $key ) {
			if ( ! is_int( $payload->$key ) ) {
				throw new RuntimeException( 'Payload de auditoria inválido.' );
			}
		}
		if ( ( null !== $payload->expires_at && ! is_int( $payload->expires_at ) ) || ! is_bool( $payload->revoked ) || $payload->user_id !== $user_id || ! is_array( $payload->targets ) ) {
			throw new RuntimeException( 'Payload de auditoria inválido.' );
		}
		$row               = clone $payload;
		$row->targets_json = json_encode( $payload->targets, JSON_THROW_ON_ERROR );
		$row->revoked      = (int) $payload->revoked;
		return $this->payload( $this->hydrate( $row ) );
	}

	/**
	 * Exige objecto JSON com todas e apenas as propriedades previstas.
	 *
	 * @param mixed $value Objecto.
	 * @param array $keys Propriedades.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function shape( mixed $value, array $keys ): void {
		if ( ! $value instanceof \stdClass ) {
			throw new RuntimeException( 'Objecto de auditoria inválido.' );
		}
		$actual = array_keys( get_object_vars( $value ) );
		sort( $actual );
		sort( $keys );
		if ( $actual !== $keys ) {
			throw new RuntimeException( 'Campos de auditoria inválidos.' );
		}
	}

	/**
	 * Valida evento, estruturas e relação entre snapshots antigos e novos.
	 *
	 * @param array $event Envelope já validado.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function validateEvent( array $event ): void {
		$old     = null === $event['previous_json'] ? null : json_decode( $event['previous_json'], false, 512, JSON_THROW_ON_ERROR );
		$new     = json_decode( $event['new_json'], false, 512, JSON_THROW_ON_ERROR );
		$user_id = $event['user_id'];
		$name    = $event['event'];
		if ( ! in_array( $name, array( 'account_created', 'state_changed', 'grant_created', 'grant_revoked', 'grant_replaced' ), true ) || ( 'account_created' === $name ? 1 !== $event['revision'] : $event['revision'] < 2 ) ) {
			throw new RuntimeException( 'Evento de auditoria inválido.' );
		}
		if ( in_array( $name, array( 'account_created', 'state_changed' ), true ) ) {
			$this->shape( $new, array( 'user_id', 'state' ) );
			$state = $this->state( $new->state );
			if ( $new->user_id !== $user_id ) {
				throw new RuntimeException( 'Conta no histórico inválida.' );
			}
			if ( 'account_created' === $name ) {
				if ( null !== $event['previous_json'] || AccountState::Pending !== $state ) {
					throw new RuntimeException( 'Criação no histórico inválida.' );
				}
				return;
			}
			$this->shape( $old, array( 'user_id', 'state' ) );
			if ( $old->user_id !== $user_id || $this->state( $old->state ) === $state || AccountState::Pending === $state ) {
				throw new RuntimeException( 'Transição no histórico inválida.' );
			}
			return;
		}
		if ( 'grant_created' === $name ) {
			$grant = $this->grantPayload( $new, $user_id );
			if ( null !== $event['previous_json'] || $grant['revoked'] ) {
				throw new RuntimeException( 'Concessão nova no histórico inválida.' );
			}
			return;
		}
		$before = $this->grantPayload( $old, $user_id );
		if ( $before['revoked'] ) {
			throw new RuntimeException( 'Concessão anterior no histórico inválida.' );
		}
		$before['revoked'] = true;
		if ( 'grant_revoked' === $name ) {
			if ( $before !== $this->grantPayload( $new, $user_id ) ) {
				throw new RuntimeException( 'Revogação no histórico inválida.' );
			}
			return;
		}
		$this->shape( $new, array( 'old_grant', 'new_grant' ) );
		$replacement = $this->grantPayload( $new->new_grant, $user_id );
		if ( $before !== $this->grantPayload( $new->old_grant, $user_id ) || $replacement['revoked'] || $before['id'] >= $replacement['id'] ) {
			throw new RuntimeException( 'Substituição no histórico inválida.' );
		}
	}

	/**
	 * Recusa transacções aninhadas antes de START, sem terminar a exterior.
	 *
	 * @param callable $operation Trabalho da transacção.
	 * @return mixed
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function operation( callable $operation ): mixed {
		return $this->silently(
			function () use ( $operation ): mixed {
				$this->execute( 'SET TRANSACTION ISOLATION LEVEL READ COMMITTED' );
				$this->schema->validate();
				$this->execute( 'START TRANSACTION' );
				try {
						$result = $operation();
						$this->execute( 'COMMIT' );
						return $result;
				} catch ( Throwable ) {
					try {
						$this->database->query( 'ROLLBACK' );
					} catch ( Throwable ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Falha de rollback por perda de ligação.
						// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Ligação perdida, sem reconnect ou repetição.
						// A ligação pode ter sido perdida. Nunca reconectar ou repetir.
					}
					throw new RuntimeException( 'Operação de autorização não confirmada.' );
				}
			}
		);
	}

	/**
	 * Oculta erros SQL e elimina cadeias de excepções externas.
	 *
	 * @param callable $operation Operação interna.
	 * @return mixed
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function silently( callable $operation ): mixed {
		$previous = $this->database->suppress_errors( true );
		try {
			return $operation();
		} catch ( Throwable ) {
			throw new RuntimeException( 'Persistência de autorização indisponível.' );
		} finally {
			$this->database->suppress_errors( $previous );
		}
	}

	/**
	 * Confere resultado e contagem de linhas afectadas.
	 *
	 * @param string   $sql SQL preparado.
	 * @param int|null $affected Contagem obrigatória.
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function execute( string $sql, ?int $affected = null ): void {
		$result = $this->database->query( $sql );
		if ( false === $result || '' !== $this->database->last_error || ( null !== $affected && $result !== $affected ) ) {
			throw new RuntimeException( 'Escrita de autorização inválida.' );
		}
	}

	/**
	 * Lê directamente sem aceitar erros como conjunto vazio.
	 *
	 * @param string $sql SQL preparado.
	 * @return array
	 * @throws RuntimeException Quando a validação ou persistência recusa a operação.
	 */
	private function rows( string $sql ): array {
		$rows = $this->database->get_results( $sql );
		if ( ! is_array( $rows ) || '' !== $this->database->last_error ) {
			throw new RuntimeException( 'Leitura de autorização inválida.' );
		}
		return $rows;
	}
}
