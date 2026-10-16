<?php
/**
 * Authorised CMS model only.
 *
 * @package ArnIntranet
 */

if ( ! defined( 'ABSPATH' ) || ! isset( $model ) ) {
	return;
}
use Arn\Intranet\F112\{Input, View};
$module       = $model['module'];
$calendar     = 'calendar' === $module;
$f112_title   = $calendar ? 'Calendário' : 'Comunicados';
$base         = add_query_arg( 'page', 'arn-' . $module, admin_url( 'admin.php' ) );
$filters      = array_intersect_key( $model['query']['get'], array_flip( array( 'q', 'unit', 'state', 'month', 'arn_f112_page' ) ) );
$back         = add_query_arg( $filters, $base );
$row          = $model['row'];
$fields       = array_merge( $row['fields'] ?? array(), $model['recovery'] );
$actions      = $row['actions'] ?? array();
$labels       = array(
	'create'  => 'Criar rascunho',
	'revise'  => 'Guardar nova versão',
	'submit'  => 'Enviar para revisão',
	'publish' => 'Publicar versão',
	'approve' => 'Aprovar e publicar',
	'reject'  => 'Rejeitar',
	'return'  => 'Devolver para correcção',
	'cancel'  => 'Cancelar evento',
	'archive' => 'Arquivar',
);
$field_labels = array(
	'title'              => 'Título',
	'body'               => 'Texto do comunicado',
	'description'        => 'Descrição do evento',
	'audience'           => 'Audiência',
	'circuit'            => 'Circuito',
	'valid_until'        => 'Disponível até (UTC, opcional)',
	'mode'               => 'Modalidade',
	'location'           => 'Local',
	'url'                => 'Ligação HTTPS',
	'all_day'            => 'Tipo de intervalo',
	'start_at'           => 'Início UTC',
	'end_at'             => 'Fim UTC',
	'start_date'         => 'Primeiro dia',
	'end_date_exclusive' => 'Fim exclusivo',
	'reason'             => 'Justificação privada',
);
$hidden       = static function ( string $name, string $value ): void {
	echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
};
$proof        = static function ( string $operation ) use ( $hidden, $module, $row ): void {
	$id = $row['id'];
	$hidden( 'operation', $operation );
	$hidden( 'nonce', wp_create_nonce( Input::nonce( get_current_user_id(), $module, $operation, $id ) ) );
	if ( $id > 0 ) {
		$hidden( 'id', (string) $id );
		if ( in_array( $operation, array( 'approve', 'reject', 'return' ), true ) ) {
			$hidden( 'version', (string) $row['version'] );
			$hidden( 'token', $row['token'] );
		} else {
			$hidden( 'revision', (string) $row['revision'] );
		}
	}
};
?>
<div class="wrap arn-f112-admin">
<header class="arn-f112-admin-heading"><h1><?php echo esc_html( $f112_title ); ?></h1><p>Gestão local com contas e conteúdos fictícios. As alterações confirmadas ficam auditadas.</p><p><a href="<?php echo esc_url( $back ); ?>">Todos os resultados</a></p></header>
<?php
if ( $model['errors'] ) :
	?>
	<div class="arn-f112-error" role="alert" tabindex="-1" id="f112-errors"><h2>Não foi possível guardar</h2><p><?php echo esc_html( $model['errors']['form'] ); ?></p><p>Revê o título, o texto, a audiência e a justificação. Nos eventos, preenche apenas um tipo de intervalo. O fim tem de ser posterior ao início.</p></div>
	<?php
elseif ( isset( $model['query']['get']['saved'] ) ) :
	?>
	<p class="arn-f112-success" role="status">Operação concluída. A versão actual está apresentada abaixo.</p><?php endif; ?>
<?php if ( null === $row ) : ?>
	<?php
	if ( $model['options'] ) :
		?>
		<section class="arn-f112-new" aria-labelledby="f112-new"><h2 id="f112-new">Criar</h2><div class="arn-f112-actions">
		<?php
		foreach ( $model['options'] as $kind => $option ) :
			?>
		<a class="button" href="<?php echo esc_url( add_query_arg( 'new', $kind, $base ) ); ?>"><?php echo esc_html( $option['label'] ); ?></a><?php endforeach; ?></div></section><?php endif; ?>
