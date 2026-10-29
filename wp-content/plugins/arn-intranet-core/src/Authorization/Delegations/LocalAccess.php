<?php
/** Three explicit fictional actors, independent of WordPress administrative roles.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Authorization\Delegations;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Documents\Catalog\CurrentDocumentActor;
use Arn\Intranet\Identity\IdentityWpdb;
use RuntimeException;
use Throwable;
// phpcs:disable Squiz.Commenting, WordPress.PHP.YodaConditions, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class LocalAccess {
	public const LOGINS = array(
		'issuer'   => 'arn.acessos.designado.teste',
		'delegate' => 'arn.acessos.delegado.teste',
		'target'   => 'arn.acessos.destinatario.teste',
	);
	public static function configured(): bool {
		if ( 'local' !== wp_get_environment_type() || is_multisite() ) {
			return false; }
		foreach ( array( 'ARN_ALLOW_LOCAL_DELEGATIONS', 'ARN_ALLOW_LOCAL_AUTHORIZATION_STORAGE', 'ARN_ALLOW_FICTIONAL_LOCAL_ACCESS', 'ARN_ALLOW_LOCAL_ORGANIZATION_STORAGE' ) as $flag ) {
			if ( ! defined( $flag ) || true !== constant( $flag ) ) {
				return false; }
		}
		return true;
	}
	public static function database(): IdentityWpdb {
		if ( ! self::configured() ) {
			throw new RuntimeException( 'Circuito indisponível.' ); }
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
		$u     = get_userdata( $id );
		$token = wp_get_session_token();
		return $id > 0 && $u instanceof \WP_User && in_array( $u->user_login, array( self::LOGINS['issuer'], self::LOGINS['delegate'] ), true ) && $u->user_email === $u->user_login . '@example.invalid' && array( 'subscriber' ) === array_values( $u->roles ) && '1' === get_user_meta( $id, LocalFixtureUser::META_KEY, true ) && strlen( $token ) >= 16 && \WP_Session_Tokens::get_instance( $id )->verify( $token ) ? $id : 0;
	}
	public static function fixtures( IdentityWpdb $db ): array {
		if ( ! self::configured() || ! $db->inTransaction() ) {
			throw new RuntimeException( 'Fixtures indisponíveis.' ); }
		$rows = static function ( string $sql ) use ( $db ): array {
			$r = $db->get_results( $sql );
			if ( ! is_array( $r ) || '' !== $db->last_error ) {
				throw new RuntimeException( 'Fixtures indisponíveis.' );
			} return $r;
		};
		foreach ( array( $db->users, $db->usermeta ) as $table ) {
			$e = $rows( $db->prepare( 'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
			if ( count( $e ) !== 1 || 'InnoDB' !== $e[0]->engine ) {
				throw new RuntimeException( 'Motor inválido.' ); }
		}
		$out = array();
		foreach ( self::LOGINS as $key => $login ) {
			$r = $rows( $db->prepare( 'SELECT ID AS id, user_login AS login, user_email AS email FROM %i WHERE user_login = %s FOR UPDATE', $db->users, $login ) );
			if ( count( $r ) !== 1 || $r[0]->login !== $login || $r[0]->email !== $login . '@example.invalid' ) {
				throw new RuntimeException( 'Fixture indisponível.' ); }
			$id     = (int) $r[0]->id;
			$meta   = $rows( $db->prepare( 'SELECT meta_key, meta_value FROM %i WHERE user_id = %d AND meta_key IN (%s, %s) FOR UPDATE', $db->usermeta, $id, LocalFixtureUser::META_KEY, $db->prefix . 'capabilities' ) );
			$values = array();
			foreach ( $meta as $row ) {
				if ( isset( $values[ $row->meta_key ] ) ) {
					throw new RuntimeException( 'Metadados duplicados.' );
				} $values[ $row->meta_key ] = $row->meta_value;
			}
			if ( count( $values ) !== 2 || '1' !== ( $values[ LocalFixtureUser::META_KEY ] ?? null ) || 'a:1:{s:10:"subscriber";b:1;}' !== ( $values[ $db->prefix . 'capabilities' ] ?? null ) ) {
				throw new RuntimeException( 'Fixture indisponível.' ); }
			$out[ $key ] = $id;
		}
		return $out;
	}
	public static function repository(): Repository {
		$db        = self::database();
		$authority = new Authority( $db, self::principal( ... ), CurrentDocumentActor::session( ... ), static fn(): string => wp_get_session_token(), static fn(): int => time(), self::fixtures( ... ) );
		return new Repository( $db, $authority );
	}
	public static function allowed(): bool {
		if ( self::principal() < 1 ) {
			return false; }
		try {
			self::repository()->listing();
			return true;
		} catch ( Throwable ) {
			return false; }
	}
	public static function install( array $args, array $options ): void {
		try {
			if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || PHP_SAPI !== 'cli' || array() !== $args || array() !== $options || ! self::configured() || ! current_user_can( 'manage_options' ) ) {
				throw new RuntimeException( 'Instalação recusada.' ); }
			$db = self::database();
			try {
				( new Schema( $db ) )->install();
			} finally {
				$db->close(); }
			\WP_CLI::success( 'Schema de delegações instalado. Não cria gestores, delegações ou concessões.' );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Instalação recusada. Verifica ambiente e schema.' ); }
	}
}
