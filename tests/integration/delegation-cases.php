<?php
/** Real SQL on the register harness's exclusive prefix, never native users.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Authorization\Delegations\{Schema as DelegationSchema, Repository as DelegationRepository, Authority as DelegationAuthority, Command as DelegationCommand};
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{WpdbAuthorizationRepository, ChangeContext};
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
use Arn\Intranet\Identity\IdentityWpdb;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
$deleg_now      = time() + 1000;
$deleg_actor    = 3001;
$deleg_token    = str_repeat( 'delegation-session-', 3 );
$deleg_sessions = array();
$deleg_context  = new ChangeContext( 1002, 'Preparação exclusiva de delegações fictícias', 'test/delegations', $deleg_now );
foreach ( array( 3001, 3002, 3003 ) as $fixture_id ) {
	$auth->createAccount( $fixture_id, $deleg_context );
	$auth->changeState( $fixture_id, AccountState::Active, 1, $deleg_context );
}
$auth->createGrant( new Grant( 3001, Role::AccessAdministrator, Scope::people( array( 3002, 3003 ) ), 0, null ), 2, $deleg_context );
$delegate_permission = $auth->createGrant( new Grant( 3002, Role::AccessAdministrator, Scope::people( array( 3003 ) ), 0, null ), 2, $deleg_context );
foreach ( array( 3001, 3002, 3003 ) as $fixture_id ) {
	$deleg_sessions[ $fixture_id ] = array(
		'arn_authz_local'    => true,
		'arn_authz_user_id'  => $fixture_id,
		'arn_authz_revision' => $auth->find( $fixture_id )->revision,
	);
}
$make_delegations = static function ( IdentityWpdb $connection ) use ( &$deleg_actor, &$deleg_now, &$deleg_token, &$deleg_sessions ): DelegationRepository {
	$a = new DelegationAuthority(
		$connection,
		static function () use ( &$deleg_actor ): int {
			return $deleg_actor; },
		static function ( int $fixture_id ) use ( &$deleg_sessions ): array {
			return $deleg_sessions[ $fixture_id ] ?? array(); },
		static function () use ( &$deleg_token ): string {
			return $deleg_token; },
		static function () use ( &$deleg_now ): int {
			return $deleg_now; },
		static fn(): array => array(
			'issuer'   => 3001,
			'delegate' => 3002,
			'target'   => 3003,
		)
	);
	return new DelegationRepository( $connection, $a );
};
$deleg_schema     = new DelegationSchema( $db );
$deleg            = $make_delegations( $db );
$deleg_other      = $make_delegations( $second );
$deny( static fn() => $deleg->listing() );
$deleg_schema->install();
$deleg_digest = static function () use ( $db, $prefix ): string {
	$all = array();
	foreach ( array( 'arn_delegation_header', 'arn_delegation_batches', 'arn_delegation_audit', 'arn_authz_accounts', 'arn_authz_grants', 'arn_authz_audit', 'arn_reg_header', 'arn_reg_audit', 'arn_reg_batches', 'arn_lifecycle_header', 'arn_lifecycle_audit', 'arn_lifecycle_batches' ) as $suffix ) {
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i', $prefix . $suffix ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $db->last_error ) {
			throw new RuntimeException( 'Snapshot indisponível.' ); }
		$hashes = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
		sort( $hashes );
		$all[ $suffix ] = $hashes;
	}
	return hash( 'sha256', json_encode( $all, JSON_THROW_ON_ERROR ) );
};
$deleg_before = $deleg_digest();
$deleg->listing();
$deleg_schema->install();
$assert( $deleg_before === $deleg_digest(), 'leituras e reinstalação não criam delegações' );
$start       = $deleg_now - 60;
$end         = $deleg_now + 60;
$deleg_cmd   = static function ( string $action, int $revision, int $target_revision = 0 ) use ( $start, $end ): DelegationCommand {
	return new DelegationCommand( $action, 'issue' === $action ? '' : 'LAB-DEL-000001', 3002, 3003, 'Employee', 'own', $start, 'grant' === $action ? $end + 3600 : $end, $revision, $target_revision, 'Motivo sintético &amp; &#65;', bin2hex( random_bytes( 32 ) ) );
};
$deleg_actor = 3002;
$deny( static fn() => $deleg->apply( $deleg_cmd( 'issue', 0 ) ) );
$deny( static fn() => $deleg->apply( $deleg_cmd( 'grant', 0, 2 ) ) );
$deleg_actor   = 3001;
$issue_cmd     = $deleg_cmd( 'issue', 0 );
$issue_receipt = $deleg->apply( $issue_cmd );
$assert( 1 === $issue_receipt['revision'] && 2 === $auth->find( 3003 )->revision && array() === $auth->find( 3003 )->stored_grants, 'emitir não cria concessão nem muda sessão do destinatário' );
$assert( $deleg_other->apply( $issue_cmd ) === $issue_receipt, 'emissão idempotente noutra ligação' );
$deny( static fn() => $deleg->apply( $deleg_cmd( 'grant', 1, 2 ) ) );
$deleg_actor = 3002;
$grant_cmd   = $deleg_cmd( 'grant', 1, 2 );
$deny( static fn() => $auth->createGrantInTransaction( $grant_cmd->grant(), 2, $deleg_context ) );
$before = $deleg_digest();
$db->query( 'START TRANSACTION' );
$auth->createGrantInTransaction( $grant_cmd->grant(), 2, $deleg_context );
$assert( $db->inTransaction() && 3 === $auth->findInTransaction( 3003 )->revision, 'criação canónica não confirma transacção do proprietário' );
$db->query( 'ROLLBACK' );
$assert( $before === $deleg_digest(), 'rollback canónico desfaz concessão e revisão' );
$wrong = new DelegationCommand( 'grant', 'LAB-DEL-000001', 3002, 3003, 'UnitManager', 'LAB-B', $start, $end + 3600, 1, 2, 'Alargamento indevido', bin2hex( random_bytes( 32 ) ) );
$deny( static fn() => $deleg->apply( $wrong ) );
$deny( static fn() => $deleg->apply( $deleg_cmd( 'grant', 1, 1 ) ) );
foreach ( array( 'arn_authz_audit', 'arn_delegation_batches', 'arn_delegation_audit' ) as $suffix ) {
	$before = $deleg_digest();
	$filter = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $prefix . $suffix . '`' ) ? 'INVALID DELEGATION SQL TEST' : $sql;
	add_filter( 'query', $filter );
	try {
		$deny( static fn() => $deleg->apply( $grant_cmd ) );
	} finally {
		remove_filter( 'query', $filter );
		$filter = null; }
	$assert( $before === $deleg_digest(), 'falha em ' . $suffix . ' não deixa concessão ou revisão parcial' );
}
$before       = $deleg_digest();
$previous_now = $deleg_now;
$filter       = static function ( string $sql ) use ( &$deleg_now, $end, $prefix ): string {
	if ( str_starts_with( $sql, 'UPDATE `' . $prefix . 'arn_delegation_header`' ) ) {
		$deleg_now = $end;
	} return $sql;
};
add_filter( 'query', $filter );
try {
	$deny( static fn() => $deleg->apply( $grant_cmd ) );
} finally {
	remove_filter( 'query', $filter );
	$filter    = null;
	$deleg_now = $previous_now; }
$assert( $before === $deleg_digest(), 'expiração antes de commit reverte atribuição' );
$old_deleg_token = $deleg_token;
$filter          = static function ( string $sql ) use ( &$deleg_token, $prefix ): string {
	if ( str_starts_with( $sql, 'UPDATE `' . $prefix . 'arn_delegation_header`' ) ) {
		$deleg_token = '';
	} return $sql;
};
add_filter( 'query', $filter );
try {
	$deny( static fn() => $deleg->apply( $grant_cmd ) );
} finally {
	remove_filter( 'query', $filter );
	$filter      = null;
	$deleg_token = $old_deleg_token; }
$assert( $before === $deleg_digest(), 'perda de sessão antes de commit reverte atribuição' );
$attempted    = false;
$blocked      = false;
$revoke_stale = $deleg_cmd( 'revoke', 1 );
$filter       = static function ( string $sql ) use ( &$deleg_actor, &$attempted, &$blocked, $prefix, $deleg_other, $revoke_stale ): string {
	if ( ! $attempted && str_starts_with( $sql, 'UPDATE `' . $prefix . 'arn_delegation_header`' ) ) {
		$attempted   = true;
		$deleg_actor = 3001;
		try {
			$deleg_other->apply( $revoke_stale );
		} catch ( RuntimeException ) {
			$blocked = true;
		} finally {
			$deleg_actor = 3002; }
	}
	return $sql;
};
add_filter( 'query', $filter );
try {
	$grant_receipt = $deleg->apply( $grant_cmd );
} finally {
	remove_filter( 'query', $filter );
	$filter = null; }
$assert( $attempted && $blocked, 'revogação concorrente não atravessa atribuição bloqueada' );
$target = $auth->find( 3003 );
$assert( $target->revision === 3 && count( $target->stored_grants ) === 1, 'uma concessão canónica com revisão única' );
$assert( ! LocalAccountSessionPolicy::valid( $target, 3003, $deleg_sessions[3003], true ), 'nova competência invalida geração anterior da sessão' );
$deleg_sessions[3003]['arn_authz_revision'] = 3;
$deleg_now                                  = $end;
$deny( static fn() => $deleg->apply( $deleg_cmd( 'grant', 2, 3 ) ) );
$assert( $deleg->apply( $grant_cmd ) === $grant_receipt && 3 === $auth->find( 3003 )->revision, 'recibo depois de expirar não reaplica concessão' );
$deleg_actor = 3001;
$deny( static fn() => $deleg->apply( $revoke_stale ) );
$revoke = $deleg_cmd( 'revoke', 2 );
$deleg->apply( $revoke );
$assert( $target->revision === $auth->find( 3003 )->revision && $target->stored_grants == $auth->find( 3003 )->stored_grants && LocalAccountSessionPolicy::valid( $auth->find( 3003 ), 3003, $deleg_sessions[3003], true ), 'revogar delegação conserva concessão e sessão do destinatário' ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Compare immutable value objects loaded by separate reads.
$assert( $auth->find( 3003 )->stored_grants[0]->grant->validAt( $end + 1 ), 'validade própria prossegue após fim da delegação' );
$deleg_actor = 3002;
$deny( static fn() => $deleg->apply( $deleg_cmd( 'grant', 3, 3 ) ) );
$assert( $deleg->apply( $grant_cmd ) === $grant_receipt, 'recibo antigo depois de revogar não repete efeitos' );
$deleg_actor = 3001;
$unit_issue  = new DelegationCommand( 'issue', '', 3002, 3003, 'UnitManager', 'LAB-A', $deleg_now + 10, $deleg_now + 120, 3, 0, 'Delegação de unidade futura', bin2hex( random_bytes( 32 ) ) );
$deleg->apply( $unit_issue );
$deleg_actor = 3002;
$unit_grant  = new DelegationCommand( 'grant', 'LAB-DEL-000004', 3002, 3003, 'UnitManager', 'LAB-A', $deleg_now, $deleg_now + 3600, 4, 3, 'Concessão de unidade autorizada', bin2hex( random_bytes( 32 ) ) );
$deny( static fn() => $deleg->apply( $unit_grant ) );
$deleg_now += 10;
$deleg->apply( $unit_grant );
$assert( 4 === $auth->find( 3003 )->revision && 2 === count( $auth->find( 3003 )->stored_grants ), 'delegação futura só concede no início e aceita unidade activa' );
$assert( $auth->find( 3003 )->stored_grants[1]->grant->scope->targets === array( 'LAB-A' ), 'concessão conserva âmbito autorizado da unidade' );
$missing_audit   = $db->get_row( $db->prepare( 'SELECT * FROM %i WHERE revision=1', $deleg_schema->table( 'audit' ) ), ARRAY_A );
$missing_receipt = $db->get_row( $db->prepare( 'SELECT * FROM %i WHERE id=%s', $deleg_schema->table( 'batches' ), $missing_audit['reason'] ), ARRAY_A );
$db->delete( $deleg_schema->table( 'audit' ), array( 'revision' => 1 ) );
$db->delete( $deleg_schema->table( 'batches' ), array( 'id' => $missing_audit['reason'] ) );
$gap_refused = false;
try {
	$deleg->listing();
} catch ( RuntimeException ) {
	$gap_refused = true;
} finally {
	$db->insert( $deleg_schema->table( 'audit' ), $missing_audit );
	$db->insert( $deleg_schema->table( 'batches' ), $missing_receipt );
}
$assert( $gap_refused, 'histórico sem decisão inicial é recusado mesmo com último recibo íntegro' );
$before                   = $deleg_digest();
$clock_before_final_units = $deleg_now;
$unit_reads               = 0;
$filter                   = static function ( string $sql ) use ( &$deleg_now, &$unit_reads, $prefix, $unit_issue ): string {
	if ( str_starts_with( $sql, 'SELECT id, revision FROM `' . $prefix . 'arn_org_header`' ) && str_ends_with( $sql, 'FOR UPDATE' ) ) {
		++$unit_reads;
		if ( 2 === $unit_reads ) {
			$deleg_now = $unit_issue->expires; }
	}
	return $sql;
};
$last_moment              = new DelegationCommand( 'grant', 'LAB-DEL-000004', 3002, 3003, 'UnitManager', 'LAB-A', $deleg_now, $deleg_now + 3600, 5, 4, 'Expiração durante leitura final', bin2hex( random_bytes( 32 ) ) );
add_filter( 'query', $filter );
try {
	$deny( static fn() => $deleg->apply( $last_moment ) );
} finally {
	remove_filter( 'query', $filter );
	$filter    = null;
	$deleg_now = $clock_before_final_units;
}
$assert( 2 === $unit_reads && $before === $deleg_digest(), 'expiração durante SQL da revalidação final desfaz toda a atribuição' );
$auth->revokeGrant( 3002, $delegate_permission, 3, new ChangeContext( 3001, 'Retirar competência de ensaio', 'test/delegations-capability', $deleg_now ) );
$deleg_sessions[3002]['arn_authz_revision'] = 4;
$deny( static fn() => $deleg->listing() );
$deleg_actor = 3003;
$deny( static fn() => $deleg->listing() );
$deleg_actor = 3001;
$assert( 5 === count( $deleg->listing()['history'] ) && 5 === $deleg->listing()['state']->revision, 'história de emissão, atribuição e revogação completa' );
$stored_payload = $db->get_var( $db->prepare( 'SELECT payload FROM %i WHERE id=1', $deleg_schema->table( 'header' ) ) );
$db->query( $db->prepare( 'UPDATE %i SET payload=%s WHERE id=1', $deleg_schema->table( 'header' ), '[]' ) );
$deny( static fn() => $deleg->listing() );
$db->query( $db->prepare( 'UPDATE %i SET payload=CONVERT(UNHEX(%s) USING utf8mb4) WHERE id=1', $deleg_schema->table( 'header' ), bin2hex( $stored_payload ) ) );
$assert( 5 === $deleg->listing()['state']->revision, 'corrupção sintética recusada e restaurada exclusivamente' );
