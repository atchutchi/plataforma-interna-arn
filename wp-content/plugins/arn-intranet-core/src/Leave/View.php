<?php
/** Native accessible forms using the established ARN request visual language.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Leave;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput -- Dynamic text uses e(), URLs use esc_url(), only fixed markup is raw.
final class View {
	private static int $serial = 0;
	private static function e( mixed $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true );}
	private static function hidden( string $key, mixed $value ): void {
		echo '<input type="hidden" name="' . self::e( $key ) . '" value="' . self::e( $value ) . '">';}
	private static function form( array $m, string $op, array $fields = array() ): void {
		$key = bin2hex( random_bytes( 16 ) );
		echo '<form class="arn-request-form" method="post">';
		self::hidden( 'operation', $op );
		self::hidden( 'key', $key );
		self::hidden( 'nonce', wp_create_nonce( Input::nonce( $m['access']['actor'], $m['view'], $op, $key ) ) );
		foreach ( $fields as$k => $v ) {
			self::hidden( $k, $v );
		}
	}
	private static function field( string $key, string $label, string $value = '', string $type = 'text', bool $required = true ): void {
		$id = 'arn-leave-' . ( ++self::$serial );
		echo '<div class="arn-request-field"><label for="' . $id . '">' . self::e( $label ) . '</label>';
		$attr = ' id="' . $id . '" name="' . self::e( $key ) . '"' . ( $required ? ' required' : '' );
		if ( 'textarea' === $type ) {
			echo '<textarea' . $attr . ' rows="5" maxlength="2000">' . self::e( $value ) . '</textarea>';
		} else {
			echo '<input' . $attr . ' type="' . self::e( $type ) . '" value="' . self::e( $value ) . '">';
		}
		echo '</div>';
	}
	private static function end( string $label ): void {
		echo '<button type="submit">' . self::e( $label ) . '</button></form>';}
	public static function operationLabel( string $op ): string {
		return match ( $op ) {
			'create'=>'Criar rascunho', 'edit'=>'Alterar período', 'submit'=>'Submeter pedido', 'start'=>'Iniciar análise', 'return'=>'Devolver para correcção', 'approve'=>'Aprovar férias', 'reject'=>'Rejeitar pedido', 'withdraw'=>'Retirar pedido', 'cancel'=>'Cancelar férias futuras', default=>'Alteração de férias'};}
	public static function render( array $m ): void {
		$base   = get_permalink();
		$year   = $m['query']['year'];
		$screen = $m['query']['screen'];
		$title  = 'own' === $m['view'] ? 'Minhas férias' : 'Gerir férias';
		echo '<section class="arn-requests alignwide"><nav class="arn-request-breadcrumb" aria-label="Localização"><a href="' . esc_url( home_url( '/' ) ) . '">Início</a><span>/</span><a href="' . esc_url( $base ) . '">' . self::e( $title ) . '</a></nav><header class="arn-request-heading"><h1>' . self::e(
			match ( $screen ) {
			'new'=>'Pedir férias',
			'holidays'=>'Calendário de feriados fictícios',
			'detail'=>$m['detail']['request']->title(),
			'notices'=>'Avisos de férias',
			default=>$title}
		) . '</h1><p>Planeia as tuas férias e acompanha cada decisão.</p><p class="arn-request-local">Laboratório local. Contas, feriados e saldos fictícios.</p><nav class="arn-request-actions" aria-label="Vistas de férias"><a href="' . esc_url( add_query_arg( 'arn_leave_year', $year, $base ) ) . '">Pedidos e saldo</a><a href="' . esc_url( add_query_arg( 'notices', '1', $base ) ) . '">Avisos internos</a>';
		if ( 'own' === $m['view'] ) {
			echo '<a class="arn-request-primary-link" href="' . esc_url(
				add_query_arg(
					array(
						'new'            => '1',
						'arn_leave_year' => $year,
					),
					$base
				)
			) . '">Pedir férias</a>';
		}
		if ( $m['access']['configure'] ) {
			echo '<a href="' . esc_url(
				add_query_arg(
					array(
						'holidays'       => '1',
						'arn_leave_year' => $year,
					),
					$base
				)
			) . '">Configurar feriados</a>';
		}echo '</nav></header>';
		echo '<p>22 dias úteis por ano. Os pedidos pendentes não reservam saldo. Nesta etapa só são aceites períodos futuros dentro do mesmo ano. Cancelamentos de períodos já iniciados ficam bloqueados.</p>';
		if ( '' !== $m['error'] ) {
			echo '<div role="alert" class="arn-request-errors"><p>' . self::e( $m['error'] ) . '</p><a href="">Recarregar dados</a></div>';
			return;}
		if ( $m['query']['saved'] ) {
			echo '<p role="status" class="arn-request-saved">Alteração guardada.</p>';
		}
		if ( in_array( $screen, array( 'list', 'new', 'holidays' ), true ) ) {
			echo '<form method="get" class="arn-request-filters">';
			self::hidden( 'page_id', get_the_ID() );
			if ( 'list' !== $screen ) {
				self::hidden( $screen, '1' );
			}echo '<label for="arn-leave-year">Ano civil</label><input id="arn-leave-year" type="number" name="arn_leave_year" min="2000" max="2100" value="' . self::e( $year ) . '" required><button type="submit">Consultar ano</button></form>';}
		match ( $screen ) {
			'new'=>self::create( $m ), 'holidays'=>self::calendar( $m ), 'detail'=>self::detail( $m ), 'notices'=>self::notices( $m ), default=>self::listing( $m )};
		echo '</section>';
	}
	private static function create( array $m ): void {
		$calendar = $m['listing']['calendar'];
		if ( null === $calendar ) {
			echo '<p>RH ainda não configurou o calendário fictício deste ano.</p>';
			return;}
		echo '<p>Calendário fictício, versão ' . self::e( $calendar->revision ) . '. O rascunho fica visível apenas para ti.</p>';
		self::form( $m, 'create', array( 'calendar_revision' => $calendar->revision ) );
		echo '<label for="arn-leave-unit">Unidade</label><select id="arn-leave-unit" name="unit">';
		foreach ( $m['access']['units']as$unit ) {
			echo '<option value="' . self::e( $unit ) . '">' . self::e( $unit ) . '</option>';
		}echo '</select>';
		self::field( 'starts', 'Primeiro dia', '', 'date' );
		self::field( 'ends', 'Último dia', '', 'date' );
		self::end( 'Guardar rascunho' );
	}
	private static function calendar( array $m ): void {
		$calendar = $m['listing']['calendar'];
		echo '<p>Usa apenas datas fictícias, uma por linha no formato AAAA-MM-DD. Esta lista não representa feriados oficiais. Uma nova versão não altera férias já aprovadas. Os pedidos pendentes da versão anterior precisam de nova submissão.</p>';
		self::form(
			$m,
			'calendar',
			array(
				'calendar_year' => $m['query']['year'],
				'expected'      => $calendar?->revision ?? 0,
			)
		);
		self::field( 'holidays', 'Datas dos feriados fictícios', implode( "\n", $calendar?->holidays ?? array() ), 'textarea', false );
		self::field( 'reason', 'Motivo da configuração', '', 'textarea' );
		self::end( 'Guardar calendário fictício' );
	}
	private static function listing( array $m ): void {
		$list = $m['listing'];
		echo '<h2>Saldo de ' . self::e( $list['year'] ) . '</h2><p>Os dias decorridos correspondem a dias aprovados anteriores a hoje, calculados pelo calendário. Não são um registo de assiduidade. As datas usam UTC.</p>';
		foreach ( $list['balances']as$id => $balance ) {
			echo '<section><h3>' . self::e( Portal::name( $id ) ) . '</h3><dl>';
			foreach ( array(
				'entitlement' => 'Direito anual',
				'elapsed'     => 'Aprovados decorridos',
				'future'      => 'Aprovados de hoje em diante',
				'available'   => 'Saldo disponível',
			)as$key => $label ) {
				echo '<dt>' . self::e( $label ) . '</dt><dd>' . self::e( $balance[ $key ] ) . ' dias úteis</dd>';
			}echo '</dl></section>';}
		echo '<h2>Pedidos</h2>';
		if ( ! $list['items'] ) {
			echo '<p>Não há pedidos disponíveis neste ano.</p>';
		}echo '<ul class="arn-request-list">';
		foreach ( $list['items']as$r ) {
			echo '<li><a href="' . esc_url( add_query_arg( 'entry', $r->id, get_permalink() ) ) . '">' . self::e( $r->reference() . ' · ' . $r->title() ) . '</a><p>' . self::e( Portal::name( $r->owner ) . ' · ' . $r->state->label() . ' · ' . count( $r->days ) . ' dias úteis' ) . '</p></li>';
		}echo '</ul>';
		$pages = (int) ceil( $list['total'] / 20 );
		if ( $pages > 1 ) {
			echo '<nav aria-label="Páginas dos pedidos">';for ( $p = 1;$p <= $pages;$p++ ) {
				echo '<a href="' . esc_url(
					add_query_arg(
						array(
							'arn_leave_year' => $list['year'],
							'arn_leave_page' => $p,
						),
						get_permalink()
					)
				) . '"' . ( $p === $list['page'] ? ' aria-current="page"' : '' ) . '>' . self::e( $p ) . '</a> ';
			}echo '</nav>';}
		echo '<h2>Ausências aprovadas</h2><p>Apenas pessoas e períodos autorizados. Sem motivos ou observações.</p><ul>';
		foreach ( $list['approved']as$r ) {
			echo '<li>' . self::e( Portal::name( $r->owner ) . ' · ' . $r->unit . ' · ' . $r->starts . ' a ' . $r->ends ) . '</li>';
		}echo '</ul>';
	}
	private static function detail( array $m ): void {
		$r        = $m['detail']['request'];
		$calendar = $m['detail']['calendar'];
		echo '<p>' . self::e( $r->reference() . ' · ' . $r->state->label() . ' · revisão ' . $r->revision ) . '</p><dl><dt>Funcionário</dt><dd>' . self::e( Portal::name( $r->owner ) ) . '</dd><dt>Unidade</dt><dd>' . self::e( $r->unit ) . '</dd><dt>Dias úteis</dt><dd>' . self::e( count( $r->days ) ) . '</dd><dt>Calendário guardado</dt><dd>' . self::e( $r->calendar->year . ' · versão ' . $r->calendar->revision ) . '</dd></dl>';
		if ( null !== $calendar && $calendar->data() !== $r->calendar->data() ) {
			echo '<p>O calendário foi alterado. Uma aprovação exige devolução e nova submissão com o cálculo actualizado.</p>';
		}
		echo '<details><summary>Dias úteis do pedido</summary><p>' . self::e( implode( ', ', $r->days ) ) . '</p></details>';
		foreach ( $m['detail']['operations']as$op ) {
			if ( in_array( $op, array( 'edit', 'submit', 'approve' ), true ) && null === $calendar ) {
				continue;
			}echo '<details><summary>' . self::e( self::operationLabel( $op ) ) . '</summary>';
			$hidden = array(
				'id'       => $r->id,
				'expected' => $r->revision,
			);
			if ( in_array( $op, array( 'edit', 'submit', 'approve' ), true ) ) {
				$hidden['calendar_revision'] = $calendar->revision;
			}self::form( $m, $op, $hidden );
			if ( 'edit' === $op ) {
				self::field( 'starts', 'Primeiro dia', $r->starts, 'date' );
					self::field( 'ends', 'Último dia', $r->ends, 'date' );}
			if ( in_array( $op, array( 'return', 'approve', 'reject', 'cancel' ), true ) ) {
				self::field( 'reason', 'Fundamentação', '', 'textarea' );
			}
			if ( 'submit' === $op ) {
				echo '<p>O cálculo usa a versão actual do calendário e será validado antes de guardar. Os pendentes não reservam saldo.</p>';
			}
			self::end( self::operationLabel( $op ) );
			echo '</details>';
		}
		echo '<h2>Histórico</h2><ol>';
		foreach ( $r->history as$event ) {
			echo '<li>' . self::e( gmdate( 'd/m/Y H:i', $event['at'] ) . ' UTC · ' . self::operationLabel( $event['operation'] ) . ' · ' . Portal::name( $event['actor'] ) ) . ( '' !== $event['reason'] ? '<p>' . self::e( $event['reason'] ) . '</p>' : '' ) . '</li>';
		}echo '</ol>';
	}
	private static function notices( array $m ): void {
		if ( ! $m['notices'] ) {
			echo '<p>Não há avisos disponíveis.</p>';
		}
		foreach ( $m['notices']as$row ) {
			$r    = $row['request'];
			$page = get_page_by_path( ( new Pages() )->slug( $row['view'] ) );
			echo '<article><h2><a href="' . esc_url( add_query_arg( 'entry', $r->id, get_permalink( $page ) ) ) . '">' . self::e( $r->title() ) . '</a></h2><p>' . self::e( self::operationLabel( $row['kind'] ) . ' · ' . gmdate( 'd/m/Y H:i', $row['created_at'] ) . ' UTC' ) . '</p><p>' . ( null === $row['read_at'] ? 'Por ler' : 'Lida' ) . '</p>';
			if ( null === $row['read_at'] ) {
				self::form( $m, 'read', array( 'id' => $row['id'] ) );
					self::end( 'Marcar como lida' );
			}echo '</article>';}
	}
}
