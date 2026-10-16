<?php
/**
 * Consulta corrente sem cache de autorização.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Identity\PreRegistration;

use Arn\Intranet\Identity\{IdentityWpdb, GoogleLoginController};
use RuntimeException;

// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Adaptador interno tipado, sem parâmetros de browser.
/** Marcador e persistência são revalidados mesmo com as flags retiradas. */
final class CurrentPreRegistration {
	public static function repository(): WpdbPreRegistrationRepository {
		global$wpdb;
		$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$database->set_prefix( $wpdb->prefix );
		return new WpdbPreRegistrationRepository( $database );}
	public static function lookup( int $user_id ): ?PreRegistration {
		global$wpdb;
		$database = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		try {
			$database->set_prefix( $wpdb->prefix );
			$repository = new WpdbPreRegistrationRepository( $database );
			$marker     = get_user_meta( $user_id, WpdbPreRegistrationRepository::MARKER, true );
			$record     = $repository->hasSchema() ? $repository->findForUser( $user_id ) : null;
			if ( null === $record ) {
				if ( '' !== $marker ) {
					throw new RuntimeException( 'Pré-cadastro indisponível.' );
				}return null;}
			if ( ! is_string( $marker ) || ! hash_equals( $record->id, $marker ) ) {
				throw new RuntimeException( 'Pré-cadastro indisponível.' );
			}return $record;
		} finally {
			$database->close();}
	}
	public static function enabled(): bool {
		return 'local' === wp_get_environment_type() && defined( 'ARN_ALLOW_LOCAL_WORKSPACE_PREREGISTRATION' ) && true === ARN_ALLOW_LOCAL_WORKSPACE_PREREGISTRATION && '1' === get_option( 'arn_workspace_prereg_ready' ) && class_exists( \GuzzleHttp\Client::class ) && null !== GoogleLoginController::configuration();}
}
