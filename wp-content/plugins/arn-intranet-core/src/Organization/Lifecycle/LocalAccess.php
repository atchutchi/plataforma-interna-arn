<?php
/** Composition restricted to the existing fictional HR actor and a dedicated target.
 *
 * @package ArnIntranet
 */

declare(strict_types=1);
namespace Arn\Intranet\Organization\Lifecycle;

use Arn\Intranet\Access\LocalFixtureUser;
use Arn\Intranet\Authorization\Storage\WpdbAuthorizationRepository;
use Arn\Intranet\Documents\Catalog\CurrentDocumentActor;
use Arn\Intranet\Identity\IdentityWpdb;
use Arn\Intranet\Organization\Register\{LocalAccess as RegisterAccess, Authority, Confirmation, Repository as RegisterRepository};
use RuntimeException;
use Throwable;
// phpcs:disable WordPress.PHP.YodaConditions -- Structured record comparisons.
// phpcs:disable Squiz.Commenting, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.Security.EscapeOutput.ExceptionNotEscaped
final class LocalAccess {
	public const TARGET = 'arn.cadastro.conta.teste';
	public static function configured(): bool {
		return RegisterAccess::configured() && defined( 'ARN_ALLOW_LOCAL_REGISTER_LIFECYCLE' ) && true === ARN_ALLOW_LOCAL_REGISTER_LIFECYCLE;
	}
	public static function allowed(): bool {
		return self::configured() && RegisterAccess::allowed();
	}
	public static function targets( IdentityWpdb $db, int $id, int $actor ): array|bool {
		if ( ! self::configured() || ! $db->inTransaction() ) {
			throw new RuntimeException( 'Conta indisponível.' ); }
		$rows = static function ( string $sql ) use ( $db ): array {
			$r = $db->get_results( $sql );
			if ( ! is_array( $r ) || '' !== $db->last_error ) {
				throw new RuntimeException( 'Conta indisponível.' ); }
			return $r;
		};
		foreach ( array( $db->users, $db->usermeta ) as $table ) {
			$engine = $rows( $db->prepare( 'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
			if ( count( $engine ) !== 1 || 'InnoDB' !== $engine[0]->engine ) {
				throw new RuntimeException( 'Conta indisponível.' ); }
		}
		$users = $rows( $db->prepare( 'SELECT ID AS id, user_login AS login, user_email AS email FROM %i WHERE user_login = %s FOR UPDATE', $db->users, self::TARGET ) );
		if ( array() === $users ) {
			return 0 === $id ? array() : false; }
		if ( count( $users ) !== 1 || self::TARGET !== $users[0]->login || self::TARGET . '@example.invalid' !== $users[0]->email || (int) $users[0]->id === $actor ) {
			throw new RuntimeException( 'Conta indisponível.' ); }
		$user_id = (int) $users[0]->id;
		$meta    = $rows( $db->prepare( 'SELECT meta_key, meta_value FROM %i WHERE user_id = %d AND meta_key IN (%s, %s) FOR UPDATE', $db->usermeta, $user_id, LocalFixtureUser::META_KEY, $db->prefix . 'capabilities' ) );
		$values  = array();
		foreach ( $meta as $row ) {
			if ( isset( $values[ $row->meta_key ] ) ) {
				throw new RuntimeException( 'Conta indisponível.' ); }
			$values[ $row->meta_key ] = $row->meta_value;
		}
		if ( count( $values ) !== 2 || '1' !== ( $values[ LocalFixtureUser::META_KEY ] ?? null ) || 'a:1:{s:10:"subscriber";b:1;}' !== ( $values[ $db->prefix . 'capabilities' ] ?? null ) ) {
			throw new RuntimeException( 'Conta indisponível.' ); }
		$account = ( new WpdbAuthorizationRepository( $db ) )->findInTransaction( $user_id );
		if ( null === $account ) {
			throw new RuntimeException( 'Conta indisponível.' ); }
		return 0 === $id ? array(
			$user_id => array(
				'login'    => self::TARGET,
				'revision' => $account->revision,
				'state'    => $account->state->name,
			),
		) : $id === $user_id;
	}
	public static function repository(): Repository {
		if ( ! self::configured() ) {
			throw new RuntimeException( 'Circuito indisponível.' ); }
		$db        = RegisterAccess::database();
		$clock     = static fn(): int => time();
		$authority = new Authority( $db, RegisterAccess::principal( ... ), CurrentDocumentActor::session( ... ), static fn(): string => wp_get_session_token(), $clock );
		$register  = new RegisterRepository( $db, $authority, new Confirmation( wp_salt( 'nonce' ) ), $clock );
		return new Repository( $db, $authority, $register, self::targets( ... ), $clock );
	}
	public static function install( array $args, array $options ): void {
		try {
			if ( ! defined( 'WP_CLI' ) || true !== WP_CLI || PHP_SAPI !== 'cli' || array() !== $args || array() !== $options || ! self::configured() || ! current_user_can( 'manage_options' ) ) {
				throw new RuntimeException( 'Instalação recusada.' ); }
			$db = RegisterAccess::database();
			try {
				( new Schema( $db ) )->install();
			} finally {
				$db->close(); }
			\WP_CLI::success( 'Schema de ciclo profissional instalado. Não cria contas, estados ou permissões.' );
		} catch ( Throwable ) {
			\WP_CLI::error( 'Instalação recusada. Verifica o ambiente e o schema existente.' ); }
	}
}
