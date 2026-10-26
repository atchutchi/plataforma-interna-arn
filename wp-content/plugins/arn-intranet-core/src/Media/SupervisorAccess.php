<?php
/** Opt-in CLI adapter and operator-only summary.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Authorization\Session\WordPressLocalAccountSessions;
use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class SupervisorAccess {
	public static function allowed( int $actor, bool $cli = false ): bool {
		return defined( 'ARN_ALLOW_LOCAL_MEDIA_SUPERVISOR' ) && true === ARN_ALLOW_LOCAL_MEDIA_SUPERVISOR && QueueAccess::allowed( $actor, $cli ) && ( new WordPressLocalAccountSessions() )->filterAuthentication( new \WP_User( $actor ) ) instanceof \WP_User;
	}
	public static function service( bool $cli = false ): QueueSupervisor {
		if ( ! self::allowed( get_current_user_id(), $cli ) || ! defined( 'ARN_MEDIA_SUPERVISOR_DIR' ) || ! is_string( ARN_MEDIA_SUPERVISOR_DIR ) || ! defined( 'ARN_MEDIA_QUEUE_DIR' ) || ! defined( 'ARN_MEDIA_QUARANTINE_DIR' ) || ! defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ) {
			throw new RuntimeException( 'Supervisão indisponível.' ); }
		return new QueueSupervisor( ARN_MEDIA_SUPERVISOR_DIR, array_merge( LocalPrivateFiles::publicRoots(), array( ARN_MEDIA_QUEUE_DIR, ARN_MEDIA_QUARANTINE_DIR, ARN_LOCAL_PRIVATE_FILES_DIR ) ), static fn( int $id ): bool => self::allowed( $id, $cli ), static fn(): int => time(), static fn( int $seconds ): int => sleep( $seconds ) );
	}
	public static function options( array $args, array $options ): array {
		if ( array() !== $args || array_diff( array_keys( $options ), array( 'cycles', 'interval', 'limit' ) ) ) {
			throw new RuntimeException( 'Argumentos inválidos.' ); }
		$result = array();
		foreach ( array(
			'cycles'   => array( 1, 10, '3' ),
			'interval' => array( 10, 300, '30' ),
			'limit'    => array( 1, 3, '1' ),
		) as $key => [$min, $max, $default] ) {
			$value = $options[ $key ] ?? $default;
			if ( ! is_string( $value ) || 1 !== preg_match( '/^[1-9][0-9]{0,2}$/D', $value ) || (int) $value < $min || (int) $value > $max ) {
				throw new RuntimeException( 'Limites inválidos.' ); }
			$result[] = (int) $value;
		}
		return $result;
	}
	public static function command( array $args, array $options ): void {
		try {
			[$cycles, $interval, $limit] = self::options( $args, $options );
			$result                      = self::service( true )->run( get_current_user_id(), $cycles, $interval, $limit, static fn( int $actor, int $batch ): int => QueueAccess::queue( true )->work( $actor, $batch, static fn( int $id ): ?MediaPipeline => LocalMediaProcessing::pipeline( $id ), true ) );
			\WP_CLI::success( 'Supervisão terminada. Ciclos: ' . $result['cycle'] . '. Entradas analisadas: ' . $result['processed'] . '. Consulta a auditoria no painel.' );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Supervisão não concluída. Consulta o painel e conserva os diários e a quarentena. Não reinicies automaticamente.' );
		}
	}
	public static function register(): void {
		if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
			\WP_CLI::add_command(
				'arn media supervise',
				array( self::class, 'command' ),
				array(
					'shortdesc' => 'Executa ciclos limitados da fila fictícia local.',
					'synopsis'  => array_map(
						static fn( string $name ): array => array(
							'type'     => 'assoc',
							'name'     => $name,
							'optional' => true,
						),
						array( 'cycles', 'interval', 'limit' )
					),
				)
			);
		}
	}
	public static function render(): void {
		$actor = get_current_user_id();
		if ( ! self::allowed( $actor ) ) {
			return; }
		echo '<section aria-label="Supervisão da fila"><h2>Supervisão da fila</h2><p>A execução começa por comando local e termina no limite definido. Pedir paragem aguarda o fim do ciclo em curso. Actualiza a página para consultar o estado.</p>';
		try {
			$s = self::service()->status( $actor );
			if ( null === $s ) {
				echo '<p>Ainda não há execuções supervisionadas.</p></section>';
				return;
			}
			$labels = array(
				'waiting'     => 'A aguardar ciclo',
				'working'     => 'Ciclo em execução',
				'finished'    => 'Concluída',
				'stopped'     => 'Parada a pedido',
				'failed'      => 'Interrompida por falha',
				'interrupted' => 'Interrupção reconhecida',
			);
			echo '<p><strong>' . esc_html( $labels[ $s['status'] ] ) . '</strong>. Ciclos iniciados: ' . esc_html( (string) $s['cycle'] ) . ' de ' . esc_html( (string) $s['cycles'] ) . '. Entradas analisadas em ciclos confirmados: ' . esc_html( (string) $s['processed'] ) . '. Limite por ciclo: ' . esc_html( (string) $s['limit'] ) . '.</p><p>Último registo: ' . esc_html( gmdate( 'Y-m-d H:i:s', $s['at'] ) ) . ' UTC. Revisão ' . esc_html( (string) $s['revision'] ) . '.</p>';
			if ( null !== $s['next_at'] && 'waiting' === $s['status'] && ! $s['stop'] ) {
				echo '<p>Próximo ciclo previsto: ' . esc_html( gmdate( 'Y-m-d H:i:s', $s['next_at'] ) ) . ' UTC.</p>'; }
			if ( in_array( $s['status'], array( 'waiting', 'working' ), true ) ) {
				echo '<p>Se o processo tiver terminado sem confirmação, o estado acima é o último registo. Reconhecer a interrupção só funciona com o supervisor parado e não recupera entradas da fila.</p>';
				if ( $s['stop'] ) {
					echo '<p>Paragem pedida. O ciclo em curso pode ainda estar a terminar.</p>'; }
				echo '<form method="post" action="' . esc_url( admin_url( 'tools.php?page=arn-media-queue' ) ) . '"><input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'arn-media-queue-' . $actor ) ) . '"><input type="hidden" name="revision" value="' . esc_attr( (string) $s['revision'] ) . '"><p><label for="arn-supervisor-reason">Motivo da decisão de supervisão</label><br><textarea id="arn-supervisor-reason" name="reason" rows="2" minlength="10" maxlength="300" required></textarea></p>';
				if ( ! $s['stop'] ) {
					echo '<button class="button" name="action" value="supervisor_stop">Pedir paragem</button> '; }
				echo '<button class="button" name="action" value="supervisor_ack">Reconhecer interrupção</button></form>';
			}
			echo '<details><summary>Auditoria da supervisão</summary><ol>';
			$events = array(
				'start'    => 'Execução iniciada',
				'begin'    => 'Ciclo iniciado',
				'complete' => 'Ciclo confirmado',
				'stop'     => 'Paragem pedida',
				'ack'      => 'Interrupção reconhecida',
				'finish'   => 'Execução terminada',
			);
			foreach ( array_slice( $s['history'], -25 ) as $event ) {
				echo '<li>' . esc_html( gmdate( 'Y-m-d H:i:s', $event['at'] ) . ' UTC · ' . $events[ $event['type'] ] . ' · Conta fictícia ' . $event['actor'] . ( isset( $event['data']['reason'] ) ? ' · ' . $event['data']['reason'] : '' ) . ( isset( $event['data']['outcome'] ) ? ' · ' . $labels[ $event['data']['outcome'] ] : '' ) ) . '</li>';
			}
			echo '</ol></details><p>São apresentados os últimos 25 eventos. O histórico completo permanece no diário privado.</p>';
		} catch ( Throwable ) {
			echo '<p>Supervisão indisponível ou auditoria não confirmada. Conserva os ficheiros para diagnóstico.</p>';
		}
		echo '</section><hr>';
	}
}
