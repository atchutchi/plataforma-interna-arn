<?php
/** Institutional native forms, literal copy and accessible disclosure.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Support;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput -- Every text/attribute passes e(), every URL esc_url(); only fixed markup is raw.
final class View {
	private static int $serial = 0;
	private static function e( mixed $text ): string {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true );
	}
	private static function hidden( string $key, mixed $value ): void {
		echo '<input type="hidden" name="' . self::e( $key ) . '" value="' . self::e( $value ) . '">';
	}
	private static function form( array $m, string $op, array $hidden = array(), bool $upload = false ): void {
		$key = bin2hex( random_bytes( 16 ) );
		echo '<form class="arn-ticket-form" method="post"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>';
		self::hidden( 'operation', $op );
		self::hidden( 'key', $key );
		self::hidden( 'nonce', wp_create_nonce( Input::nonce( $m['access']['actor'], $m['view'], $op, $key ) ) );
		foreach ( $hidden as $name => $value ) {
			self::hidden( $name, $value );
		}
	}
	private static function field( string $name, string $label, string $value = '', string $kind = 'text', int $max = 160, bool $required = true ): void {
		$id = 'arn-ticket-field-' . ( ++self::$serial );
		echo '<div class="arn-ticket-field"><label for="' . $id . '">' . self::e( $label ) . '</label>';
		$attrs = ' id="' . $id . '" name="' . self::e( $name ) . '" maxlength="' . $max . '"' . ( $required ? ' required' : '' );
		if ( 'textarea' === $kind ) {
			echo '<textarea' . $attrs . ' rows="5">' . self::e( $value ) . '</textarea>';
		} else {
			echo '<input type="' . self::e( $kind ) . '"' . $attrs . ' value="' . self::e( $value ) . '">';
		}
		echo '</div>';
	}
	private static function select( string $name, string $label, array $options, string $selected, bool $required = true ): void {
		$id = 'arn-ticket-field-' . ( ++self::$serial );
		echo '<div class="arn-ticket-field"><label for="' . $id . '">' . self::e( $label ) . '</label><select id="' . $id . '" name="' . self::e( $name ) . '"' . ( $required ? ' required' : '' ) . '>';
		foreach ( $options as $value => $copy ) {
			echo '<option value="' . self::e( $value ) . '"' . ( (string) $value === $selected ? ' selected' : '' ) . '>' . self::e( $copy ) . '</option>';
		}
		echo '</select></div>';
	}
	private static function end( string $label, bool $danger = false ): void {
		echo '<button type="submit"' . ( $danger ? ' class="arn-ticket-danger"' : '' ) . '>' . self::e( $label ) . '</button></form>';
	}
	public static function render( array $m ): void {
		$screen  = $m['query']['screen'];
		$base    = get_permalink();
		$title   = 'own' === $m['view'] ? 'Meus tickets' : 'Suporte TI';
		$heading = match ( $screen ) {
			'detail' => $m['detail']['ticket']->title, 'new' => 'Novo ticket', 'notices' => 'Avisos dos tickets', default => $title };
		echo '<section class="arn-tickets alignwide"><nav class="arn-ticket-breadcrumb" aria-label="Localização"><a href="' . esc_url( home_url( '/' ) ) . '">Início</a><span aria-hidden="true">/</span><a href="' . esc_url( $base ) . '">' . self::e( $title ) . '</a></nav><header class="arn-ticket-heading"><h1>' . self::e( $heading ) . '</h1><p>' . ( 'own' === $m['view'] ? 'Descreve o problema e acompanha a resposta do suporte.' : 'Consulta a fila autorizada, assume um ticket e regista a solução.' ) . '</p><p class="arn-ticket-local">Laboratório local. Tickets e contas fictícios.</p><nav class="arn-ticket-actions" aria-label="Vistas de tickets"><a href="' . esc_url( $base ) . '">Ver tickets</a><a href="' . esc_url( add_query_arg( 'notices', '1', $base ) ) . '">Avisos internos</a>';
		if ( 'own' === $m['view'] ) {
			echo '<a class="arn-ticket-primary-link" href="' . esc_url( add_query_arg( 'new', '1', $base ) ) . '">Novo ticket</a>';
		}

		echo '</nav></header>';
		\Arn\Intranet\Media\ConsumerPage::link();
		if ( '' !== $m['error'] ) {
			echo '<div class="arn-ticket-errors" role="alert" tabindex="-1"><h2>Alteração não confirmada</h2><p>' . self::e( $m['error'] ) . '</p><a href="">Recarregar</a></div><details class="arn-ticket-unsaved"><summary>Consultar o texto que tentaste enviar</summary>';
			foreach ( $m['input'] as $text ) {
				echo '<p class="arn-ticket-prose">' . self::e( $text ) . '</p>';
			}
			echo '</details>';
		} elseif ( $m['query']['saved'] ) {
			echo '<p role="status" class="arn-ticket-saved">Alteração guardada.</p>';
		}
		switch ( $screen ) {
			case 'new':
				self::newForms( $m );
				break;
			case 'detail':
				self::detail( $m );
				break;
			case 'notices':
				self::notices( $m );
				break;
			default:
				self::listing( $m );
		}
		echo '</section>';
	}

	private static function newForms( array $m ): void {
		if ( '' !== $m['error'] ) {
			return; }
		self::form( $m, 'create' );
		self::select( 'unit', 'Unidade', array_combine( $m['access']['units'], $m['access']['units'] ), $m['access']['units'][0] );
		self::field( 'title', 'Assunto' );
		self::field( 'body', 'Descrição do problema', '', 'textarea', 5000 );
		self::select(
			'priority',
			'Prioridade',
			array(
				'low'    => 'Baixa',
				'normal' => 'Normal',
				'high'   => 'Alta',
			),
			'normal'
		);
		echo '<p>O ticket será visível ao suporte competente. Podes acrescentar anexos após a abertura. A prioridade não representa um prazo de resposta garantido.</p>';
		self::end( 'Abrir ticket' );
	}
	public static function priority( string $value ): string {
		return match ( $value ) {
			'low' => 'Baixa', 'high' => 'Alta', default => 'Normal' }; }
	private static function detail( array $m ): void {
		$r = $m['detail']['ticket'];
		echo '<div class="arn-ticket-detail-grid"><div class="arn-ticket-detail"><dl class="arn-ticket-facts">';
		foreach ( array(
			'Referência'  => $r->reference(),
			'Estado'      => $r->state->label(),
			'Prioridade'  => self::priority( $r->priority ),
			'Solicitante' => Portal::name( $r->owner ),
			'Técnico'     => $r->assigned > 0 ? Portal::name( $r->assigned ) : 'Por atribuir',
			'Unidade'     => $r->unit,
			'Revisão'     => $r->revision,
		) as $label => $value ) {
			echo '<dt>' . self::e( $label ) . '</dt><dd>' . self::e( $value ) . '</dd>'; }
		echo '</dl><p class="arn-ticket-prose">' . self::e( $r->body ) . '</p><h2>Conversa</h2><p>Todas as respostas e anexos são visíveis ao solicitante e ao suporte autorizado.</p><ol class="arn-ticket-list">';
		foreach ( $r->messages as $message ) {
			echo '<li><div><h3>' . ( $message['solution'] ? 'Solução' : 'Resposta' ) . '</h3><p>' . self::e( Portal::name( $message['actor'] ) ) . ' · ' . self::e( gmdate( 'd/m/Y H:i', $message['at'] ) ) . ' UTC</p><p class="arn-ticket-prose">' . self::e( $message['body'] ) . '</p></div></li>'; }
		echo '</ol>';
		echo '<h2>Anexos privados</h2>';
		if ( ! $r->attachments ) {
			echo '<p>Este ticket ainda não tem anexos.</p>';
		}
		foreach ( $r->attachments as $attachment ) {
			$item = \Arn\Intranet\Tasks\Attachment::fromArray( $attachment );
			$url  = add_query_arg(
				array(
					'arn_ticket_file' => $item->blob->id,
					'arn_ticket'      => $r->id,
				),
				home_url( '/' )
			);
			echo '<p><a href="' . esc_url( $url ) . '">Descarregar: ' . self::e( $item->label ) . '</a></p>';
			if ( isset( $item->blob->derivatives['preview'] ) ) {
				echo '<details><summary>Pré-visualização de ' . self::e( $item->label ) . '</summary><img class="arn-ticket-preview" loading="lazy" src="' . esc_url( add_query_arg( 'arn_media_variant', 'preview', $url ) ) . '" alt="Pré-visualização de ' . self::e( $item->label ) . '"></details>';
			}
		}

		echo '<details class="arn-ticket-history"><summary>Histórico do ticket (' . count( $r->history ) . ')</summary><ol>';
		foreach ( array_reverse( $r->history ) as $event ) {
			echo '<li><p>' . self::e( self::operationLabel( $event['operation'] ) ) . ' · ' . self::e( Portal::name( $event['actor'] ) ) . ' · revisão ' . $event['after']['revision'] . ' · ' . self::e( gmdate( 'd/m/Y H:i:s', $event['at'] ) ) . ' UTC</p></li>'; }
		echo '</ol></details></div>';
		if ( '' === $m['error'] && $m['detail']['operations'] ) {
			echo '<aside class="arn-ticket-management"><h2>Acções disponíveis</h2>';
			foreach ( $m['detail']['operations'] as $op ) {
				echo '<details><summary>' . self::e( self::operationLabel( $op ) ) . '</summary>';
				self::form(
					$m,
					$op,
					array(
						'id'       => $r->id,
						'expected' => $r->revision,
					),
					'attach' === $op
				);
				if ( 'reply' === $op || 'resolve' === $op ) {
					self::field( 'message', 'resolve' === $op ? 'Solução' : 'Resposta', '', 'textarea', 2000 ); }
				if ( 'claim' === $op ) {
					echo '<p>Ficarás responsável pelo atendimento deste ticket.</p>'; }
				if ( 'attach' === $op ) {
					self::field( 'label', 'Descrição do anexo' );
					self::field( 'attachment', 'Ficheiro PDF, PNG ou JPEG, até 1 MiB', '', 'file' );
					echo '<p>O envio passa pela análise privada e exige confirmação após o processamento.</p>'; }
				self::end( self::operationLabel( $op ) );
				echo '</details>';
			}
			echo '</aside>';
		}
		if ( TicketState::Resolved === $r->state ) {
			echo '<p>Ticket resolvido. Se existe outro problema, abre um novo ticket e indica esta referência.</p>'; }
		echo '</div>';
	}
	private static function listing( array $m ): void {
		echo '<form class="arn-ticket-filters" method="get">';
		if ( ! get_option( 'permalink_structure' ) ) {
			self::hidden( 'page_id', get_queried_object_id() );
		}
		self::field( 'q', 'Pesquisar por referência ou assunto', $m['query']['q'], 'search', 100, false );
		$options = array( '' => 'Todos os estados' );
		foreach ( TicketState::cases() as $state ) {
			$options[ $state->value ] = $state->label();
		}
		self::select( 'state', 'Estado', $options, $m['query']['state'], false );
		self::select(
			'priority',
			'Prioridade',
			array(
				''       => 'Todas as prioridades',
				'low'    => 'Baixa',
				'normal' => 'Normal',
				'high'   => 'Alta',
			),
			$m['query']['priority'],
			false
		);
		echo '<button type="submit">Pesquisar</button></form><p>' . self::e( $m['listing']['total'] ) . ' tickets disponíveis.</p><ul class="arn-ticket-list">';
		foreach ( $m['listing']['items'] as $r ) {
			echo '<li><div class="arn-ticket-list-copy"><p class="arn-ticket-meta">' . self::e( $r->reference() ) . ' · ' . self::e( self::priority( $r->priority ) ) . '</p><h2><a href="' . esc_url( add_query_arg( 'entry', $r->id, get_permalink() ) ) . '">' . self::e( $r->title ) . '</a></h2><p><span class="arn-ticket-state">' . self::e( $r->state->label() ) . '</span> · ' . self::e( gmdate( 'd/m/Y H:i', $r->updated_at ) ) . ' UTC</p></div></li>';
		}
		echo '</ul>';
		if ( ! $m['listing']['items'] ) {
			echo '<p class="arn-ticket-empty">Não há tickets nesta vista. Ajusta os filtros ou prepara um novo ticket.</p>';
		}
		$pages = max( 1, (int) ceil( $m['listing']['total'] / 20 ) );
		echo '<nav class="arn-ticket-pagination" aria-label="Paginação">';
		foreach ( array(
			-1 => 'Anterior',
			1  => 'Seguinte',
		) as $delta => $copy ) {
			$page = $m['query']['page'] + $delta;
			if ( $page > 0 && $page <= $pages ) {
				echo '<a href="' . esc_url(
					add_query_arg(
						array(
							'arn_tickets_page' => $page,
							'q'                => $m['query']['q'],
							'state'            => $m['query']['state'],
							'priority'         => $m['query']['priority'],
						),
						get_permalink()
					)
				) . '">' . self::e( $copy ) . '</a>';
			}
		}
		echo '</nav>';
	}
	private static function notices( array $m ): void {
		if ( ! $m['notices'] ) {
			echo '<p class="arn-ticket-empty">Não tens avisos de tickets disponíveis.</p>';
		}
		echo '<ul class="arn-ticket-list">';
		foreach ( $m['notices'] as $notice ) {
			$r    = $notice['ticket'];
			$view = $r->owner === $m['access']['actor'] ? 'own' : 'team';
			$page = get_page_by_path( ( new Pages() )->slug( $view ) );
			echo '<li><div><h2>' . self::e( self::operationLabel( $notice['kind'] ) ) . '</h2><a href="' . esc_url( add_query_arg( 'entry', $r->id, get_permalink( $page ) ) ) . '">' . self::e( $r->reference() . ' · ' . $r->title ) . '</a><p>' . ( $notice['read'] ? 'Lido' : 'Por ler' ) . '</p>';
			if ( ! $notice['read'] && '' === $m['error'] ) {
				self::form( $m, 'read', array( 'id' => $notice['id'] ) );
				self::end( 'Marcar como lido' );
			}
			echo '</div></li>';
		}
		echo '</ul>';
	}
	public static function operationLabel( string $op ): string {
		return match ( $op ) {
			'create' => 'Ticket aberto', 'claim' => 'Assumir ticket', 'reply' => 'Enviar resposta', 'resolve' => 'Resolver ticket', 'attach' => 'Adicionar anexo', default => 'Alteração registada' }; }
}
