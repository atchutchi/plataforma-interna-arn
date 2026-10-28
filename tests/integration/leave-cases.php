<?php
/** Leave transactions in disposable request identities and independent tables.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Requests\{CurrentActor, RequestState};
use Arn\Intranet\Leave\Repository;
use Arn\Intranet\Identity\IdentityWpdb;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals -- Exclusive synthetic SQL resources and native sessions.
if ( ! defined( 'ARN_REQUESTS_SQL_HARNESS' ) || ! isset( $repo, $db, $result, $prefix, $auth, $clock, $root, $leave_schema ) || ! \Arn\Intranet\Requests\LocalRoster::validPrefix( $prefix ) ) {
	throw new RuntimeException( 'Fronteira de ensaio ausente.' );
}
$ids        = $result['user_ids'];
$login      = static function ( int $id ) use ( $auth ): void {
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
$key        = static fn(): string=>bin2hex( random_bytes( 16 ) );
$digest     = static fn(): array=>$snapshot( $tables );
$leave_year = (int) gmdate( 'Y', $clock() ) + 1;
$day        = static fn( string $month, int $offset = 0 ): string=>( new DateTimeImmutable( $leave_year . '-' . $month . '-01', new DateTimeZone( 'UTC' ) ) )->modify( 'next monday' )->modify( '+' . $offset . ' days' )->format( 'Y-m-d' );
$before     = $digest();
$assert( $repo->listing( 'team', $leave_year )['items'] === array() && $before === $digest(), 'leitura inicial sem DML' );
$calendar_key = $key();
$calendar     = $repo->saveCalendar( $leave_year, 0, array(), 'Calendário exclusivamente fictício', $calendar_key );
$after        = $digest();
$assert( $repo->saveCalendar( $leave_year, 0, array(), 'Calendário exclusivamente fictício', $calendar_key )->data() === $calendar->data() && $after === $digest(), 'calendário idempotente' );
$deny( static fn()=>$repo->saveCalendar( $leave_year, 0, array( $leave_year . '-12-31' ), 'Outro conteúdo', $calendar_key ) );
$login( $ids['deputy'] );
$deny( static fn()=>$repo->saveCalendar( $leave_year, 1, array(), 'Sem âmbito institucional', $key() ) );
$login( $ids['a'] );
$assert( null === $repo->access( 'team' ), 'funcionário não gere férias' );
$query_count = 0;
$count_query = static function ( string $sql ) use ( &$query_count ): string {
	++$query_count;
	return $sql;
};
add_filter( 'query', $count_query );
$separate_access  = array(
	'own'  => $repo->access( 'own' ),
	'team' => $repo->access( 'team' ),
);
$separate_queries = $query_count;
$query_count      = 0;
$combined_access  = $repo->navigation();
remove_filter( 'query', $count_query );
$assert( $combined_access === $separate_access, 'navegação conjunta conserva autoridade própria e de equipa' );
$assert( $query_count < $separate_queries, 'navegação evita segunda validação SQL duplicada sem cache de autorização' );
$create_key = $key();
$r          = $repo->create( 'LAB-A', $day( '02' ), $day( '02', 4 ), 1, $create_key );
$after      = $digest();
$assert( count( $r->days ) === 5 && $repo->create( 'LAB-A', $day( '02' ), $day( '02', 4 ), 1, $create_key )->snapshot() === $r->snapshot() && $after === $digest(), 'cinco dias e criação idempotente' );
$login( $ids['manager'] );
$assert( null === $repo->detail( $r->id, 'team' ), 'RH não lê rascunho' );
$login( $ids['b'] );
$assert( null === $repo->detail( $r->id, 'own' ), 'outra unidade não lê pedido' );
$deny( static fn()=>$repo->create( 'LAB-A', $day( '02' ), $day( '02', 4 ), 1, $key() ) );
$b = $repo->create( 'LAB-B', $day( '02' ), $day( '02', 4 ), 1, $key() );
$b = $repo->change( $b->id, 1, 'submit', array( 'calendar_revision' => 1 ), $key() );
$login( $ids['deputy'] );
$assert( null === $repo->detail( $b->id, 'team' ), 'RH unidade A não lê B' );
$login( $ids['a'] );
$r       = $repo->change( $r->id, 1, 'submit', array( 'calendar_revision' => 1 ), $key() );
$overlap = $repo->create( 'LAB-A', $day( '02', 2 ), $day( '02', 4 ), 1, $key() );
$deny( static fn()=>$repo->change( $overlap->id, 1, 'submit', array( 'calendar_revision' => 1 ), $key() ) );
$assert( $repo->listing( 'own', $leave_year )['balances'][ $ids['a'] ]['available'] === 22, 'pendentes não reservam saldo' );
$login( $ids['manager'] );
$r = $repo->change( $r->id, 2, 'start', array(), $key() );
foreach ( array( 'audit', 'notifications' )as$part ) {
	$before = $digest();
	$filter = static fn( string $sql ): string=>str_starts_with( $sql, 'INSERT INTO `' . $leave_schema->table( $part ) . '`' ) ? 'INSERT INTO `' . $prefix . 'missing_table` VALUES (1)' : $sql;
	add_filter( 'query', $filter );
	try {
		$deny(
			static fn()=>$repo->change(
				$r->id,
				3,
				'approve',
				array(
					'reason'            => 'Falha transaccional',
					'calendar_revision' => 1,
				),
				$key()
			)
		);
	} finally {
		remove_filter( 'query', $filter );
	}$assert( $before === $digest(), 'falha ' . $part . ' reverte estado e saldo' );}
$approval_key = $key();
$r            = $repo->change(
	$r->id,
	3,
	'approve',
	array(
		'reason'            => 'Aprovação fictícia',
		'calendar_revision' => 1,
	),
	$approval_key
);
$after        = $digest();
$assert(
	$repo->change(
		$r->id,
		3,
		'approve',
		array(
			'reason'            => 'Aprovação fictícia',
			'calendar_revision' => 1,
		),
		$approval_key
	)->snapshot() === $r->snapshot() && $after === $digest(),
	'decisão idempotente sem reserva dupla'
);
$assert( $repo->listing( 'team', $leave_year )['balances'][ $ids['a'] ]['available'] === 17, 'aprovação de cinco consome cinco' );
$r = $repo->change( $r->id, 4, 'cancel', array( 'reason' => 'Reposição futura fictícia' ), $key() );
$assert( $repo->listing( 'team', $leave_year )['balances'][ $ids['a'] ]['available'] === 22, 'cancelamento futuro repõe sem apagar histórico' );
$login( $ids['a'] );
$large = $repo->create( 'LAB-A', $day( '03' ), $day( '03', 25 ), 1, $key() );
$large = $repo->change( $large->id, 1, 'submit', array( 'calendar_revision' => 1 ), $key() );
$small = $repo->create( 'LAB-A', $day( '06' ), $day( '06', 2 ), 1, $key() );
$small = $repo->change( $small->id, 1, 'submit', array( 'calendar_revision' => 1 ), $key() );
$login( $ids['manager'] );
$large  = $repo->change( $large->id, 2, 'start', array(), $key() );
$small  = $repo->change( $small->id, 2, 'start', array(), $key() );
$second = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second->set_prefix( $prefix );
$second->suppress_errors( true );
$second->query( 'SET SESSION innodb_lock_wait_timeout=1' );
$other     = new Repository( $second, new CurrentActor( $second, $roster, $clock ), $clock );
$attempted = false;
$blocked   = false;
$filter    = static function ( string $sql ) use ( $leave_schema, $other, $small, $key, &$attempted, &$blocked ): string {
	if ( ! $attempted && str_starts_with( $sql, 'UPDATE `' . $leave_schema->table( 'requests' ) . '`' ) ) {
		$attempted = true;
		try {
			$other->change(
				$small->id,
				3,
				'approve',
				array(
					'reason'            => 'Concorrente fictício',
					'calendar_revision' => 1,
				),
				$key()
			);
		} catch ( RuntimeException ) {
			$blocked = true;
		}
	}return $sql;
};
add_filter( 'query', $filter );
try {
	$large = $repo->change(
		$large->id,
		3,
		'approve',
		array(
			'reason'            => 'Vinte dias fictícios',
			'calendar_revision' => 1,
		),
		$key()
	);
} finally {
	remove_filter( 'query', $filter );
	$second->close();}
$assert( $attempted && $blocked && count( $large->days ) === 20, 'aprovações concorrentes serializadas' );
$deny(
	static fn()=>$repo->change(
		$small->id,
		3,
		'approve',
		array(
			'reason'            => 'Excede saldo',
			'calendar_revision' => 1,
		),
		$key()
	)
);
$assert( $repo->listing( 'team', $leave_year )['balances'][ $ids['a'] ]['available'] === 2, 'saldo dois após vinte dias' );
$calendar = $repo->saveCalendar( $leave_year, 1, array( $day( '06', 1 ) ), 'Feriado fictício adicional', $key() );
$deny(
	static fn()=>$repo->change(
		$small->id,
		3,
		'approve',
		array(
			'reason'            => 'Calendário alterado',
			'calendar_revision' => 2,
		),
		$key()
	)
);
$small = $repo->change( $small->id, 3, 'return', array( 'reason' => 'Recalcular calendário' ), $key() );
$login( $ids['a'] );
$small = $repo->change( $small->id, 4, 'submit', array( 'calendar_revision' => 2 ), $key() );
$assert( count( $small->days ) === 2, 'resubmissão explícita recalcula dois dias' );
$login( $ids['manager'] );
$small = $repo->change( $small->id, 5, 'start', array(), $key() );
$small = $repo->change(
	$small->id,
	6,
	'approve',
	array(
		'reason'            => 'Completa vinte e dois',
		'calendar_revision' => 2,
	),
	$key()
);
$assert( $repo->listing( 'team', $leave_year )['balances'][ $ids['a'] ]['available'] === 0, 'limite vinte e dois sem excesso' );
$login( $ids['a'] );
$before            = $digest();
$projection_result = $repo->projection( 'search', 'LAB-FER', 1 );
$notices           = $repo->notifications();
$assert( $projection_result['total'] >= 3 && count( $notices ) > 0 && $before === $digest(), 'pesquisa e avisos sem escrita' );
$notice = $notices[0]['id'];
$repo->markRead( $notice );
$assert( null !== $repo->notifications()[0]['read_at'], 'recibo de leitura por destinatário' );
$login( $ids['manager'] );
$own = $repo->create( 'LAB-A', $day( '08' ), $day( '08', 4 ), 2, $key() );
$own = $repo->change( $own->id, 1, 'submit', array( 'calendar_revision' => 2 ), $key() );
$deny( static fn()=>$repo->change( $own->id, 2, 'start', array(), $key() ) );
$before = $digest();
$db->query( $db->prepare( 'UPDATE %i SET payload=%s WHERE id=%d', $leave_schema->table( 'requests' ), '{}', $own->id ) );
$deny( static fn()=>$repo->detail( $own->id, 'own' ) );
$login( $ids['a'] );
$service = new \Arn\Intranet\Authorization\Storage\AuthorizationStorageService( $auth, new \Arn\Intranet\Authorization\Storage\LocalAuthorizationStorageAuthority() );
wp_set_current_user( $admin );
$service->changeState( $ids['a'], \Arn\Intranet\Authorization\AccountState::Suspended, $auth->find( $ids['a'] )->revision, new \Arn\Intranet\Authorization\Storage\ChangeContext( $admin, 'Suspensão fictícia', 'LAB-LEAVE', $clock() ) );
wp_set_current_user( $ids['a'] );
$deny( static fn()=>$repo->access( 'own' ) );
