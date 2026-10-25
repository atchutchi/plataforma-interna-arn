<?php
/** Opt-in technical laboratory operator, never a business role.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Media;

use Arn\Intranet\Documents\PrivateFiles\{LocalPrivateFiles, ReconciliationPage};
use Arn\Intranet\Authorization\Session\WordPressLocalAccountSessions;
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class QueueAccess {
	public static function allowed( int $actor, bool $cli = false ): bool {
		if ( is_multisite() || ! defined( 'ARN_ALLOW_LOCAL_MEDIA_QUEUE' ) || ! defined( 'ARN_LOCAL_MEDIA_QUEUE_USER_ID' ) || ! defined( 'ARN_ALLOW_LOCAL_MEDIA_PROCESSING' ) || true !== ARN_ALLOW_LOCAL_MEDIA_PROCESSING ) {
			return false; }
		clean_user_cache( $actor );
		$user    = new \WP_User( $actor );
		$token   = $cli ? '' : wp_get_session_token();
		$session = $cli ? defined( 'WP_CLI' ) && true === WP_CLI && PHP_SAPI === 'cli' : '' !== $token && \WP_Session_Tokens::get_instance( $actor )->verify( $token ) && ( new WordPressLocalAccountSessions() )->filterCurrentUser( $actor ) === $actor;
		return get_current_user_id() === $actor && ReconciliationPage::enabled( wp_get_environment_type(), ARN_ALLOW_LOCAL_MEDIA_QUEUE, ARN_LOCAL_MEDIA_QUEUE_USER_ID, $actor, (string) $user->user_email, $user->has_cap( 'manage_options' ), $session );
	}

	public static function queue( bool $cli = false ): MediaQueue {
		if ( ! self::allowed( get_current_user_id(), $cli ) || ! defined( 'ARN_MEDIA_QUEUE_DIR' ) || ! is_string( ARN_MEDIA_QUEUE_DIR ) || ! defined( 'ARN_MEDIA_QUARANTINE_DIR' ) || ! defined( 'ARN_LOCAL_PRIVATE_FILES_DIR' ) ) {
			throw new RuntimeException( 'Fila privada indisponível.' ); }
		return new MediaQueue( ARN_MEDIA_QUEUE_DIR, array_merge( LocalPrivateFiles::publicRoots(), array( ARN_MEDIA_QUARANTINE_DIR, ARN_LOCAL_PRIVATE_FILES_DIR ) ), static fn( int $id ): bool => self::allowed( $id, $cli ), static fn(): int => time() );
	}

	public static function command( array $args, array $options ): void {
		try {
			if ( array() !== $args || array_diff( array_keys( $options ), array( 'limit' ) ) || ! in_array( $options['limit'] ?? '1', array( '1', '2', '3' ), true ) ) {
				throw new RuntimeException( 'Argumentos inválidos.' ); }
			$count = self::queue( true )->work( get_current_user_id(), (int) ( $options['limit'] ?? '1' ), static fn( int $id ): ?MediaPipeline => LocalMediaProcessing::pipeline( $id ) );
			\WP_CLI::success( 'Entradas processadas: ' . $count . '. Consulta os estados no painel de media.' );
		} catch ( \Throwable ) {
			\WP_CLI::error( 'Worker indisponível ou resultado indeterminado. Consulta a fila e conserva a quarentena.' );
		}
	}
}