<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="arn-f112-filters"><input type="hidden" name="page" value="<?php echo esc_attr( 'arn-' . $module ); ?>"><div><label for="f112-q">Pesquisar</label><input type="search" name="q" id="f112-q" maxlength="100" value="<?php echo esc_attr( $model['query']['selection']->q ); ?>"></div><div><label for="f112-state">Estado</label><select id="f112-state" name="state"><option value="">Todos os estados</option>
	<?php
	foreach ( $calendar ? array( 'Draft', 'Published', 'Cancelled', 'Archived' ) : array( 'Draft', 'Pending', 'Rejected', 'Published', 'Archived' ) as $state ) :
		?>
	<option value="<?php echo esc_attr( $state ); ?>" <?php selected( $model['query']['selection']->state, $state ); ?>><?php echo esc_html( View::stateLabel( $state ) ); ?></option><?php endforeach; ?></select></div><button type="submit" class="button">Aplicar filtros</button><a href="<?php echo esc_url( $base ); ?>">Limpar filtros</a></form>
<section><h2><?php echo esc_html( (string) $model['list']['total'] ); ?> resultados autorizados</h2>
	<?php
	if ( ! $model['list']['items'] ) :
		?>
	<p>Ainda não há resultados nesta consulta. Remove os filtros ou cria um rascunho, se tiveres essa competência.</p>
		<?php
else :
	?>
	<ul class="arn-f112-admin-list">
	<?php
	foreach ( $model['list']['items'] as $item ) :
		?>
	<li><a href="<?php echo esc_url( add_query_arg( $filters + array( 'entry' => (string) $item['id'] ), $base ) ); ?>"><?php echo esc_html( $item['title'] ); ?></a><span><?php echo esc_html( View::stateLabel( $item['state'] ) ); ?> · Versão <?php echo esc_html( (string) $item['version'] ); ?></span></li><?php endforeach; ?></ul><?php endif; ?></section>
	<?php
	if ( $model['list']['pages'] > 1 ) :
		?>
		<nav class="arn-f112-pagination" aria-label="Páginas de gestão">
		<?php
		if ( $model['list']['page'] > 1 ) :
			?>
		<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, array( 'arn_f112_page' => (string) ( $model['list']['page'] - 1 ) ) ), $base ) ); ?>">Anterior</a><?php endif; ?><span>Página <?php echo esc_html( (string) $model['list']['page'] ); ?> de <?php echo esc_html( (string) $model['list']['pages'] ); ?></span>
		<?php
		if ( $model['list']['page'] < $model['list']['pages'] ) :
			?>
	<a href="<?php echo esc_url( add_query_arg( array_merge( $filters, array( 'arn_f112_page' => (string) ( $model['list']['page'] + 1 ) ) ), $base ) ); ?>">Seguinte</a><?php endif; ?></nav><?php endif; ?>
<?php else : ?>
<section class="arn-f112-editor"><h2><?php echo esc_html( $row['title'] ); ?></h2><p><?php echo esc_html( View::stateLabel( $row['state'] ) ); ?>
	<?php
	if ( isset( $row['version'] ) ) :
		?>
	· Versão <?php echo esc_html( (string) $row['version'] ); ?><?php endif; ?></p>
	<?php if ( in_array( 'create', $actions, true ) || in_array( 'revise', $actions, true ) ) : ?>
<form method="post" class="arn-f112-edit-form" aria-describedby="f112-form-help<?php echo $model['errors'] ? ' f112-errors' : ''; ?>">
		<?php $proof( in_array( 'create', $actions, true ) ? 'create' : 'revise' ); ?>
<p id="f112-form-help">Título e texto são obrigatórios. O autor e a unidade proprietária são fixados pelo servidor. Guardar prepara um rascunho. A publicação é uma operação separada.</p>
		<?php foreach ( $calendar ? Input::EVENT_FIELDS : Input::NOTICE_FIELDS as $key ) : ?>
<div class="arn-f112-field"><label for="f112-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field_labels[ $key ] ); ?></label>
			<?php
			if ( isset( $model['errors'][ $key ] ) ) :
				?>
				<p class="arn-f112-field-error" id="f112-error-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $model['errors'][ $key ] ); ?></p><?php endif; ?>
			<?php
			if ( in_array( $key, array( 'body', 'description' ), true ) ) :
				?>
				<textarea id="f112-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="8" maxlength="10000" required <?php echo $model['errors'] ? 'aria-describedby="f112-errors"' : ''; ?>><?php echo esc_textarea( $fields[ $key ] ); ?></textarea>
