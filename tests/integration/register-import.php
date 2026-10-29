<?php
/** Isolated register integration with synthetic authority providers and real SQL.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Register\{Schema, Repository, Authority, Confirmation, Catalog, LocalAccess, Page};
use Arn\Intranet\Organization\Storage\{WpdbLocalUnitRepository, LocalUnitContext};
use Arn\Intranet\Authorization\{AccountState, Grant, Role, Scope};
use Arn\Intranet\Authorization\Storage\{WpdbAuthorizationRepository, ChangeContext};
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped, Squiz.Commenting, WordPress.WP.AlternativeFunctions.json_encode_json_encode
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'Ensaio exclusivo do laboratório.' ); }
$prefix = 'arn_regtest_' . bin2hex( random_bytes( 5 ) ) . '_';
$db     = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$db->set_prefix( $prefix );
$second = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second->set_prefix( $prefix );
$db->suppress_errors( true );
$second->suppress_errors( true );
$checks       = 0;
$assert       = static function ( bool $ok, string $label ) use ( &$checks ): void {
	if ( ! $ok ) {
		throw new RuntimeException( 'Falhou cadastro: ' . $label ); }
	++$checks;
};
$deny         = static function ( callable $operation ) use ( $assert ): void {
	try {
		$operation();
	} catch ( RuntimeException ) {
		$assert( true, 'recusa' );
		return;
	}
	$assert( false, 'operação deveria ser recusada' );
};
$actor        = 1001;
$session      = array(
	'arn_authz_local'    => true,
	'arn_authz_user_id'  => 1001,
	'arn_authz_revision' => 3,
);
$token        = str_repeat( 'synthetic-session-', 3 );
$secret       = new Confirmation( bin2hex( random_bytes( 32 ) ) );
$now          = time();
$make         = static function ( IdentityWpdb $connection ) use ( &$actor, &$session, &$token, &$now, $secret ): Repository {
	$authority = new Authority(
		$connection,
		static function () use ( &$actor ): int {
			return $actor;
		},
		static function ( int $id ) use ( &$session ): array {
			return 1001 === $id ? $session : array();
		},
		static function () use ( &$token ): string {
			return $token;
		},
		static function () use ( &$now ): int {
			return $now;
		}
	);
	return new Repository(
		$connection,
		$authority,
		$secret,
		static function () use ( &$now ): int {
			return $now;
		}
	);
};
$filter       = null;
$header       = "professional_id,name,email,unit_id\n";
$csv          = $header . "LAB-PER-001,Pessoa fictícia Alfa,alfa@example.invalid,LAB-A\nLAB-PER-002,Pessoa fictícia Beta,beta@example.invalid,LAB-B\n";
$context      = new ChangeContext( 1002, 'Ensaio sintético exclusivo', 'test/register', $now );
$unit_context = new LocalUnitContext( 1002, 'Ensaio sintético exclusivo', $now );
$digest       = static function () use ( $db, $prefix ): string {
	$all = array();
	foreach ( array( 'header', 'batches', 'audit' ) as $suffix ) {
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i ORDER BY id', $prefix . 'arn_reg_' . $suffix ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $db->last_error ) {
			throw new RuntimeException( 'Snapshot indisponível.' ); }
		$all[ $suffix ] = $rows;
	}
	return hash( 'sha256', json_encode( $all, JSON_THROW_ON_ERROR ) );
};
try {
	$assert( ! LocalAccess::allowed(), 'nenhuma autoridade de negócio na sessão técnica CLI' );
	$auth = new WpdbAuthorizationRepository( $db );
	$auth->installSchema();
	$auth->createAccount( $actor, $context );
	$auth->changeState( $actor, AccountState::Active, 1, $context );
	$auth->createGrant( new Grant( $actor, Role::HumanResources, Scope::institutional(), 0, null ), 2, $context );
	$units = new WpdbLocalUnitRepository( $db );
	$units->installSchema();
	$units->apply( 'initialize', 0, $unit_context );
	$schema = new Schema( $db );
	$deny( static fn() => $make( $db )->listing() );
	$assert( '0' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $schema->table( 'header' ) ) ), 'leitura não instala schema ausente' );
	$schema->install();
	$repo     = $make( $db );
	$other    = $make( $second );
	$before   = $digest();
	$proposal = $repo->preview( $csv );
	$assert( $proposal['report']->valid && 2 === $proposal['report']->totals['create'], 'criações propostas' );
	$assert( $before === $digest(), 'preview sem escrita' );
	$assert( 0 === $repo->listing()['catalog']->revision && $before === $digest(), 'GET sem escrita' );
	$invalid = $repo->preview( str_replace( 'example.invalid', 'arn.gw', $csv ) );
	$assert( ! $invalid['report']->valid && '' === $invalid['proof'] && $before === $digest(), 'dados reais recusados' );
	$deny( static fn() => $repo->apply( str_replace( 'Alfa', 'Outra', $csv ), $proposal['proof'] ) );
	$r = $repo->apply( $csv, $proposal['proof'] );
	$assert( 1 === $r['revision'] && 2 === $r['totals']['create'], 'commit de criação' );
	$after = $digest();
	$assert( $other->apply( $csv, $proposal['proof'] ) === $r && $after === $digest(), 'reenvio idempotente noutra ligação' );
	$assert( count( $repo->listing()['history'] ) === 1, 'um recibo e auditoria' );
	$schema->install();
	$assert( $after === $digest(), 'reinstalação preserva dados' );
	$update = $header . "LAB-PER-001,Pessoa fictícia Alfa revista,alfa@example.invalid,LAB-A\n";
	$p      = $repo->preview( $update );
	$assert( 1 === $p['report']->totals['update'], 'actualização por identificador' );
	$other_csv = $header . "LAB-PER-003,Pessoa fictícia Gama,gama@example.invalid,LAB-A\n";
	$stale     = $other->preview( $other_csv );
	$repo->apply( $update, $p['proof'] );
	$assert( count( $repo->listing()['catalog']->people ) === 2, 'ausência preserva pessoa' );
	$deny( static fn() => $other->apply( $other_csv, $stale['proof'] ) );
	$assert( $r === $repo->apply( $csv, $proposal['proof'] ), 'recibo antigo não repõe estado anterior' );
	$assert( 'Pessoa fictícia Alfa revista' === $repo->listing()['catalog']->people['LAB-PER-001']['name'], 'reenvio não reverte' );
	$p = $repo->preview( $other_csv );
	$units->apply( 'rename-a', 1, $unit_context );
	$deny( static fn() => $repo->apply( $other_csv, $p['proof'] ) );
	$assert( 2 === $repo->listing()['catalog']->revision, 'unidades mudadas impedem confirmação' );
	$p = $repo->preview( $other_csv );
	foreach ( array( 'batches', 'audit' ) as $suffix ) {
		$before = $digest();
		$filter = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $prefix . 'arn_reg_' . $suffix . '`' ) ? 'INVALID REGISTER TEST SQL' : $sql;
		add_filter( 'query', $filter );
		try {
			$deny( static fn() => $repo->apply( $other_csv, $p['proof'] ) );
		} finally {
			remove_filter( 'query', $filter );
			$filter = null; }
		$assert( $before === $digest(), 'rollback integral de ' . $suffix );
	}
	$before    = $digest();
	$old_token = $token;
	$filter    = static function ( string $sql ) use ( $prefix, &$token ): string {
		if ( str_starts_with( $sql, 'INSERT INTO `' . $prefix . 'arn_reg_audit`' ) ) {
			$token = ''; }
		return $sql;
	};
	add_filter( 'query', $filter );
	try {
		$deny( static fn() => $repo->apply( $other_csv, $p['proof'] ) );
	} finally {
		remove_filter( 'query', $filter );
		$filter = null;
		$token  = $old_token;
	}
	$assert( $before === $digest(), 'sessão perdida antes do commit desfaz todo o lote' );
	$assert( false !== $second->query( 'SET SESSION innodb_lock_wait_timeout = 1' ), 'timeout de concorrência só na ligação de teste' );
	$attempted     = false;
	$blocked       = false;
	$units_blocked = false;
	$other_units   = new WpdbLocalUnitRepository( $second );
	$filter        = static function ( string $sql ) use ( $prefix, $other, $other_csv, $p, $other_units, $unit_context, &$attempted, &$blocked, &$units_blocked ): string {
		if ( ! $attempted && str_starts_with( $sql, 'UPDATE `' . $prefix . 'arn_reg_header`' ) ) {
			$attempted = true;
			try {
				$other->apply( $other_csv, $p['proof'] );
			} catch ( RuntimeException ) {
				$blocked = true; }
			try {
				$other_units->apply( 'move-b-to-a', 2, $unit_context );
			} catch ( RuntimeException ) {
				$units_blocked = true; }
		}
		return $sql;
	};
	add_filter( 'query', $filter );
	try {
		$won = $repo->apply( $other_csv, $p['proof'] );
	} finally {
		remove_filter( 'query', $filter );
		$filter = null; }
	$assert( $attempted && $blocked, 'segunda transacção impedida enquanto primeiro mantém bloqueios' );
	$assert( $units_blocked && 2 === $units->snapshot()->revision, 'alteração de estrutura independente impedida pelo bloqueio das unidades' );
	$assert( $won === $other->apply( $other_csv, $p['proof'] ) && 3 === $repo->listing()['catalog']->revision, 'retoma concorrente devolve recibo único' );
	$auth->changeState( $actor, AccountState::Suspended, 3, $context );
	$deny( static fn() => $repo->listing() );
	$deny( static fn() => $repo->apply( $other_csv, $p['proof'] ) );
	$auth->changeState( $actor, AccountState::Active, 4, $context );
	$deny( static fn() => $repo->listing() );
	$session['arn_authz_revision'] = 5;
	$assert( 3 === $repo->listing()['catalog']->revision, 'nova geração de sessão necessária' );
	$before = $digest();
	$now   += 601;
	$deny( static fn() => $repo->apply( $other_csv, $p['proof'] ) );
	$assert( $before === $digest(), 'prova expirada sem alteração' );
	$last = $db->get_var( $db->prepare( 'SELECT payload FROM %i WHERE id = 1', $schema->table( 'header' ) ) );
	$db->query( $db->prepare( 'UPDATE %i SET payload = %s WHERE id = 1', $schema->table( 'header' ), '[]' ) );
	$deny( static fn() => $repo->listing() );
	$deny( static fn() => $repo->preview( $csv ) );
	$db->query( $db->prepare( 'UPDATE %i SET payload = CONVERT(UNHEX(%s) USING utf8mb4) WHERE id = 1', $schema->table( 'header' ), bin2hex( $last ) ) );
	$assert( $before === $digest(), 'restauro exclusivo da corrupção injectada' );
	$assert( 3 === count( $repo->listing()['history'] ), 'histórico completo e recibos consistentes' );
	$literal = "Pessoa fictícia A &amp; B &#65; \"C\" 'D'";
	$assert( html_entity_decode( esc_attr( Page::escapeLiteral( $literal ) ), ENT_QUOTES, 'UTF-8' ) === $literal, 'entidades e aspas conservadas pelo escape WordPress real' );
	require __DIR__ . '/lifecycle-cases.php';
} finally {
	if ( null !== $filter ) {
		remove_filter( 'query', $filter ); }
	$db->query( 'ROLLBACK' );
	$second->query( 'ROLLBACK' );
	$clean = true;
	foreach ( array( 'arn_lifecycle_audit', 'arn_lifecycle_batches', 'arn_lifecycle_header', 'arn_reg_audit', 'arn_reg_batches', 'arn_reg_header', 'arn_org_audit', 'arn_org_units', 'arn_org_header', 'arn_authz_audit', 'arn_authz_grants', 'arn_authz_accounts', 'arn_authz_schema' ) as $suffix ) {
		$clean = false !== $second->query( $second->prepare( 'DROP TABLE IF EXISTS %i', $prefix . $suffix ) ) && $clean; }
	$db->close();
	$second->close();
	$assert( $clean, 'limpeza apenas das dez tabelas exclusivas' );
}
WP_CLI::success( 'Cadastro fictício: ' . $checks . ' verificações SQL.' );
