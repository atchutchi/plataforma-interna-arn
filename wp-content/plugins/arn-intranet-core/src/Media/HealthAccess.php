<?php
/** Operator-only diagnostics, explicit POST/CLI and read-only GET.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\LocalPrivateFiles;
use Arn\Intranet\Authorization\Session\WordPressLocalAccountSessions;
use Arn\Intranet\F112\Controller;
use RuntimeException;
use Throwable;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class HealthAccess {
	private static string $message = '';
	public static function allowed( int $actor, bool $cli = false ): bool {
		return defined( 'ARN_ALLOW_LOCAL_MEDIA_HEALTH' ) && true === ARN_ALLOW_LOCAL_MEDIA_HEALTH && QueueAccess::allowed( $actor, $cli ) && ( new WordPressLocalAccountSessions() )->filterAuthentication( new \WP_User( $actor ) ) instanceof \WP_User;
	}
	public static function service( bool $cli = false ): HealthReports {
		if ( ! self::allowed( get_current_user_id(), $cli ) || ! defined( 'ARN_MEDIA_HEALTH_DIR' ) || ! is_string( ARN_MEDIA_HEALTH_DIR ) ) {
			throw new RuntimeException( 'Diagnóstico indisponível.' ); }
		$excluded = LocalPrivateFiles::publicRoots();
		foreach ( array( 'ARN_MEDIA_QUEUE_DIR', 'ARN_MEDIA_QUARANTINE_DIR', 'ARN_LOCAL_PRIVATE_FILES_DIR', 'ARN_MEDIA_SIGNATURES_DIR', 'ARN_MEDIA_SUPERVISOR_DIR' ) as $key ) {
			if ( defined( $key ) ) {
				$excluded[] = constant( $key ); }
		}
		return new HealthReports( ARN_MEDIA_HEALTH_DIR, $excluded, static fn( int $id ): bool => self::allowed( $id, $cli ), static fn(): int => time() );
	}
	public static function request( string $method, array $get, array $post, array $files, callable $nonce ): ?string {
		if ( array( 'page' => 'arn-media-health' ) !== $get || array() !== $files ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		if ( 'GET' === $method && array() === $post ) {
			return null; }
		$keys = array_keys( $post );
		sort( $keys );
		if ( 'POST' !== $method || array( 'action', 'nonce', 'request_id' ) !== $keys || 'check' !== $post['action'] || ! is_string( $post['nonce'] ) || true !== $nonce( $post['nonce'] ) || ! is_string( $post['request_id'] ) || 1 !== preg_match( '/^[a-f0-9]{32}$/D', $post['request_id'] ) ) {
			throw new RuntimeException( 'Pedido inválido.' ); }
		return $post['request_id'];
	}
	public static function guard(): void {
		if ( ! is_admin() || 'arn-media-health' !== ( $_GET['page'] ?? null ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Route only.
			return; }
		Controller::privateHeaders();
		if ( is_network_admin() || is_user_admin() || ! self::allowed( get_current_user_id() ) ) {
			Controller::refuse(); }
		try {
			$id = self::request( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ), wp_unslash( $_GET ), wp_unslash( $_POST ), $_FILES, static fn( string $n ): bool => false !== wp_verify_nonce( $n, 'arn-media-health-' . get_current_user_id() ) ); // phpcs:ignore WordPress.Security.NonceVerification -- Strict parser verifies nonce.
		} catch ( Throwable ) {
			Controller::refuse(); }
		if ( null !== $id ) {
			try {
				if ( ! set_time_limit( 150 ) ) {
					throw new RuntimeException( 'Tempo indisponível.' ); }
				self::service()->run( get_current_user_id(), $id, MediaHealth::probe( ... ) );
				self::$message = 'Verificação registada. Consulta os resultados e o instante do ensaio.';
			} catch ( Throwable ) {
				self::$message = 'Verificação não confirmada. Consulta o histórico antes de repetir. Conserva os recibos e os ficheiros.'; }
		}
		if ( ! self::allowed( get_current_user_id() ) ) {
			Controller::refuse(); }
	}
	public static function command( array $args, array $options ): void {
		try {
			if ( array() !== $args || array() !== $options ) {
				throw new RuntimeException( 'Argumentos inválidos.' ); }
			$row = self::service( true )->run( get_current_user_id(), bin2hex( random_bytes( 16 ) ), MediaHealth::probe( ... ) );
			\WP_CLI::line( wp_json_encode( $row ) );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Diagnóstico não confirmado. Consulta o histórico e conserva os ficheiros.' ); }
	}
	public static function register(): void {
		add_action( 'init', array( self::class, 'guard' ), -28 );
		add_action(
			'admin_menu',
			static function (): void {
				if ( self::allowed( get_current_user_id() ) ) {
					add_management_page( 'Estado de media', 'Estado de media', 'manage_options', 'arn-media-health', array( self::class, 'render' ) ); }
			}
		);
		if ( defined( 'WP_CLI' ) && true === WP_CLI ) {
			\WP_CLI::add_command( 'arn media check', array( self::class, 'command' ), array( 'shortdesc' => 'Regista diagnóstico sintético de media no laboratório local.' ) ); }
	}
	public static function render(): void {
		if ( ! self::allowed( get_current_user_id() ) ) {
			Controller::refuse(); }
		echo '<div class="wrap arn-media-health"><h1>Estado de media</h1><p>Verifica os serviços técnicos com ficheiros fictícios. Cada envio continua a exigir a sua própria análise e autorização.</p>';
		if ( '' !== self::$message ) {
			echo '<p role="status">' . esc_html( self::$message ) . '</p>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'tools.php?page=arn-media-health' ) ) . '"><input type="hidden" name="action" value="check"><input type="hidden" name="nonce" value="' . esc_attr( wp_create_nonce( 'arn-media-health-' . get_current_user_id() ) ) . '"><input type="hidden" name="request_id" value="' . esc_attr( bin2hex( random_bytes( 16 ) ) ) . '"><p><button class="button button-primary" style="min-height:44px" type="submit">Verificar agora</button> <a class="button" style="min-height:44px;align-content:center" href="' . esc_url( admin_url( 'tools.php?page=arn-media-health' ) ) . '">Actualizar histórico</a></p><p>Aguarda a conclusão. Uma verificação pode demorar até 90 segundos. Actualizar o histórico apenas consulta os recibos.</p></form><h2>Histórico operacional</h2>';
		try {
			$rows = self::service()->listing( get_current_user_id() );
			if ( array() === $rows ) {
				echo '<p>Ainda não há verificações. Usa Verificar agora para testar os serviços.</p>'; }
			usort( $rows, static fn( array $a, array $b ): int => ( $b['start']['at'] ?? PHP_INT_MAX ) <=> ( $a['start']['at'] ?? PHP_INT_MAX ) );
			echo '<p>Relatórios conservados: ' . esc_html( (string) count( $rows ) ) . ' de 50. São apresentados os dez mais recentes.</p>';
			foreach ( array_slice( $rows, 0, 10 ) as $row ) {
				if ( 'unavailable' === $row['status'] ) {
					echo '<p>Recibo indisponível ou divergente. Conserva o arquivo e solicita diagnóstico técnico.</p>';
					continue; }
				echo '<section><h3>' . esc_html( gmdate( 'Y-m-d H:i:s', $row['start']['at'] ) ) . ' UTC</h3><p>Conta fictícia ' . esc_html( (string) $row['start']['actor'] ) . '.</p>';
				if ( 'complete' !== $row['status'] ) {
					echo '<p>Sem recibo de conclusão. Pode estar em execução ou ter sido interrompida. Não interpretar como sucesso.</p></section>';
					continue; }
				$r = $row['end']['result'];
				echo '<p>Concluída em ' . esc_html( gmdate( 'Y-m-d H:i:s', $row['end']['at'] ) ) . ' UTC. Este resultado descreve esse instante.</p><dl>';
				foreach ( array(
					'scanner' => 'Antimalware e assinaturas recentes',
					'image'   => 'Conversão de imagens',
					'pdf'     => 'Pré-visualização PDF',
				) as $key => $label ) {
					$state = array(
						'available'   => 'Ensaio disponível',
						'unavailable' => 'Ensaio indisponível. Verifica a configuração e as dependências.',
						'disabled'    => 'Desactivado na configuração local.',
					)[ $r[ $key ] ];
					echo '<dt><strong>' . esc_html( $label ) . '</strong></dt><dd>' . esc_html( $state ) . '</dd>';
				}
				echo '</dl><h4>Armazenamento observado</h4><ul>';
				foreach ( array(
					'queue'      => 'Fila',
					'quarantine' => 'Quarentena',
					'delivery'   => 'Entrega privada',
				) as $key => $label ) {
					$m = $r['storage'][ $key ];
					echo '<li><strong>' . esc_html( $label ) . ':</strong> ';
					if ( null === $m ) {
						echo 'Inventário indisponível ou acima dos limites de observação.'; } else {
						echo esc_html( $m['files'] . ' ficheiros, ' . size_format( $m['bytes'], 2 ) . ' ocupados e ' . size_format( $m['free'], 2 ) . ' livres no volume.' );
						if ( 'delivery' !== $key ) {
							echo esc_html( ' ' . $m['batches'] . ' directórios conservados de ' . ( 'queue' === $key ? '50 lotes.' : '100 processamentos.' ) ); }
						if ( $m['free'] < 67108864 || ( 'queue' === $key && $m['batches'] >= 50 ) || ( 'quarantine' === $key && $m['entries'] >= 101 ) ) {
							echo ' Capacidade local esgotada ou insuficiente. Conserva os originais e solicita intervenção.'; }
						}
						echo '</li>';
				}
				echo '</ul></section><hr>';
			}
		} catch ( Throwable ) {
			echo '<p>Histórico indisponível. Conserva os recibos e solicita diagnóstico técnico.</p>'; }
		echo '<p>Não são apagados originais, recibos ou ficheiros em quarentena. Actualizações de assinaturas, versões e políticas de conservação exigem uma operação separada. O ensaio benigno não comprova detecção de malware.</p></div>';
	}
}
