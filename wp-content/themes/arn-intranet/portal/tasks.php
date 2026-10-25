<?php
/** Private task lists, detail, forms and notices from authorised DTOs.
 *
 * @package ArnIntranet
 */

use Arn\Intranet\Tasks\View;

if ( ! defined( 'ABSPATH' ) || ! isset( $args['tasks'] ) ) {
	return;
}
$tasks       = $args['tasks'];
$team        = 'team' === $tasks['view'];
$screen      = $tasks['screen'];
$data        = $tasks['data'];
$selection   = $tasks['query']['selection'];
$tasks_title = $team ? 'Tarefas da equipa' : 'Minhas tarefas';
$base        = get_permalink();
$filters     = array_intersect_key( $tasks['query']['get'], array_flip( array( 'q', 'state', 'assignee', 'arn_tasks_page' ) ) );
$back        = add_query_arg( $filters, $base );
$detail_url  = static fn( int $id ): string => add_query_arg( $filters + array( 'entry' => (string) $id ), $base );
$assignees   = array_column( $tasks['access']['assignees'] ?? array(), 'label', 'id' );
?>
<section class="arn-tasks alignwide">
<nav class="arn-task-breadcrumb" aria-label="Localização"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a><span aria-hidden="true">/</span><a href="<?php echo esc_url( $back ); ?>"><?php echo esc_html( $tasks_title ); ?></a>
<?php
if ( 'list' !== $screen ) :
	?>
	<span aria-hidden="true">/</span><span aria-current="page">
	<?php
	echo esc_html(
		match ( $screen ) {
			'detail' => 'Detalhe',
			'create' => 'Nova tarefa',
			default => 'Avisos' }
	);
	?>
	</span><?php endif; ?></nav>
<?php \Arn\Intranet\Media\ConsumerPage::link(); ?>
<header class="arn-task-heading"><h1>
<?php
echo esc_html(
	match ( $screen ) {
	'detail' => $data['title'],
	'create' => 'Atribuir nova tarefa',
	'notices' => 'Avisos das minhas tarefas',
	default => $tasks_title }
);
?>
</h1>
<?php
if ( 'list' === $screen ) :
	?>
	<p><?php echo esc_html( $team ? 'Acompanha o trabalho atribuído na tua unidade e consulta o histórico de cada tarefa.' : 'Consulta o trabalho que te está atribuído, actualiza o estado e partilha o progresso.' ); ?></p><?php endif; ?>
<p class="arn-task-local">Laboratório local com contas e tarefas fictícias.</p>
<nav class="arn-task-actions" aria-label="Vistas de tarefas"><a href="<?php echo esc_url( $base ); ?>">Ver tarefas</a>
<?php
if ( ! $team ) :
	?>
	<a href="<?php echo esc_url( add_query_arg( 'notices', '1', $base ) ); ?>">Avisos internos</a>
	<?php
elseif ( $tasks['access']['create'] ) :
	?>
	<a class="arn-task-primary-link" href="<?php echo esc_url( add_query_arg( 'new', '1', $base ) ); ?>">Atribuir tarefa</a><?php endif; ?></nav></header>

<?php if ( $tasks['errors'] ) : ?>
<div class="arn-task-errors" id="arn-task-errors" role="alert" tabindex="-1"><h2>Não foi possível guardar</h2>
	<?php
	foreach ( $tasks['errors'] as $key => $task_error ) :
		?>
	<p>
		<?php
		if ( 'form' === $key ) :
			?>
			<?php echo esc_html( $task_error ); ?>
			<?php
	else :
		?>
	<a href="#<?php echo esc_attr( 'arn-task-' . $tasks['input']['operation'] . '-' . $key ); ?>"><?php echo esc_html( $task_error ); ?></a><?php endif; ?></p><?php endforeach; ?>
	<?php
	if ( 'detail' === $screen ) :
		?>
	<p><a href="<?php echo esc_url( $detail_url( $data['id'] ) ); ?>">Recarregar a tarefa</a></p><?php endif; ?></div>
	<?php
elseif ( isset( $tasks['query']['get']['saved'] ) ) :
	?>
	<p class="arn-task-saved" role="status">Alteração guardada.</p><?php endif; ?>

