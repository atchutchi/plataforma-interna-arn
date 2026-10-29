<?php
/** Read-only navigation assertions within the disposable service harnesses.
 *
 * @package ArnIntranet
 */

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.NamingConventions.PrefixAllGlobals -- Exclusive fictional harness only.
if ( ! isset( $repo, $db, $ids, $login, $digest, $assert, $deny, $schema, $roster, $navigation_actor_class ) || ( ! defined( 'ARN_REQUESTS_SQL_HARNESS' ) && ! defined( 'ARN_TICKETS_SQL_HARNESS' ) ) ) {
	throw new RuntimeException( 'Ensaio de navegação fora do harness.' );
}
$navigation_before = $digest();
foreach ( $ids as $navigation_id ) {
	$login( $navigation_id );
	$navigation_queries  = $db->num_queries;
	$navigation_expected = array(
		'own'  => $repo->access( 'own' ),
		'team' => $repo->access( 'team' ),
	);
	$navigation_separate = $db->num_queries - $navigation_queries;
	$navigation_starts   = 0;
	$navigation_commits  = 0;
	$navigation_counter  = static function ( string $sql ) use ( &$navigation_starts, &$navigation_commits ): string {
		$navigation_starts  += 'START TRANSACTION' === $sql ? 1 : 0;
		$navigation_commits += 'COMMIT' === $sql ? 1 : 0;
		return $sql;
	};
	add_filter( 'query', $navigation_counter );
	$navigation_queries = $db->num_queries;
	try {
		$navigation_actual = $repo->navigation();
	} finally {
		remove_filter( 'query', $navigation_counter );
	}
	$assert( $navigation_expected === $navigation_actual, 'mesmos dados e âmbitos nas duas vistas' );
	$assert( 1 === $navigation_starts && 1 === $navigation_commits && $db->num_queries - $navigation_queries < $navigation_separate, 'uma transacção e menos consultas sem cache' );
	if ( $navigation_id === $ids['a'] || $navigation_id === $ids['b'] ) {
		$assert( null === $navigation_actual['team'] && null !== $navigation_actual['own'], 'funcionário sem vista de gestão' );
	}
}
$login( $ids['a'] );
$navigation_cookie = $_COOKIE[ LOGGED_IN_COOKIE ];
unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
try {
	$deny( static fn() => $repo->navigation() );
} finally {
	$_COOKIE[ LOGGED_IN_COOKIE ] = $navigation_cookie;
}
$navigation_now              = time();
$navigation_clock            = static function () use ( &$navigation_now ): int {
	return $navigation_now;
};
$navigation_repository_class = get_class( $repo );
$navigation_expiring         = new $navigation_repository_class( $db, new $navigation_actor_class( $db, $roster, $navigation_clock ), $navigation_clock );
$navigation_expired          = false;
$navigation_expiry           = static function ( string $sql ) use ( $schema, &$navigation_now, &$navigation_expired ): string {
	if ( str_starts_with( $sql, 'SELECT id FROM `' . $schema->table( 'schema' ) . '` WHERE id=1 FOR UPDATE' ) ) {
		$navigation_now    += 7200;
		$navigation_expired = true;
	}
	return $sql;
};
add_filter( 'query', $navigation_expiry );
try {
	$deny( static fn() => $navigation_expiring->navigation() );
} finally {
	remove_filter( 'query', $navigation_expiry );
}
$assert( $navigation_expired && $navigation_before === $digest(), 'sessão expirada antes do commit recusa as vistas e conserva dados' );
$login( $ids['manager'] );
