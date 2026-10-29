<?php
/** Composed with the exclusive register SQL harness. Never touches native accounts.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Lifecycle\{Schema as LifecycleSchema, Repository as LifecycleRepository, Command};
use Arn\Intranet\Organization\Register\Authority;
use Arn\Intranet\Authorization\AccountState;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Authorization\Session\LocalAccountSessionPolicy;
// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.WP.AlternativeFunctions.json_encode_json_encode
$make_life   = static function ( IdentityWpdb $connection ) use ( &$actor, &$session, &$token, &$now, $make ): LifecycleRepository {
	$authority = new Authority(
		$connection,
		static function () use ( &$actor ): int {
			return $actor;
		},
		static function () use ( &$session ): array {
			return $session;
		},
		static function () use ( &$token ): string {
			return $token;
		},
		static function () use ( &$now ): int {
			return $now;
		}
	);
	$target    = static function ( IdentityWpdb $database, int $id ): array|bool {
		if ( 0 !== $id ) {
			return in_array( $id, array( 2001, 2002 ), true ); }
		$result = array();
		foreach ( array( 2001, 2002 ) as $account_id ) {
			$a = ( new WpdbAuthorizationRepository( $database ) )->findInTransaction( $account_id );
			if ( null !== $a ) {
				$result[ $account_id ] = array(
					'revision' => $a->revision,
					'state'    => $a->state->name,
				); }
		}
		return $result;
	};
	return new LifecycleRepository(
		$connection,
		$authority,
		$make( $connection ),
		$target,
		static function () use ( &$now ): int {
			return $now;
		}
	);
};
$life_schema = new LifecycleSchema( $db );
$life        = $make_life( $db );
$other_life  = $make_life( $second );
$legacy      = $digest();
$deny( static fn() => $life->listing() );
$life_schema->install();
$assert( $legacy === $digest(), 'ciclo não modifica o catálogo anterior' );
$auth->createAccount( 2001, $context );
$auth->changeState( 2001, AccountState::Active, 1, $context );
$auth->createAccount( 2002, $context );
$all_digest = static function () use ( $db, $prefix ): string {
	$all = array();
	foreach ( array( 'arn_lifecycle_header', 'arn_lifecycle_audit', 'arn_lifecycle_batches', 'arn_authz_accounts', 'arn_authz_grants', 'arn_authz_audit', 'arn_reg_header', 'arn_reg_audit', 'arn_reg_batches' ) as $suffix ) {
		$r = $db->get_results( $db->prepare( 'SELECT * FROM %i', $prefix . $suffix ), ARRAY_A );
		if ( ! is_array( $r ) || '' !== $db->last_error ) {
			throw new RuntimeException( 'Snapshot inválido.' ); }
		$hashes = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $r );
		sort( $hashes );
		$all[ $suffix ] = $hashes;
	}
	return hash( 'sha256', json_encode( $all, JSON_THROW_ON_ERROR ) );
};
$command    = static function ( string $action, string $person, string $state, int $account, int $revision, int $account_revision = 0 ) use ( $repo ): Command {
	return new Command( $action, $person, $state, $account, $revision, $repo->listing()['catalog']->revision, $account_revision, 'Decisão sintética de teste', bin2hex( random_bytes( 32 ) ) );
};
$assert( 0 === $life->listing()['state']->revision, 'GET não infere estados ou associações' );
$unchanged = $all_digest();
$life_schema->install();
$assert( $unchanged === $all_digest(), 'instalação repetida não altera dados' );
$deny( static fn() => $auth->suspendInTransaction( 2001, 2, $context ) );
$db->query( 'START TRANSACTION' );
$auth->suspendInTransaction( 2001, 2, $context );
$assert( $db->inTransaction() && AccountState::Suspended === $auth->findInTransaction( 2001 )->state, 'suspensão não confirma a transacção do chamador' );
$db->query( 'ROLLBACK' );
$assert( $unchanged === $all_digest(), 'rollback desfaz suspensão e auditoria canónicas' );
$link_command = $command( 'link', 'LAB-PER-001', '', 2001, 0, 2 );
$r            = $life->apply( $link_command );
$assert( 1 === $r['revision'] && 2 === $auth->find( 2001 )->revision && array() === $auth->find( 2001 )->stored_grants, 'vínculo não activa nem concede funções' );
$assert( $r === $other_life->apply( $link_command ), 'reenvio noutra ligação devolve recibo' );
$deny( static fn() => $life->apply( $command( 'link', 'LAB-PER-002', '', 2001, 1, 2 ) ) );
$deny( static fn() => $life->apply( $command( 'link', 'LAB-PER-001', '', 2002, 1, 1 ) ) );
$deny( static fn() => $life->apply( $command( 'link', 'LAB-PER-002', '', 9000, 1, 1 ) ) );
$deny( static fn() => $life->apply( $command( 'state', 'LAB-PER-001', 'Suspended', 2001, 0, 2 ) ) );
$deny( static fn() => $life->apply( $command( 'state', 'LAB-PER-001', 'Suspended', 2001, 1, 1 ) ) );
$suspend = $command( 'state', 'LAB-PER-001', 'Suspended', 2001, 1, 2 );
foreach ( array( 'arn_lifecycle_batches', 'arn_lifecycle_audit', 'arn_authz_audit' ) as $suffix ) {
	$unchanged = $all_digest();
	$filter    = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $prefix . $suffix . '`' ) ? 'INVALID LIFECYCLE TEST SQL' : $sql;
	add_filter( 'query', $filter );
	try {
		$deny( static fn() => $life->apply( $suspend ) );
	} finally {
		remove_filter( 'query', $filter );
		$filter = null; }
	$assert( $unchanged === $all_digest(), 'falha em ' . $suffix . ' preserva estado, acesso e evidências' );
}
$old_token = $token;
$filter    = static function ( string $sql ) use ( &$token, $prefix ): string {
	if ( str_starts_with( $sql, 'UPDATE `' . $prefix . 'arn_lifecycle_header`' ) ) {
		$token = ''; }
	return $sql;
};
$unchanged = $all_digest();
add_filter( 'query', $filter );
try {
	$deny( static fn() => $life->apply( $suspend ) );
} finally {
	remove_filter( 'query', $filter );
	$filter = null;
	$token  = $old_token; }
$assert( $unchanged === $all_digest(), 'perda de sessão antes de commit reverte também acesso' );
$second->query( 'SET SESSION innodb_lock_wait_timeout = 1' );
$attempted = false;
$blocked   = false;
$filter    = static function ( string $sql ) use ( &$attempted, &$blocked, $prefix, $other_life, $suspend ): string {
	if ( ! $attempted && str_starts_with( $sql, 'UPDATE `' . $prefix . 'arn_lifecycle_header`' ) ) {
		$attempted = true;
		try {
			$other_life->apply( $suspend );
		} catch ( RuntimeException ) {
			$blocked = true; }
	}
	return $sql;
};
add_filter( 'query', $filter );
try {
	$suspended = $life->apply( $suspend );
} finally {
	remove_filter( 'query', $filter );
	$filter = null; }
$assert( $attempted && $blocked && $other_life->apply( $suspend ) === $suspended, 'concorrência serializada e retoma idempotente' );
$account = $auth->find( 2001 );
$assert( AccountState::Suspended === $account->state && 3 === $account->revision, 'suspensão profissional invalida revisão de acesso' );
$native = array(
	'arn_authz_local'    => true,
	'arn_authz_user_id'  => 2001,
	'arn_authz_revision' => 2,
);
$assert( ! LocalAccountSessionPolicy::valid( $account, 2001, $native, true ), 'sessão antiga recusada' );
$life->apply( $command( 'state', 'LAB-PER-001', 'Active', 2001, 2, 3 ) );
$assert( AccountState::Suspended === $auth->find( 2001 )->state && 3 === $auth->find( 2001 )->revision, 'reactivação profissional não reactiva acesso' );
$auth->changeState( 2001, AccountState::Active, 3, $context );
$life->apply( $command( 'state', 'LAB-PER-001', 'Closed', 2001, 3, 4 ) );
$assert( AccountState::Suspended === $auth->find( 2001 )->state && 5 === $auth->find( 2001 )->revision, 'encerramento suspende acesso previamente reactivado explicitamente' );
$assert( $r === $life->apply( $link_command ) && 4 === $life->listing()['state']->revision, 'recibo antigo não repõe estado nem conta' );
$life->apply( $command( 'state', 'LAB-PER-002', 'Suspended', 0, 4 ) );
$life->apply( $command( 'link', 'LAB-PER-002', '', 2002, 5, 1 ) );
$assert( AccountState::Suspended === $auth->find( 2002 )->state && 2 === $auth->find( 2002 )->revision, 'associar a registo suspenso suspende conta Pending' );
$assert( $legacy === $digest() && 6 === count( $life->listing()['history'] ), 'catálogo original intacto e histórico completo' );
$wrong = new Command( 'state', 'LAB-PER-001', 'Closed', 2001, 3, 3, 4, 'Outro motivo', $suspend->request_id );
$deny( static fn() => $life->apply( $wrong ) );
$old_actor = $actor;
$actor     = 2001;
try {
	$deny( static fn() => $life->listing() );
} finally {
	$actor = $old_actor; }
$raw = $db->get_var( $db->prepare( 'SELECT payload FROM %i WHERE id = 1', $life_schema->table( 'header' ) ) );
$db->query( $db->prepare( 'UPDATE %i SET payload = %s WHERE id = 1', $life_schema->table( 'header' ), '[]' ) );
$deny( static fn() => $life->listing() );
$db->query( $db->prepare( 'UPDATE %i SET payload = %s WHERE id = 1', $life_schema->table( 'header' ), $raw ) );
$assert( 6 === $life->listing()['state']->revision, 'corrupção exclusiva restaurada sem apagar história' );
foreach ( array( 'Pessoa fictícia A &amp; B &#65; "C"', 'Motivo literal &amp; &#65; &quot;C&quot;' ) as $literal_text ) {
	$html = esc_html( \Arn\Intranet\Organization\Lifecycle\Page::escapeLiteral( $literal_text ) );
	$assert( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) === $literal_text, 'nome e justificação literais preservados pelo escape WordPress real' );
}