<?php View::unapplied( $tasks ); ?>
<?php if ( 'create' === $screen ) : ?>
<form class="arn-task-form" data-task-form method="post" action="<?php echo esc_url( add_query_arg( 'new', '1', $base ) ); ?>"><fieldset><legend>Dados da tarefa</legend>
	<?php
	View::hidden( $tasks, 'create' );
	View::field( $tasks, 'create', 'title', 'Título', '', 160, true );
	View::field( $tasks, 'create', 'description', 'Descrição (opcional)', '', 8000, false, true );
	View::select( $tasks, 'create', 'assignee_id', 'Atribuir a', $assignees, '' );
	View::select( $tasks, 'create', 'priority', 'Prioridade', View::PRIORITIES, 'normal' );
	View::field( $tasks, 'create', 'due', 'Prazo (opcional)', '', 20, false, false, 'Data e hora UTC, por exemplo 2030-01-15T10:00:00Z. Deixa em branco se não houver prazo.' );
	?>
<button type="submit">Atribuir tarefa</button></fieldset></form>

<?php elseif ( 'detail' === $screen ) : ?>
<div class="arn-task-detail-grid"><article class="arn-task-detail"><div class="arn-task-meta"><span class="arn-task-state"><?php echo esc_html( View::STATES[ $data['state'] ] ); ?></span>
	<?php
	if ( $data['overdue'] ) :
		?>
	<strong class="arn-task-overdue">Prazo ultrapassado</strong><?php endif; ?><span>Prioridade <?php echo esc_html( strtolower( View::PRIORITIES[ $data['priority'] ] ) ); ?></span></div>
<dl class="arn-task-facts"><dt>Responsável</dt><dd><?php echo esc_html( $data['assignee'] ); ?></dd><dt>Atribuída por</dt><dd><?php echo esc_html( $data['author'] ); ?></dd><dt>Unidade</dt><dd><?php echo esc_html( $data['unit_id'] ); ?></dd><dt>Prazo</dt><dd><?php echo esc_html( View::date( $data['due_at'] ) ); ?></dd><dt>Última alteração</dt><dd><?php echo esc_html( View::date( $data['updated_at'] ) ); ?></dd></dl>
	<?php
	if ( '' !== $data['description'] ) :
		?>
		<div class="arn-task-prose"><?php echo nl2br( esc_html( $data['description'] ) ); ?></div><?php endif; ?>
	<?php
	foreach ( array(
		'start'    => 'Iniciar tarefa',
		'complete' => 'Concluir tarefa',
	) as $operation => $label ) :
		?>
		<?php
		if ( in_array( $operation, $data['operations'], true ) ) :
			?>
	<form class="arn-task-form arn-task-progress" data-task-form method="post" action="<?php echo esc_url( $detail_url( $data['id'] ) ); ?>">
			<?php
			View::hidden( $tasks, $operation, $data['id'] );
			View::field( $tasks, $operation, 'reason', 'Nota sobre o progresso (opcional)', '', 1000, false, true );
			?>
<button type="submit"><?php echo esc_html( $label ); ?></button></form><?php endif; ?><?php endforeach; ?>

<section class="arn-task-attachments" aria-labelledby="arn-task-attachments-title"><h2 id="arn-task-attachments-title">Anexos</h2>
	<?php if ( empty( $data['attachments'] ) ) : ?>
<p>Ainda não existem anexos nesta tarefa.</p>
	<?php else : ?>
<ul>
		<?php
		foreach ( $data['attachments'] as $task_attachment ) :
			$attachment_url  = add_query_arg(
				array(
					'arn_task'      => (string) $data['id'],
					'arn_task_file' => $task_attachment['id'],
				),
				home_url( '/' )
			);
			$attachment_type = match ( $task_attachment['mime'] ) {
				'application/pdf' => 'PDF', 'image/png' => 'PNG', 'image/jpeg' => 'JPEG' };
			?>
<li><a href="<?php echo esc_url( $attachment_url ); ?>"><?php echo esc_html( $task_attachment['label'] ); ?></a> <small><?php echo esc_html( $attachment_type . ' · ' . size_format( $task_attachment['size'] ) . ' · revisão ' . $task_attachment['revision'] ); ?></small></li>
	<?php endforeach; ?>
