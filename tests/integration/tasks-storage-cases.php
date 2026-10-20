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
$race = $repo->create( new TaskContent( 'Concorrência sintética', '', 'normal', null ), $ids['a'], bin2hex( random_bytes( 16 ) ) );
$login( $ids['a'] );
++$now;
require_once __DIR__ . '/tasks-concurrency.php';
arn_tasks_concurrency( $db, $roster, $ids['a'], $_COOKIE[ LOGGED_IN_COOKIE ], $race->id, 1, $now, $admin, $assert );
$assert( 2 === $repo->find( $race->id )?->revision && TaskState::InProgress === $repo->find( $race->id )?->state, 'CAS concorrente produziu uma só revisão' );
$assert( '2' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE task_id=%d', $schema->table( 'audit' ), $race->id ) ), 'criação e uma transição, sem evento duplicado' );
$assert( '2' === (string) $db->get_var( $db->prepare( 'SELECT COUNT(*) FROM %i WHERE task_id=%d', $schema->table( 'notifications' ), $race->id ) ), 'avisos concorrentes únicos' );
