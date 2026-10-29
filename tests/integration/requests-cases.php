<?php
/** Request lifecycle in the exclusive installer test resources.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Requests\{Repository, CurrentActor, RequestState};
use Arn\Intranet\Identity\IdentityWpdb;

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals -- Exclusive synthetic SQL exercise with native sessions.
if ( ! defined( 'ARN_REQUESTS_SQL_HARNESS' ) || ! isset( $repo, $db, $result, $prefix, $auth, $clock, $root ) || ! \Arn\Intranet\Requests\LocalRoster::validPrefix( $prefix ) ) {
	throw new RuntimeException( 'Fronteira de ensaio ausente.' );
}
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
$assert( array() === $repo->listing( 'team' )['items'] && array() === $repo->listing( 'team' )['types'] && $before === $digest(), 'instalação vazia e leitura sem DML' );
$fields       = array(
	'name'         => 'Pedido fictício de material',
	'unit'         => 'LAB-A',
	'reviewer'     => $ids['manager'],
	'active'       => true,
	'title_label'  => 'Assunto',
	'body_label'   => 'Descrição',
	'instructions' => 'Exemplo fictício &amp; literal.',
	'attachments'  => true,
);
$type_key     = $key();
$request_type = $repo->saveType( 0, 0, $fields, $type_key );
$after        = $digest();
$assert( $repo->saveType( 0, 0, $fields, $type_key )->data() === $request_type->data() && $after === $digest(), 'tipo e recibo idempotentes' );
$deny( static fn() => $repo->saveType( 0, 0, array_replace( $fields, array( 'name' => 'Pedido fictício diferente' ) ), $type_key ) );
$type_b = $repo->saveType(
	0,
	0,
	array_replace(
		$fields,
		array(
			'name' => 'Pedido fictício da unidade B',
			'unit' => 'LAB-B',
		)
	),
	$key()
);
$login( $ids['deputy'] );
$deny( static fn() => $repo->saveType( 0, 0, $fields, $key() ) );
$assert( false === $repo->access( 'team' )['configure'], 'substituto não configura tipos' );
$login( $ids['a'] );
$assert( null === $repo->access( 'team' ), 'funcionário sem gestão' );
$create_key = $key();
$r          = $repo->create( $request_type->id, 1, 'Pedido fictício Alfa &amp; <texto>', 'Texto fictício 😀', $create_key );
$after      = $digest();
$assert( $r->snapshot() === $repo->create( $request_type->id, 1, 'Pedido fictício Alfa &amp; <texto>', 'Texto fictício 😀', $create_key )->snapshot() && $after === $digest(), 'criação idempotente sem duplicados' );
$assert( $r->snapshot() === $repo->detail( $r->id, 'own' )['request']->snapshot(), 'Unicode literal e cadeia reconstruídos' );
$login( $ids['manager'] );
$assert( null === $repo->detail( $r->id, 'team' ) && 0 === $repo->listing( 'team' )['total'], 'rascunho não projectado a RH' );
$login( $ids['b'] );
$assert( null === $repo->detail( $r->id, 'own' ), 'cross-unit recusado' );
$deny( static fn() => $repo->create( $request_type->id, 1, 'Não permitido', 'Corpo', $key() ) );
$b = $repo->create( $type_b->id, 1, 'Pedido B fictício', 'Corpo B', $key() );
$b = $repo->change( $b->id, 1, 'submit', array( 'type_revision' => 1 ), $key() );
$login( $ids['deputy'] );
$assert( null === $repo->detail( $b->id, 'team' ), 'RH unidade A não lê B' );
$login( $ids['manager'] );
$assert( $b->id === $repo->detail( $b->id, 'team' )['request']->id, 'RH institucional lê B sem filiação B' );
$login( $ids['a'] );
$submit_key = $key();
$r          = $repo->change( $r->id, 1, 'submit', array( 'type_revision' => 1 ), $submit_key );
$after      = $digest();
$assert( $r->revision === $repo->change( $r->id, 1, 'submit', array( 'type_revision' => 1 ), $submit_key )->revision && $after === $digest(), 'reenvio da submissão não duplica notificação' );
$deny(
	static fn() => $repo->change(
		$r->id,
		1,
		'edit',
		array(
			'title' => 'Outro',
			'body'  => 'Texto',
		),
		$key()
	)
);
$login( $ids['manager'] );
$assert( 2 === count( $repo->notifications() ), 'avisos das duas submissões ao decisor' );
$r = $repo->change( $r->id, 2, 'start', array(), $key() );
$deny( static fn() => $repo->change( $r->id, 2, 'reject', array( 'reason' => 'Conflito' ), $key() ) );
$r = $repo->change( $r->id, 3, 'return', array( 'reason' => 'Completar descrição' ), $key() );
$login( $ids['a'] );
$r = $repo->change(
	$r->id,
	4,
	'edit',
	array(
		'title' => 'Pedido corrigido',
		'body'  => 'Descrição corrigida',
	),
	$key()
);
$login( $ids['manager'] );
$request_type = $repo->saveType( $request_type->id, 1, array_replace( $fields, array( 'instructions' => 'Nova versão fictícia' ) ), $key() );
$login( $ids['a'] );
$deny( static fn() => $repo->change( $r->id, 5, 'submit', array( 'type_revision' => 1 ), $key() ) );
$r = $repo->change( $r->id, 5, 'submit', array( 'type_revision' => 2 ), $key() );
$assert( 2 === $r->type->revision && 1 === $r->history[1]['after']['type']['revision'], 'tipo corrente capturado sem reescrever primeira submissão' );
$login( $ids['manager'] );
$r = $repo->change(
	$r->id,
	6,
	'substitute',
	array(
		'user'   => $ids['deputy'],
		'starts' => $clock(),
		'ends'   => $clock() + 3600,
		'reason' => 'Substituição fictícia',
	),
	$key()
);
$login( $ids['deputy'] );
$assert( in_array( 'start', $repo->detail( $r->id, 'team' )['operations'], true ), 'substituto com competência pode iniciar' );
$r = $repo->change( $r->id, 7, 'start', array(), $key() );
$login( $ids['manager'] );
$r = $repo->change( $r->id, 8, 'revoke_substitute', array(), $key() );
$login( $ids['deputy'] );
$deny( static fn() => $repo->change( $r->id, 9, 'approve', array( 'reason' => 'Após revogação' ), $key() ) );
$login( $ids['manager'] );
foreach ( array( 'audit', 'notifications' ) as $part ) {
	$before = $digest();
	$filter = static fn( string $sql ): string => str_starts_with( $sql, 'INSERT INTO `' . $schema->table( $part ) . '`' ) ? 'INVALID REQUEST TEST SQL' : $sql;
	add_filter( 'query', $filter );
	try {
		$deny( static fn() => $repo->change( $r->id, 9, 'approve', array( 'reason' => 'Rollback fictício' ), $key() ) );
	} finally {
		remove_filter( 'query', $filter );
	}
	$assert( $before === $digest(), 'decisão, auditoria e notificação atómicas: ' . $part );
}
$second = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
$second->set_prefix( $prefix );
$second->suppress_errors( true );
$second->query( 'SET SESSION innodb_lock_wait_timeout=1' );
$other     = new Repository( $second, new CurrentActor( $second, $roster, $clock ), $clock );
$blocked   = false;
$attempted = false;
$filter    = static function ( string $sql ) use ( $schema, $other, $r, $key, &$blocked, &$attempted ): string {
	if ( ! $attempted && str_starts_with( $sql, 'UPDATE `' . $schema->table( 'requests' ) . '`' ) ) {
		$attempted = true;
		try {
			$other->change( $r->id, 9, 'reject', array( 'reason' => 'Decisão concorrente' ), $key() );
		} catch ( RuntimeException ) {
			$blocked = true;
		}
	}
	return $sql;
};
add_filter( 'query', $filter );
try {
	$r = $repo->change( $r->id, 9, 'approve', array( 'reason' => 'Aprovação fictícia' ), $key() );
} finally {
	remove_filter( 'query', $filter );
	$second->close();
}
$assert( $attempted && $blocked && RequestState::Approved === $r->state, 'duas ligações não confirmam decisões incompatíveis' );
$deny( static fn() => $repo->change( $r->id, 9, 'reject', array( 'reason' => 'Decisão tardia' ), $key() ) );
$r = $repo->change( $r->id, 10, 'cancel', array( 'reason' => 'Cancelamento fictício' ), $key() );
$assert( RequestState::Cancelled === $r->state && 'approved' === $r->history[9]['after']['state'], 'cancelamento conserva aprovação' );
$login( $ids['a'] );
$notices = $repo->notifications();
$assert( count( $notices ) >= 4, 'autor recebe avisos de decisões' );
$repo->markRead( $notices[0]['id'] );
$after = $digest();
$repo->markRead( $notices[0]['id'] );
$assert( $after === $digest() && $repo->notifications()[0]['read'], 'recibo de leitura idempotente' );
$before = $digest();
$assert( 1 === $repo->listing( 'own', 'corrigido', 'cancelled' )['total'] && 0 === $repo->listing( 'own', '', 'draft' )['total'] && $before === $digest(), 'pesquisa e filtros autorizados sem DML' );
$login( $ids['manager'] );
$self_request = $repo->create( $request_type->id, 2, 'Pedido próprio RH', 'Corpo', $key() );
$deny( static fn() => $repo->change( $self_request->id, 1, 'submit', array( 'type_revision' => 2 ), $key() ) );
$login( $ids['a'] );
$withdrawn = $repo->create( $request_type->id, 2, 'Rascunho privado retirado', 'Privado', $key() );
$withdrawn = $repo->change( $withdrawn->id, 1, 'withdraw', array(), $key() );
$login( $ids['manager'] );
$assert( null === $repo->detail( $withdrawn->id, 'team' ), 'retirada não publica rascunho a RH' );
// Alter only the exclusive audit fixture, then restore it byte-for-byte.
$stored = $db->get_var( $db->prepare( 'SELECT payload FROM %i WHERE id=%d', $schema->table( 'requests' ), $r->id ) );
$db->query( $db->prepare( 'UPDATE %i SET payload=%s WHERE id=%d', $schema->table( 'requests' ), '[]', $r->id ) );
try {
	$deny( static fn() => $repo->detail( $r->id, 'team' ) );
} finally {
	$db->query( $db->prepare( 'UPDATE %i SET payload=CONVERT(UNHEX(%s) USING utf8mb4) WHERE id=%d', $schema->table( 'requests' ), bin2hex( $stored ), $r->id ) );
}
$assert( $r->snapshot() === $repo->detail( $r->id, 'team' )['request']->snapshot(), 'leitura só após reposição exacta da fixture' );

// Expire the native session after allocation, before the commit guard.
$login( $ids['a'] );
$boundary_now         = time();
$boundary_clock       = static function () use ( &$boundary_now ): int {
	return $boundary_now;
};
$boundary_repo        = new Repository( $db, new CurrentActor( $db, $roster, $boundary_clock ), $boundary_clock );
$before               = $digest();
$expired_during_write = false;
$filter               = static function ( string $sql ) use ( $schema, &$boundary_now, &$expired_during_write ): string {
	if ( str_starts_with( $sql, 'INSERT INTO `' . $schema->table( 'requests' ) . '`' ) ) {
		$boundary_now        += 7200;
		$expired_during_write = true;
	}
	return $sql;
};
add_filter( 'query', $filter );
try {
	$deny( static fn() => $boundary_repo->create( $request_type->id, 2, 'Sessão expira durante escrita', 'Fictício', $key() ) );
} finally {
	remove_filter( 'query', $filter );
}
$assert( $expired_during_write && $before === $digest(), 'sessão expirada antes de commit reverte pedido e auditoria' );
$login( $ids['a'] );
$before = $digest();
$assert( array() === $repo->projection( 'home' )['items'], 'cancelado e retirado não ocupam resumo' );
$projection_result = $repo->projection( 'search', 'corrigido' );
$assert( array( $r->id ) === array_column( $projection_result['items'], 'id' ) && 'own' === $projection_result['items'][0]['view'], 'pesquisa própria mínima' );
$assert( $before === $digest(), 'resumo e pesquisa não gravam' );
$login( $ids['manager'] );
$before            = $digest();
$projection_result = $repo->projection( 'search' );
$assert( 3 === $projection_result['total'] && ! in_array( $withdrawn->id, array_column( $projection_result['items'], 'id' ), true ), 'união própria e RH não revela rascunho privado retirado' );
$assert( array( $self_request->id ) === array_column( $repo->projection( 'home' )['items'], 'id' ), 'RH conserva o próprio rascunho no painel' );
$assert( $before === $digest(), 'projecção RH não altera recibos' );