</ul>
	<?php endif; ?>
	<?php if ( in_array( 'attach', $data['operations'], true ) ) : ?>
<form class="arn-task-form" data-task-form method="post" enctype="multipart/form-data" action="<?php echo esc_url( $detail_url( $data['id'] ) ); ?>"><fieldset><legend>Acrescentar anexo</legend>
		<?php
		View::hidden( $tasks, 'attach', $data['id'] );
		View::field( $tasks, 'attach', 'label', 'Descrição do anexo', '', 160, true );
		?>
<div class="arn-task-field"><label for="arn-task-attachment">Ficheiro</label><input id="arn-task-attachment" name="attachment" type="file" accept="application/pdf,image/png,image/jpeg" required aria-describedby="arn-task-attachment-help"><small id="arn-task-attachment-help">Um PDF, PNG ou JPEG até 1 MiB, sujeito aos limites de documentos configurados. Máximo de oito anexos por tarefa. A análise pode demorar até dois minutos. O ficheiro só fica disponível depois de aprovado pela análise.</small></div>
<button type="submit">Enviar anexo</button></fieldset></form>
	<?php endif; ?>
</section>

<section class="arn-task-comments" aria-labelledby="arn-task-comments-title"><h2 id="arn-task-comments-title">Comentários</h2>
	<?php
	if ( ! $data['comments'] ) :
		?>
	<p>Ainda não existem comentários nesta tarefa.</p>
		<?php
else :
	?>
	<ol>
	<?php
	foreach ( $data['comments'] as $task_comment ) :
		?>
	<li><p class="arn-task-meta"><strong><?php echo esc_html( $task_comment['actor'] ); ?></strong><time datetime="<?php echo esc_attr( gmdate( 'c', $task_comment['occurred_at'] ) ); ?>"><?php echo esc_html( View::date( $task_comment['occurred_at'] ) ); ?></time></p><p><?php echo nl2br( esc_html( $task_comment['body'] ) ); ?></p></li><?php endforeach; ?></ol><?php endif; ?>
	<?php
	if ( in_array( 'comment', $data['operations'], true ) ) :
		?>
		<form class="arn-task-form" data-task-form method="post" action="<?php echo esc_url( $detail_url( $data['id'] ) ); ?>">
		<?php
		View::hidden( $tasks, 'comment', $data['id'] );
		View::field( $tasks, 'comment', 'body', 'O teu comentário', '', 2000, true, true, 'Os comentários ficam no histórico. Os ficheiros da tarefa são apresentados na secção Anexos.' );
		?>
<button type="submit">Acrescentar comentário</button></form><?php endif; ?></section>

<details class="arn-task-history"><summary>Histórico da tarefa</summary><ol>
	<?php
	foreach ( $data['history'] as $event ) :
		?>
	<li><p><strong><?php echo esc_html( View::EVENTS[ $event['operation'] ] ); ?></strong> por <?php echo esc_html( $event['actor'] ); ?></p><p class="arn-task-meta"><?php echo esc_html( View::date( $event['occurred_at'] ) ); ?> · Revisão <?php echo esc_html( (string) $event['revision'] ); ?></p><p><?php echo esc_html( View::STATES[ $event['state'] ] ); ?>. Responsável: <?php echo esc_html( $event['assignee'] ); ?>. Prazo: <?php echo esc_html( View::date( $event['due_at'] ) ); ?>.</p>
		<?php
		if ( $team && isset( $event['reason'] ) && '' !== $event['reason'] ) :
			?>
	<p>Motivo interno: <?php echo nl2br( esc_html( $event['reason'] ) ); ?></p><?php endif; ?></li><?php endforeach; ?></ol></details>
