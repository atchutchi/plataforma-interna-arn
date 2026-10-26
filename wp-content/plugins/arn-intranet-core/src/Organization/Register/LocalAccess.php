<?php
/** Explicit local composition. No business authority from WordPress administration.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Register;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\Catalog\CurrentDocumentActor;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;
// phpcs:disable Squiz.Commenting, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class LocalAccess {
	public const LOGIN = 'arn.rh.cadastro.teste';
	public static function configured(): bool {
		if ( 'local' !== wp_get_environment_type() || is_multisite() ) {
			return false; }
		foreach ( array( 'ARN_ALLOW_LOCAL_REGISTER', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS' ) as $flag ) {
			if ( ! defined( $flag ) || true !== constant( $flag ) ) {
				return false; }
		}
		return true;
	}
	public static function database(): IdentityWpdb {
		if ( ! self::configured() ) {
			throw new RuntimeException( 'Cadastro local indisponível.' ); }
		global $wpdb;
		$db = new IdentityWpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		$db->set_prefix( $wpdb->prefix );
		return $db;
	}
	public static function principal(): int {
		if ( ! self::configured() ) {
			return 0; }
		$id = get_current_user_id();
		clean_user_cache( $id );
		$user  = get_userdata( $id );
		$token = wp_get_session_token();
		return $id > 0 && $user instanceof \WP_User && self::LOGIN === $user->user_login && self::LOGIN . '@example.invalid' === $user->user_email && array( 'subscriber' ) === array_values( $user->roles ) && '1' === get_user_meta( $id, LocalFixtureUser::META_KEY, true ) && strlen( $token ) >= 16 && \WP_Session_Tokens::get_instance( $id )->verify( $token ) ? $id : 0;
	}
	public static function allowed(): bool {
		$db = null;
		try {
			$id = self::principal();
			if ( $id < 1 ) {
				return false; }
			$db = self::database();
			return Authority::permits( ( new WpdbAuthorizationRepository( $db ) )->find( $id ), CurrentDocumentActor::session( $id ), time() );
		} catch ( Throwable ) {
			return false;
		} finally {
			$db?->close();
		}
	}
	public static function repository(): Repository {
		$db        = self::database();
		$authority = new Authority( $db, self::principal( ... ), CurrentDocumentActor::session( ... ), static fn(): string => wp_get_session_token(), static fn(): int => time() );
		return new Repository( $db, $authority, new Confirmation( wp_salt( 'nonce' ) ), static fn(): int => time() );
	}
	public static function install( array $args, array $options ): void {
		try {
			if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || PHP_SAPI !== 'cli' || array() !== $args || array() !== $options || ! self::configured() || get_current_user_id() < 1 || ! current_user_can( 'manage_options' ) ) {
				throw new RuntimeException( 'Instalação recusada.' ); }
			$db = self::database();
			try {
				( new Schema( $db ) )->install();
			} finally {
				$db->close(); }
			\WP_CLI::success( 'Schema de cadastro fictício instalado. Nenhuma conta ou concessão foi criada.' );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Instalação recusada. Verifica o ambiente e o schema existente.' ); }
	}
}
