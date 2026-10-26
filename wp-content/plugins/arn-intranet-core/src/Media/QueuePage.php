<?php
/** Local technical queue dashboard with explicit, reversible decisions.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\F112\Controller;
use Throwable;

// phpcs:disable Squiz.Commenting
final class QueuePage {
	private static ?array $rows    = null;
	private static string $message = '';
	private static bool $failed    = false;
	public const LABELS            = array(
		'queued'     => 'Em fila',
		'processing' => 'Em análise',
		'ready'      => 'Analisado',
		'held'       => 'Retido',
		'cancelled'  => 'Cancelado',
	);
	public const ACTIONS           = array(
		'retry'   => 'Repetir análise',
		'hold'    => 'Reter',
		'cancel'  => 'Cancelar',
		'recover' => 'Recuperar interrupção',
		'archive' => 'Arquivar lote',
	);

	public static function register(): void {
		HealthAccess::register();
		SupervisorAccess::register();
		add_action( 'init', array( self::class, 'guard' ), -29 );
		add_action(
			'admin_menu',
			static function (): void {
				if ( QueueAccess::allowed( get_current_user_id() ) ) {
					add_management_page( 'Fila de media', 'Fila de media', 'manage_options', 'arn-media-queue', array( self::class, 'render' ) ); }
			}
		);
		if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
			\WP_CLI::add_command(
				'arn media work',
				array( QueueAccess::class, 'command' ),
				array(
					'shortdesc' => 'Processa até três entradas da fila fictícia local.',
					'synopsis'  => array(
						array(
							'type'        => 'assoc',
							'name'        => 'limit',
							'optional'    => true,
							'description' => '1, 2 ou 3 entradas.',
						),
					),
				)
			);
		}
	}

	public static function guard(): void {
		if ( ! is_admin() || 'arn-media-queue' !== ( $_GET['page'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route only.
			return; }
		Controller::privateHeaders();
		if ( is_network_admin() || is_user_admin() || ! QueueAccess::allowed( get_current_user_id() ) ) {
			Controller::refuse(); }
		try {
			$input = QueueRequest::parse( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), wp_unslash( $_GET ), wp_unslash( $_POST ), $_FILES, static fn( string $nonce ): bool => false !== wp_verify_nonce( $nonce, 'arn-media-queue-' . get_current_user_id() ), is_uploaded_file( ... ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Strict parser verifies nonce before mutation.
		} catch ( Throwable ) {
			Controller::refuse();
		}
		try {
			$queue = QueueAccess::queue();
			if ( in_array( $input['action'], array( 'supervisor_stop', 'supervisor_ack' ), true ) ) {
				$supervisor = SupervisorAccess::service();
				if ( 'supervisor_stop' === $input['action'] ) {
					$supervisor->stop( get_current_user_id(), (int) $input['revision'], $input['reason'] );
				} else {
					$supervisor->acknowledge( get_current_user_id(), (int) $input['revision'], $input['reason'] );
				}
				self::$message = 'Decisão de supervisão registada. As entradas e os originais foram conservados.';
			} elseif ( 'enqueue' === $input['action'] ) {
				$queue->enqueue( $input['sources'], get_current_user_id(), $input['request_id'] );
				self::$message = 'Lote recebido. A análise será executada pelo worker local.';
			} elseif ( 'list' !== $input['action'] ) {
				$queue->manage( $input['batch'], (int) $input['revision'], (int) $input['item'], $input['action'], $input['reason'], get_current_user_id() );
				self::$message = 'Decisão registada na auditoria. Os ficheiros foram conservados.';
			}
			self::$rows = $queue->listing( get_current_user_id() );
		} catch ( Throwable ) {
			self::$failed  = true;
			self::$message = 'Operação não confirmada. Actualiza a página e consulta os lotes antes de repetir. A fila pode estar ocupada, alterada ou sem capacidade. Não apagues ficheiros.';
		}
		if ( ! QueueAccess::allowed( get_current_user_id() ) ) {
			Controller::refuse(); }
	}

	private static function fields( array $fields ): void {
		$fields['nonce'] = wp_create_nonce( 'arn-media-queue-' . get_current_user_id() );
		foreach ( $fields as $key => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
	}

	public static function render(): void {
		if ( ! QueueAccess::allowed( get_current_user_id() ) ) {
			Controller::refuse(); }
		$url = admin_url( 'tools.php?page=arn-media-queue' );
		echo '<div class="wrap arn-media-queue"><h1>Fila de media</h1><p>Preparação técnica de ficheiros fictícios. Um resultado analisado não publica conteúdo nem concede acesso.</p><p><a class="button" href="' . esc_url( $url ) . '">Actualizar estados</a></p>';
		if ( '' !== self::$message ) {
			echo '<div class="notice ' . esc_attr( self::$failed ? 'notice-error' : 'notice-success' ) . '"><p role="status">' . esc_html( self::$message ) . '</p></div>'; }
		SupervisorAccess::render();
		echo '<h2>Novo lote</h2><form method="post" enctype="multipart/form-data" action="' . esc_url( $url ) . '">';
		self::fields(
			array(
				'action'     => 'enqueue',
				'request_id' => bin2hex( random_bytes( 16 ) ),
			)
		);
		echo '<p><label for="arn-queue-media">Ficheiros fictícios</label><br><input id="arn-queue-media" name="media[]" type="file" accept=".pdf,.png,.jpg,.jpeg" multiple required aria-describedby="arn-queue-help"></p><p id="arn-queue-help">Até oito PDF, PNG ou JPEG por lote. Máximo de 1 MiB por ficheiro. Os originais ficam conservados em quarentena privada.</p><p><button class="button button-primary" type="submit">Colocar na fila</button></p></form><hr><h2>Lotes e quarentena</h2>';
		if ( null === self::$rows ) {
			echo '<p>Listagem indisponível. Actualiza a página para voltar a verificar.</p>';
		} elseif ( array() === self::$rows ) {
			echo '<p>Ainda não há lotes. Envia os primeiros ficheiros no formulário acima.</p>';
		}
		foreach ( self::$rows ?? array() as $id => $row ) {
			echo '<section aria-label="Lote ' . esc_attr( $id ) . '"><h3>Lote <code>' . esc_html( $id ) . '</code></h3>';
			if ( isset( $row['unavailable'] ) ) {
				echo '<p>Lote incompleto ou diário indisponível. Conserva os ficheiros e solicita diagnóstico técnico.</p></section>';
				continue;
			}
			echo '<p>Recebido em ' . esc_html( gmdate( 'Y-m-d H:i:s', $row['created_at'] ) ) . ' UTC. Revisão ' . esc_html( (string) $row['revision'] ) . '. ' . esc_html( $row['archived'] ? 'Arquivado, sem apagar ficheiros.' : 'Em acompanhamento.' ) . '</p>';
			foreach ( $row['items'] as $i => $item ) {
				echo '<h4>Ficheiro ' . esc_html( (string) ( $i + 1 ) ) . ': ' . esc_html( self::LABELS[ $item['status'] ] ) . '</h4><p>' . esc_html( $item['blob']['mime'] ) . ', ' . esc_html( (string) $item['blob']['size'] ) . ' bytes. Tentativas: ' . esc_html( (string) $item['attempts'] ) . ' de 3.</p>';
				$actions = match ( $item['status'] ) {
					'queued', 'ready' => array( 'hold', 'cancel' ),
					'held' => $item['attempts'] < 3 && $item['retries'] < 3 ? array( 'retry', 'cancel' ) : array( 'cancel' ),
					'processing' => array( 'recover' ),
					default => array(),
				};
				if ( ! $row['archived'] && 'open' === $row['consumption'] && array() !== $actions ) {
					self::decision( $url, $id, $row['revision'], $i, $actions ); }
			}
			if ( ! $row['archived'] && 'applying' !== $row['consumption'] && ! array_intersect( array( 'queued', 'processing' ), array_column( $row['items'], 'status' ) ) ) {
				self::decision( $url, $id, $row['revision'], -1, array( 'archive' ) ); }
			echo '<details><summary>Histórico de decisões e processamento</summary><ol>';
			foreach ( $row['history'] as $event ) {
				$label = self::ACTIONS[ $event['type'] ] ?? match ( $event['type'] ) {
					'queued' => 'Lote recebido', 'submitted' => 'Envio de formulário recebido', 'applying' => 'Conclusão iniciada pelo autor', 'applied' => 'Registo confirmado pelo módulo', 'claim' => 'Análise iniciada', 'processed' => 'Análise concluída', default => 'Análise não aprovada' };
				echo '<li>' . esc_html( gmdate( 'Y-m-d H:i:s', $event['at'] ) . ' UTC · ' . $label . ' · Conta fictícia ' . $event['actor'] . ( $event['item'] >= 0 ? ' · Ficheiro ' . ( $event['item'] + 1 ) : '' ) . ( '' !== $event['reason'] ? ' · ' . $event['reason'] : '' ) ) . '</li>';
			}
			echo '</ol></details><hr></section>';
		}
		echo '<p>Capacidade local: 50 lotes conservados. O worker processa até três ficheiros por execução. Repetir exige nova análise completa. Recuperar só funciona quando nenhum worker está activo. Cancelar e arquivar não removem bytes. Não existe libertação manual de ficheiros.</p></div>';
	}

	private static function decision( string $url, string $id, int $revision, int $item, array $actions ): void {
		$field = 'arn-reason-' . $id . '-' . ( $item + 1 );
		echo '<form method="post" action="' . esc_url( $url ) . '">';
		self::fields(
			array(
				'batch'    => $id,
				'revision' => $revision,
				'item'     => $item,
			)
		);
		echo '<p><label for="' . esc_attr( $field ) . '">Motivo da decisão</label><br><input class="regular-text" id="' . esc_attr( $field ) . '" name="reason" required minlength="10" maxlength="300" aria-describedby="' . esc_attr( $field . '-help' ) . '"><br><span id="' . esc_attr( $field . '-help' ) . '">Entre 10 e 300 caracteres. Usa apenas informação fictícia.</span></p><p>';
		foreach ( $actions as $action ) {
			echo '<button class="button" type="submit" name="action" value="' . esc_attr( $action ) . '">' . esc_html( self::ACTIONS[ $action ] ) . '</button> ';
		}
		echo '</p></form>';
	}
}