<p><a href="<?php echo esc_url( $back ); ?>">Voltar aos resultados</a></p></article>

	<?php
	if ( $team && array_intersect( $data['operations'], array( 'reassign', 'change_due', 'cancel' ) ) ) :
		?>
		<aside class="arn-task-management" aria-labelledby="arn-task-management-title"><h2 id="arn-task-management-title">Gerir atribuição</h2>
		<?php
		if ( in_array( 'reassign', $data['operations'], true ) ) :
			?>
			<details
			<?php
			if ( $tasks['errors'] && 'reassign' === ( $tasks['input']['operation'] ?? null ) ) :
				?>
				open<?php endif; ?>><summary>Alterar responsável</summary><form class="arn-task-form" data-task-form method="post" action="<?php echo esc_url( $detail_url( $data['id'] ) ); ?>">
			<?php
			View::hidden( $tasks, 'reassign', $data['id'] );
			View::select( $tasks, 'reassign', 'assignee_id', 'Novo responsável', $assignees, (string) $data['assignee_id'] );
			View::field( $tasks, 'reassign', 'reason', 'Motivo interno (opcional)', '', 1000, false, true );
			?>
<button type="submit">Guardar responsável</button></form></details><?php endif; ?>
		<?php
		if ( in_array( 'change_due', $data['operations'], true ) ) :
			?>
			<details
			<?php
			if ( $tasks['errors'] && 'change_due' === ( $tasks['input']['operation'] ?? null ) ) :
				?>
				open<?php endif; ?>><summary>Alterar prazo</summary><form class="arn-task-form" data-task-form method="post" action="<?php echo esc_url( $detail_url( $data['id'] ) ); ?>">
			<?php
			View::hidden( $tasks, 'change_due', $data['id'] );
			View::field( $tasks, 'change_due', 'due', 'Novo prazo (opcional)', null === $data['due_at'] ? '' : gmdate( 'Y-m-d\TH:i:s\Z', $data['due_at'] ), 20, false, false, 'Formato UTC: AAAA-MM-DDTHH:MM:SSZ. Em branco retira o prazo.' );
			View::field( $tasks, 'change_due', 'reason', 'Motivo interno (opcional)', '', 1000, false, true );
			?>
<button type="submit">Guardar prazo</button></form></details><?php endif; ?>
		<?php
		if ( in_array( 'cancel', $data['operations'], true ) ) :
			?>
			<details
			<?php
			if ( $tasks['errors'] && 'cancel' === ( $tasks['input']['operation'] ?? null ) ) :
				?>
				open<?php endif; ?>><summary>Cancelar tarefa</summary><form class="arn-task-form" data-task-form method="post" action="<?php echo esc_url( $detail_url( $data['id'] ) ); ?>"><?php View::hidden( $tasks, 'cancel', $data['id'] ); ?><p>O cancelamento encerra as actualizações de estado e conserva o histórico.</p><?php View::field( $tasks, 'cancel', 'reason', 'Motivo do cancelamento', '', 1000, true, true ); ?><button class="arn-task-danger" type="submit">Cancelar tarefa</button></form></details><?php endif; ?>
</aside><?php endif; ?></div>

