<?php
/** Runs only inside the ownership boundary of tasks-foundation.php.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Tasks\{TaskRepository, TaskContent, TaskState};

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals
if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || ! defined( 'ARN_TASKS_SQL_HARNESS' ) || true !== ARN_TASKS_SQL_HARNESS || ! isset( $db, $actors, $login, $ids, $schema, $owned_users ) || ! \Arn\Intranet\Tasks\LocalRoster::validPrefix( $db->prefix ) || 3 !== count( $owned_users ) ) {
	throw new RuntimeException( 'Fronteira de fixture de tarefas ausente.' );
}
$repo = new TaskRepository( $db, $actors, $clock );
$login( $ids['manager'] );
$content = new TaskContent( 'Parecer sintético de laboratório', 'Texto sem dados pessoais.', 'normal', '2030-01-16T10:00:00Z' );
$request = bin2hex( random_bytes( 16 ) );
$first   = $repo->create( $content, $ids['a'], $request );
$access  = $repo->access( 'team' );
$assert( $ids['manager'] === $access['actor_id'] && true === $access['create'] && array( $ids['a'], $ids['manager'] ) === $access['assignees'], 'equipa só oferece destinatários elegíveis da mesma unidade' );
$dto = $repo->detail( $first->id, 'team' );
$assert( null !== $dto && ! isset( $dto['create_request_id'] ) && isset( $dto['history'][0]['reason'] ), 'projecção usa autoridade corrente dentro da transacção' );
$assert( $first->id > 0 && $first->author_id === $ids['manager'] && $first->assignee_id === $ids['a'] && 'LAB-A' === $first->unit_id, 'criação e âmbito derivados do servidor' );
$again = $repo->create( $content, $ids['a'], $request );
$assert( $again->id === $first->id && 1 === $again->revision && '1' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'tasks' ) ) ), 'reenvio de criação não duplica tarefa' );
$assert( '1' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i', $schema->table( 'audit' ) ) ), 'reenvio sem evento adicional' );
$assert( $first->id === $repo->find( $first->id, 'team' )?->id, 'gestor consulta âmbito' );
$login( $ids['b'] );
$assert( null === $repo->find( $first->id ), 'outra unidade sem leitura própria' );
$assert( array() === $repo->notifications(), 'outra unidade sem avisos da tarefa' );
$login( $ids['a'] );
$assert( $first->id === $repo->find( $first->id )?->id, 'destinatário actual lê tarefa' );
$notices = $repo->notifications();
$assert( 1 === count( $notices ) && null === $notices[0]['read_at'], 'aviso interno só ao destinatário' );
$repo->markRead( $notices[0]['id'] );
$read_at = $repo->notifications()[0]['read_at'];
++$now;
$repo->markRead( $notices[0]['id'] );
$assert( $read_at === $repo->notifications()[0]['read_at'] && 1 === $repo->find( $first->id )?->revision, 'recibo preserva primeiro instante e revisão' );
$started = $repo->change( $first->id, 'start', 1, array( 'reason' => 'Início sintético.' ) );
$assert( TaskState::InProgress === $started->state && 2 === $started->revision, 'transição própria atómica' );
$login( $ids['manager'] );
$same = $repo->change(
	$first->id,
	'change_due',
	2,
	array(
		'due'    => '2030-01-16T10:00:00Z',
		'reason' => '',
	)
);
$assert( 2 === $same->revision && 2 === count( $same->history ), 'no-op sem revisão' );
$changed = $repo->change(
	$first->id,
	'reassign',
	2,
	array(
		'assignee_id' => $ids['manager'],
		'reason'      => 'Reatribuição sintética.',
	)
);
$assert( 3 === $changed->revision && $ids['manager'] === $changed->assignee_id, 'reatribuição na mesma unidade' );
$login( $ids['a'] );
$assert( null === $repo->find( $first->id ) && array() === $repo->notifications(), 'destinatário antigo perde tarefa e avisos' );
$login( $ids['manager'] );
$commented = $repo->change( $first->id, 'comment', 3, array( 'body' => 'Comentário sintético com símbolo 😀.' ) );
$assert( 4 === $commented->revision && 1 === count( $commented->comments ) && $commented->toArray() === $repo->find( $first->id )?->toArray(), 'comentário persistido com reconstrução' );
foreach ( array(
	static fn() => $repo->change( $first->id, 'comment', 3, array( 'body' => 'Reenvio.' ) ),
	static fn() => $repo->change(
		$first->id,
		'reassign',
		4,
		array(
			'assignee_id' => $ids['b'],
			'reason'      => 'Alvo alheio.',
		)
	),
	static fn() => $repo->create( $content, $ids['b'], bin2hex( random_bytes( 16 ) ) ),
	static fn() => $repo->create( new TaskContent( 'Outro pedido', '', 'normal', null ), $ids['a'], $request ),
) as $operation ) {
	try {
		$operation();
		$assert( false, 'operação indevida aceite' );
	} catch ( DomainException | InvalidArgumentException ) {
		$assert( true, 'CAS/alvo/idempotência recusados' ); }
}
$snapshot = static function () use ( $db, $schema ): array {
	$result = array();
	foreach ( array( 'tasks', 'comments', 'audit', 'notifications' ) as $part ) {
		$rows = $db->get_results( $db->prepare( 'SELECT * FROM %i', $schema->table( $part ) ), ARRAY_A );
		if ( ! is_array( $rows ) || '' !== $db->last_error ) {
			throw new RuntimeException( 'Snapshot de tarefas indisponível.' ); }
		$result[ $part ] = array_map( static fn( array $row ): string => hash( 'sha256', json_encode( $row, JSON_THROW_ON_ERROR ) ), $rows );
		sort( $result[ $part ] );
	}
	return $result;
};
foreach ( array( 'audit', 'notifications' ) as $part ) {
	$before  = $snapshot();
	$trigger = $prefix . 'fail_' . $part;
	$assert( false !== $db->query( $db->prepare( 'CREATE TRIGGER %i BEFORE INSERT ON %i FOR EACH ROW SIGNAL SQLSTATE %s SET MESSAGE_TEXT=%s', $trigger, $schema->table( $part ), '45000', 'Falha sintética Tasks' ) ), 'trigger de falha exclusivo' );
	try {
		$faulted = false;
		try {
			$repo->change( $first->id, 'comment', 4, array( 'body' => 'Comentário que deve reverter.' ) );
		} catch ( RuntimeException ) {
			$faulted = true; }
		$assert( $faulted, 'erro SQL convertido em recusa' );
	} finally {
		$assert( false !== $db->query( $db->prepare( 'DROP TRIGGER %i', $trigger ) ), 'trigger removido' ); }
	$assert( $snapshot() === $before, 'rollback integral de tarefa comentário auditoria e último aviso' );
}
$assert( 4 === $repo->find( $first->id )?->revision, 'revisão não avançou após falhas injectadas' );
wp_set_current_user( $admin );
$team_b_grant = $service->createGrant( new \Arn\Intranet\Authorization\Grant( $ids['b'], \Arn\Intranet\Authorization\Role::UnitManager, \Arn\Intranet\Authorization\Scope::units( array( 'LAB-B' ) ), $now, $now + 100 ), $auth->find( $ids['b'] )->revision, $context() );
$login( $ids['b'] );
$other_unit = $repo->create( new TaskContent( 'Tarefa exclusiva de Beta', 'Conteúdo de outra unidade.', 'normal', null ), $ids['b'], bin2hex( random_bytes( 16 ) ) );
wp_set_current_user( $admin );
$service->revokeGrant( $ids['b'], $team_b_grant, $auth->find( $ids['b'] )->revision, $context() );
$login( $ids['b'] );
$assert( $other_unit->id === $repo->find( $other_unit->id )?->id, 'Beta conserva tarefa própria sem a concessão temporária de gestão' );
$login( $ids['a'] );
$assert( null === $repo->find( $other_unit->id ), 'Alfa não lê tarefa existente de Beta' );
try {
	$repo->change( $other_unit->id, 'start', 1, array( 'reason' => '' ) );
	$assert( false, 'Alfa alterou tarefa de Beta' );
} catch ( DomainException ) {
	$assert( true, 'Alfa não altera tarefa existente de Beta' );
}
$login( $ids['manager'] );
$assert( null === $repo->find( $other_unit->id, 'team' ), 'gestor A não recupera tarefa existente em B' );
$page_ids = array();
for ( $number = 1; 21 >= $number; ++$number ) {
	$page_ids[] = $repo->create( new TaskContent( sprintf( 'Paginação sintética %02d', $number ), '', 'normal', null ), $ids['a'], bin2hex( random_bytes( 16 ) ) )->id;
}
$query = static fn( string $page ): \Arn\Intranet\Tasks\TaskSelection => \Arn\Intranet\Tasks\TaskSelection::parse(
	array(
		'q'    => 'Paginação sintética',
		'page' => $page,
	)
);
$one   = $repo->listing( $query( '1' ), 'team' );
$two   = $repo->listing( $query( '2' ), 'team' );
$assert( 21 === $one['total'] && 20 === count( $one['items'] ) && 1 === count( $two['items'] ), 'selecção SQL autorizada pagina vinte mais um' );
$assert( array_merge( array_column( $one['items'], 'id' ), array_column( $two['items'], 'id' ) ) === $page_ids, 'ordem estável sem duplicados entre páginas' );
$assert( array() === $repo->listing( $query( (string) PHP_INT_MAX ), 'team' )['items'], 'página excessiva vazia sem overflow' );
$assert( 0 === $repo->listing( $query( '1' ), 'own' )['total'], 'gestão não transforma tarefas da equipa em próprias' );
$login( $ids['a'] );
$assert( 21 === $repo->listing( $query( '1' ), 'own' )['total'], 'destinatário conta apenas tarefas próprias autorizadas' );
$assert( ! isset( $repo->detail( $page_ids[0], 'own' )['history'][0]['reason'] ), 'destinatário não recebe motivos de gestão na projecção SQL' );
$login( $ids['b'] );
$assert( 0 === $repo->listing( $query( '1' ), 'own' )['total'] && null === $repo->listing( $query( '1' ), 'team' ), 'outra unidade não infere linhas ou contagens' );
$login( $ids['manager'] );
$race = $repo->create( new TaskContent( 'Concorrência sintética', '', 'normal', null ), $ids['a'], bin2hex( random_bytes( 16 ) ) );
$login( $ids['a'] );
++$now;
require_once __DIR__ . '/tasks-concurrency.php';
arn_tasks_concurrency( $db, $roster, $ids['a'], $_COOKIE[ LOGGED_IN_COOKIE ], $race->id, 1, $now, $admin, $assert );
$assert( 2 === $repo->find( $race->id )?->revision && TaskState::InProgress === $repo->find( $race->id )?->state, 'CAS concorrente produziu uma só revisão' );
$assert( '2' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE task_id=%d', $schema->table( 'audit' ), $race->id ) ), 'criação e uma transição, sem evento duplicado' );
$assert( '2' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE task_id=%d', $schema->table( 'notifications' ), $race->id ) ), 'avisos concorrentes únicos' );