<?php elseif ( in_array( $key, array( 'audience', 'circuit', 'mode', 'all_day' ), true ) ) : ?>
	<?php
	$choices = match ( $key ) {
		'audience' => array(
			'all'   => 'Toda a comunidade',
			'LAB-A' => 'LAB-A',
			'LAB-B' => 'LAB-B',
		), 'circuit' => array(
			'team-direct'          => 'Equipa: publicação directa',
			'institutional-review' => 'Institucional: revisão por segundo actor',
		), 'mode' => array(
			'presencial' => 'Presencial',
			'online'     => 'Online',
			'hybrid'     => 'Híbrido',
		), default => array(
			'0' => 'Com horário UTC',
			'1' => 'Dia inteiro',
		) };
	if ( in_array( $key, array( 'audience', 'circuit' ), true ) ) {
		$permitted = array_values( array_unique( array_column( $model['options'], $key ) ) );
		$choices   = array_intersect_key( $choices, array_flip( $permitted ) );
		if ( 'audience' === $key && 'team-direct' === ( $fields['circuit'] ?? '' ) ) {
			$choices = array_intersect_key( $choices, array( $row['fields']['audience'] => true ) );
		}
	}
	?>
<select id="f112-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>">
	<?php
	foreach ( $choices as $value => $label ) :
		?>
	<option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $fields[ $key ], (string) $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
<?php else : ?>
	<?php $hint = in_array( $key, array( 'start_at', 'end_at', 'valid_until' ), true ) ? 'AAAA-MM-DDTHH:MM:SSZ, por exemplo 2030-01-15T10:00:00Z.' : ( in_array( $key, array( 'start_date', 'end_date_exclusive' ), true ) ? 'AAAA-MM-DD. Em dias inteiros, o fim exclusivo é o primeiro dia que já não integra o evento.' : ( 'url' === $key ? 'Endereço HTTPS absoluto. Não é consultado automaticamente.' : '' ) ); ?>
<input type="<?php echo 'url' === $key ? 'url' : 'text'; ?>" id="f112-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $fields[ $key ] ); ?>" maxlength="
	<?php
	echo esc_attr(
		(string) match ( $key ) {
		'title' => 160,
		'location' => 240,
		'url' => 2048,
		default => 30 }
	);
	?>
				" <?php echo 'title' === $key ? 'required' : ''; ?> <?php echo '' !== $hint ? 'aria-describedby="f112-help-' . esc_attr( $key ) . '"' : ''; ?>>
	<?php
	if ( '' !== $hint ) :
		?>
		<p id="f112-help-<?php echo esc_attr( $key ); ?>" class="description"><?php echo esc_html( $hint ); ?></p><?php endif; ?>
<?php endif; ?></div>
<?php endforeach; ?>
<div class="arn-f112-field"><label for="f112-reason">Justificação privada (obrigatória)</label><textarea id="f112-reason" name="reason" maxlength="1000" rows="3" required><?php echo esc_textarea( $fields['reason'] ?? '' ); ?></textarea></div><button type="submit" class="button button-primary"><?php echo esc_html( in_array( 'create', $actions, true ) ? $labels['create'] : $labels['revise'] ); ?></button></form>
		<?php
else :
	?>
	<dl class="arn-f112-review">
	<?php
	foreach ( $row['fields'] as $key => $value ) :
		?>
	<dt><?php echo esc_html( $field_labels[ $key ] ?? $key ); ?></dt><dd><?php echo nl2br( esc_html( (string) $value ) ); ?></dd><?php endforeach; ?></dl><?php endif; ?>
	<?php
	foreach ( array_diff( $actions, array( 'create', 'revise' ) ) as $operation ) :
		?>
		<form method="post" class="arn-f112-transition"><?php $proof( $operation ); ?><h3><?php echo esc_html( $labels[ $operation ] ); ?></h3><label for="f112-reason-<?php echo esc_attr( $operation ); ?>">Justificação privada para <?php echo esc_html( strtolower( $labels[ $operation ] ) ); ?></label><textarea id="f112-reason-<?php echo esc_attr( $operation ); ?>" name="reason" maxlength="1000" rows="2" required><?php echo esc_textarea( ( $model['failed_operation'] ?? null ) === $operation ? ( $model['recovery']['reason'] ?? '' ) : '' ); ?></textarea><button type="submit" class="button"><?php echo esc_html( $labels[ $operation ] ); ?></button></form><?php endforeach; ?>
</section>
	<?php
	if ( $row['history'] ) :
		?>
		<section class="arn-f112-audit"><h2>Histórico privado</h2><ol>
		<?php
		foreach ( $row['history'] as $event ) :
			?>
		<li><strong><?php echo esc_html( $event['operation'] ?? $event['event'] ?? 'Transição' ); ?></strong><p><?php echo esc_html( $event['reason'] ); ?></p><p>Actor <?php echo esc_html( (string) $event['actor_id'] ); ?> · Revisão <?php echo esc_html( (string) $event['revision'] ); ?></p></li><?php endforeach; ?></ol></section><?php endif; ?>
<?php endif; ?></div>