<?php else : ?>
	<?php
	if ( 'list' === $screen ) :
		?>
		<form class="arn-task-filters" method="get" action="<?php echo esc_url( $base ); ?>" role="search" aria-label="Filtrar tarefas">
		<?php
		if ( isset( $tasks['query']['get']['page_id'] ) ) :
			?>
		<input type="hidden" name="page_id" value="<?php echo esc_attr( $tasks['query']['get']['page_id'] ); ?>"><?php endif; ?><div><label for="arn-task-q">Pesquisar</label><input id="arn-task-q" name="q" type="search" maxlength="120" value="<?php echo esc_attr( $selection->q ); ?>"></div><div><label for="arn-task-state">Estado</label><select id="arn-task-state" name="state"><option value="">Todos os estados</option>
		<?php
		foreach ( View::STATES as $state => $label ) :
			?>
	<option value="<?php echo esc_attr( $state ); ?>" <?php selected( $selection->state, $state ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select></div>
		<?php
		if ( $team ) :
			?>
		<div><label for="arn-task-assignee">Responsável</label><select id="arn-task-assignee" name="assignee"><option value="">Toda a equipa</option>
			<?php
			foreach ( $tasks['access']['members'] as $member ) :
				?>
	<option value="<?php echo esc_attr( (string) $member['id'] ); ?>" <?php selected( $selection->assignee, $member['id'] ); ?>><?php echo esc_html( $member['label'] ); ?></option><?php endforeach; ?></select></div><?php endif; ?><button type="submit">Aplicar filtros</button><a href="<?php echo esc_url( $base ); ?>">Limpar filtros</a></form><?php endif; ?>
<section aria-labelledby="arn-task-results"><h2 id="arn-task-results"><?php echo esc_html( (string) $data['total'] . ( 'notices' === $screen ? ( 1 === $data['total'] ? ' aviso' : ' avisos' ) : ( 1 === $data['total'] ? ' tarefa' : ' tarefas' ) ) ); ?></h2>
	<?php
	if ( ! $data['items'] ) :
		?>
		<div class="arn-task-empty"><h3><?php echo esc_html( 'notices' === $screen ? 'Sem avisos para consultar' : 'Sem tarefas nesta consulta' ); ?></h3><p><?php echo esc_html( 'notices' === $screen ? 'Os avisos aparecem aqui quando há alterações nas tarefas que te estão atribuídas.' : 'Experimenta retirar os filtros. As novas tarefas atribuídas aparecem nesta lista.' ); ?></p>
		<?php
		if ( 'list' === $screen ) :
			?>
		<a href="<?php echo esc_url( $base ); ?>">Ver todas as tarefas</a><?php endif; ?></div>
		<?php
else :
	?>
	<ol class="arn-task-list">
	<?php
	foreach ( $data['items'] as $item ) :
		?>
	<li><div class="arn-task-list-copy"><h3><a href="<?php echo esc_url( $detail_url( 'notices' === $screen ? $item['task_id'] : $item['id'] ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a></h3>
		<?php
		if ( 'notices' === $screen ) :
			?>
		<p><?php echo esc_html( View::NOTICES[ $item['kind'] ] ); ?> · <?php echo esc_html( View::date( $item['created_at'] ) ); ?></p><p><?php echo esc_html( null === $item['read_at'] ? 'Por ler' : 'Lido em ' . View::date( $item['read_at'] ) ); ?></p>
			<?php
	else :
		?>
		<div class="arn-task-meta"><span class="arn-task-state"><?php echo esc_html( View::STATES[ $item['state'] ] ); ?></span><span><?php echo esc_html( $item['assignee'] ); ?></span><span>Prioridade <?php echo esc_html( strtolower( View::PRIORITIES[ $item['priority'] ] ) ); ?></span></div><p><?php echo esc_html( View::date( $item['due_at'] ) ); ?>
		<?php
		if ( $item['overdue'] ) :
			?>
	· <strong class="arn-task-overdue">Prazo ultrapassado</strong><?php endif; ?></p><?php endif; ?></div>
		<?php
		if ( 'notices' === $screen && null === $item['read_at'] ) :
			?>
	<form data-task-form method="post" action="
			<?php
			echo esc_url(
				add_query_arg(
					array(
						'notices'        => '1',
						'arn_tasks_page' => (string) $data['page'],
					),
					$base
				)
			);
			?>
	"><?php View::hidden( $tasks, 'mark-read', $item['id'] ); ?><button type="submit" class="arn-task-secondary">Marcar como lido</button></form><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?>
	<?php
	if ( $data['pages'] > 1 ) :
		?>
		<nav class="arn-task-pagination" aria-label="Páginas de tarefas">
		<?php
		if ( $data['page'] > 1 ) :
			?>
		<a href="<?php echo esc_url( add_query_arg( array_replace( $filters, array( 'arn_tasks_page' => (string) ( $data['page'] - 1 ) ) ) + ( 'notices' === $screen ? array( 'notices' => '1' ) : array() ), $base ) ); ?>">Página anterior</a><?php endif; ?><span>Página <?php echo esc_html( (string) $data['page'] ); ?> de <?php echo esc_html( (string) $data['pages'] ); ?></span>
		<?php
		if ( $data['page'] < $data['pages'] ) :
			?>
	<a href="<?php echo esc_url( add_query_arg( array_replace( $filters, array( 'arn_tasks_page' => (string) ( $data['page'] + 1 ) ) ) + ( 'notices' === $screen ? array( 'notices' => '1' ) : array() ), $base ) ); ?>">Página seguinte</a><?php endif; ?></nav><?php endif; ?></section>
<?php endif; ?>
</section>
