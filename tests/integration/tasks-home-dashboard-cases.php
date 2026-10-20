<?php
/** Home reads inside the existing exclusive task fixture boundary.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\EditorialPreview\HomeDashboard;
use Arn\Intranet\Tasks\{TaskContent, TaskSelection};

// phpcs:disable Squiz.Commenting, WordPress.DB, WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput, WordPress.NamingConventions.PrefixAllGlobals
if ( ! defined( 'ARN_TASKS_SQL_HARNESS' ) || true !== ARN_TASKS_SQL_HARNESS || ! isset( $repo, $db, $schema, $ids, $page_ids, $owned_users, $snapshot ) || ! \Arn\Intranet\Tasks\LocalRoster::validPrefix( $db->prefix ) || 3 !== count( $owned_users ) ) {
	throw new RuntimeException( 'Fronteira exclusiva do painel de tarefas ausente.' );
}
$login( $ids['manager'] );
foreach ( $page_ids as $closed_id ) {
	$repo->change( $closed_id, 'cancel', 1, array( 'reason' => 'Encerramento sintético antes do ensaio do painel.' ) );
}
$home_tasks = array();
foreach ( array( '09', '10', '11', '07', '08' ) as $hour ) {
	$home_tasks[] = $repo->create( new TaskContent( 'Painel pessoal fictício ' . $hour, 'Descrição privada do painel.', 'high', '2030-01-15T' . $hour . ':00:00Z' ), $ids['a'], bin2hex( random_bytes( 16 ) ) );
}
$repo->change( $home_tasks[4]->id, 'cancel', 1, array( 'reason' => 'Motivo privado de cancelamento do painel.' ) );
$login( $ids['a'] );
$repo->change( $home_tasks[1]->id, 'start', 1, array( 'reason' => '' ) );
$repo->change( $home_tasks[3]->id, 'start', 1, array( 'reason' => '' ) );
$repo->change( $home_tasks[3]->id, 'complete', 2, array( 'reason' => 'Conclusão fictícia do painel.' ) );
$home              = new HomeDashboard( static fn( string $module ): ?array => 'tasks_own' === $module ? $repo->listing( TaskSelection::pending(), 'own' ) : null );
$home_destinations = array(
	array(
		'id'  => 'tasks_own',
		'url' => '/minhas-tarefas/',
	),
);
$before_home       = $snapshot();
$personal          = $home->model( $home_destinations )['tasks_own'];
$assert( $personal['available'] && array( 'Painel pessoal fictício 09', 'Painel pessoal fictício 10', 'Painel pessoal fictício 11' ) === array_column( $personal['items'], 'title' ), 'painel pessoal limita a três pendentes por prazo, depois de excluir terminais antes da paginação' );
$assert( array( 'Por iniciar', 'Em curso', 'Por iniciar' ) === array_column( $personal['items'], 'state' ), 'painel conserva o estado corrente das próprias tarefas' );
$assert( true === $personal['items'][0]['overdue'] && str_ends_with( $personal['items'][0]['date'], ' UTC' ), 'painel identifica atraso e UTC sem mudar estado' );
$assert( ! str_contains( wp_json_encode( $personal ), 'privad' ) && ! isset( $personal['total'] ), 'painel sem descrição, motivos, comentários ou contagens' );
$assert( $snapshot() === $before_home, 'leitura do painel conserva tarefas, comentários, auditoria e recibos' );
$login( $ids['manager'] );
$manager_home = $home->model( $home_destinations )['tasks_own'];
$assert( array( $first->content->title ) === array_column( $manager_home['items'], 'title' ), 'gestão de equipa não transforma o painel pessoal em lista de terceiros' );
$login( $ids['b'] );
$beta_home = $home->model( $home_destinations )['tasks_own'];
$assert( array( $other_unit->content->title ) === array_column( $beta_home['items'], 'title' ), 'painel B conserva apenas a tarefa própria autorizada' );
$login( $ids['manager'] );
$repo->change(
	$home_tasks[0]->id,
	'reassign',
	1,
	array(
		'assignee_id' => $ids['manager'],
		'reason'      => 'Reatribuição privada do painel.',
	)
);
$login( $ids['a'] );
$assert( ! in_array( 'Painel pessoal fictício 09', array_column( $home->model( $home_destinations )['tasks_own']['items'], 'title' ), true ), 'reatribuição retira tarefa do antigo painel pessoal' );
$home_notices   = new \Arn\Intranet\EditorialPreview\HomeNotifications(
	static function ( string $module ) use ( $repo ): ?array {
		if ( 'tasks_own' !== $module ) {
			return null; }
		return array(
			'url'   => '/minhas-tarefas/?notices=1',
			'items' => array_map(
				static fn( array $row ): array => $row + array(
					'url'   => '/minhas-tarefas/?entry=' . $row['task_id'],
					'event' => \Arn\Intranet\Tasks\View::NOTICES[ $row['kind'] ],
				),
				array_slice( $repo->notifications(), 0, 20 )
			),
		);
	}
);
$before_notices = $snapshot();
$notice_model   = $home_notices->model();
$assert( count( $notice_model['items'] ) > 0 && count( $notice_model['items'] ) <= 5, 'resumo limita os avisos próprios depois da autorização SQL' );
$assert( ! in_array( 'Painel pessoal fictício 09', array_column( $notice_model['items'], 'title' ), true ), 'reatribuição retira também avisos do antigo destinatário' );
$assert( $before_notices === $snapshot(), 'resumo não altera tarefas, auditoria, comentários ou recibos' );
$first_notice = $repo->notifications()[0];
$repo->markRead( $first_notice['id'] );
$assert( true === $home_notices->model()['items'][0]['read'], 'resumo reflecte recibo criado pelo percurso nativo' );
$after_receipt = $snapshot();
$repo->markRead( $first_notice['id'] );
$assert( $after_receipt === $snapshot(), 'repetir recibo nativo é idempotente' );
$login( $ids['b'] );
$assert( ! array_filter( $home_notices->model()['items'], static fn( array $row ): bool => str_starts_with( $row['title'], 'Painel pessoal fictício' ) ), 'resumo de B não projecta avisos das tarefas A' );
wp_set_current_user( $admin );
$service->changeState( $ids['a'], \Arn\Intranet\Authorization\AccountState::Suspended, $auth->find( $ids['a'] )->revision, $context() );
wp_set_current_user( $ids['a'] );
$assert( ! $home->model( $home_destinations )['tasks_own']['available'], 'suspensão entre navegação e fonte fecha o painel pessoal' );
$assert( array() === $home_notices->model()['items'], 'suspensão fecha também os avisos SQL do painel' );
wp_set_current_user( $admin );
$assert( ! $home->model( $home_destinations )['tasks_own']['available'], 'administrador técnico não recebe excepção no painel pessoal' );
