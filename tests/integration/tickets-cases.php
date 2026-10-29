<?php
/** Synthetic tickets within exclusive installer resources.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Support\{Repository, CurrentActor, TicketState};
use Arn\Intranet\Identity\IdentityWpdb;
// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals
if ( ! defined( 'ARN_TICKETS_SQL_HARNESS' ) || ! isset( $repo, $db, $result, $prefix, $auth, $clock, $root ) || ! \Arn\Intranet\Support\LocalRoster::validPrefix( $prefix ) ) {
	throw new RuntimeException( 'Fronteira de ensaio ausente.' ); }
$ids                    = $result['user_ids'];
$login                  = static function ( int $id ) use ( $auth ): void {
	wp_set_current_user( $id );
	$expiry                        = time() + 3600;
	$tokens                        = WP_Session_Tokens::get_instance( $id );
	$token                         = $tokens->create( $expiry );
	$session                       = $tokens->get( $token );
	$session['arn_authz_local']    = true;
	$session['arn_authz_user_id']  = $id;
	$session['arn_authz_revision'] = $auth->find( $id )->revision;
	$tokens->update( $token, $session );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $id, $expiry, 'logged_in', $token );
};
$key                    = static fn(): string => bin2hex( random_bytes( 16 ) );
$digest                 = static fn(): array => $snapshot( $tables );
$navigation_actor_class = CurrentActor::class;
require __DIR__ . '/service-navigation-cases.php';
$before = $digest();
$assert( array() === $repo->listing( 'team' )['items'] && $before === $digest(), 'catálogo inicial vazio, leitura sem escrita' );
$login( $ids['a'] );
$assert( null === $repo->access( 'team' ), 'solicitante sem gestão' );
$create_key = $key();
$t          = $repo->create( 'LAB-A', 'Problema fictício Alfa &amp; <texto>', "Corpo fictício 😀\nLinha dois", 'high', $create_key );
$after      = $digest();
$assert( $t->snapshot() === $repo->create( 'LAB-A', 'Problema fictício Alfa &amp; <texto>', "Corpo fictício 😀\nLinha dois", 'high', $create_key )->snapshot() && $after === $digest(), 'criação e avisos idempotentes' );
$deny( static fn() => $repo->create( 'LAB-A', 'Outro título', 'Corpo', 'high', $create_key ) );
$deny( static fn() => $repo->create( 'LAB-B', 'Unidade indevida', 'Corpo', 'normal', $key() ) );
$assert( $t->snapshot() === $repo->detail( $t->id, 'own' )['ticket']->snapshot(), 'texto literal e histórico reconstruídos' );
$login( $ids['b'] );
$assert( null === $repo->detail( $t->id, 'own' ), 'outro solicitante não lê ticket' );
$b = $repo->create( 'LAB-B', 'Problema Beta', 'Corpo B', 'normal', $key() );
$login( $ids['deputy'] );
$assert( null === $repo->detail( $b->id, 'team' ) && 1 === count( $repo->notifications() ), 'TI unidade A não lê B nem recebe os seus avisos' );
$login( $ids['manager'] );
$assert( $b->id === $repo->detail( $b->id, 'team' )['ticket']->id && 2 === count( $repo->notifications() ), 'TI institucional vê ambas as unidades' );
// Two native technical actors race using two independent SQL connections.
$second = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second->set_prefix( $prefix );
$second->suppress_errors( true );
$second->query( 'SET SESSION innodb_lock_wait_timeout=1' );
$other          = new Repository( $second, new CurrentActor( $second, $roster, $clock ), $clock );
$blocked        = false;
$attempted      = false;
$manager_cookie = $_COOKIE[ LOGGED_IN_COOKIE ];
$login( $ids['deputy'] );
$deputy_cookie = $_COOKIE[ LOGGED_IN_COOKIE ];
wp_set_current_user( $ids['manager'] );
$_COOKIE[ LOGGED_IN_COOKIE ] = $manager_cookie;
$filter                      = static function ( string $sql ) use ( $schema, $other, $t, $ids, $key, $deputy_cookie, $manager_cookie, &$blocked, &$attempted ): string {
	if ( ! $attempted && str_starts_with( $sql, 'UPDATE `' . $schema->table( 'tickets' ) . '`' ) ) {
		$attempted = true;
		wp_set_current_user( $ids['deputy'] );
		$_COOKIE[ LOGGED_IN_COOKIE ] = $deputy_cookie;
		try {
			$other->change( $t->id, 1, 'claim', array(), $key() );
		} catch ( RuntimeException ) {
			$blocked = true; } finally {
			wp_set_current_user( $ids['manager'] );
			$_COOKIE[ LOGGED_IN_COOKIE ] = $manager_cookie; }
	}
	return $sql;
};
add_filter( 'query', $filter );
try {
	$t = $repo->change( $t->id, 1, 'claim', array(), $key() );
} finally {
	remove_filter( 'query', $filter );
	$second->close(); }
$assert( $attempted && $blocked && $ids['manager'] === $t->assigned, 'apenas um técnico assume na concorrência' );
$login( $ids['deputy'] );
$deny( static fn() => $repo->change( $t->id, 1, 'claim', array(), $key() ) );
$deny( static fn() => $repo->change( $t->id, 2, 'reply', array( 'message' => 'Não sou responsável' ), $key() ) );
$login( $ids['a'] );
$reply_key = $key();
$t         = $repo->change( $t->id, 2, 'reply', array( 'message' => "Detalhes\nmais informação" ), $reply_key );
$after     = $digest();
$assert( $t->revision === $repo->change( $t->id, 2, 'reply', array( 'message' => "Detalhes\nmais informação" ), $reply_key )->revision && $after === $digest(), 'reenvio não duplica resposta nem aviso' );
$login( $ids['manager'] );
foreach ( array( 'audit', 'notifications' ) as $part ) {
	$before = $digest();
	$filter = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $schema->table( $part ) . '`' ) ? 'INVALID TICKET TEST SQL' : $sql;
	add_filter( 'query', $filter );
	try {
		$deny( static fn() => $repo->change( $t->id, 3, 'resolve', array( 'message' => 'Solução com rollback' ), $key() ) );
	} finally {
		remove_filter( 'query', $filter ); }
	$assert( $before === $digest(), 'ticket, conversa, recibo e aviso atómicos: ' . $part );
}
$t = $repo->change( $t->id, 3, 'resolve', array( 'message' => 'Solução fictícia validada' ), $key() );
$assert( TicketState::Resolved === $t->state && 2 === count( $t->messages ), 'resolução com solução obrigatória e conversa conservada' );
$deny( static fn() => $repo->change( $t->id, 4, 'reopen', array(), $key() ) );
$own_ticket = $repo->create( 'LAB-A', 'Ticket próprio do técnico', 'Corpo', 'low', $key() );
$deny( static fn() => $repo->change( $own_ticket->id, 1, 'claim', array(), $key() ) );
$login( $ids['a'] );
$notices = $repo->notifications();
$assert( 2 === count( $notices ), 'solicitante recebe assunção e resolução' );
$repo->markRead( $notices[0]['id'] );
$after = $digest();
$repo->markRead( $notices[0]['id'] );
$assert( $after === $digest() && $repo->notifications()[0]['read'], 'leitura idempotente' );
$before = $digest();
$assert( 1 === $repo->listing( 'own', 'Alfa', 'resolved', 1, 'high' )['total'] && 0 === $repo->listing( 'own', '', '', 1, 'low' )['total'] && $before === $digest(), 'filtros autorizados sem escrita' );
$stored = $db->get_var( $db->prepare( 'SELECT payload FROM %i WHERE id=%d', $schema->table( 'tickets' ), $t->id ) );
$db->query( $db->prepare( 'UPDATE %i SET payload=%s WHERE id=%d', $schema->table( 'tickets' ), '[]', $t->id ) );
try {
	$deny( static fn() => $repo->detail( $t->id, 'own' ) );
} finally {
	$db->query( $db->prepare( 'UPDATE %i SET payload=CONVERT(UNHEX(%s) USING utf8mb4) WHERE id=%d', $schema->table( 'tickets' ), bin2hex( $stored ), $t->id ) ); }
$assert( $t->snapshot() === $repo->detail( $t->id, 'own' )['ticket']->snapshot(), 'integridade obrigatória para ler' );
$boundary_now   = time();
$boundary_clock = static function () use ( &$boundary_now ): int {
	return $boundary_now;
};
$boundary_repo  = new Repository( $db, new CurrentActor( $db, $roster, $boundary_clock ), $boundary_clock );
$before         = $digest();
$expired        = false;
$filter         = static function ( string $sql ) use ( $schema, &$boundary_now, &$expired ): string {
	if ( str_starts_with( $sql, 'INSERT INTO `' . $schema->table( 'tickets' ) . '`' ) ) {
		$boundary_now += 7200;
		$expired       = true;
	} return $sql;
};
add_filter( 'query', $filter );
try {
	$deny( static fn() => $boundary_repo->create( 'LAB-A', 'Sessão expirada durante escrita', 'Corpo', 'normal', $key() ) );
} finally {
	remove_filter( 'query', $filter ); }
$assert( $expired && $before === $digest(), 'expiração antes do commit reverte tudo' );
$login( $ids['a'] );
$before = $digest();
$assert( array() === $repo->projection( 'home' )['items'], 'ticket resolvido não ocupa resumo pendente' );
$projection_result = $repo->projection( 'search', 'Alfa' );
$assert( array( $t->id ) === array_column( $projection_result['items'], 'id' ) && 'own' === $projection_result['items'][0]['view'], 'pesquisa própria sem duplicados ou outra conta' );
$assert( $before === $digest(), 'projecções não escrevem recibos ou negócio' );
$login( $ids['manager'] );
$before            = $digest();
$projection_result = $repo->projection( 'search' );
$assert( 3 === $projection_result['total'] && 3 === count( array_unique( array_column( $projection_result['items'], 'id' ) ) ), 'união TI e próprios sem duplicação' );
$assert( array( $own_ticket->id ) === array_column( $repo->projection( 'home' )['items'], 'id' ), 'painel técnico só resume próprios' );
$assert( $before === $digest(), 'composição técnica só de leitura' );
$login( $ids['b'] );
$assert( array( $b->id ) === array_column( $repo->projection( 'search' )['items'], 'id' ), 'contagem não revela outra unidade' );
