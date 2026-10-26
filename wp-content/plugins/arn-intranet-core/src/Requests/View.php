<?php
/** Institutional native forms, literal copy and accessible disclosure.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Requests;

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
		echo '<form class="arn-request-form" method="post"' . ( $upload ? ' enctype="multipart/form-data"' : '' ) . '>';
		self::hidden( 'operation', $op );
		self::hidden( 'key', $key );
		self::hidden( 'nonce', wp_create_nonce( Input::nonce( $m['access']['actor'], $m['view'], $op, $key ) ) );
		foreach ( $hidden as $name => $value ) {
			self::hidden( $name, $value );
		}
	}
	private static function field( string $name, string $label, string $value = '', string $kind = 'text', int $max = 160, bool $required = true ): void {
		$id = 'arn-request-field-' . ( ++self::$serial );
		echo '<div class="arn-request-field"><label for="' . $id . '">' . self::e( $label ) . '</label>';
		$attrs = ' id="' . $id . '" name="' . self::e( $name ) . '" maxlength="' . $max . '"' . ( $required ? ' required' : '' );
		if ( 'textarea' === $kind ) {
			echo '<textarea' . $attrs . ' rows="5">' . self::e( $value ) . '</textarea>';
		} else {
			echo '<input type="' . self::e( $kind ) . '"' . $attrs . ' value="' . self::e( $value ) . '">';
		}
		echo '</div>';
	}
	private static function select( string $name, string $label, array $options, string $selected, bool $required = true ): void {
		$id = 'arn-request-field-' . ( ++self::$serial );
		echo '<div class="arn-request-field"><label for="' . $id . '">' . self::e( $label ) . '</label><select id="' . $id . '" name="' . self::e( $name ) . '"' . ( $required ? ' required' : '' ) . '>';
		foreach ( $options as $value => $copy ) {
			echo '<option value="' . self::e( $value ) . '"' . ( (string) $value === $selected ? ' selected' : '' ) . '>' . self::e( $copy ) . '</option>';
		}
		echo '</select></div>';
	}
	private static function end( string $label, bool $danger = false ): void {
		echo '<button type="submit"' . ( $danger ? ' class="arn-request-danger"' : '' ) . '>' . self::e( $label ) . '</button></form>';
	}
	public static function render( array $m ): void {
		$screen  = $m['query']['screen'];
		$base    = get_permalink();
		$title   = 'own' === $m['view'] ? 'Meus pedidos' : 'Tratar pedidos';
		$heading = match ( $screen ) {
			'detail' => $m['detail']['request']->title, 'new' => 'Novo pedido', 'types' => 'Tipos de pedido', 'notices' => 'Avisos dos pedidos', default => $title };
		echo '<section class="arn-requests alignwide"><nav class="arn-request-breadcrumb" aria-label="Localização"><a href="' . esc_url( home_url( '/' ) ) . '">Início</a><span aria-hidden="true">/</span><a href="' . esc_url( $base ) . '">' . self::e( $title ) . '</a></nav><header class="arn-request-heading"><h1>' . self::e( $heading ) . '</h1><p>' . ( 'own' === $m['view'] ? 'Prepara o teu pedido e acompanha cada resposta.' : 'Consulta os pedidos submetidos e regista uma decisão fundamentada.' ) . '</p><p class="arn-request-local">Laboratório local. Pedidos e contas fictícios.</p><nav class="arn-request-actions" aria-label="Vistas de pedidos"><a href="' . esc_url( $base ) . '">Ver pedidos</a><a href="' . esc_url( add_query_arg( 'notices', '1', $base ) ) . '">Avisos internos</a>';
		if ( 'own' === $m['view'] ) {
			echo '<a class="arn-request-primary-link" href="' . esc_url( add_query_arg( 'new', '1', $base ) ) . '">Novo pedido</a>';
		}
		if ( $m['access']['configure'] ) {
			echo '<a href="' . esc_url( add_query_arg( 'types', '1', $base ) ) . '">Configurar tipos</a>';
		}
		echo '</nav></header>';
		\Arn\Intranet\Media\ConsumerPage::link();
		if ( '' !== $m['error'] ) {
			echo '<div class="arn-request-errors" role="alert" tabindex="-1"><h2>Alteração não confirmada</h2><p>' . self::e( $m['error'] ) . '</p><a href="">Recarregar</a></div><details class="arn-request-unsaved"><summary>Consultar o texto que tentaste enviar</summary>';
			foreach ( $m['input'] as $text ) {
				echo '<p class="arn-request-prose">' . self::e( $text ) . '</p>';
			}
			echo '</details>';
		} elseif ( $m['query']['saved'] ) {
			echo '<p role="status" class="arn-request-saved">Alteração guardada.</p>';
		}
		switch ( $screen ) {
			case 'new':
				self::newForms( $m );
				break;
			case 'types':
				self::types( $m );
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
		$types = array_filter( $m['listing']['types'], static fn( RequestType $type ): bool => $type->active );
		if ( ! $types ) {
			echo '<p class="arn-request-empty">Ainda não existem tipos disponíveis para a tua unidade.</p>';
		}
		foreach ( $types as $type ) {
			echo '<details' . ( count( $types ) === 1 ? ' open' : '' ) . '><summary>' . self::e( $type->name ) . '</summary><p>' . self::e( $type->instructions ) . '</p><p>Responsável: ' . self::e( Portal::name( $type->reviewer ) ) . '</p>';
			if ( '' === $m['error'] ) {
				self::form(
					$m,
					'create',
					array(
						'type_id'       => $type->id,
						'type_revision' => $type->revision,
					)
				);
				self::field( 'title', $type->title_label );
				self::field( 'body', $type->body_label, '', 'textarea', 5000 );
				echo '<p>O rascunho fica visível apenas para ti. Podes acrescentar anexos antes de submeter.</p>';
				self::end( 'Guardar rascunho' );
			}
			echo '</details>';
		}
	}
	private static function types( array $m ): void {
		echo '<p>Configura apenas exemplos fictícios. As alterações aplicam-se às próximas submissões e não reescrevem pedidos já submetidos. Os anexos anteriormente aceites são conservados.</p>';
		foreach ( array_merge( $m['listing']['types'], array( null ) ) as $type ) {
			echo '<details><summary>' . self::e( null === $type ? 'Criar tipo fictício' : $type->name . ' · versão ' . $type->revision ) . '</summary>';
			if ( '' !== $m['error'] ) {
				echo '</details>';
				continue;
			}
			self::form(
				$m,
				'type',
				array(
					'id'       => $type?->id ?? 0,
					'expected' => $type?->revision ?? 0,
				)
			);
			self::field( 'type_name', 'Nome do tipo', $type?->name ?? 'Pedido fictício ', 'text', 120 );
			if ( null === $type ) {
				self::select( 'unit', 'Unidade', array_combine( $m['access']['units'], $m['access']['units'] ), $m['access']['units'][0] );
			} else {
				self::hidden( 'unit', $type->unit );
				echo '<p>Unidade: ' . self::e( $type->unit ) . '</p>';
			}
			$ids       = array_unique( array_merge( ...array_values( $m['access']['reviewers'] ) ) );
			$reviewers = array();
			foreach ( $ids as $id ) {
				$reviewers[ $id ] = Portal::name( $id );
			}
			self::select( 'reviewer', 'Responsável pela decisão', $reviewers, (string) ( $type?->reviewer ?? ( $ids[0] ?? 0 ) ) );
			self::field( 'title_label', 'Etiqueta do título', $type?->title_label ?? 'Assunto', 'text', 80 );
			self::field( 'body_label', 'Etiqueta da descrição', $type?->body_label ?? 'Descrição do pedido', 'text', 80 );
			self::field( 'instructions', 'Instruções', $type?->instructions ?? '', 'textarea', 500, false );
			self::select(
				'active',
				'Disponibilidade',
				array(
					'1' => 'Activo',
					'0' => 'Inactivo',
				),
				null === $type || $type->active ? '1' : '0'
			);
			self::select(
				'attachments',
				'Anexos',
				array(
					'1' => 'Permitir anexos privados',
					'0' => 'Não permitir anexos',
				),
				null === $type || $type->attachments ? '1' : '0'
			);
			self::end( 'Guardar tipo' );
			echo '</details>';
		}
	}
	private static function listing( array $m ): void {
		echo '<form class="arn-request-filters" method="get">';
		if ( ! get_option( 'permalink_structure' ) ) {
			self::hidden( 'page_id', get_queried_object_id() );
		}
		self::field( 'q', 'Pesquisar por referência ou assunto', $m['query']['q'], 'search', 100, false );
		$options = array( '' => 'Todos os estados' );
		foreach ( RequestState::cases() as $state ) {
			$options[ $state->value ] = $state->label();
		}
		self::select( 'state', 'Estado', $options, $m['query']['state'], false );
		echo '<button type="submit">Pesquisar</button></form><p>' . self::e( $m['listing']['total'] ) . ' pedidos disponíveis.</p><ul class="arn-request-list">';
		foreach ( $m['listing']['items'] as $r ) {
			echo '<li><div class="arn-request-list-copy"><p class="arn-request-meta">' . self::e( $r->reference() ) . ' · ' . self::e( $r->type->name ) . '</p><h2><a href="' . esc_url( add_query_arg( 'entry', $r->id, get_permalink() ) ) . '">' . self::e( $r->title ) . '</a></h2><p><span class="arn-request-state">' . self::e( $r->state->label() ) . '</span> · ' . self::e( gmdate( 'd/m/Y H:i', $r->updated_at ) ) . ' UTC</p></div></li>';
		}
		echo '</ul>';
		if ( ! $m['listing']['items'] ) {
			echo '<p class="arn-request-empty">Não há pedidos nesta vista. Ajusta os filtros ou prepara um novo pedido.</p>';
		}
		$pages = max( 1, (int) ceil( $m['listing']['total'] / 20 ) );
		echo '<nav class="arn-request-pagination" aria-label="Paginação">';
		foreach ( array(
			-1 => 'Anterior',
			1  => 'Seguinte',
		) as $delta => $copy ) {
			$page = $m['query']['page'] + $delta;
			if ( $page > 0 && $page <= $pages ) {
				echo '<a href="' . esc_url(
					add_query_arg(
						array(
							'arn_requests_page' => $page,
							'q'                 => $m['query']['q'],
							'state'             => $m['query']['state'],
						),
						get_permalink()
					)
				) . '">' . self::e( $copy ) . '</a>';
			}
		}
		echo '</nav>';
	}
	private static function detail( array $m ): void {
		$r = $m['detail']['request'];
		echo '<div class="arn-request-detail-grid"><div class="arn-request-detail"><dl class="arn-request-facts"><dt>Referência</dt><dd>' . self::e( $r->reference() ) . '</dd><dt>Estado</dt><dd>' . self::e( $r->state->label() ) . '</dd><dt>Tipo</dt><dd>' . self::e( $r->type->name ) . '</dd><dt>Requerente</dt><dd>' . self::e( Portal::name( $r->owner ) ) . '</dd><dt>Responsável</dt><dd>' . self::e( Portal::name( $r->type->reviewer ) ) . '</dd><dt>Unidade</dt><dd>' . self::e( $r->type->unit ) . '</dd><dt>Revisão</dt><dd>' . $r->revision . '</dd></dl><p class="arn-request-prose">' . self::e( $r->body ) . '</p>';
		if ( $r->substitute ) {
			echo '<p>Substituição: ' . self::e( Portal::name( $r->substitute['user'] ) ) . ', de ' . self::e( gmdate( 'd/m/Y H:i', $r->substitute['starts'] ) ) . ' a ' . self::e( gmdate( 'd/m/Y H:i', $r->substitute['ends'] ) ) . ' UTC.</p>';
		}
		echo '<h2>Anexos privados</h2>';
		if ( ! $r->attachments ) {
			echo '<p>Este pedido ainda não tem anexos.</p>';
		}
		foreach ( $r->attachments as $attachment ) {
			$item = \Arn\Intranet\Tasks\Attachment::fromArray( $attachment );
			$url  = add_query_arg(
				array(
					'arn_request_file' => $item->blob->id,
					'arn_request'      => $r->id,
				),
				home_url( '/' )
			);
			echo '<p><a href="' . esc_url( $url ) . '">Descarregar: ' . self::e( $item->label ) . '</a></p>';
			if ( isset( $item->blob->derivatives['preview'] ) ) {
				echo '<details><summary>Pré-visualização de ' . self::e( $item->label ) . '</summary><img class="arn-request-preview" loading="lazy" src="' . esc_url( add_query_arg( 'arn_media_variant', 'preview', $url ) ) . '" alt="Pré-visualização de ' . self::e( $item->label ) . '"></details>';
			}
		}
		echo '<details class="arn-request-history"><summary>Histórico do pedido (' . count( $r->history ) . ')</summary><ol>';
		foreach ( array_reverse( $r->history ) as $event ) {
			echo '<li><p><strong>' . self::e( self::operationLabel( $event['operation'] ) ) . '</strong> · ' . self::e( gmdate( 'd/m/Y H:i:s', $event['at'] ) ) . ' UTC</p><p>' . self::e( Portal::name( $event['actor'] ) ) . ' · revisão ' . $event['after']['revision'] . '</p>';
			if ( '' !== $event['reason'] ) {
				echo '<p class="arn-request-prose">' . self::e( $event['reason'] ) . '</p>';
			}
			echo '<details><summary>Texto desta revisão</summary><p>' . self::e( $event['after']['title'] ) . '</p><p class="arn-request-prose">' . self::e( $event['after']['body'] ) . '</p></details></li>';
		}
		echo '</ol></details></div>';
		if ( '' === $m['error'] && $m['detail']['operations'] ) {
			echo '<aside class="arn-request-management"><h2>Acções disponíveis</h2>';
			foreach ( $m['detail']['operations'] as $op ) {
				self::operationForm( $m, $op, $r );
			}
			echo '</aside>';
		}
		echo '</div>';
	}
	private static function operationForm( array $m, string $op, InternalRequest $r ): void {
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
		if ( 'edit' === $op ) {
			self::field( 'title', $r->type->title_label, $r->title );
			self::field( 'body', $r->type->body_label, $r->body, 'textarea', 5000 );
		} elseif ( 'submit' === $op ) {
			$current = array_values( array_filter( $m['listing']['types'], static fn( RequestType $type ): bool => $type->id === $r->type->id ) )[0] ?? null;
			self::hidden( 'type_revision', $current?->revision ?? $r->type->revision );
			echo '<p>O pedido ficará visível para o RH competente. Responsável: ' . self::e( Portal::name( $current?->reviewer ?? $r->type->reviewer ) ) . '.</p>';
		} elseif ( in_array( $op, array( 'return', 'approve', 'reject', 'cancel' ), true ) ) {
			self::field( 'reason', 'Fundamentação', '', 'textarea', 1000 );
			if ( 'cancel' === $op ) {
				echo '<p>O cancelamento conserva a aprovação anterior no histórico.</p>';
			}
		} elseif ( 'substitute' === $op ) {
			$options = array();
			foreach ( $m['access']['reviewers'][ $r->type->unit ] ?? array() as $id ) {
				if ( $id !== $r->owner && $id !== $r->type->reviewer ) {
					$options[ $id ] = Portal::name( $id );
				}
			}
			self::select( 'user', 'Substituto com competência', $options, (string) ( array_key_first( $options ) ?? '' ) );
			self::field( 'starts', 'Início (UTC)', gmdate( 'Y-m-d\TH:i', time() + 120 ), 'datetime-local', 16 );
			self::field( 'ends', 'Fim (UTC)', gmdate( 'Y-m-d\TH:i', time() + 86400 ), 'datetime-local', 16 );
			self::field( 'reason', 'Motivo da substituição', '', 'textarea', 1000 );
			echo '<p>Prazo máximo de 30 dias. A substituição não atribui competências.</p>';
		} elseif ( 'attach' === $op ) {
			self::field( 'label', 'Descrição do anexo' );
			self::field( 'attachment', 'Ficheiro PDF, PNG ou JPEG, até 1 MiB', '', 'file' );
			echo '<p>O ficheiro será analisado em privado. Confirma a conclusão em Os meus envios antes de submeter o pedido.</p>';
		} elseif ( 'withdraw' === $op ) {
			echo '<p>O pedido será retirado. O histórico será conservado.</p>';
		}
		self::end( self::operationLabel( $op ), in_array( $op, array( 'withdraw', 'reject', 'cancel' ), true ) );
		echo '</details>';
	}
	private static function notices( array $m ): void {
		if ( ! $m['notices'] ) {
			echo '<p class="arn-request-empty">Não tens avisos de pedidos disponíveis.</p>';
		}
		echo '<ul class="arn-request-list">';
		foreach ( $m['notices'] as $notice ) {
			$r    = $notice['request'];
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
	public static function operationLabel( string $operation ): string {
		return match ( $operation ) {
			'create' => 'Rascunho criado', 'edit' => 'Editar pedido', 'submit' => 'Submeter pedido', 'start' => 'Iniciar análise', 'return' => 'Devolver para correcção', 'approve' => 'Aprovar pedido', 'reject' => 'Rejeitar pedido', 'withdraw' => 'Retirar pedido', 'cancel' => 'Cancelar aprovação', 'substitute' => 'Designar substituto', 'revoke_substitute' => 'Terminar substituição', 'attach' => 'Adicionar anexo', default => 'Alteração registada' };
	}
}
